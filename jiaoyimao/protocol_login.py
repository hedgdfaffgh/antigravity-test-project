# -*- coding: utf-8 -*-
"""
交易猫纯协议登录脚本 (无需浏览器)
通过 mtop 接口直接发送短信验证码 + 登录，获取 sessionId。

核心 API:
  1. mtop.ieu.member.passport.login.sendSmsCode   - 发送验证码
  2. mtop.ieu.member.passport.login.loginWithSmsCode - 验证码登录

mtop 签名算法: MD5(token + "&" + timestamp + "&" + appKey + "&" + data)

运行: E:\Python313\python.exe protocol_login.py
"""

import json
import time
import hashlib
import requests
import sys
import io

# Windows GBK 兼容
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

# ============ 常量 ============
MTOP_HOST = "https://mtop.jiaoyimao.com"
MEMBER_HOST = "https://member.jiaoyimao.com"
APP_KEY = "12574478"
JSV = "2.7.2"
UA = "Mozilla/5.0 (Linux; Android 13; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36"
REFERER = f"{MEMBER_HOST}/pages/one-click-login-modal?bizId=jiaoyimao&appCode=JYM_H5&referrer=index&gameid=undefined"


class JiaoYiMaoLogin:
    """交易猫纯协议登录"""

    def __init__(self):
        self.session = requests.Session()
        self.session.headers.update({
            "User-Agent": UA,
            "Origin": MEMBER_HOST,
            "Referer": REFERER,
        })
        self._m_h5_tk = ""  # mtop 令牌

    # -------- 内部方法 --------

    def _get_token(self) -> str:
        """从 _m_h5_tk cookie 中提取签名令牌 (MD5 值)"""
        tk = self.session.cookies.get("_m_h5_tk", domain=".jiaoyimao.com") or ""
        return tk.split("_")[0] if tk else ""

    def _mtop_sign(self, t: int, data: str) -> str:
        """
        mtop 签名算法
        sign = MD5(token + "&" + timestamp + "&" + appKey + "&" + data)
        """
        token = self._get_token()
        raw = f"{token}&{t}&{APP_KEY}&{data}"
        return hashlib.md5(raw.encode("utf-8")).hexdigest()

    def _build_request_dto(self, phone: str = "", sms_code: str = "",
                           session_id: str = "") -> str:
        """
        构造 mtop requestDTO JSON
        这是所有 passport 接口通用的请求体结构
        """
        dto = {
            "clientUser": {
                "sessionId": session_id,
                "localId": "",
                "passportId": "",
            },
            "clientDevice": {
                "os": "",
                "umid": "",
                "utdid": "",
                "appVer": "",
                "ip": "",
                "idfa": "",
                "userAgent": UA,
                "clientBizId": "jiaoyimao",
                "umidToken": "",
                "deviceId": "",
                "osVer": "",
                "clientAppCode": "JYM_H5",
                "clientType": "H5",
                "port": "",
                "refer": REFERER,
                "pkgName": "",
                "clientFlag": 0,
                "sdkVer": "",
                "appKey": "",
                "model": "",
                "brand": "",
                "oaid": "",
            },
            "clientScene": {
                "gameId": None,
                "sceneCode": "normal",
                "clientCaller": "member-frontend",
                "bizId": "jiaoyimao",
                "channel": "",
                "appCode": "JYM_H5",
                "extInfo": json.dumps({"INNER_KEY_AUTH_SCENE": "", "ugSid": ""}),
            },
        }

        # 可选字段
        if phone:
            dto["mobile"] = phone
            dto["areaCode"] = "86"
        if sms_code:
            dto["smsCode"] = sms_code

        return json.dumps(dto, ensure_ascii=False, separators=(",", ":"))

    def _mtop_call(self, api: str, data_json: str) -> dict:
        """
        发起 mtop POST 请求
        返回解析后的 JSON 响应
        """
        t = int(time.time() * 1000)
        wrapper = json.dumps({"requestDTO": data_json}, ensure_ascii=False, separators=(",", ":"))
        sign = self._mtop_sign(t, wrapper)

        # API 路径: /h5/{api_lowercase}/{version}/
        api_path = api.lower()
        url = f"{MTOP_HOST}/h5/{api_path}/1.0/"

        params = {
            "jsv": JSV,
            "appKey": APP_KEY,
            "t": str(t),
            "sign": sign,
            "api": api,
            "type": "originaljson",
            "dataType": "json",
            "v": "1.0",
            "timeout": "15000",
            "preventFallback": "true",
        }

        resp = self.session.post(
            url,
            params=params,
            data={"data": wrapper},
            headers={
                "Content-Type": "application/x-www-form-urlencoded",
                "x_passport_mtop": "true",
            },
        )
        return resp.json()

    # -------- 公开方法 --------

    def init_session(self):
        """
        Step 0: 初始化会话 — 获取 _m_h5_tk 令牌
        第一次 mtop 请求会返回 FAIL_SYS_TOKEN_EMPTY，但会 Set-Cookie _m_h5_tk
        """
        print("[1/4] Initializing session...")
        # 访问登录页获取基础 cookie
        self.session.get(REFERER)

        # 调用 clientLog 触发 _m_h5_tk 下发
        dto = self._build_request_dto()
        data = json.dumps({"requestDTO": dto}, ensure_ascii=False, separators=(",", ":"))
        t = int(time.time() * 1000)
        sign = self._mtop_sign(t, data)

        url = f"{MTOP_HOST}/h5/mtop.ieu.member.passport.client.log/1.0/"
        self.session.post(url, params={
            "jsv": JSV, "appKey": APP_KEY, "t": str(t), "sign": sign,
            "api": "mtop.ieu.member.passport.client.log",
            "type": "originaljson", "dataType": "json", "v": "1.0",
        }, data={"data": data}, headers={
            "Content-Type": "application/x-www-form-urlencoded",
        })

        token = self._get_token()
        if token:
            print(f"  Token acquired: {token[:16]}...")
            return True
        else:
            # 第一次调用会失败，但 cookie 已设置，重试一次
            token = self._get_token()
            if token:
                print(f"  Token acquired (retry): {token[:16]}...")
                return True
            print("  [!] Failed to get mtop token")
            return False

    def send_sms_code(self, phone: str) -> dict:
        """
        Step 1: 发送短信验证码
        API: mtop.ieu.member.passport.login.sendSmsCode
        """
        print(f"[2/4] Sending SMS code to {phone}...")
        dto = self._build_request_dto(phone=phone)
        result = self._mtop_call("mtop.ieu.member.passport.login.sendSmsCode", dto)

        inner = result.get("data", {})
        code = inner.get("code", "")
        msg = inner.get("msg", "")
        print(f"  Result: {code} - {msg}")

        if code == "SUCCESS":
            print("  SMS sent successfully!")
        else:
            print(f"  [!] Failed: {json.dumps(result, ensure_ascii=False)[:300]}")

        return result

    def login_with_sms(self, phone: str, sms_code: str) -> dict:
        """
        Step 2: 使用短信验证码登录
        API: mtop.ieu.member.passport.login.loginWithSmsCode
        """
        print(f"[3/4] Logging in with SMS code...")
        dto = self._build_request_dto(phone=phone, sms_code=sms_code)
        result = self._mtop_call("mtop.ieu.member.passport.login.loginWithSmsCode", dto)
        
        # DEBUG
        print(f"\n[DEBUG] Full Response:")
        print(json.dumps(result, indent=2, ensure_ascii=False))
        print()

        inner = result.get("data", {})
        code = inner.get("code", "")
        msg = inner.get("msg", "")

        if code == "SUCCESS":
            data = inner.get("data", {})
            session_info = data.get("sessionInfo", {})
            user_info = data.get("userBasicInfo", {})

            session_id = session_info.get("sessionId", "")
            refresh_token = session_info.get("refreshToken", "")
            nick = user_info.get("nickName", "")
            uid = user_info.get("localId", "")

            print(f"  LOGIN SUCCESS!")
            print(f"  NickName:     {nick}")
            print(f"  UID:          {uid}")
            print(f"  SessionId:    {session_id}")
            print(f"  RefreshToken: {refresh_token}")

            # 提取 cookies
            cookies = {}
            for c in session_info.get("cookies", []):
                cookies[c["keyName"]] = c["value"]
            print(f"  Cookies:      {json.dumps(cookies, indent=2)}")

            return {
                "success": True,
                "sessionId": session_id,
                "refreshToken": refresh_token,
                "cookies": cookies,
                "userInfo": user_info,
            }
        else:
            print(f"  [!] Login failed: {code} - {msg}")
            return {"success": False, "code": code, "msg": msg}

    def full_login(self, phone: str):
        """
        完整登录流程: 初始化 -> 发送验证码 -> 等待输入 -> 登录
        """
        # Step 0: 初始化
        if not self.init_session():
            print("[!] Session init failed, aborting")
            return None

        # Step 1: 发送验证码
        sms_result = self.send_sms_code(phone)
        inner_code = sms_result.get("data", {}).get("code", "")
        if inner_code != "SUCCESS":
            print("[!] SMS send failed, aborting")
            return None

        # Step 2: 等待用户输入验证码
        print(f"\n{'=' * 40}")
        sms_code = input("Please enter SMS code: ").strip()
        print(f"{'=' * 40}\n")

        if not sms_code:
            print("[!] No code entered, aborting")
            return None

        # Step 3: 登录
        return self.login_with_sms(phone, sms_code)


def main():
    if len(sys.argv) < 2:
        print("Usage: python protocol_login.py <phone_number>")
        print("Example: python protocol_login.py 13800138000")
        return

    phone = sys.argv[1]
    print(f"{'=' * 50}")
    print(f"JiaoYiMao Protocol Login")
    print(f"Phone: {phone}")
    print(f"{'=' * 50}\n")

    client = JiaoYiMaoLogin()
    result = client.full_login(phone)

    if result and result.get("success"):
        print(f"\n[4/4] Saving session...")
        out_file = f"session_{phone}.json"
        with open(out_file, "w", encoding="utf-8") as f:
            json.dump(result, f, ensure_ascii=False, indent=2)
        print(f"  Saved to: {out_file}")
        print(f"\nDone!")
    else:
        print(f"\nLogin failed.")


if __name__ == "__main__":
    main()
