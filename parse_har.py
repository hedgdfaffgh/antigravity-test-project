# -*- coding: utf-8 -*-
"""解析 HAR 文件，提取所有 API 接口并 base64 解码响应"""
import json, base64, sys, io
from urllib.parse import urlparse, parse_qs, unquote
from collections import Counter

# 防止 GBK 编码错误
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

har_path = r'C:\Users\Administrator\Desktop\0095.har'

with open(har_path, 'r', encoding='utf-8', errors='replace') as f:
    har = json.load(f)

entries = har['log']['entries']
print(f'Total requests: {len(entries)}\n')

api_entries = []
for e in entries:
    url = e['request']['url']
    method = e['request']['method']
    status = e['response']['status']
    mime = e['response']['content'].get('mimeType', '')
    body_text = e['response']['content'].get('text', '')
    
    parsed = urlparse(url)
    
    # 尝试 base64 解码响应
    decoded_body = ''
    if body_text:
        try:
            decoded = base64.b64decode(body_text).decode('utf-8', errors='replace')
            # 检测是否为有效 JSON
            try:
                json.loads(decoded)
                decoded_body = decoded
            except:
                decoded_body = body_text[:200]
        except:
            decoded_body = body_text[:200]
    
    api_entries.append({
        'method': method,
        'url': url,
        'host': parsed.netloc,
        'path': parsed.path,
        'query': parsed.query,
        'status': status,
        'mime': mime,
        'body': decoded_body,
        'req_headers': {h['name']: h['value'] for h in e['request'].get('headers', [])},
        'req_body': e['request'].get('postData', {}).get('text', ''),
        'req_query': parse_qs(parsed.query),
    })

# 按域名分组
domains = Counter()
for e in api_entries:
    domains[e['host']] += 1

print('=== 域名统计 ===')
for d, c in domains.most_common(30):
    print(f'  {c:3d}x  {d}')

# 提取关键 API
print('\n\n=== 关键 mtop API 接口 ===')
mtop_apis = []
for e in api_entries:
    if 'mtop.' in e['path'] or 'mtop.' in e['url']:
        mtop_apis.append(e)
        # 从 URL 提取 API 名称
        path = e['path']
        api_name = path.split('/gw/')[-1].split('/')[0] if '/gw/' in path else path
        
        print(f"\n{'─'*80}")
        print(f"[{e['status']}] {e['method']} {api_name}")
        print(f"  URL: {e['url'][:150]}")
        
        if e['req_body']:
            # 解析 form data
            try:
                from urllib.parse import parse_qs
                params = parse_qs(e['req_body'])
                data_param = params.get('data', [''])[0]
                if data_param:
                    print(f"  Request data: {data_param[:300]}")
            except:
                print(f"  Request Body: {e['req_body'][:200]}")
        
        if e['body']:
            try:
                body_json = json.loads(e['body'])
                # 只打印关键字段
                print(f"  Response API: {body_json.get('api', '')}")
                print(f"  Response ret: {body_json.get('ret', '')}")
                data = body_json.get('data', {})
                if isinstance(data, dict):
                    print(f"  Response data keys: {list(data.keys())[:10]}")
                    # 如果有 result 字段，进一步展开
                    if 'result' in data and isinstance(data['result'], dict):
                        print(f"  Result keys: {list(data['result'].keys())[:15]}")
            except:
                print(f"  Response: {e['body'][:200]}")

# 提取 ecbp-api 接口
print('\n\n=== JiaoYiMao ecbp-api 接口 ===')
for e in api_entries:
    if 'ecbp-api' in e['path'] or 'api2' in e['path']:
        print(f"\n{'─'*80}")
        print(f"[{e['status']}] {e['method']} {e['host']}{e['path']}")
        if e['query']:
            print(f"  Query: {unquote(e['query'])[:200]}")
        if e['req_body']:
            print(f"  Body: {e['req_body'][:200]}")
        if e['body']:
            try:
                body_json = json.loads(e['body'])
                print(f"  Response: {json.dumps(body_json, ensure_ascii=False)[:300]}")
            except:
                print(f"  Response: {e['body'][:200]}")

# 保存所有解码的 API 到文件
output_path = r'C:\Users\Administrator\Desktop\gdfha.top\api_analysis.json'
api_output = []
for e in api_entries:
    if any(kw in e['url'] for kw in ['mtop.', 'ecbp-api', 'api2/', 'jiaoyimao']):
        entry = {
            'method': e['method'],
            'url': e['url'],
            'status': e['status'],
            'req_body': e['req_body'],
            'req_headers': e['req_headers'],
        }
        if e['body']:
            try:
                entry['response'] = json.loads(e['body'])
            except:
                entry['response'] = e['body'][:500]
        api_output.append(entry)

with open(output_path, 'w', encoding='utf-8') as f:
    json.dump(api_output, f, ensure_ascii=False, indent=2)
print(f'\n\nSaved {len(api_output)} API entries to {output_path}')
