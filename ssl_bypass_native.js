
// 交易猫 SSL Pinning Bypass - Native + Java 双层绕过
// 针对阿里 libsgmain.so 的 native SSL 实现

setTimeout(function () {

    // ===== 1. Hook SSL_CTX_set_verify (OpenSSL/BoringSSL native) =====
    try {
        var ssl_ctx_set_verify = Module.findExportByName("libssl.so", "SSL_CTX_set_verify");
        if (ssl_ctx_set_verify) {
            Interceptor.attach(ssl_ctx_set_verify, {
                onEnter: function (args) {
                    // mode=0 表示不验证
                    args[1] = ptr(0);
                    args[2] = ptr(0);
                }
            });
            console.log("[+] Hooked SSL_CTX_set_verify");
        }
    } catch (e) { console.log("[-] SSL_CTX_set_verify: " + e); }

    // ===== 2. Hook SSL_get_verify_result =====
    try {
        var ssl_get_verify_result = Module.findExportByName("libssl.so", "SSL_get_verify_result");
        if (ssl_get_verify_result) {
            Interceptor.replace(ssl_get_verify_result, new NativeCallback(function (ssl) {
                return 0; // X509_V_OK
            }, 'long', ['pointer']));
            console.log("[+] Hooked SSL_get_verify_result");
        }
    } catch (e) { console.log("[-] SSL_get_verify_result: " + e); }

    // ===== 3. Hook Java TrustManager =====
    try {
        Java.perform(function () {
            // 3a. X509TrustManager
            var TrustManagerImpl = Java.use("com.android.org.conscrypt.TrustManagerImpl");
            TrustManagerImpl.verifyChain.implementation = function (untrustedChain, trustAnchorChain, host, clientAuth, ocspData, tlsSctData) {
                console.log("[+] TrustManagerImpl.verifyChain bypassed for: " + host);
                return untrustedChain;
            };
            console.log("[+] Hooked TrustManagerImpl.verifyChain");
        });
    } catch (e) { console.log("[-] TrustManagerImpl: " + e); }

    // ===== 4. Hook OkHttp3 CertificatePinner =====
    try {
        Java.perform(function () {
            try {
                var CertificatePinner = Java.use("okhttp3.CertificatePinner");
                CertificatePinner.check.overload('java.lang.String', 'java.util.List').implementation = function (hostname, peerCertificates) {
                    console.log("[+] OkHttp3 CertificatePinner.check bypassed: " + hostname);
                    return;
                };
            } catch (e) { }

            try {
                var CertificatePinner2 = Java.use("okhttp3.CertificatePinner");
                CertificatePinner2.check.overload('java.lang.String', 'kotlin.jvm.functions.Function0').implementation = function (hostname, callback) {
                    console.log("[+] OkHttp3 CertificatePinner.check(kotlin) bypassed: " + hostname);
                    return;
                };
            } catch (e) { }
        });
    } catch (e) { console.log("[-] OkHttp3: " + e); }

    // ===== 5. Hook HttpsURLConnection =====
    try {
        Java.perform(function () {
            var HttpsURLConnection = Java.use("javax.net.ssl.HttpsURLConnection");
            HttpsURLConnection.setDefaultHostnameVerifier.implementation = function (verifier) {
                console.log("[+] HttpsURLConnection.setDefaultHostnameVerifier bypassed");
                return;
            };
            HttpsURLConnection.setSSLSocketFactory.implementation = function (factory) {
                console.log("[+] HttpsURLConnection.setSSLSocketFactory bypassed");
                return;
            };
            HttpsURLConnection.setHostnameVerifier.implementation = function (verifier) {
                return;
            };
        });
    } catch (e) { console.log("[-] HttpsURLConnection: " + e); }

    // ===== 6. Hook WebViewClient =====
    try {
        Java.perform(function () {
            var WebViewClient = Java.use("android.webkit.WebViewClient");
            WebViewClient.onReceivedSslError.implementation = function (webView, handler, error) {
                console.log("[+] WebViewClient.onReceivedSslError bypassed");
                handler.proceed();
            };
        });
    } catch (e) { console.log("[-] WebViewClient: " + e); }

    // ===== 7. Hook libsgmain.so SSL verify functions =====
    try {
        var sgmain = Process.findModuleByName("libsgmain.so");
        if (sgmain) {
            console.log("[*] libsgmain.so found at: " + sgmain.base);
            // 搜索 SSL_CTX_set_verify 在 sgmain 内的调用
            var exports = sgmain.enumerateExports();
            exports.forEach(function (exp) {
                if (exp.name.indexOf("ssl") !== -1 || exp.name.indexOf("SSL") !== -1 || exp.name.indexOf("verify") !== -1) {
                    console.log("[*] sgmain export: " + exp.name);
                }
            });
        }
    } catch (e) { console.log("[-] libsgmain: " + e); }

    // ===== 8. Hook tnet SSL (阿里自研网络库) =====
    try {
        var tnet = Process.findModuleByName("libtnet-4.0.0.so");
        if (tnet) {
            console.log("[*] libtnet found at: " + tnet.base);
            // Hook SSL_CTX_set_verify in tnet
            var ssl_verify = tnet.findExportByName("SSL_CTX_set_verify");
            if (ssl_verify) {
                Interceptor.attach(ssl_verify, {
                    onEnter: function (args) {
                        args[1] = ptr(0);
                        args[2] = ptr(0);
                    }
                });
                console.log("[+] Hooked tnet SSL_CTX_set_verify");
            }
        }
    } catch (e) { console.log("[-] tnet: " + e); }

    console.log("[*] SSL Bypass script loaded successfully!");

}, 1000); // 延迟1秒等待 Java VM 初始化
