# 支付宝 V3 SDK

支付宝开放平台 V3 接口 SDK，支持公私钥（RSA）和证书（CERT）两种签名方式。

## 目录结构

```
src/Pay/Alipay/V3/
├── Config.php        # 配置类
├── Client.php        # 客户端（核心请求）
├── Signer.php        # 签名器
├── Cryptography.php  # 加解密 / 证书工具
└── Upload.php        # 文件上传
```

## 安装

```bash
composer require wood/sdk
```

## 快速开始

### 1. 初始化配置

#### RSA 公私钥模式

```php
use Wood\Sdk\Pay\Alipay\Config;

$config = new Config([
    'appid'            => '2021xxxxxxxx',
    'sign_type'        => 'rsa',
    'gateway'          => 'https://openapi.alipay.com', // 可选，默认值
    'private_key'      => '-----BEGIN PRIVATE KEY-----...私钥内容...',
    'alipay_public_key'=> '-----BEGIN PUBLIC KEY-----...公钥内容...',
]);
```

#### 证书模式

```php
use Wood\Sdk\Pay\Alipay\Config;

$config = new Config([
    'appid'              => '2021xxxxxxxx',
    'sign_type'          => 'cert',
    'gateway'            => 'https://openapi.alipay.com',
    'private_key'        => '-----BEGIN PRIVATE KEY-----...私钥内容...',
    'alipay_app_cert'    => 'cert/alipay/appCertPublicKey.crt',
    'alipay_public_cert' => 'cert/alipay/alipayCertPublicKey_RSA2.crt',
    'alipay_root_cert'   => 'cert/alipay/alipayRootCert.crt',
]);
```

### 2. 发起请求

```php
use Wood\Sdk\Pay\Alipay\V3\Client;

$client = new Client($config);

// POST 请求
$result = $client->request('POST', '/v3/alipay.trade.pay', [
    'body' => [
        'out_trade_no' => 'ORDER_001',
        'total_amount' => '0.01',
        'subject'      => '测试订单',
    ],
]);

// GET 请求
$result = $client->request('GET', '/v3/alipay.trade.query', [
    'body' => [
        'out_trade_no' => 'ORDER_001',
    ],
]);
```

### 3. 上传图片

```php
use Wood\Sdk\Pay\Alipay\V3\Upload;

$upload = new Upload($config);

$image = file_get_contents('/path/to/image.jpg');
$result = $upload->image('/v3/alipay.marketing.material.image.upload', $image, 'jpg');
```

## 配置项说明

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `appid` | string | 是 | 小程序 AppId |
| `sign_type` | string | 是 | 签名方式：`rsa`（公私钥模式）或 `cert`（证书模式） |
| `private_key` | string | 是 | 应用私钥（RSA 模式为私钥内容，CERT 模式为私钥文件路径或内容） |
| `alipay_public_key` | string | rsa 模式必填 | 支付宝公钥 |
| `alipay_app_cert` | string(cert) | cert 模式必填 | 应用公钥证书（.crt 文件路径） |
| `alipay_public_cert` | string(cert) | cert 模式必填 | 支付宝公钥证书（.crt 文件路径） |
| `alipay_root_cert` | string(cert) | cert 模式必填 | 支付宝根证书（.crt 文件路径） |
| `gateway` | string | 否 | 网关地址，默认 `https://openapi.alipay.com` |

## 类说明

### Client — 核心客户端

继承自 `BaseClient`，封装了完整的请求流程：构建认证串 → 计算签名 → 发送 HTTP 请求 → 解析响应。

```php
$client = new Client($config);
$result = $client->request('POST', $path, ['body' => [...]]);
```

### Signer — 签名器

实现 `SignerInterface`，负责 ALIPAY-SHA256withRSA 签名计算。通常由 `Client` 内部调用，无需手动使用。

### Cryptography — 加解密 & 证书工具

提供证书序列号（SN）计算等功能。

```php
$crypto = new Cryptography($config);
$sn = $crypto->getCertSN(root_path() . $config->get('alipay_app_cert'));
```

### Upload — 文件上传

继承自 `Client`，用于图片等文件的 multipart 上传。

```php
$upload = new Upload($config);
$result = $upload->image($path, $binary_image_content, 'jpg');
```

## 异常处理

SDK 统一抛出以下异常：

- **`InvalidConfigException`** — 配置缺失或格式错误
- **`HttpRequestException`** — HTTP 请求失败（含状态码、响应体等信息）
- **`CryptoException`** — 加解密操作失败

```php
try {
    $result = $client->request('POST', '/v3/xxx', ['body' => [...]]);
} catch (\Wood\Sdk\Exceptions\HttpRequestException $e) {
    echo $e->getHttpCode();        // HTTP 状态码
    echo $e->getResBody();         // 响应体内容
    echo $e->getUrl();             // 请求 URL
} catch (\Wood\Sdk\Exceptions\InvalidConfigException $e) {
    // 配置错误处理
}
```

## 参考文档

- [支付宝 V3 开放平台文档](https://opendocs.alipay.com/open-v3)
- [V3 签名规则](https://opendocs.alipay.com/open-v3/054q58)
