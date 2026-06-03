$zipPath = "$env:TEMP\qwave.zip"
$extractDir = "$env:TEMP\qwave_extract"
$sys32 = "C:\Windows\System32\qwave.dll"

$url = "https://download.zip.dll-files.com/7a68710bac9b6809314b86c0cb1cbc4a/qwave.zip"
Write-Output "[DL] Downloading from dll-files.com..."
Invoke-WebRequest -Uri $url -OutFile $zipPath -UseBasicParsing -TimeoutSec 60 -UserAgent "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36"

if (-not (Test-Path $zipPath)) {
    Write-Output "[FAIL] Download failed"
    exit 1
}

Write-Output "[OK] Downloaded $($(Get-Item $zipPath).Length) bytes"

if (Test-Path $extractDir) { Remove-Item $extractDir -Recurse -Force }
Expand-Archive -Path $zipPath -DestinationPath $extractDir -Force

$dll = Get-ChildItem $extractDir -Filter "qwave.dll" -Recurse | Select-Object -First 1
if (-not $dll) { Write-Output "[FAIL] No dll in zip"; exit 1 }

Write-Output "[FOUND] $($dll.FullName) ($($dll.Length) bytes)"
Copy-Item $dll.FullName $sys32 -Force

$exists = Test-Path $sys32
Write-Output "[VERIFY] System32\qwave.dll exists: $exists"
if ($exists) { Write-Output "[SUCCESS] Fix complete!" } else { Write-Output "[FAIL] Copy failed" }
