# -*- coding: utf-8 -*-
"""
将 Charles DER 证书转换为 Android 系统证书格式
Android 系统证书文件名格式: <subject_hash_old>.0
"""
import struct
import hashlib
from cryptography import x509
from cryptography.hazmat.primitives import serialization, hashes
from cryptography.hazmat.backends import default_backend

def compute_subject_hash_old(cert):
    """
    计算 OpenSSL subject_hash_old (MD5 of canonical subject DER)
    这是 Android 系统证书目录使用的文件名格式
    """
    # 获取 subject 的 DER 编码
    subject_der = cert.subject.public_bytes()
    
    # MD5 hash，取前 4 字节，小端序转为 unsigned long
    md5 = hashlib.md5(subject_der).digest()
    hash_val = struct.unpack('<L', md5[:4])[0]
    return format(hash_val, '08x')

# 读取 DER 证书
with open(r'C:\Users\Administrator\Desktop\gdfha.top\charles.cer', 'rb') as f:
    der_data = f.read()

cert = x509.load_der_x509_certificate(der_data, default_backend())

# 转换为 PEM
pem_data = cert.public_bytes(serialization.Encoding.PEM)

# 计算 hash
hash_val = compute_subject_hash_old(cert)
print(f'Subject hash: {hash_val}')
print(f'Subject: {cert.subject}')

# 写入文件
out_path = rf'C:\Users\Administrator\Desktop\gdfha.top\{hash_val}.0'
with open(out_path, 'wb') as f:
    f.write(pem_data)
print(f'Written: {out_path}')
print(f'File size: {len(pem_data)} bytes')
