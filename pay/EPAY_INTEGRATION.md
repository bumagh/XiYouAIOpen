# 易支付接入经验总结

## 架构概览

```
用户浏览器
    │
    ▼
new-api (Go, Docker)          ← 用户充值请求
    │  MD5签名
    ▼
nginx反向代理
    │
    ▼
PHP桥接层 /var/www/pay/       ← 负责签名转换
    │  RSA签名
    ▼
上游易支付平台 pay.plugins-world.cn
    │
    ├─ notify.php (异步回调)  ← RSA验签 → MD5重签 → 转发new-api
    └─ return.php (同步跳转)  ← 仅做用户跳转，不做支付确认
```

## 两套系统说明

本项目有**两套独立的易支付集成**，共用同一个上游平台账号（pid=1019）：

| 系统 | 位置 | 用途 | 签名方式 |
|------|------|------|----------|
| new-api PHP桥接 | VPS `/var/www/pay/` | 用户在线充值 | new-api→桥接用MD5，桥接→上游用RSA |
| 本地ThinkPHP应用 | `pay/paysdk/` + `pay/Payworld.php` | Petto/创乐坊商城支付 | 全程RSA |

---

## 关键配置

### VPS PHP桥接配置 `/var/www/pay/config.php`

```php
define('UPSTREAM_API', 'https://pay.plugins-world.cn/');
define('MERCHANT_PID', '1019');
define('PLATFORM_PUBLIC_KEY', '平台RSA公钥');   // 用于验证上游回调签名
define('MERCHANT_PRIVATE_KEY', '商户RSA私钥');  // 用于向上游发起请求时签名
define('BRIDGE_MD5_KEY', 'srTKu6D6Yeyuts10y5Dy31KVB1J10yF1');  // 与new-api后台一致
define('NEWAPI_NOTIFY_URL', 'https://xiyouai.cloud/api/user/epay/notify');
define('NEWAPI_RETURN_URL', 'https://xiyouai.cloud/console/log');
```

### 本地ThinkPHP配置 `pay/paysdk/lib/epay.config.php`

```php
'pid'                  => '1019',
'apiurl'               => 'https://pay.plugins-world.cn/',
'merchant_private_key' => '商户RSA私钥（与VPS一致）',
'platform_public_key'  => '平台RSA公钥（与VPS一致）',
```

### new-api后台配置

- 支付地址：`https://xiyouai.cloud/pay`（nginx转发到PHP桥接的submit.php）
- 商户ID：`1019`
- 商户密钥：`srTKu6D6Yeyuts10y5Dy31KVB1J10yF1`（即BRIDGE_MD5_KEY）

---

## 签名规则（易支付V2 RSA）

### 签名内容构造

1. 取所有请求参数
2. 排除 `sign`、`sign_type` 字段
3. 排除值为空字符串、null、数组的字段
4. 按参数名 ASCII 升序排列（ksort）
5. 拼接为 `key=value&key=value` 格式
6. 用商户私钥做 SHA256WithRSA 签名，base64编码

```php
function buildSignContent(array $params): string {
    $filtered = [];
    foreach ($params as $k => $v) {
        if ($k === 'sign' || $k === 'sign_type') continue;
        if (is_array($v) || is_object($v)) continue;
        if ($v === null) continue;
        $v = trim((string)$v);
        if ($v === '') continue;
        $filtered[$k] = $v;
    }
    ksort($filtered);
    return implode('&', array_map(fn($k,$v) => "$k=$v", array_keys($filtered), $filtered));
}

function rsaSign(string $data, string $privateKey): string {
    $key = "-----BEGIN PRIVATE KEY-----\n" . wordwrap($privateKey, 64, "\n", true) . "\n-----END PRIVATE KEY-----";
    openssl_sign($data, $sign, openssl_get_privatekey($key), OPENSSL_ALGO_SHA256);
    return base64_encode($sign);
}
```

### 验签（验证上游回调）

用平台公钥验证，算法同上（SHA256WithRSA）。

---

## 踩坑记录

### 坑1：notify.php 包含了自定义参数参与签名

**现象**：支付成功后 notify 回调 RSA 验签失败，返回 `fail`，上游平台反复重试。

**原因**：`notify_url` 里带了 `orig_notify` 参数（桥接层自己加的，用于记录转发目标），上游平台回调时会把 URL 上的参数一起带回来。旧代码没有 `unset($params['orig_notify'])`，导致签名内容多了这个字段。

**修复**：在验签前先 `unset($params['orig_notify'])`。

```php
$origNotify = $_GET['orig_notify'] ?? NEWAPI_NOTIFY_URL;
$params = array_merge($_GET, $_POST);
unset($params['orig_notify']);  // ← 关键：排除自定义参数再验签
// 然后再做 verifyRsa($params, PLATFORM_PUBLIC_KEY)
```

同理，`return.php` 也要 `unset($params['orig_return'])`。

---

### 坑2：return URL 始终验签失败

**现象**：用户支付完成跳回页面，`return.php` 的 RSA 验签始终返回 false，用户看到 `pay=fail`。

**原因**：上游平台对 return URL 和 notify URL 使用的签名密钥/方式可能不同，或 return URL 参数经过浏览器跳转后有编码变化。

**修复**：return URL 仅用于用户体验跳转，**支付确认以 notify 为准**。无论 RSA 验签是否通过，都跳转到 `?pay=pending`，让前端轮询订单状态。

```php
$rsaOk = verifyRsa($params, PLATFORM_PUBLIC_KEY);
if (!$rsaOk) {
    error_log("[pay/return] RSA verify failed");
}
// 无论验签结果，都跳 pending
header("Location: " . $origReturn . "?pay=pending");
exit;
```

---

### 坑3：商户私钥不匹配

**现象**：向上游提交支付请求，上游返回"RSA签名校验失败"。

**原因**：VPS `config.php` 和本地 `epay.config.php` 里的 `MERCHANT_PRIVATE_KEY` 是旧密钥，平台已更换新密钥对。

**修复**：从平台后台获取新的商户私钥，同步更新两处配置文件。

**验证方法**：
```php
$key = "-----BEGIN PRIVATE KEY-----\n" . wordwrap(MERCHANT_PRIVATE_KEY, 64, "\n", true) . "\n-----END PRIVATE KEY-----";
$pk = openssl_get_privatekey($key);
echo $pk ? "OK" : "FAIL: " . openssl_error_string();
```
然后发一个测试请求，上游返回"最小支付金额是X元"而非"RSA签名校验失败"即为成功。

---

### 坑4：EpayCore 时间戳窗口过短

**现象**：用户支付后超过5分钟才回到页面，`payReturn()` 验签失败（`abs(time() - $arr['timestamp']) > 300`）。

**修复**：将 `EpayCore.php` 中的时间戳窗口从 300 秒改为 600 秒（或在 return URL 场景跳过时间戳检查）。

---

### 坑5：submit.php 路径重复

**现象**：PHP 报 parse error，路径变成 `/pay/submit.php/submit.php`。

**原因**：nginx location 配置或 PHP 路由拼接时路径重复。

**修复**：检查 nginx `fastcgi_param SCRIPT_FILENAME` 配置，确保路径不重复。

---

## VPS PHP桥接文件说明

| 文件 | 作用 |
|------|------|
| `config.php` | 配置：上游API、商户ID、RSA密钥、MD5密钥、回调地址 |
| `submit.php` | 入口：验证new-api的MD5签名，用RSA重签后POST表单提交给上游 |
| `notify.php` | 异步回调：验证上游RSA签名，用MD5重签后转发给new-api |
| `return.php` | 同步跳转：用户支付后跳回，无论验签结果都跳pending |
| `EpayCore.php` | 核心SDK：RSA签名/验签工具类 |

---

## 调试技巧

### 查看回调日志
```bash
docker exec new-api tail -f /app/logs/$(date +%Y-%m-%d).log
# 或查看PHP错误日志
tail -f /var/log/nginx/error.log
```

### 手动测试签名
```bash
scp test_sign.php ubuntu@43.161.248.131:/tmp/
ssh ubuntu@43.161.248.131 "sudo php /tmp/test_sign.php"
```

### 查看订单状态
```bash
docker exec postgres psql -U root -d new-api -c \
  "SELECT id, amount, money, trade_no, status FROM top_ups ORDER BY id DESC LIMIT 10;"
```

### 查看用户额度
```bash
docker exec postgres psql -U root -d new-api -c \
  "SELECT username, quota, used_quota, round(quota::numeric/500000,4) as quota_usd FROM users;"
```
