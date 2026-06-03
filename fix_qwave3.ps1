$token_url = "https://download.zip.dll-files.com/7a68710bac9b6809314b86c0cb1cbc4a/qwave.zip?token=0BdMy1o7L88TMiAtlXknqQ&expires=1774914701"
$zipPath = "$env:TEMP\qwave_real.zip"
$extractDir = "$env:TEMP\qwave_real"
$sys32 = "C:\Windows\System32\qwave.dll"
$syswow = "C:\Windows\SysWOW64\qwave.dll"

Write-Output "[DL] Fetching qwave.zip with valid token..."
Invoke-WebRequest -Uri $token_url -OutFile $zipPath -UseBasicParsing -TimeoutSec 60 -UserAgent "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0"

$fileSize = (Get-Item $zipPath).Length
Write-Output "[OK] Downloaded $fileSize bytes"

if ($fileSize -lt 50000) {
    Write-Output "[FAIL] File too small - not a valid zip (got HTML likely)"
    Get-Content $zipPath -Raw | Select-String "html|error|404" -AllMatches
    exit 1
}

if (Test-Path $extractDir) { Remove-Item $extractDir -Recurse -Force }
Expand-Archive -Path $zipPath -DestinationPath $extractDir -Force

$dll = Get-ChildItem $extractDir -Filter "qwave.dll" -Recurse | Select-Object -First 1
if (-not $dll) {
    Write-Output "[FAIL] No qwave.dll found inside zip"
    Get-ChildItem $extractDir -Recurse | Format-Table Name, Length
    exit 1
}

Write-Output "[FOUND] $($dll.FullName) - $($dll.Length) bytes"
Copy-Item $dll.FullName $sys32 -Force

if (Test-Path "C:\Windows\SysWOW64") {
    Copy-Item $dll.FullName $syswow -Force
    Write-Output "[COPY] SysWOW64 done: $(Test-Path $syswow)"
}

Write-Output "[VERIFY] System32\qwave.dll: $(Test-Path $sys32)"
if (Test-Path $sys32) {
    Write-Output "[SUCCESS] qwave.dll installed. devicespace.exe should work now."
} else {
    Write-Output "[FAIL] Copy failed - check permissions"
}
