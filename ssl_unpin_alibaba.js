/**
 * ssl_unpin_alibaba.js
 * 增强版 SSL Unpinning 脚本 — 专门针对阿里系 SDK (tnet/mtopsdk/accs)
 * 
 * 策略：延迟 hook，只 hook Java 层 TrustManager，不碰 native 层
 * 通过 Android 系统的 TrustManagerImpl 来绕过证书校验
 */

function hookAll() {
    Java.perform(function () {
        console.log('[*] SSL Unpinning 开始...');

        // ===== 1. 强制信任所有证书 — TrustManagerImpl.checkServerTrusted =====
        try {
            var TrustManagerImpl = Java.use('com.android.org.conscrypt.TrustManagerImpl');
            // Android 7+ 的 verifyChain
            TrustManagerImpl.verifyChain.overload(
                '[Ljava.security.cert.X509Certificate;',
                'java.lang.String',
                'java.net.Socket',
                'boolean',
                '[B',
                '[B'
            ).implementation = function (untrustedChain, authType, socket, checkPinning, ocspData, tlsSctData) {
                console.log('[+] TrustManagerImpl.verifyChain => 跳过 pinning 检查');
                // 调用原函数但传 checkPinning=false 来跳过 pinning
                return this.verifyChain(untrustedChain, authType, socket, false, ocspData, tlsSctData);
            };
            console.log('[+] TrustManagerImpl.verifyChain hooked');
        } catch (e) {
            console.log('[-] TrustManagerImpl.verifyChain: ' + e.message);
            // 尝试其他重载
            try {
                var TrustManagerImpl2 = Java.use('com.android.org.conscrypt.TrustManagerImpl');
                TrustManagerImpl2.checkServerTrusted.overload(
                    '[Ljava.security.cert.X509Certificate;',
                    'java.lang.String'
                ).implementation = function (certs, authType) {
                    console.log('[+] TrustManagerImpl.checkServerTrusted(2) => 已绕过');
                };
                console.log('[+] TrustManagerImpl.checkServerTrusted(2) hooked');
            } catch (e2) {
                console.log('[-] TrustManagerImpl fallback: ' + e2.message);
            }
        }

        // ===== 2. OkHttp3 CertificatePinner =====
        try {
            var CertPinner = Java.use('okhttp3.CertificatePinner');
            CertPinner.check.overload('java.lang.String', 'java.util.List').implementation = function (hostname, peerCerts) {
                console.log('[+] OkHttp3 CertificatePinner.check => 跳过: ' + hostname);
                // 空实现 = 不校验
            };
            console.log('[+] OkHttp3 CertificatePinner hooked');
        } catch (e) {
            console.log('[-] OkHttp3 CertificatePinner: ' + e.message);
        }

        // ===== 3. OkHttp3 混淆版 (a/b/c 类名) =====
        // 交易猫可能使用了混淆后的 OkHttp
        var okhttp_variants = [
            'com.squareup.okhttp.CertificatePinner',
            'okhttp3.internal.tls.OkHostnameVerifier'
        ];
        okhttp_variants.forEach(function (className) {
            try {
                var cls = Java.use(className);
                if (cls.check) {
                    cls.check.overloads.forEach(function (overload) {
                        overload.implementation = function () {
                            console.log('[+] ' + className + '.check => 跳过');
                        };
                    });
                    console.log('[+] ' + className + ' hooked');
                }
            } catch (e) { }
        });

        // ===== 4. Conscrypt / BoringSSL =====
        try {
            var Platform = Java.use('com.android.org.conscrypt.Platform');
            Platform.checkServerTrusted.overload(
                'javax.net.ssl.X509TrustManager',
                '[Ljava.security.cert.X509Certificate;',
                'java.lang.String',
                'com.android.org.conscrypt.AbstractConscryptSocket'
            ).implementation = function (tm, chain, authType, socket) {
                console.log('[+] Conscrypt Platform.checkServerTrusted => 跳过');
            };
            console.log('[+] Conscrypt Platform hooked');
        } catch (e) {
            console.log('[-] Conscrypt Platform: ' + e.message);
        }

        // ===== 5. 阿里系 ACCS/TNet 的 SSLSocketFactory =====
        var aliClasses = [
            'anetwork.channel.http.NetworkSdkSetting',
            'mtopsdk.network.NetworkSetting',
            'com.taobao.accs.net.AccsSSLSocketFactory',
            'anetwork.channel.security.SSLHelper'
        ];
        aliClasses.forEach(function (className) {
            try {
                var cls = Java.use(className);
                var methods = cls.class.getDeclaredMethods();
                console.log('[*] Found ' + className + ' with ' + methods.length + ' methods');
                methods.forEach(function (m) {
                    var methodName = m.getName();
                    if (methodName.toLowerCase().indexOf('ssl') >= 0 ||
                        methodName.toLowerCase().indexOf('cert') >= 0 ||
                        methodName.toLowerCase().indexOf('trust') >= 0 ||
                        methodName.toLowerCase().indexOf('verify') >= 0 ||
                        methodName.toLowerCase().indexOf('pin') >= 0) {
                        console.log('[*]   -> ' + className + '.' + methodName);
                    }
                });
            } catch (e) { }
        });

        // ===== 6. 通用 X509TrustManager 拦截 =====
        try {
            var X509TM = Java.use('javax.net.ssl.X509TrustManager');
            var SSLContext = Java.use('javax.net.ssl.SSLContext');

            // 创建一个信任所有证书的 TrustManager
            var TrustManager = Java.registerClass({
                name: 'com.trustall.TrustAllManager',
                implements: [X509TM],
                methods: {
                    checkClientTrusted: function (chain, authType) { },
                    checkServerTrusted: function (chain, authType) { },
                    getAcceptedIssuers: function () {
                        return [];
                    }
                }
            });
            console.log('[+] TrustAll manager registered');
        } catch (e) {
            console.log('[-] TrustAll: ' + e.message);
        }

        // ===== 7. HostnameVerifier =====
        try {
            var HV = Java.use('javax.net.ssl.HttpsURLConnection');
            HV.setDefaultHostnameVerifier.implementation = function (verifier) {
                console.log('[+] setDefaultHostnameVerifier => 跳过');
            };
            console.log('[+] HttpsURLConnection.setDefaultHostnameVerifier hooked');
        } catch (e) {
            console.log('[-] setDefaultHostnameVerifier: ' + e.message);
        }

        // ===== 8. 阿里系 mtopsdk 网络安全配置 =====
        try {
            var MtopEnv = Java.use('mtopsdk.mtop.global.MtopConfig');
            var methods = MtopEnv.class.getDeclaredMethods();
            methods.forEach(function (m) {
                console.log('[*] MtopConfig.' + m.getName());
            });
        } catch (e) { }

        try {
            var MtopEnv = Java.use('mtopsdk.mtop.global.MtopEnv');
            var methods = MtopEnv.class.getDeclaredMethods();
            methods.forEach(function (m) {
                console.log('[*] MtopEnv.' + m.getName());
            });
        } catch (e) { }

        console.log('[*] SSL Unpinning 完成! 等待网络请求...');
    });
}

// 延迟执行，等 APP 完全加载
setTimeout(hookAll, 3000);
