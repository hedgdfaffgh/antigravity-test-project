@echo off
echo ==========================================
echo   Start Chrome Debug Mode for OpenClaw
echo ==========================================
echo.

set "CHROME_PATH="
if exist "E:\Google\Chrome\Application\chrome.exe" set "CHROME_PATH=E:\Google\Chrome\Application\chrome.exe"
if "%CHROME_PATH%"=="" if exist "%PROGRAMFILES%\Google\Chrome\Application\chrome.exe" set "CHROME_PATH=%PROGRAMFILES%\Google\Chrome\Application\chrome.exe"
if "%CHROME_PATH%"=="" if exist "%LOCALAPPDATA%\Google\Chrome\Application\chrome.exe" set "CHROME_PATH=%LOCALAPPDATA%\Google\Chrome\Application\chrome.exe"

if "%CHROME_PATH%"=="" (
    echo [ERROR] Chrome not found.
    pause
    exit /b 1
)

set "UDD=%LOCALAPPDATA%\Chrome-OpenClaw-Debug"

echo Chrome: %CHROME_PATH%
echo DataDir: %UDD%
echo Port: 9222
echo.

start "" "%CHROME_PATH%" --remote-debugging-port=9222 --user-data-dir=%UDD% --no-first-run --no-default-browser-check --disable-sync --remote-allow-origins=*

timeout /t 3 /nobreak >nul

start "" "%CHROME_PATH%" --user-data-dir=%UDD% "https://chat.deepseek.com/"
start "" "%CHROME_PATH%" --user-data-dir=%UDD% "https://claude.ai/new"
start "" "%CHROME_PATH%" --user-data-dir=%UDD% "https://chatgpt.com"
start "" "%CHROME_PATH%" --user-data-dir=%UDD% "https://chat.qwen.ai"
start "" "%CHROME_PATH%" --user-data-dir=%UDD% "https://gemini.google.com/app"

echo.
echo ==========================================
echo Chrome started! Next steps:
echo 1. Login to AI platforms in the browser
echo 2. Then run run-onboard.bat
echo ==========================================
echo.
pause
