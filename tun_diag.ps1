# tun_diag.ps1 - TUN Mode Full Diagnostic
# Usage: Run as Administrator in PowerShell
# chcp 65001 > $null

$OK = "[+]"; $WARN = "[?]"; $ERR = "[!]"

function Section($title) {
    Write-Host ""
    Write-Host "======== $title ========" -ForegroundColor Cyan
}

# ---- [0] Admin Check ----
Section "0 - Admin Permission"
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host " $ERR Not running as Admin. TUN cannot modify route table. EXIT." -ForegroundColor Red
    exit 1
}
Write-Host " $OK Admin confirmed." -ForegroundColor Green

# ---- [1] Wintun Driver ----
Section "1 - Wintun Driver File"
$paths = @(
    "$env:SystemRoot\System32\wintun.dll",
    "$env:SystemRoot\SysWOW64\wintun.dll"
)
foreach ($p in $paths) {
    if (Test-Path $p) {
        $ver = (Get-Item $p).VersionInfo.FileVersion
        Write-Host " $OK Found: $p (ver: $ver)" -ForegroundColor Green
    }
    else {
        Write-Host " $ERR Missing: $p -> reinstall proxy or check antivirus quarantine" -ForegroundColor Red
    }
}

# ---- [2] Virtual NIC Status ----
Section "2 - TUN/TAP Virtual NIC"
$tunAdapters = Get-NetAdapter | Where-Object { $_.InterfaceDescription -match "Wintun|Clash|Meta|TAP|Mihomo|NekoRay|sing-box" }
if (-not $tunAdapters) {
    Write-Host " $ERR No TUN/TAP adapter found -> kernel driver not loaded or process crashed" -ForegroundColor Red
}
else {
    $tunAdapters | Format-Table Name, InterfaceDescription, Status, LinkSpeed -AutoSize
    foreach ($a in $tunAdapters) {
        if ($a.Status -eq "Disabled") {
            Write-Host " $WARN Adapter '$($a.Name)' is Disabled -> enable in Device Manager" -ForegroundColor Yellow
        }
    }
}

# ---- [3] Conflict Process Detection ----
Section "3 - Conflict Processes"
$conflictProcs = @("Tailscale","zerotier","openvpn","v2rayN","v2ray","xray","hysteria","tuic","warp-svc","vmnat","vmnetdhcp")
$foundConflict = $false
foreach ($proc in $conflictProcs) {
    $p = Get-Process -Name $proc -ErrorAction SilentlyContinue
    if ($p) {
        Write-Host " $WARN Conflict: $proc (PID=$($p.Id)) -> consider closing" -ForegroundColor Yellow
        $foundConflict = $true
    }
}
if (-not $foundConflict) {
    Write-Host " $OK No conflict processes detected." -ForegroundColor Green
}

# ---- [4] Default Route Competition ----
Section "4 - Default Route 0.0.0.0/0 Analysis"
$routes = Get-NetRoute -DestinationPrefix "0.0.0.0/0" -ErrorAction SilentlyContinue | Sort-Object RouteMetric
if (-not $routes) {
    Write-Host " $ERR No default route -> fully disconnected" -ForegroundColor Red
}
elseif ($routes.Count -gt 1) {
    Write-Host " $WARN Found $($routes.Count) default routes (lowest Metric wins):" -ForegroundColor Yellow
    foreach ($r in $routes) {
        $name = (Get-NetAdapter -InterfaceIndex $r.ifIndex -ErrorAction SilentlyContinue).Name
        Write-Host "   ifIndex=$($r.ifIndex)  NextHop=$($r.NextHop)  Metric=$($r.RouteMetric)  Adapter=$name"
    }
}
else {
    $r = $routes[0]
    $name = (Get-NetAdapter -InterfaceIndex $r.ifIndex -ErrorAction SilentlyContinue).Name
    Write-Host " $OK Single default route -> $name (NextHop=$($r.NextHop), Metric=$($r.RouteMetric))" -ForegroundColor Green
}

# ---- [5] DNS Hijack Check ----
Section "5 - DNS Takeover Check"
$dnsServers = Get-DnsClientServerAddress -AddressFamily IPv4 | Where-Object { $_.ServerAddresses.Count -gt 0 }
$dnsServers | Select-Object InterfaceAlias, ServerAddresses | Format-Table -AutoSize

try {
    $result = Resolve-DnsName -Name "www.google.com" -TcpOnly -ErrorAction Stop
    $ip = $result[0].IPAddress
    Write-Host " $OK DNS resolved: $ip" -ForegroundColor Green
    if ($ip -match "^198\.18\." -or $ip -match "^198\.19\.") {
        Write-Host " $OK Fake-IP range hit (198.18.x.x) -> TUN DNS hijack working" -ForegroundColor Green
    }
    elseif ($ip -match "^10\." -or $ip -match "^192\.168\." -or $ip -match "^172\.(1[6-9]|2[0-9]|3[01])\.") {
        Write-Host " $WARN Resolved to private IP -> DNS pollution or TUN not managing DNS" -ForegroundColor Yellow
    }
    else {
        Write-Host " $WARN Resolved to real public IP -> TUN DNS forwarding may not be active" -ForegroundColor Yellow
    }
}
catch {
    Write-Host " $ERR DNS resolution failed -> TUN DNS chain broken / port 53 blocked by firewall" -ForegroundColor Red
}

# ---- [6] IPv6 Leak ----
Section "6 - IPv6 Leak Detection"
$ipv6Routes = Get-NetRoute -DestinationPrefix "::/0" -ErrorAction SilentlyContinue
if ($ipv6Routes) {
    Write-Host " $WARN IPv6 default route exists. Traffic may bypass TUN proxy:" -ForegroundColor Yellow
    foreach ($r in $ipv6Routes) {
        Write-Host "   ifIndex=$($r.ifIndex)  NextHop=$($r.NextHop)  Metric=$($r.RouteMetric)"
    }
}
else {
    Write-Host " $OK No IPv6 default route. No leak risk." -ForegroundColor Green
}

# ---- [7] Winsock LSP Check ----
Section "7 - Winsock Stack Integrity"
$winsock = netsh winsock show catalog 2>&1
$lspCount = ($winsock | Select-String "Layered").Count
if ($lspCount -gt 0) {
    Write-Host " $WARN Found $lspCount LSP entries -> may intercept TUN traffic" -ForegroundColor Yellow
    Write-Host "   Fix: netsh winsock reset (then reboot)" -ForegroundColor Yellow
}
else {
    Write-Host " $OK Winsock stack clean. No LSP injection." -ForegroundColor Green
}

# ---- [8] Summary ----
Section "Diagnostic Complete"
Write-Host "  Priority order:" -ForegroundColor DarkCyan
Write-Host "  1. $ERR RED    => Must fix, TUN will not work" -ForegroundColor Red
Write-Host "  2. $WARN YELLOW => Potential conflict, handle if needed" -ForegroundColor Yellow
Write-Host "  3. $OK GREEN  => Normal" -ForegroundColor Green
Write-Host "" 
Write-Host "  Common fix commands (Admin PowerShell):" -ForegroundColor DarkCyan
Write-Host "  - Reset Winsock : netsh winsock reset" -ForegroundColor DarkCyan
Write-Host "  - Reset IP stack: netsh int ip reset" -ForegroundColor DarkCyan
Write-Host "  - Restart NIC   : Restart-NetAdapter -Name '<TUN_NIC_NAME>'" -ForegroundColor DarkCyan
Write-Host "  - Reinstall TUN : Use proxy client menu -> Install/Repair Service" -ForegroundColor DarkCyan
