# 🚀 简木 PHP-SDK

✨ 个人开发的聚合支付与云服务 SDK，为简木聚合支付系统及其他项目提供支付宝、微信支付、易支付、阿里云、腾讯云、七牛云等第三方服务的统一接口封装。

> 📦 采用「`Config` 配置类 + `Client` 客户端类」的统一架构，所有服务共享同一套请求/签名/异常体系。

## 🌟 功能特性

- 💳 **支付宝 V3** — 支持 RSA 公私钥与证书两种签名方式，支持图片上传
- 💰 **微信支付 V3** — 支持 `WECHATPAY2-SHA256-RSA2048` 签名、AES-256-GCM 解密、RSA 公钥加密、图片上传
- 🔄 **易支付（EPay）** — 兼容 V1（MD5）与 V2（RSA-SHA256）签名，适配主流易支付网关
- ☁️ **阿里云短信** — 基于 `ACS3-HMAC-SHA256` 签名算法
- ☁️ **腾讯云短信** — 基于 `TC3-HMAC-SHA256` 签名算法
- 📦 **七牛云 Kodo** — 文件上传、列举、元信息查询、删除、移动、修改类型/元数据、私有下载链接
- ⚡ **双 HTTP 驱动** — 内置 Guzzle 与 Swoole 协程两种驱动，可全局切换

## 📋 环境要求

- 🐘 PHP >= 8.3
- 🔐 ext-openssl
- 🛡️ ext-sodium
- 🔢 ext-bcmath
- 📦 GuzzleHttp 7.x

## 📥 安装

```bash
composer require wood/sdk
```

## 🏗️ 架构概览

```
src/
├── Abstracts/
│   ├── BaseClient.php        # 客户端基类：统一请求流程、异常封装、HTTP 驱动切换
│   └── BaseConfig.php        # 配置基类：必填项校验、配置读取
├── Contracts/
│   └── SignerInterface.php   # 签名器契约
├── Pay/
│   ├── Alipay/V3/            # 支付宝 V3（Client / Signer / Cryptography / Upload）
│   ├── WeChat/               # 微信支付 V3（Client / Signer / Cryptography / Upload）
│   └── EPay/                 # 易支付（Client + V1/V2 Signer）
├── Cloud/
│   ├── Aliyun/Sms/           # 阿里云短信
│   └── Tencent/Sms/          # 腾讯云短信
├── Oss/Qiniu/                # 七牛云对象存储
├── Http/Swoole/              # Swoole 协程 HTTP 驱动
├── Exceptions/               # 统一异常体系
├── PathHelper.php            # 宿主项目根目录定位
└── helpers.php               # 全局辅助函数
```

每个模块遵循相同的用法范式：先构造 `Config`，再用它构造对应的 `Client`，最后调用 `request()` 或模块方法。

## 🚀 快速开始

### 💳 支付宝 V3

```php
use Wood\Sdk\Pay\Alipay\Config;
use Wood\Sdk\Pay\Alipay\V3\Client;

// RSA 公私钥模式
$config = new Config([
    'appid'             => '2021xxxxxxxx',
    'sign_type'         => 'rsa',
    'private_key'       => '-----BEGIN PRIVATE KEY-----...私钥内容...',
    'alipay_public_key' => '-----BEGIN PUBLIC KEY-----...公钥内容...',
    // 'gateway' => 'https://openapi.alipay.com', // 可选，默认值
]);

// 或证书模式
// $config = new Config([
//     'appid'              => '2021xxxxxxxx',
//     'sign_type'          => 'cert',
//     'private_key'        => '...私钥内容...',
//     'alipay_app_cert'    => 'cert/alipay/appCertPublicKey.crt',
//     'alipay_public_cert' => 'cert/alipay/alipayCertPublicKey_RSA2.crt',
//     'alipay_root_cert'   => 'cert/alipay/alipayRootCert.crt',
// ]);

$client = new Client($config);

// POST 请求（如当面付、电脑网站支付下单）
$result = $client->request('POST', '/v3/alipay.trade.pay', [
    'body' => [
        'out_trade_no' => 'ORDER_001',
        'total_amount' => '0.01',
        'subject'      => '测试订单',
    ],
]);

// GET 请求（如订单查询）
$result = $client->request('GET', '/v3/alipay.trade.query', [
    'body' => ['out_trade_no' => 'ORDER_001'],
]);
```

图片上传：

```php
use Wood\Sdk\Pay\Alipay\V3\Upload;

$upload  = new Upload($config);
$image   = file_get_contents('/path/to/image.jpg');
$result  = $upload->image('/v3/alipay.marketing.material.image.upload', $image, 'jpg');
```

### 💰 微信支付 V3

```php
use Wood\Sdk\Pay\WeChat\Config;
use Wood\Sdk\Pay\WeChat\Client;

$config = new Config([
    'merch_no'              => '商户号',
    'api_v3'                => 'API V3 密钥',
    'cert_number'           => '商户证书序列号',
    'private_key_path'      => 'test/apiclient_key.pem',       // 相对 root_path()
    'wxpay_public_key_id'   => '微信支付公钥 ID',
    'wxpay_public_key_path' => 'test/wxpay_public_key.pem',    // 可选，公钥加密时需要
]);

$client = new Client($config);

// JSAPI 下单
$result = $client->request('POST', '/v3/pay/transactions/jsapi', [
    'body' => [
        'mchid'        => $config->get('merch_no'),
        'appid'        => 'your_appid',
        'description'  => '测试订单',
        'out_trade_no' => 'ORDER_001',
        'notify_url'   => 'https://your-domain.com/notify/',
        'amount'       => ['total' => 100, 'currency' => 'CNY'],
        'payer'        => ['openid' => 'user_openid'],
    ],
]);

// GET 查询（body 作为 query 参数）
$result = $client->request('GET', '/v3/merchant-service/complaints-v2', [
    'body'     => ['begin_date' => '2023-10-01', 'end_date' => '2023-10-30', 'limit' => 10],
    'is_query' => true, // 将 body 转为 URL query 参数
]);
```

加解密与图片上传：

```php
use Wood\Sdk\Pay\WeChat\Cryptography;
use Wood\Sdk\Pay\WeChat\Upload;

$crypto = new Cryptography($config);

// AES-256-GCM 解密（回调通知/退款等）
$plain = $crypto->decryptByV3([
    'ciphertext'      => $encryptedData,
    'associated_data' => $associatedData,
    'nonce'           => $nonce,
]);

// RSA 私钥解密（敏感信息回传）
$plain = $crypto->decrypt($ciphertext);

// RSA 公钥加密（敏感信息提交）
$cipher = $crypto->encryptByPubKey($sensitiveData);

// 图片上传
$upload = new Upload($config);
$result = $upload->image('/v3/merchant-service/images/upload', file_get_contents('test/test-upload.png'), 'test-upload.png');
```

### 🔄 易支付（EPay）

```php
use Wood\Sdk\Pay\EPay\Config;
use Wood\Sdk\Pay\EPay\Client;

// V1 MD5 模式
$config = new Config([
    'gateway'   => 'https://your-epay-gateway.com',
    'sign_type' => 'md5',
    'secret'    => '商户密钥',
]);

// 或 V2 RSA 模式
// $config = new Config([
//     'gateway'     => 'https://your-epay-gateway.com',
//     'sign_type'   => 'rsa',
//     'private_key' => '商户私钥',
// ]);

$client = new Client($config);

$result = $client->request('POST', '/submit.php', [
    'body' => [
        'pid'         => '1000',
        'type'        => 'alipay',
        'out_trade_no'=> 'ORDER_001',
        'name'        => '测试订单',
        'money'       => '0.01',
        'notify_url'  => 'https://your-domain.com/notify',
        'return_url'  => 'https://your-domain.com/return',
        'sign_type'   => 'MD5', // 与配置一致：MD5 或 RSA
    ],
]);
```

### ☁️ 阿里云短信

```php
use Wood\Sdk\Cloud\Aliyun\Config;
use Wood\Sdk\Cloud\Aliyun\Sms\Client;

$config = new Config([
    'access_key_id'     => 'your_access_key_id',
    'access_key_secret' => 'your_access_key_secret',
    'sign_name'         => '你的签名',
    'template_code'     => 'SMS_123456789',
    // 'gateway' => 'dysmsapi.aliyuncs.com', // 可选，默认值
    // 'version' => '2017-05-25',            // 可选，默认值
]);

$client = new Client($config);

$result = $client->send('13800138000', ['code' => '1234']);

// 批量发送 / 覆盖模板
$result = $client->send(['13800138000', '13900139000'], ['code' => '1234'], [
    'template_code' => 'SMS_987654321',
]);
```

### ☁️ 腾讯云短信

```php
use Wood\Sdk\Cloud\Tencent\Config;
use Wood\Sdk\Cloud\Tencent\Sms\Client;

$config = new Config([
    'secret_id'      => 'your_secret_id',
    'secret_key'     => 'your_secret_key',
    'sms_sdk_app_id' => 'your_sdk_app_id',
    'sign_name'      => '你的签名',
    'template_id'    => '1234567',
    // 'region' => 'ap-guangzhou', // 可选，默认广州
]);

$client = new Client($config);

$result = $client->send('13800138000', ['1234']);
```

### 📦 七牛云 Kodo

```php
use Wood\Sdk\Oss\Qiniu\Config;
use Wood\Sdk\Oss\Qiniu\Client;

// 各区域域名参考：https://developer.qiniu.com/kodo/1671/region-endpoint-fq
$config = new Config([
    'access_key'      => 'your_access_key',
    'secret_key'      => 'your_secret_key',
    'bucket'          => 'your_bucket',
    'uc_domain'       => 'https://uc.qiniuapi.com',
    'upload_domain'   => 'https://up-z1.qiniup.com',
    'download_domain' => 'https://oss.example.com',
    'rs_domain'       => 'https://rs-z1.qiniuapi.com',
    'rsf_domain'      => 'https://rsf-z1.qiniuapi.com',
]);

$client = new Client($config);

// 上传本地文件
$result = $client->upload('images/photo.jpg', '/path/to/photo.jpg');

// 列举文件
$list = $client->getList(['prefix' => 'images/', 'limit' => 100]);

// 查询元信息（不存在时返回 FileNonExistent 实例，而非抛异常）
$stat = $client->stat('images/photo.jpg');

// 删除 / 移动 / 修改存储类型 / 修改元数据
$client->delete('images/photo.jpg');
$client->move('images/photo.jpg', 'images/photo2.jpg', true);
$client->chtype('images/photo.jpg', 1); // 0=标准 1=低频 2=归档 3=深度归档 4=归档直读 5=智能分层
$client->chgm('images/photo.jpg', ['mime' => 'image/jpeg', 'metadata' => ['key' => 'val']]);

// 生成私有空间下载链接
$url = $client->downloadUrl('images/photo.jpg', 3600);
```

## ⚡ HTTP 驱动

SDK 默认使用 Guzzle 驱动；在 Swoole 协程环境下可切换为内置的 Swoole 驱动，避免阻塞 Worker：

```php
use function Wood\Sdk\setDefaultHttpDriver;

setDefaultHttpDriver('swoole'); // 全局切换；默认 'guzzle'
```

切换后所有 `Client` 实例将使用 `Wood\Sdk\Http\Swoole\Client` 发起协程化请求，需在协程上下文中调用（如 `Co\run` / `go`）。

## 🛡️ 异常处理

所有 HTTP 错误统一抛出 `HttpRequestException`，携带状态码、响应体、请求 URL 等上下文：

```php
use Wood\Sdk\Exceptions\HttpRequestException;

try {
    $result = $client->request('POST', '/v3/xxx', ['body' => $data]);
} catch (HttpRequestException $e) {
    echo $e->getDetailedInfo(); // 终端/日志：完整请求响应详情
    echo $e->getSimpleInfo();   // 前端展示：一句话摘要
    $code = $e->getHttpCode();  // HTTP 状态码
    $body = $e->getResBody();   // 原始响应体
}
```

其他异常类型：

| 异常 | 触发场景 |
|------|----------|
| `InvalidConfigException` | 配置缺失或格式错误 |
| `CryptoException` | 加解密操作失败 |
| `SignException` | 签名计算/验证失败 |
| `FileNonExistent` | 七牛云 `stat` 查询的资源不存在（作为返回值，非抛出） |

## ⚠️ 注意事项

1. 📁 私钥/证书等文件路径**相对于 `root_path()` 解析**（微信支付、支付宝证书模式）。宿主项目需定义 `root_path()` 函数返回项目根目录；通过 Composer 安装时也可使用 `Wood\Sdk\PathHelper::getHostRoot()` 自动定位。
2. 🔒 私钥与证书文件请妥善保管，切勿提交到版本控制系统。
3. 💳 微信支付 V3 需商户证书序列号、API V3 密钥与商户私钥；公钥加密模式另需微信支付公钥。
4. ☁️ 阿里云使用 `ACS3-HMAC-SHA256`，腾讯云使用 `TC3-HMAC-SHA256`，均由对应 `Signer` 自动完成。
5. 📦 七牛云各区域域名需在 `Config` 中显式配置，参见 [区域 Endpoint 文档](https://developer.qiniu.com/kodo/1671/region-endpoint-fq)。
6. ⚡ Swoole 驱动仅在协程上下文可用，且需已安装 ext-swoole。

## 📚 模块文档

- [支付宝 V3 SDK](src/Pay/Alipay/V3/支付宝v3SDK.md)
- [微信支付 V3 SDK](src/Pay/WeChat/微信V3SDK.md)

## 👤 作者

🧑‍💻 WooD — 📧 wood217@163.com

## 📄 许可证

📜 本项目基于 MIT 许可证，详见 [LICENSE](LICENSE) 文件。
