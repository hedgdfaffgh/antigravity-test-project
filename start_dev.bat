@echo off
chcp 65001 > nul
title GDFHA 本地开发环境

echo ========================================
echo    GDFHA.TOP 本地开发环境启动脚本
echo ========================================
echo.

:: 设置环境变量
set DB_HOST=localhost
set DB_USERNAME=root
set DB_PASSWORD=root123
set DB_NAME=ovrn
set DB_PORT=3306
set BASE_URL=localhost:8080

:: 添加PHP和MySQL到PATH
set PATH=E:\DevTools\php;E:\DevTools\mysql\bin;E:\DevTools\ngrok;%PATH%

echo [1/3] 检查 MySQL 服务...
sc query MySQL > nul 2>&1
if %errorlevel% neq 0 (
    echo     MySQL 服务未找到，正在启动...
    net start MySQL
) else (
    echo     MySQL 服务已运行
)

echo.
echo [2/3] 启动 PHP 内置服务器...
echo     访问地址: http://localhost:8080
echo.
cd /d "c:\Users\Administrator\Desktop\gdfha.top\index"

:: 启动PHP服务器
start "PHP Server" cmd /c "E:\DevTools\php\php.exe -S 0.0.0.0:8080"

echo [3/3] PHP服务器已启动!
echo.
echo ========================================
echo   本地访问: http://localhost:8080
echo ========================================
echo.
echo 如需外网访问(供手机/淘宝APP测试)，请运行:
echo   start_ngrok.bat
echo.
echo 按任意键继续...
pause > nul
