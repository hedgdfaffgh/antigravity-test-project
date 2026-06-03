# Auto-generated chat restore worker
# Waits for IDE to close, then restores chat history from backup

$sqliteExe  = "C:\Users\Administrator\Desktop\gdfha.top\sqlite-tools\sqlite3.exe"
$stateDbPath = "C:\Users\Administrator\AppData\Roaming\Antigravity IDE\User\globalStorage\state.vscdb"
$backupPath  = "C:\Users\Administrator\Desktop\gdfha.top\state_chat_backup.db"
$logFile     = "C:\Users\Administrator\Desktop\gdfha.top\switch_account.log"

function WLog($msg) {
    $ts = Get-Date -Format "yyyy-MM-dd HH:mm:ss.fff"
    Add-Content -Path $logFile -Value "[$ts] [CHAT-RESTORE] $msg" -Encoding UTF8
}

WLog "Worker started. Waiting for IDE to create new state.vscdb..."

# Phase 1: Wait for state.vscdb to be created (IDE opened) - max 30 min
$maxWait = 1800
$waited = 0
while (-not (Test-Path $stateDbPath) -and $waited -lt $maxWait) {
    Start-Sleep -Seconds 5
    $waited += 5
}
if (-not (Test-Path $stateDbPath)) {
    WLog "Timeout: state.vscdb never created. Backup kept at: $backupPath"
    exit 1
}
WLog "state.vscdb detected. Waiting for IDE to close..."

# Phase 2: Wait for IDE to close - max 4 hours
$maxWait2 = 14400
$waited2 = 0
while ($waited2 -lt $maxWait2) {
    Start-Sleep -Seconds 5
    $waited2 += 5
    $procs = Get-Process | Where-Object { $_.ProcessName -like "*antigravity*" }
    if (-not $procs) {
        WLog "IDE closed. Starting restore..."
        break
    }
}

Start-Sleep -Seconds 2

# Phase 3: Restore chat data using SQLite ATTACH
$chatKeys = @(
    'antigravityUnifiedStateSync.trajectorySummaries',
    'antigravityUnifiedStateSync.sidebarWorkspaces',
    'antigravityUnifiedStateSync.agentPreferences',
    'antigravityUnifiedStateSync.artifactReview',
    'antigravityUnifiedStateSync.browserPreferences',
    'antigravityUnifiedStateSync.editorPreferences',
    'antigravityUnifiedStateSync.seenNuxIds',
    'antigravityUnifiedStateSync.tabPreferences',
    'antigravityUnifiedStateSync.theme',
    'antigravityUnifiedStateSync.windowPreferences',
    'chat.participantNameRegistry',
    'chat.workspaceTransfer'
)
$keyList = ($chatKeys | ForEach-Object { "'" + $_ + "'" }) -join ","

$bkEscaped = $backupPath -replace '\\', '/'
$sql  = "ATTACH '$bkEscaped' AS bak;" + [char]10
$sql += "INSERT OR REPLACE INTO main.ItemTable SELECT * FROM bak.ItemTable WHERE key IN ($keyList);" + [char]10
$sql += "SELECT 'RESTORED:' || changes();" + [char]10
$sql += "DETACH bak;"

try {
    $result = $sql | & $sqliteExe $stateDbPath 2>&1
    foreach ($line in $result) {
        WLog $line
    }
    WLog "Chat history restored successfully!"
    Remove-Item $backupPath -Force -ErrorAction SilentlyContinue
    WLog "Backup file removed. Done."
} catch {
    WLog "Restore FAILED: $_"
    WLog "Backup kept at: $backupPath (manual restore possible)"
}