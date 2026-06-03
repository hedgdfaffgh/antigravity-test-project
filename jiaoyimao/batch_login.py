# -*- coding: utf-8 -*-
"""
交易猫 批量登录主控脚本 (纯协议版，无需浏览器)
读取 accounts.json，逐个通过 mtop 接口发送验证码并登录。

用法:
  python batch_login.py              # 交互模式，逐个输入验证码
  python batch_login.py --auto       # 自动模式 (需对接短信转发)
"""

import os
import sys
import json
import time
import io

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

from protocol_login import JiaoYiMaoLogin

ACCOUNTS_FILE = os.path.join(os.path.dirname(__file__), "accounts.json")


def load_accounts() -> list:
    """加载账号列表"""
    if not os.path.exists(ACCOUNTS_FILE):
        print(f"[!] 配置文件不存在: {ACCOUNTS_FILE}")
        sys.exit(1)
    with open(ACCOUNTS_FILE, "r", encoding="utf-8") as f:
        return json.load(f)


def batch_login():
    """批量登录所有账号"""
    accounts = load_accounts()
    results = []

    print(f"{'=' * 50}")
    print(f"  交易猫批量登录 (纯协议)")
    print(f"  账号数: {len(accounts)}")
    print(f"{'=' * 50}\n")

    for i, account in enumerate(accounts, 1):
        phone = account.get("phone", "")
        remark = account.get("remark", "")

        if not phone:
            print(f"[{i}] 手机号为空，跳过")
            results.append({"phone": phone, "status": "skipped", "remark": remark})
            continue

        print(f"\n{'=' * 40}")
        print(f"[{i}/{len(accounts)}] {phone} ({remark})")
        print(f"{'=' * 40}")

        client = JiaoYiMaoLogin()

        # 初始化
        if not client.init_session():
            print("  [!] Session 初始化失败")
            results.append({"phone": phone, "status": "init_failed", "remark": remark})
            continue

        # 发送验证码
        sms_result = client.send_sms_code(phone)
        inner_code = sms_result.get("data", {}).get("code", "")
        if inner_code != "SUCCESS":
            print(f"  [!] 验证码发送失败")
            results.append({"phone": phone, "status": "sms_failed", "remark": remark})
            continue

        # 等待输入验证码
        sms_code = input(f"  输入 {phone} 的验证码: ").strip()
        if not sms_code:
            print("  [!] 未输入验证码，跳过")
            results.append({"phone": phone, "status": "skipped", "remark": remark})
            continue

        # 登录
        login_result = client.login_with_sms(phone, sms_code)
        if login_result.get("success"):
            # 保存 session
            out_file = os.path.join(os.path.dirname(__file__), f"session_{phone}.json")
            with open(out_file, "w", encoding="utf-8") as f:
                json.dump(login_result, f, ensure_ascii=False, indent=2)
            results.append({"phone": phone, "status": "success", "remark": remark})
        else:
            results.append({"phone": phone, "status": "login_failed", "remark": remark})

        # 间隔
        if i < len(accounts):
            time.sleep(3)

    # 汇总
    print(f"\n{'=' * 50}")
    print(f"  批量登录结果")
    print(f"{'=' * 50}")
    ok = 0
    for r in results:
        tag = "OK" if r["status"] == "success" else "FAIL"
        if r["status"] == "success":
            ok += 1
        print(f"  [{tag:4s}] {r['phone']}  {r['remark']}")
    print(f"\n  Total: {len(results)} | Success: {ok} | Failed: {len(results) - ok}")


if __name__ == "__main__":
    batch_login()
