# restart_proxy.ps1 - Restart Clash Verge (with Mihomo TUN)
# Usage: Run as Administrator when TUN crashes

$clashExe = "E:\Clash.Verge_1.6.6_x64\clash-verge.exe"

Write-Host "[1] Killing existing processes..." -ForegroundColor Yellow
Get-Process -Name "clash-verge","verge-mihomo" -ErrorAction SilentlyContinue | Stop-Process -Force
Start-Sleep -Seconds 2

Write-Host "[2] Starting Clash Verge..." -ForegroundColor Yellow
Start-Process -FilePath $clashExe -WindowStyle Minimized
Start-Sleep -Seconds 5

Write-Host "[3] Verifying..." -ForegroundColor Cyan
$procs = Get-Process -Name "clash-verge","verge-mihomo" -ErrorAction SilentlyContinue
if ($procs.Count -ge 2) {
    Write-Host "[+] clash-verge + verge-mihomo both running." -ForegroundColor Green
}
else {
    Write-Host "[!] Process not fully started. Check manually." -ForegroundColor Red
}

$tun = Get-NetAdapter | Where-Object { $_.InterfaceDescription -match "Wintun|Meta|Mihomo" }
if ($tun -and $tun.Status -eq "Up") {
    Write-Host "[+] TUN adapter UP: $($tun.Name)" -ForegroundColor Green
}
else {
    Write-Host "[!] TUN adapter not ready. Wait a few seconds and re-run tun_diag.ps1" -ForegroundColor Yellow
}

Write-Host "[Done] Proxy restarted." -ForegroundColor Green
