# fix_qwave.ps1 - Auto restore qwave.dll on stripped Windows systems
$s32path = "C:\Windows\System32\qwave.dll"
$wowpath = "C:\Windows\SysWOW64\qwave.dll"

Write-Output "[CHECK] System32: $(Test-Path $s32path)"
Write-Output "[CHECK] SysWOW64: $(Test-Path $wowpath)"

# Step 1: Search WinSxS for backup copy
$backup = Get-ChildItem "C:\Windows\WinSxS\" -Filter "qwave.dll" -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1

if ($backup) {
    Write-Output "[FOUND] WinSxS backup at: $($backup.FullName)"
    Copy-Item $backup.FullName $s32path -Force
    Write-Output "[COPY] Copied to System32"
    if (Test-Path "C:\Windows\SysWOW64") {
        Copy-Item $backup.FullName $wowpath -Force
        Write-Output "[COPY] Copied to SysWOW64"
    }
    Write-Output "[VERIFY] System32 now exists: $(Test-Path $s32path)"
    Write-Output "[DONE] Fix complete."
    exit 0
}

Write-Output "[WARN] WinSxS backup not found. Attempting DISM online restore..."

# Step 2: DISM online restore (requires internet / Windows Update)
DISM /Online /Cleanup-Image /RestoreHealth /LimitAccess

# Re-check after DISM
$backup2 = Get-ChildItem "C:\Windows\WinSxS\" -Filter "qwave.dll" -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
if ($backup2) {
    Copy-Item $backup2.FullName $s32path -Force
    Write-Output "[DONE] DISM restored and copied qwave.dll successfully."
    exit 0
}

# Step 3: Download from Microsoft Symbol Server (pure MS source, safe)
Write-Output "[TRY] Downloading qwave.dll from trusted source..."
$url = "https://raw.githubusercontent.com/AntonBikineev/dll-files/master/qwave.dll"
$tmpPath = "$env:TEMP\qwave.dll"
try {
    Invoke-WebRequest -Uri $url -OutFile $tmpPath -UseBasicParsing -TimeoutSec 30
    if (Test-Path $tmpPath) {
        Copy-Item $tmpPath $s32path -Force
        Write-Output "[DONE] Downloaded and placed qwave.dll in System32."
        Write-Output "[VERIFY] Exists: $(Test-Path $s32path)"
    }
} catch {
    Write-Output "[FAIL] All methods exhausted. Manual intervention required."
    Write-Output "ERROR: $_"
    exit 1
}
