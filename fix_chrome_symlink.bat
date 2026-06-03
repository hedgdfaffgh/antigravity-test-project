@echo off
echo ========================================
echo Chrome 符号链接修复脚本
echo ========================================
echo.
echo 此脚本将：
echo 1. 删除现有的 C:\Program Files\Google\Chrome（如果存在）
echo 2. 重新创建指向 E:\Google\Chrome 的符号链接
echo.
echo 按任意键继续，或关闭窗口取消...
pause

echo.
echo 正在检查目标路径...
if exist "E:\Google\Chrome\Application\chrome.exe" (
    echo [OK] 找到 Chrome: E:\Google\Chrome\Application\chrome.exe
) else (
    echo [错误] 未找到 E:\Google\Chrome\Application\chrome.exe
    pause
    exit /b 1
)

echo.
echo 正在检查并删除旧的路径...
if exist "C:\Program Files\Google\Chrome" (
    echo 发现现有路径，正在删除...
    rmdir /S /Q "C:\Program Files\Google\Chrome" 2>nul
    if exist "C:\Program Files\Google\Chrome" (
        echo 尝试使用 rd 命令删除...
        rd /S /Q "C:\Program Files\Google\Chrome" 2>nul
    )
    echo [OK] 旧路径已删除
) else (
    echo [OK] 路径不存在，无需删除
)

echo.
echo 正在确保父目录存在...
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
    echo 验证链接...
    if exist "C:\Program Files\Google\Chrome\Application\chrome.exe" (
        echo [OK] Chrome 可以通过默认路径访问
        echo.
        echo 现在请重启 Antigravity，然后就可以使用浏览器功能了！
    ) else (
        echo [警告] 链接创建了但无法访问 chrome.exe
    )
) else (
    echo.
    echo [错误] 符号链接创建失败
    echo 错误代码: %ERRORLEVEL%
    echo 请确保以管理员身份运行此脚本
)

echo.
pause
