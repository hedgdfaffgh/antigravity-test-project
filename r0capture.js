// r0capture - Hook SSL_read/SSL_write 捕获解密后的明文流量
// 不修改 SSL 验证逻辑，仅在数据解密后读取明文
// 避免触发阿里城盾(chengdun)的反调试检测

// 保存抓包数据到文件
var output_file = "/data/local/tmp/r0capture.log";

function hexdump(buffer, options) {
    options = options || {};
    var length = options.length || buffer.byteLength;
    var offset = options.offset || 0;
    var result = '';
    for (var i = 0; i < length; i += 16) {
        var hex = '';
        var ascii = '';
        for (var j = 0; j < 16; j++) {
            if (i + j < length) {
                var b = buffer[offset + i + j];
                hex += ('0' + b.toString(16)).slice(-2) + ' ';
                ascii += (b >= 0x20 && b < 0x7f) ? String.fromCharCode(b) : '.';
            } else {
                hex += '   ';
            }
        }
        result += ('0000' + (offset + i).toString(16)).slice(-4) + '  ' + hex + ' ' + ascii + '\n';
    }
    return result;
}

function dumpData(tag, ssl_ptr, buf, len) {
    if (len <= 0) return;
    try {
        var data = Memory.readByteArray(buf, len);
        if (data) {
            var u8 = new Uint8Array(data);
            // 尝试解析为 UTF-8 文本
            var text = '';
            try {
                text = Memory.readUtf8String(buf, Math.min(len, 4096));
            } catch (e) {
                text = '[binary data]';
            }

            var timestamp = new Date().toISOString();
            console.log('\n========== ' + tag + ' [' + timestamp + '] ==========');
            console.log('SSL: ' + ssl_ptr + ' | Length: ' + len);

            // 判断是否是 HTTP 相关内容
            if (text && (text.indexOf('HTTP') !== -1 || text.indexOf('GET ') === 0 ||
                text.indexOf('POST ') === 0 || text.indexOf('{') === 0 ||
                text.indexOf('mtop') !== -1 || text.indexOf('jiaoyimao') !== -1 ||
                text.indexOf('Content-Type') !== -1)) {
                console.log('>>> HTTP DATA <<<');
                console.log(text.substring(0, 2048));
            } else {
                // 只显示前 128 字节的 hexdump
                console.log(hexdump(u8, { length: Math.min(len, 128) }));
            }
        }
    } catch (e) {
        console.log('[!] dumpData error: ' + e);
    }
}

// Hook 所有 SSL 库
function hookSSLLib(libName) {
    try {
        var mod = Process.findModuleByName(libName);
        if (!mod) return false;

        console.log('[*] Found ' + libName + ' at ' + mod.base);

        // Hook SSL_read
        var ssl_read = mod.findExportByName("SSL_read");
        if (ssl_read) {
            Interceptor.attach(ssl_read, {
                onEnter: function (args) {
                    this.ssl = args[0];
                    this.buf = args[1];
                    this.len = args[2].toInt32();
                },
                onLeave: function (retval) {
                    var ret = retval.toInt32();
                    if (ret > 0) {
                        dumpData('[' + libName + '] SSL_read', this.ssl, this.buf, ret);
                    }
                }
            });
            console.log('[+] Hooked ' + libName + '::SSL_read');
        }

        // Hook SSL_write
        var ssl_write = mod.findExportByName("SSL_write");
        if (ssl_write) {
            Interceptor.attach(ssl_write, {
                onEnter: function (args) {
                    this.ssl = args[0];
                    this.buf = args[1];
                    this.len = args[2].toInt32();
                    if (this.len > 0) {
                        dumpData('[' + libName + '] SSL_write', this.ssl, this.buf, this.len);
                    }
                }
            });
            console.log('[+] Hooked ' + libName + '::SSL_write');
        }

        return true;
    } catch (e) {
        console.log('[-] hookSSLLib(' + libName + '): ' + e);
        return false;
    }
}

// 延迟执行，等 native 库加载完
setTimeout(function () {
    console.log('[*] r0capture starting...');

    // Hook 系统 libssl.so
    hookSSLLib("libssl.so");

    // Hook 阿里自研网络库 libtnet
    var tnetNames = [
        "libtnet-4.0.0.so",
        "libtnet.so",
        "libtnet3.so",
        "libboringssl.so",
        "libcronet.so",
        "libnetjni.so"
    ];
    for (var i = 0; i < tnetNames.length; i++) {
        hookSSLLib(tnetNames[i]);
    }

    // 监控新模块加载 - 如果 libtnet 后续才加载
    Process.enumerateModules().forEach(function (mod) {
        if (mod.name.indexOf('tnet') !== -1 || mod.name.indexOf('ssl') !== -1 ||
            mod.name.indexOf('boring') !== -1 || mod.name.indexOf('cronet') !== -1) {
            console.log('[*] Detected SSL-related module: ' + mod.name + ' at ' + mod.base);
            hookSSLLib(mod.name);
        }
    });

    console.log('[*] r0capture loaded! Waiting for SSL traffic...');

}, 3000); // 延迟 3 秒等库加载
