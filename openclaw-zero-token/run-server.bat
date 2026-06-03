@echo off
chcp 65001 >nul
echo ==========================================
echo   启动 OpenClaw Gateway 服务 (Windows 专用)
echo ==========================================
echo.

set "SCRIPT_DIR=%~dp0"
set "STATE_DIR=%SCRIPT_DIR%.openclaw-upstream-state"
set "CONFIG_FILE=%STATE_DIR%\openclaw.json"

if not exist "%STATE_DIR%" mkdir "%STATE_DIR%"

set "EXAMPLE_CONFIG=%SCRIPT_DIR%.openclaw-state.example\openclaw.json"
if not exist "%CONFIG_FILE%" (
    if exist "%EXAMPLE_CONFIG%" (
        copy "%EXAMPLE_CONFIG%" "%CONFIG_FILE%" >nul
        echo 已从示例复制配置文件: %EXAMPLE_CONFIG% -^> %CONFIG_FILE%
    ) else (
        echo {} > "%CONFIG_FILE%"
        echo 已创建空配置文件: %CONFIG_FILE%
    )
)

:: 导出环境变量
set OPENCLAW_CONFIG_PATH=%CONFIG_FILE%
set OPENCLAW_STATE_DIR=%STATE_DIR%
set OPENCLAW_GATEWAY_PORT=3001

echo 正在启动 Gateway 服务...
echo 端口: 3001
echo.

node "%SCRIPT_DIR%openclaw.mjs" gateway --port 3001

echo.
pause
