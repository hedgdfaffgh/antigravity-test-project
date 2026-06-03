@echo off
chcp 65001 > nul
title Ngrok 内网穿透

echo ========================================
echo    Ngrok 内网穿透服务
echo ========================================
echo.
echo 正在启动内网穿透...
echo 启动后请复制分配的 https://*.ngrok-free.app 域名
echo 然后在手机/淘宝APP中使用该地址访问
echo.
echo 首次使用需要注册免费账号并配置 authtoken:
echo   1. 访问 https://ngrok.com 注册账号
echo   2. 复制你的 authtoken
echo   3. 运行: E:\DevTools\ngrok\ngrok.exe authtoken YOUR_TOKEN
echo.
echo ----------------------------------------
echo.

E:\DevTools\ngrok\ngrok.exe http 8080
