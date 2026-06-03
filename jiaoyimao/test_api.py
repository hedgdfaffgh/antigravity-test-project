# -*- coding: utf-8 -*-
"""
交易猫 API 探测脚本
使用已有的 session 测试各种 API 接口
"""
import json
import time
import hashlib
import requests

# 加载 session
with open('session_17850298259.json', 'r', encoding='utf-8') as f:
    session_data = json.load(f)

SESSION_ID = session_data['sessionId']
UID = session_data['userInfo']['localId']
COOKIES = session_data['cookies']

print(f"Session: {SESSION_ID[:20]}...")
print(f"UID: {UID}")
print()

# mtop 签名
APP_KEY = "12574478"
API_VERSION = "1.0"

def mtop_sign(token, timestamp, app_key, data_str):
    raw = f"{token}&{timestamp}&{app_key}&{data_str}"
    return hashlib.md5(raw.encode()).hexdigest()

def call_mtop(api_name, data_dict, session=None):
    """调用 mtop 接口"""
    timestamp = str(int(time.time() * 1000))
    data_str = json.dumps(data_dict, separators=(',', ':'))
    
    # 获取 token
    s = requests.Session()
    s.headers.update({
        'User-Agent': 'Mozilla/5.0 (Linux; Android 12; OPPO) AppleWebKit/537.36',
        'Referer': 'https://m.jiaoyimao.com/',
    })
    
    # 设置 cookies
    cookies = {
        '_m_h5_tk': f"{SESSION_ID}_",
        '_m_h5_tk_enc': '',
    }
    if session:
        cookies.update(session)
    
    token = SESSION_ID
    sign = mtop_sign(token, timestamp, APP_KEY, data_str)
    
    params = {
        'jsv': '2.7.2',
        'appKey': APP_KEY,
        't': timestamp,
        'sign': sign,
        'v': API_VERSION,
        'type': 'originaljson',
        'dataType': 'json',
        'api': api_name,
        'data': data_str,
        'AntiCreep': 'true',
        'AntiFlood': 'true',
    }
    
    url = f"https://mtop.jiaoyimao.com/h5/{api_name}/{API_VERSION}/"
    
    try:
        resp = s.post(url, params=params, cookies=cookies, timeout=10)
        return resp.json()
    except Exception as e:
        return {'error': str(e)}

# ===== 测试各种 API =====

print("=" * 50)
print("1. 测试用户信息接口")
print("=" * 50)
result = call_mtop('mtop.ieu.member.passport.user.getuserinfo', {}, COOKIES)
print(json.dumps(result, ensure_ascii=False, indent=2)[:500])
print()

print("=" * 50)
print("2. 测试商品列表接口")
print("=" * 50)
result = call_mtop('mtop.jym.item.search', {
    'pageIndex': 1,
    'pageSize': 10,
    'gameId': '1',
}, COOKIES)
print(json.dumps(result, ensure_ascii=False, indent=2)[:500])
print()

print("=" * 50)
print("3. 测试订单列表接口")
print("=" * 50)
result = call_mtop('mtop.jym.order.list', {
    'pageIndex': 1,
    'pageSize': 10,
}, COOKIES)
print(json.dumps(result, ensure_ascii=False, indent=2)[:500])
print()

print("=" * 50)
print("4. 直接 HTTP 请求测试 (非 mtop)")
print("=" * 50)
headers = {
    'User-Agent': 'JYM/7.0.0 (Android 12; OPPO)',
    'Cookie': '; '.join([f'{k}={v}' for k, v in COOKIES.items()]),
    'Authorization': f'Bearer {SESSION_ID}',
}
try:
    resp = requests.get('https://a.jiaoyimao.com/api/user/info', headers=headers, timeout=10)
    print(f"Status: {resp.status_code}")
    print(resp.text[:500])
except Exception as e:
    print(f"Error: {e}")
