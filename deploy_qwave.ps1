$zipPath = "$env:USERPROFILE\Downloads\qwave.zip"
$extractDir = "$env:TEMP\qwave_extract"
$sys32 = "C:\Windows\System32\qwave.dll"
$syswow = "C:\Windows\SysWOW64\qwave.dll"

if (-not (Test-Path $zipPath)) {
    Write-Output "[FAIL] qwave.zip not found at $zipPath"
    exit 1
}

if (Test-Path $extractDir) { Remove-Item $extractDir -Recurse -Force }
Expand-Archive -Path $zipPath -DestinationPath $extractDir -Force

$dll = Get-ChildItem $extractDir -Filter "qwave.dll" -Recurse | Select-Object -First 1
if (-not $dll) {
    Write-Output "[FAIL] qwave.dll not found inside zip"
    exit 1
}

Write-Output "[FOUND] DLL at: $($dll.FullName)"

Copy-Item $dll.FullName $sys32 -Force
Write-Output "[COPY] -> System32: $(Test-Path $sys32)"

if (Test-Path "C:\Windows\SysWOW64") {
    Copy-Item $dll.FullName $syswow -Force
    Write-Output "[COPY] -> SysWOW64: $(Test-Path $syswow)"
}

Write-Output "[VERIFY] System32 qwave.dll exists: $(Test-Path $sys32)"
Write-Output "[DONE] Fix complete. Please re-run devicespace.exe"
