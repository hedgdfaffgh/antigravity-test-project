// SSL Bypass - 最小化版本，只 bypass WebView，不动 TrustManagerImpl
// 附加到已运行的进程，不重启 APP

Java.perform(function () {
    console.log("[*] Minimal SSL Bypass - attach mode");

    // 只 bypass WebView SSL 错误（不影响 native 网络层）
    try {
        var WebViewClient = Java.use("android.webkit.WebViewClient");
        WebViewClient.onReceivedSslError.implementation = function (webView, handler, error) {
            console.log("[+] WebViewClient SSL error bypassed");
            handler.proceed();
        };
        console.log("[+] WebViewClient hooked");
    } catch (e) { console.log("[-] WebViewClient: " + e); }

    // 尝试 hook OkHttp (如果 APP 用的是 OkHttp)
    try {
        var OkHttpClient = Java.use("okhttp3.OkHttpClient");
        console.log("[+] OkHttp3 found in app");
    } catch (e) { console.log("[-] OkHttp3 not found: " + e); }

    console.log("[*] Done - Charles should now capture traffic");
});
