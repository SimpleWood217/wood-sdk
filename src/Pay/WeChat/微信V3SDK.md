# 微信支付 V3 SDK 使用文档

本 SDK 提供了微信支付 API V3 接口的统一封装，包含签名、加解密、图片上传等功能。

## 目录结构

- `Config.php` — 配置管理
- `Client.php` — 通用 HTTP 请求客户端
- `Upload.php` — 图片/媒体上传客户端
- `Signer.php` — RSA-SHA256 签名器
- `Cryptography.php` — 加解密工具类

## 快速开始

### 1. 初始化配置

```php
use Wood\Sdk\Pay\WeChat\Config as WechatConfig;

$config = new WechatConfig([
    'merch_no'              => '商户号',
    'api_v3'                => 'API V3 密钥',
    'cert_number'           => '商户证书序列号',
    'private_key_path'      => 'test/apiclient_key.pem', // 商户 API 私钥路径（相对 root_path）
    'wxpay_public_key_id'   => '微信支付公钥 ID',
    'wxpay_public_key_path' => 'test/wxpay_public_key.pem', // 微信支付公钥路径（可选）
]);
```

### 2. POST 请求（下单）

```php
use Wood\Sdk\Pay\WeChat\Client as WechatClient;

$client = new WechatClient($config);

try {
    $result = $client->request('POST', '/v3/pay/transactions/jsapi', [
        'body' => [
            'mchid'        => $config->get('merch_no'),
            'appid'        => 'your_appid',
            'description'  => '测试订单',
            'out_trade_no' => '1694534567890123456',
            'notify_url'   => 'https://your-domain.com/notify/',
            'amount'       => [
                'total'    => 100,
                'currency' => 'CNY',
            ],
            'payer'        => [
                'openid' => 'user_openid',
            ],
        ],
    ]);
    
    // $result 为 array（微信返回的 JSON 数据）
    var_dump($result);
} catch (\Wood\Sdk\Exceptions\HttpRequestException $e) {
    // 终端调试输出
    echo $e->getDetailedInfo();
    
    // 前端简短提示
    echo $e->getSimpleInfo();
}
```

### 3. GET 请求（查询）

```php
// 路径参数
$result = $client->request('GET', '/v3/pay/transactions/out-trade-no/{trade_no}', [
    'body' => [
        'mchid' => $config->get('merch_no'),
    ],
]);

// Query 参数（is_query => true）
$result = $client->request('GET', '/v3/merchant-service/complaints-v2', [
    'body'     => [
        'begin_date' => '2023-10-01',
        'end_date'   => '2023-10-30',
        'limit'      => 10,
    ],
    'is_query' => true, // 将 body 转为 URL query 参数
]);
```

### 4. 图片上传

```php
use Wood\Sdk\Pay\WeChat\Upload as UploadClient;

$uploadClient = new UploadClient($config);

$result = $uploadClient->image(
    '/v3/merchant-service/images/upload',
    file_get_contents(root_path() . 'test/test-upload.png'),
    'test-upload.png'
);

var_dump($result);
```

## 加解密工具

```php
use Wood\Sdk\Pay\WeChat\Cryptography;

$crypto = new Cryptography($config);

// RSA 私钥解密（敏感信息）
$decrypted = $crypto->decrypt($ciphertext);

// AES-256-GCM 解密（V3 回调通知/退款等）
$decrypted = $crypto->decryptByV3([
    'ciphertext'      => $encryptedData,
    'associated_data' => $associatedData,
    'nonce'           => $nonce,
]);

// RSA 公钥加密（敏感信息提交）
$encrypted = $crypto->encryptByPubKey($sensitiveData);
```

## 异常处理

### 详细调试输出（终端/日志）

```php
catch (\Wood\Sdk\Exceptions\HttpRequestException $e) {
    echo $e->getDetailedInfo();
}
```

输出示例：
```
========== HTTP Request Exception ==========
Message:    请求网关失败
HTTP Code:  400
Method:     POST
URL:        https://api.mch.weixin.qq.com/v3/pay/transactions/jsapi
Response:   {
    "code": "SIGN_ERROR",
    "message": "签名错误"
}
Request:    {"mchid":"123",...}
====================================================
```

### 简短信息输出（前端/API 返回）

```php
catch (\Wood\Sdk\Exceptions\HttpRequestException $e) {
    echo $e->getSimpleInfo();
}
```

输出示例：
```
HTTP请求异常：请求网关失败 (状态码: 400)，原因: 签名错误
```

### 获取原始响应体

```php
catch (\Wood\Sdk\Exceptions\HttpRequestException $e) {
    $rawBody = $e->getResBody();
    $httpCode = $e->getHttpCode();
}
```

## 配置参数说明

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `merch_no` | string | 是 | 微信支付商户号 |
| `api_v3` | string | 是 | API V3 密钥（用于 AES-256-GCM 解密） |
| `cert_number` | string | 是 | 商户 API 证书序列号 |
| `private_key_path` | string | 是 | 商户 API 私钥文件路径（相对 `root_path()`） |
| `wxpay_public_key_id` | string | 是 | 微信支付公钥 ID |
| `wxpay_public_key_path` | string | 否 | 微信支付公钥文件路径（公钥加密模式需要） |

## 注意事项

1. 确保 `root_path()` 函数已定义，返回项目根目录路径
2. `jsonEncode()` 函数需全局可用，用于 JSON 序列化
3. 私钥文件路径相对于 `root_path()` 解析
4. GET 请求使用 `is_query => true` 时，body 会转为 URL query 参数
5. 所有请求自动处理签名（WECHATPAY2-SHA256-RSA2048）
