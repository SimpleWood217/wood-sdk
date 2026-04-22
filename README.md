# 🚀 简木PHP-SDK

✨ 个人开发的聚合支付PHP SDK，封装支付宝、微信支付、阿里云等第三方服务的统一接口。

> 📦 用于简木聚合支付系统及其他项目的依赖包

## 🌐 官网展示

- 💳 **简木聚合收款系统** - [www.jmpay.cn](https://www.jmpay.cn)
- 🌐 **简木网络** - [www.jianm.cn](https://www.jianm.cn)

## 🌟 功能特性

- 💳 **支付宝 V3 接口** - 支持公私钥和证书两种签名方式，支持图片上传
- 🔄 **支付宝 V2 接口** - 传统接口版本，支持页面跳转和后端调用模式
- 💰 **微信支付 V3 接口** - 支持签名、解密、公钥加密、图片上传等功能
- ☁️ **阿里云 OpenAPI** - 支持 ACS3-HMAC-SHA256 签名算法，适用于短信等服务

## 📋 环境要求

- 🐘 PHP >= 8.3
- 🔐 ext-openssl
- 🛡️ ext-sodium
- 🔢 ext-bcmath
- 📦 GuzzleHttp 7.5.x

## 📥 安装

```bash
composer require wood/sdk
```

## 🚀 快速开始

### 💳 支付宝 V3 接口

```php
use Wood\Sdk\AlipayServiceClient;

// 📌 使用公私钥模式
$client = AlipayServiceClient::newInstance([
    'appid' => 'your_app_id',
    'private_key' => 'your_private_key',
    'alipay_public_key' => 'alipay_public_key',
    'sign_type' => 'rsa', // 默认值，可省略
]);

// 📌 或使用证书模式
$client = AlipayServiceClient::newInstance([
    'appid' => 'your_app_id',
    'private_key' => 'your_private_key',
    'alipay_app_cert' => 'path/to/appCertPublicKey.crt',
    'alipay_public_cert' => 'path/to/alipayCertPublicKey_RSA2.crt',
    'sign_type' => 'cert',
]);

// 🚀 发起请求
$client->setPath('/v3/alipay/trade/pay');
$client->setRequest([
    'out_trade_no' => '20230101001',
    'total_amount' => '0.01',
    'subject' => '测试订单',
]);
$result = $client->execute();

// 🧪 沙箱环境
$client->setSandbox(true);
```

### 🔄 支付宝 V2 接口

```php
use Wood\Sdk\AlipayServiceClientV2;

$client = AlipayServiceClientV2::newInstance([
    'appid' => 'your_app_id',
    'private_key' => 'your_private_key',
    'alipay_public_key' => 'alipay_public_key',
    'sign_type' => 'rsa',
]);

// 📝 设置接口和业务参数
$client->setAction('alipay.trade.page.pay');
$client->setBizContent([
    'out_trade_no' => '20230101001',
    'total_amount' => '0.01',
    'subject' => '测试订单',
]);
$client->setNotifyUrl('https://yourdomain.com/notify');
$client->setReturnUrl('https://yourdomain.com/return');

// 🔗 页面跳转模式（生成自动提交的表单）
$form = $client->execute();

// 🖥️ 后端调用模式
$client->setIsPageExecute(false);
$result = $client->execute();
```

### 💰 微信支付 V3 接口

```php
use Wood\Sdk\WxpayServiceClient;

$client = WxpayServiceClient::newInstance([
    'merch_no' => 'your_merchant_id',
    'api_v3' => 'your_api_v3_key',
    'cert_number' => 'your_cert_serial_number',
    'private_key_path' => 'path/to/apiclient_key.pem',
    'wxpay_public_key_id' => 'your_wxpay_public_key_id',
    'wxpay_public_key_path' => 'path/to/wxpay_pub_key.pem',
]);

// 🚀 发起支付请求
$client->setPath('/v3/pay/transactions/jsapi');
$client->setRequestBody([
    'mchid' => 'your_merchant_id',
    'out_trade_no' => '20230101001',
    'amount' => ['total' => 1, 'currency' => 'CNY'],
    'payer' => ['openid' => 'user_openid'],
]);
$result = $client->execute();

// 🔓 解密回调数据
$decryptData = $client->decryptByV3($ciphertext, $associatedData, $nonce);

// 🖼️ 上传图片
$client->setPath('/v3/merchant/media/upload');
$imageResult = $client->uploadImage($imageContent, 'image.jpg');
```

### ☁️ 阿里云 OpenAPI

```php
use Wood\Sdk\AliyunOpenAPI;

$client = new AliyunOpenAPI();
$client->setAccessKeyId('your_access_key_id');
$client->setAccessKeySecret('your_access_key_secret');
$client->setAction('SendSms');
$client->setVersion('2017-05-25');
$client->setRequestData([
    'PhoneNumbers' => '13800138000',
    'SignName' => '你的签名',
    'TemplateCode' => 'SMS_123456789',
    'TemplateParam' => '{"code":"1234"}',
]);
$result = $client->execute();
```

## ⚙️ 配置说明

### 🔐 签名类型

| 签名类型 | 支付宝 V3 | 支付宝 V2 | 微信支付 V3 | 阿里云 OpenAPI |
|---------|-----------|-----------|-------------|----------------|
| 🔑 RSA公私钥 | ✅ | ✅ | ✅ | ❌ |
| 📜 证书模式 | ✅ | ✅ | ✅ | ❌ |
| 🛡️ HMAC-SHA256 | ❌ | ❌ | ❌ | ✅ |

### 🛡️ 严格模式

支付宝客户端默认开启严格模式，会校验支付宝返回的响应签名：

```php
// ❌ 关闭严格模式
$client = new AlipayServiceClient(false);
```

## ⚠️ 注意事项

1. 📁 所有路径参数相对于项目根目录
2. 🔒 私钥和证书文件请妥善保管，不要提交到版本控制系统
3. 💳 微信支付V3接口需要商户证书序列号和API V3密钥
4. ☁️ 阿里云OpenAPI使用ACS3-HMAC-SHA256签名算法

## 👤 作者

🧑‍💻 WooD - 📧 wood217@163.com

## 📄 许可证

📜 本项目基于 MIT 许可证，详见 [LICENSE](LICENSE) 文件。
