# Cloudflare Workers VPN 搭建教程（完整版）

> 原理：利用 Cloudflare Workers 边缘节点 + KV 存储搭建代理订阅服务，配合 Clash Verge 使用。

---

## 准备条件
- [x] Cloudflare 账号（已有）
- [x] Clash Verge（已安装）
- [ ] 访问链接：https://dash.cloudflare.com

---

## 第一步：创建 KV 命名空间

1. 登录 https://dash.cloudflare.com
2. 左侧菜单 → **Storage & Databases** → **KV**
3. 点击右上角 **Create a namespace**
4. 名称填写：`SUBLINK`
5. 点击 **Add** 完成创建

---

## 第二步：创建 Cloudflare Worker

1. 左侧菜单 → **Workers & Pages**
2. 点击 **Create application**
3. 选择 **Create Worker**
4. Worker 名称填写：`my-vpn-sub`
5. 点击 **Deploy**（用默认代码先部署）
6. 部署成功后，点击 **Edit code**

---

## 第三步：粘贴 Worker 代码

在代码编辑器中：
1. **Ctrl+A** 全选所有代码
2. **Delete** 清空
3. 粘贴以下代码（见 worker.js 文件）
4. 点击右上角 **Deploy** 部署

> 📌 Worker 代码在同目录下的 `worker.js` 文件中，复制粘贴即可

---

## 第四步：绑定 KV 命名空间

1. Worker 页面顶部点 **Settings**（设置）
2. 找到 **Bindings**（绑定）区域
3. 点击 **Add binding**
4. 类型选择：**KV Namespace**
5. Variable name（变量名）填写：**`KV`**（必须大写）
6. KV namespace 下拉选择：**SUBLINK**
7. 点击 **Save**

---

## 第五步：设置 TOKEN（访问密码）

1. 继续在 Settings → Bindings
2. 再点 **Add binding**
3. 类型选择：**Environment Variable**（或 Text）
4. Variable name：`TOKEN`
5. Value（值）：`vpn2026`（这就是你的访问密码，可自定义）
6. 点击 **Save and deploy**

---

## 第六步：验证部署

打开浏览器访问：
```
https://my-vpn-sub.【你的CF用户名】.workers.dev/vpn2026
```

如果看到"节点管理"页面，说明部署成功！

---

## 第七步：添加代理节点

在节点管理页面的文本框中粘贴你的节点（每行一个），例如：
```
vless://xxxx@xxxx:443?...#节点名称
vmess://...
ss://...
```

点击**保存节点**。

---

## 第八步：Clash Verge 导入订阅

1. 打开 **Clash Verge**
2. 点击左侧 **Profiles**（配置）
3. 点击 **New Profile** 或 **导入**
4. 选择 **Remote**（远程）
5. URL 填写：
   ```
   https://my-vpn-sub.【你的CF用户名】.workers.dev/vpn2026?b64
   ```
6. 点击 **Save** 保存
7. 点击 **Update**（更新/刷新）
8. 点击 **Use**（使用）

---

## 常见问题

| 问题 | 解决方法 |
|------|---------|
| 访问返回"请绑定KV命名空间" | KV 绑定变量名必须是 **KV**（大写） |
| 访问返回 401 | TOKEN 设置错误，检查环境变量 |
| Worker 部署失败 | 检查代码是否有语法错误 |
| Clash 订阅失败 | 确保节点管理页面已保存节点 |

---

## 订阅链接格式

| 客户端 | 链接 |
|--------|------|
| Base64通用 | `https://worker地址/vpn2026?b64` |
| Clash | `https://worker地址/vpn2026?b64`（Clash识别Base64） |
| 管理页面 | `https://worker地址/vpn2026` |
