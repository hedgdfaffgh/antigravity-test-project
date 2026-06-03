import os, re

base = r'C:\Users\Administrator\Desktop\gdfha.top\jiaoyimao_decompiled'

# 搜索所有 mtop API 端点（格式 mtop.xxx.xxx.xxx）
pattern = re.compile(r'mtop\.[a-zA-Z0-9_.]+')
api_set = set()

for root, dirs, files in os.walk(base):
    for f in files:
        if f.endswith('.smali'):
            fpath = os.path.join(root, f)
            try:
                with open(fpath, 'r', encoding='utf-8', errors='ignore') as fp:
                    content = fp.read()
                    matches = pattern.findall(content)
                    for m in matches:
                        if len(m) > 15 and m != 'mtop.jiaoyimao.com':
                            api_set.add(m)
            except:
                pass

print(f'Found {len(api_set)} unique mtop APIs:')
for api in sorted(api_set):
    print(f'  {api}')
