@echo off
echo ========================================
echo Chrome 符号链接创建脚本
echo ========================================
echo.
echo 此脚本将创建从 C:\Program Files\Google\Chrome 到 E:\Google\Chrome 的符号链接
echo 这样 Antigravity 就能在默认位置找到您的 Chrome 浏览器
echo.
echo 按任意键继续，或关闭窗口取消...
pause

echo.
echo 正在检查目标路径是否存在...
if exist "E:\Google\Chrome\Application\chrome.exe" (
    echo [OK] 找到 Chrome: E:\Google\Chrome\Application\chrome.exe
) else (
    echo [错误] 未找到 E:\Google\Chrome\Application\chrome.exe
    echo 请确认 Chrome 安装路径正确
    pause
    exit /b 1
)

echo.
echo 正在检查是否需要创建父目录...
if not exist "C:\Program Files\Google" (
    echo 创建目录: C:\Program Files\Google
    mkdir "C:\Program Files\Google"
)

echo.
echo 正在创建符号链接...
mklink /D "C:\Program Files\Google\Chrome" "E:\Google\Chrome"

if %ERRORLEVEL% EQU 0 (
    echo.
    echo [成功] 符号链接创建成功！
    echo.
    echo 现在 Antigravity 应该能够找到 Chrome 了
    echo 请重启 Antigravity 后再次尝试
) else (
    echo.
    echo [错误] 符号链接创建失败
    echo 请确保以管理员身份运行此脚本
)

echo.
pause
