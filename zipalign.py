# -*- coding: utf-8 -*-
"""
zipalign.py - 手动实现 zipalign 功能
确保 resources.arsc 以 ZIP_STORED 方式存储且数据部分 4 字节对齐
"""
import struct
import os
import sys

ALIGNMENT = 4

def zipalign(src_path, dst_path):
    """
    对 APK 进行 zipalign，确保 resources.arsc 不压缩且 4 字节对齐
    直接操作二进制数据，不使用 zipfile 模块（避免 extra field 解析问题）
    """
    with open(src_path, 'rb') as f:
        src_data = f.read()
    
    # 找到所有 Local File Header
    entries = []
    offset = 0
    while offset < len(src_data):
        sig = struct.unpack_from('<I', src_data, offset)[0]
        if sig != 0x04034b50:  # Local File Header 签名
            break
        
        # 解析 Local File Header
        (version, flags, method, mtime, mdate, crc32, 
         comp_size, uncomp_size, name_len, extra_len) = struct.unpack_from(
            '<HHHHIIIIIHH', src_data, offset + 4
        )
        
        name = src_data[offset + 30 : offset + 30 + name_len].decode('utf-8', errors='replace')
        header_size = 30 + name_len + extra_len
        data_start = offset + header_size
        
        entries.append({
            'offset': offset,
            'name': name,
            'method': method,
            'flags': flags,
            'version': version,
            'mtime': mtime,
            'mdate': mdate,
            'crc32': crc32,
            'comp_size': comp_size,
            'uncomp_size': uncomp_size,
            'name_len': name_len,
            'extra_len': extra_len,
            'extra': src_data[offset + 30 + name_len : offset + 30 + name_len + extra_len],
            'data': src_data[data_start : data_start + comp_size],
            'header_size': header_size,
        })
        
        offset = data_start + comp_size
    
    # 找到 Central Directory
    cd_start = offset
    cd_data = src_data[cd_start:]
    
    # 重新写入，对齐 resources.arsc
    with open(dst_path, 'wb') as fout:
        new_offsets = {}
        
        for entry in entries:
            cur_pos = fout.tell()
            new_offsets[entry['name']] = cur_pos
            
            need_align = (entry['name'] == 'resources.arsc' or 
                         entry['name'].endswith('.so'))
            
            method = entry['method']
            if entry['name'] == 'resources.arsc':
                method = 0  # ZIP_STORED
            
            # 计算对齐需要的 padding
            if need_align and method == 0:
                base_header_size = 30 + entry['name_len']
                data_offset = cur_pos + base_header_size
                # 需要的 extra 大小使得 data 4 字节对齐
                padding = (ALIGNMENT - (data_offset % ALIGNMENT)) % ALIGNMENT
                extra = b'\x00' * padding
            else:
                extra = entry['extra']
                padding = 0
            
            extra_len = len(extra)
            
            # 写 Local File Header
            fout.write(struct.pack('<I', 0x04034b50))  # 签名
            fout.write(struct.pack('<HHHHIIIIIHH',
                entry['version'],
                entry['flags'],
                method,
                entry['mtime'],
                entry['mdate'],
                entry['crc32'],
                entry['comp_size'],
                entry['uncomp_size'],
                entry['name_len'],
                extra_len
            ))
            fout.write(entry['name'].encode('utf-8'))
            fout.write(extra)
            fout.write(entry['data'])
        
        # 写 Central Directory，更新偏移
        cd_new_start = fout.tell()
        
        # 解析并更新 Central Directory entries
        cd_offset = 0
        while cd_offset < len(cd_data):
            sig = struct.unpack_from('<I', cd_data, cd_offset)[0]
            if sig == 0x06054b50:  # End of Central Directory
                # 更新 EOCD
                eocd = bytearray(cd_data[cd_offset:])
                # 更新 CD 偏移 (offset 16)
                struct.pack_into('<I', eocd, 16, cd_new_start)
                fout.write(bytes(eocd))
                break
            elif sig != 0x02014b50:  # Central Directory 签名
                # 可能是 zip64 或其他记录，直接写入
                fout.write(cd_data[cd_offset:])
                break
            
            # 解析 CD entry
            (ver_made, ver_need, flags, method, mtime, mdate,
             crc32, comp_size, uncomp_size, name_len, extra_len,
             comment_len, disk_start, internal_attr, external_attr,
             local_offset) = struct.unpack_from(
                '<HHHHHHIIIIHHHHII', cd_data, cd_offset + 4
            )
            
            cd_entry_size = 46 + name_len + extra_len + comment_len
            cd_entry = bytearray(cd_data[cd_offset : cd_offset + cd_entry_size])
            
            # 获取文件名
            name = cd_data[cd_offset + 46 : cd_offset + 46 + name_len].decode('utf-8', errors='replace')
            
            # 更新 local file header 偏移
            if name in new_offsets:
                struct.pack_into('<I', cd_entry, 42, new_offsets[name])
            
            # 如果是 resources.arsc，确保 method = STORED
            if name == 'resources.arsc':
                struct.pack_into('<H', cd_entry, 10, 0)  # method = STORED
            
            # 更新 extra 长度（如果我们修改了 local file header 的 extra）
            # CD extra 保持不变
            
            fout.write(bytes(cd_entry))
            cd_offset += cd_entry_size
    
    size = os.path.getsize(dst_path)
    print(f"Aligned APK: {size} bytes ({size // 1024 // 1024} MB)")
    
    # 验证
    import zipfile
    try:
        with zipfile.ZipFile(dst_path) as z:
            for info in z.infolist():
                if info.filename == 'resources.arsc':
                    data_offset = info.header_offset + 30 + len(info.filename) + len(info.extra)
                    print(f"resources.arsc: offset={data_offset}, aligned={data_offset % 4 == 0}, stored={info.compress_type == 0}")
                    break
    except Exception as e:
        print(f"Verification warning: {e}")

if __name__ == '__main__':
    src = r'C:\Users\Administrator\Desktop\gdfha.top\jiaoyimao_patched2.apk'
    dst = r'C:\Users\Administrator\Desktop\gdfha.top\jiaoyimao_final.apk'
    zipalign(src, dst)
