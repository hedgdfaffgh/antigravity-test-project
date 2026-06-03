@echo off
echo [%date% %time%] GitHub MCP wrapper started >> C:\Users\Administrator\Desktop\gdfha.top\github_mcp_debug.log
echo [%date% %time%] Args: %* >> C:\Users\Administrator\Desktop\gdfha.top\github_mcp_debug.log
echo [%date% %time%] GITHUB_PERSONAL_ACCESS_TOKEN=%GITHUB_PERSONAL_ACCESS_TOKEN% >> C:\Users\Administrator\Desktop\gdfha.top\github_mcp_debug.log
"E:\Node.js\node.exe" "C:\Users\Administrator\AppData\Roaming\npm\node_modules\@modelcontextprotocol\server-github\dist\index.js" %*