# VPN 连接监控 & 自动修复脚本
# 功能：每30秒检测 Google API 连通性，连续失败时自动刷新 Clash 订阅并切换节点
# 用法：右键 -> 使用 PowerShell 运行，或在终端执行: powershell -ExecutionPolicy Bypass -File vpn_monitor.ps1

param(
    [int]$CheckInterval = 30,        # 检测间隔（秒）
    [int]$FailThreshold = 2,         # 连续失败几次后触发修复
    [string]$TestUrl = "https://generativelanguage.googleapis.com",  # 测试目标（Gemini API）
    [int]$TimeoutSec = 10            # 单次请求超时（秒）
)

# ===== 配置 =====
$ClashApiBase = "http://127.0.0.1:9097"
$ClashSecret = "123456"  # Clash 外部控制器的 API 密钥

# 颜色输出函数
function Write-Status($msg) { Write-Host "[$(Get-Date -F 'HH:mm:ss')] $msg" -ForegroundColor Cyan }
function Write-Ok($msg) { Write-Host "[$(Get-Date -F 'HH:mm:ss')] ✅ $msg" -ForegroundColor Green }
function Write-Fail($msg) { Write-Host "[$(Get-Date -F 'HH:mm:ss')] ❌ $msg" -ForegroundColor Red }
function Write-Fix($msg) { Write-Host "[$(Get-Date -F 'HH:mm:ss')] 🔧 $msg" -ForegroundColor Yellow }

# 测试连通性
function Test-VpnConnection {
    try {
        $response = Invoke-WebRequest -Uri $TestUrl -UseBasicParsing -TimeoutSec $TimeoutSec -ErrorAction Stop
        return $true
    } catch {
        # 404 也算连通（说明 DNS 解析和 TCP 连接都成功了）
        if ($_.Exception.Response -and $_.Exception.Response.StatusCode) {
            return $true
        }
        return $false
    }
}

# 尝试通过 Clash API 刷新提供者（provider）
function Refresh-ClashProviders {
    $headers = @{}
    if ($ClashSecret) { $headers["Authorization"] = "Bearer $ClashSecret" }
    
    try {
        # 获取所有 proxy providers
        $providers = Invoke-RestMethod -Uri "$ClashApiBase/providers/proxies" -Headers $headers -TimeoutSec 5 -ErrorAction Stop
        foreach ($name in $providers.providers.PSObject.Properties.Name) {
            $encodedName = [uri]::EscapeDataString($name)
            try {
                Invoke-RestMethod -Uri "$ClashApiBase/providers/proxies/$encodedName" -Method Put -Headers $headers -TimeoutSec 5 -ErrorAction Stop
                Write-Fix "已刷新代理提供者: $name"
            } catch {
                # 忽略单个刷新失败
            }
        }
        return $true
    } catch {
        return $false
    }
}

# 修复方案1: 通过 Clash API 强制测速切换节点
function Repair-ViaClashApi {
    $headers = @{}
    if ($ClashSecret) { $headers["Authorization"] = "Bearer $ClashSecret" }
    
    try {
        # 获取所有代理组
        $groups = Invoke-RestMethod -Uri "$ClashApiBase/group" -Headers $headers -TimeoutSec 5 -ErrorAction Stop
        
        foreach ($group in $groups) {
            if ($group.type -eq "URLTest" -or $group.type -eq "url-test") {
                $encodedName = [uri]::EscapeDataString($group.name)
                # 触发延迟测试
                try {
                    Invoke-RestMethod -Uri "$ClashApiBase/group/$encodedName/delay" -Method Get -Headers $headers -Body @{url="http://www.gstatic.com/generate_204";timeout=5000} -TimeoutSec 10 -ErrorAction Stop
                    Write-Fix "已触发代理组测速: $($group.name)"
                } catch {}
            }
        }
        return $true
    } catch {
        Write-Fail "Clash API 不可用 (需要在 Clash Verge 设置中开启'外部控制')"
        return $false
    }
}

# 修复方案2: 刷新 DNS 缓存 + 重置网络适配器
function Repair-ViaNetwork {
    Write-Fix "正在刷新 DNS 缓存..."
    ipconfig /flushdns | Out-Null
    
    Write-Fix "正在重置 Winsock..."
    netsh winsock reset catalog 2>&1 | Out-Null
    
    # 尝试重启 TUN 网卡(Mihomo)
    $tunAdapter = Get-NetAdapter | Where-Object { $_.InterfaceDescription -like "*Mihomo*" -or $_.Name -like "*Mihomo*" } | Select-Object -First 1
    if ($tunAdapter) {
        Write-Fix "正在重启 TUN 网卡: $($tunAdapter.Name)..."
        Disable-NetAdapter -Name $tunAdapter.Name -Confirm:$false -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
        Enable-NetAdapter -Name $tunAdapter.Name -Confirm:$false -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 3
    }
}

# 修复方案3: 重启 Clash Verge 进程
function Repair-ViaRestart {
    Write-Fix "正在重启 Clash Verge..."
    
    # 查找并关闭当前的 Clash Verge
    $clashProcess = Get-Process -Name "Clash Verge" -ErrorAction SilentlyContinue
    if (-not $clashProcess) {
        $clashProcess = Get-Process -Name "clash-verge" -ErrorAction SilentlyContinue
    }
    
    # 找到可执行文件路径
    $clashPath = $null
    if ($clashProcess) {
        $clashPath = $clashProcess.Path
        Write-Fix "找到 Clash Verge 进程: $clashPath"
        Stop-Process -Name $clashProcess.Name -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 3
    } else {
        # 尝试常见安装路径
        $possiblePaths = @(
            "$env:LOCALAPPDATA\Clash Verge\Clash Verge.exe",
            "$env:ProgramFiles\Clash Verge\Clash Verge.exe",
            "${env:ProgramFiles(x86)}\Clash Verge\Clash Verge.exe"
        )
        foreach ($p in $possiblePaths) {
            if (Test-Path $p) { $clashPath = $p; break }
        }
    }
    
    if ($clashPath -and (Test-Path $clashPath)) {
        Start-Process -FilePath $clashPath
        Write-Fix "已重新启动 Clash Verge，等待15秒初始化..."
        Start-Sleep -Seconds 15
        return $true
    } else {
        Write-Fail "找不到 Clash Verge 可执行文件，请手动重启"
        return $false
    }
}

# ===== 主循环 =====
Clear-Host
Write-Host "========================================" -ForegroundColor Magenta
Write-Host "  VPN 连接监控 & 自动修复脚本" -ForegroundColor Magenta
Write-Host "  检测间隔: ${CheckInterval}s | 失败阈值: ${FailThreshold}次" -ForegroundColor Magenta  
Write-Host "  测试目标: $TestUrl" -ForegroundColor Magenta
Write-Host "  按 Ctrl+C 停止" -ForegroundColor Magenta
Write-Host "========================================" -ForegroundColor Magenta
Write-Host ""

$failCount = 0
$totalChecks = 0
$totalFails = 0
$repairCount = 0

while ($true) {
    $totalChecks++
    $connected = Test-VpnConnection
    
    if ($connected) {
        $failCount = 0
        Write-Ok "连接正常 (总检测: $totalChecks | 总失败: $totalFails | 修复次数: $repairCount)"
    } else {
        $failCount++
        $totalFails++
        Write-Fail "连接失败 (连续第 $failCount 次 | 阈值: $FailThreshold)"
        
        if ($failCount -ge $FailThreshold) {
            Write-Host ""
            Write-Fix "===== 触发自动修复流程 ====="
            $repairCount++
            
            # 修复步骤1: 刷新 DNS + 网络重置
            Write-Fix "[1/3] 刷新网络..."
            Repair-ViaNetwork
            Start-Sleep -Seconds 3
            
            # 检查是否恢复
            if (Test-VpnConnection) {
                Write-Ok "网络刷新后连接已恢复！"
                $failCount = 0
                Write-Host ""
                Start-Sleep -Seconds $CheckInterval
                continue
            }
            
            # 修复步骤2: 尝试 Clash API
            Write-Fix "[2/3] 尝试通过 Clash API 切换节点..."
            $apiResult = Repair-ViaClashApi
            Refresh-ClashProviders | Out-Null
            Start-Sleep -Seconds 5
            
            if (Test-VpnConnection) {
                Write-Ok "切换节点后连接已恢复！"
                $failCount = 0
                Write-Host ""
                Start-Sleep -Seconds $CheckInterval
                continue
            }
            
            # 修复步骤3: 重启 Clash Verge
            Write-Fix "[3/3] 重启 Clash Verge..."
            $restartResult = Repair-ViaRestart
            
            if ($restartResult -and (Test-VpnConnection)) {
                Write-Ok "重启 Clash 后连接已恢复！"
                $failCount = 0
            } else {
                Write-Fail "自动修复失败，可能需要手动检查网络或更换 VPN 节点"
                Write-Fail "等待60秒后重试..."
                Start-Sleep -Seconds 60
                $failCount = 0  # 重置计数，下次再检测
            }
            Write-Host ""
        }
    }
    
    Start-Sleep -Seconds $CheckInterval
}
