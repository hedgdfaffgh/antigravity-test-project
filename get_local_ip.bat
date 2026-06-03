@echo off
chcp 65001 > nul
title 获取本机IP地址

echo ========================================
echo    本机IP地址查询
echo ========================================
echo.

:: 获取本机IP地址
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4"') do (
    set IP=%%a
    set IP=!IP:~1!
    echo 本机IP地址: !IP!
    echo.
    echo 手机访问地址: http://!IP!:8080/index.html
    echo.
)

echo ========================================
echo 使用说明:
echo 1. 确保手机和电脑连接到同一个WiFi
echo 2. 在手机浏览器输入上面显示的地址
echo 3. 如果无法访问，请检查防火墙设置
echo ========================================
echo.
pause
