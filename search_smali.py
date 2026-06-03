import os, re

base = r'C:\Users\Administrator\Desktop\gdfha.top\jiaoyimao_decompiled'
patterns = [
    r'item[_/]detail', r'product[_/]detail', r'goods[_/]detail',
    r'jym\.item', r'jym\.product', r'jym\.goods', r'jym\.trade',
    r'mtop\.jiaoyimao', r'itemDetail', r'productDetail',
    r'getItemInfo', r'getProductInfo', r'queryItem',
    r'item_id', r'product_id', r'goods_id', r'itemId', r'productId',
]
combined = re.compile('|'.join(patterns), re.IGNORECASE)

results = []
for root, dirs, files in os.walk(base):
    for f in files:
        if f.endswith('.smali'):
            fpath = os.path.join(root, f)
            try:
                with open(fpath, 'r', encoding='utf-8', errors='ignore') as fp:
                    for i, line in enumerate(fp, 1):
                        if combined.search(line):
                            rel = os.path.relpath(fpath, base)
                            results.append((rel, i, line.strip()))
            except:
                pass

# 只显示包含字符串常量的行
string_results = [(r, i, l) for r, i, l in results if 'const-string' in l]
print(f'Total const-string matches: {len(string_results)}')
for rel, i, line in string_results[:50]:
    print(f'{rel}:{i}: {line}')
