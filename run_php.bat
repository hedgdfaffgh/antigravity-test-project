@echo off
:: 设置环境变量
set DB_HOST=localhost
set DB_USERNAME=root
set DB_PASSWORD=root123
set DB_NAME=ovrn
set DB_PORT=3306
set BASE_URL=localhost:8080

:: 启动PHP服务器
echo Starting PHP server with environment variables...
cd /d "c:\Users\Administrator\Desktop\gdfha.top\index"
E:\DevTools\php\php.exe -S 0.0.0.0:8080
