<?php

namespace Wood\Sdk;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use OpenSSLAsymmetricKey;
use SodiumException;

/**
 * 微信支付V3接口SDK
 * V3文档：https://pay.weixin.qq.com/doc/v3/merchant/4012081709
 *
 * @author  WooD
 * @version 1.0.0
 */
class WxpayServiceClient
{
    private string $gateway               = 'https://api.mch.weixin.qq.com';
    private string $method                = 'POST';
    private bool   $async                 = false;
    private bool   $is_query              = false;
    private string $path                  = '';
    private string $merch_no;
    private string $api_v3;
    private string $cert_number;
    private string $privateKeyPath;
    private string $wxpay_public_key_id;
    private string $wxpay_public_key_path;
    private bool   $is_encrypt_by_pub_key = false;
    private array  $requestBody           = [];


    /**
     * @throws Exception
     */
    public function execute()
    {
        // 效验参数
        if (empty($this->merch_no)) {
            throw new Exception('商户号不能为空');
        }
        if (empty($this->api_v3)) {
            throw new Exception('API V3密钥不能为空');
        }
        if (empty($this->cert_number)) {
            throw new Exception('商户证书序列号不能为空');
        }
        if (!file_exists(root_path() . $this->privateKeyPath)) {
            throw new Exception('商户API私钥不存在');
        }

        // 将请求数据编码成JSON格式
        $data = $this->jsonEncode($this->requestBody);
        if ($this->method === 'GET' && $this->is_query) {
            $this->path .= '?' . http_build_query($this->requestBody);
        }


        $nonceStr = strtoupper(uniqid());
        $timestamp = time();
        $sign = $this->sign($data, $nonceStr, $timestamp);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'WECHATPAY2-SHA256-RSA2048 '
                . 'mchid="' . $this->merch_no . '",'
                . 'nonce_str="' . $nonceStr . '",'
                . 'timestamp="' . $timestamp . '",'
                . 'serial_no="' . $this->cert_number . '",'
                . 'signature="' . $sign . '"',
        ];

        if ($this->is_encrypt_by_pub_key) {
            $headers = array_merge($headers, [
                'Wechatpay-Serial' => $this->wxpay_public_key_id,
            ]);
        }

        //        if ($this->async) {
        //            $client = new Client();
        //            return $client->requestAsync($this->method, $this->gateway . $this->path, [
        //                'headers' => $headers,
        //                'body' => $data,
        //            ])->then(function ($res) {
        //                $body = $res->getBody()->getContents();
        //                return json_validate($body) ? json_decode($body, true) : $body;
        //            }); // 返回 Promise，不等待
        //        }

        try {
            $client = new Client();
            $res = $client->request($this->method, $this->gateway . $this->path, [
                'headers' => $headers,
                'body' => $data,
            ]);
            $body = $res->getBody()->getContents();
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
        } catch (GuzzleException $e) {
            $body = $e->getMessage();
        }
        return json_validate($body) ? json_decode($body, true) : $body;
    }

    /**
     * 生成签名
     *
     * @param string $data      请求体JSON字符串
     * @param string $nonceStr  随机字符串
     * @param int    $timestamp 时间戳
     *
     * @throws Exception
     * @return string 编码后的签名
     */
    private function sign(string $data, string $nonceStr, int $timestamp): string
    {
        $signStr = $this->method . "\n"
            . $this->path . "\n"
            . $timestamp . "\n"
            . $nonceStr . "\n";
        if ($this->is_query) {
            $signStr .= "\n";
        } else {
            $signStr .= $data . "\n";
        }

        $sign = '';
        openssl_sign($signStr, $sign, $this->loadApiPrivateKey(), OPENSSL_ALGO_SHA256);
        return base64_encode($sign);
    }

    /**
     * 使用商户API私钥解密数据
     *
     * @param string $data 加密后的字符串
     *
     * @throws Exception
     * @return string 解密后的字符串
     */
    public function decrypt(string $data): string
    {
        $decrypted = '';
        openssl_private_decrypt(base64_decode($data), $decrypted, $this->loadApiPrivateKey(), OPENSSL_PKCS1_OAEP_PADDING);
        return $decrypted;
    }

    /**
     * 使用API V3密钥解密数据
     *
     * @param string $ciphertext      加密后的字符串
     * @param string $associated_data 关联数据
     * @param string $nonce           随机数
     *
     * @throws Exception
     * @return string 解密后的字符串
     */
    public function decryptByV3(string $ciphertext, string $associated_data, string $nonce): string
    {
        $ciphertext = base64_decode($ciphertext);
        // 微信是：密文 + 16字节TAG
        $tag = substr($ciphertext, -16);
        $ctext = substr($ciphertext, 0, -16);
        $result = openssl_decrypt(
            $ctext,
            'aes-256-gcm',
            $this->api_v3,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $associated_data
        );
        if ($result === false) {
            throw new Exception('AES-256-GCM 解密失败');
        }
        return $result;
    }

    /**
     * 使用微信支付公钥加密数据
     *
     * @param string $data
     *
     * @throws Exception
     * @return string
     */
    public function encryptByPubKey(string $data): string
    {
        if (empty($this->wxpay_public_key_id)) throw new Exception('微信支付公钥ID不能为空');
        $encrypted = '';
        openssl_public_encrypt(
            $data,
            $encrypted,
            $this->loadWxpayPublicKey(),
            OPENSSL_PKCS1_OAEP_PADDING
        );
        return base64_encode($encrypted);
    }

    /**
     * 加载商户API私钥
     *
     * @throws Exception
     */
    private function loadApiPrivateKey(): OpenSSLAsymmetricKey
    {
        $privateKey = openssl_pkey_get_private(file_get_contents(root_path() . $this->privateKeyPath));
        if (!$privateKey) {
            throw new Exception('加载商户API私钥失败');
        }
        return $privateKey;
    }

    /**
     * 加载微信支付公钥
     *
     * @throws Exception
     * @return OpenSSLAsymmetricKey
     */
    private function loadWxpayPublicKey(): OpenSSLAsymmetricKey
    {
        $publicKey = openssl_pkey_get_public(file_get_contents(root_path() . $this->wxpay_public_key_path));
        if (!$publicKey) {
            throw new Exception('加载微信支付公钥失败');
        }
        return $publicKey;
    }

    /**
     * 上传图片
     *
     * @param string $image    图片内容
     * @param string $filename 文件名
     *
     * @throws Exception
     * @return array|string
     */
    public function uploadImage(string $image, string $filename): array|string
    {
        $meta = [
            'filename' => $filename,
            'sha256' => hash('sha256', $image),
        ];
        $signStr = $this->jsonEncode($meta);
        $data = [
            [
                'name' => 'meta',
                'contents' => $this->jsonEncode($meta),
            ],
            [
                'name' => 'file',
                'contents' => $image,
                'filename' => $filename,
            ],
        ];
        $nonceStr = strtoupper(uniqid());
        $timestamp = time();
        $sign = $this->sign($signStr, $nonceStr, $timestamp);
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'WECHATPAY2-SHA256-RSA2048 '
                . 'mchid="' . $this->merch_no . '",'
                . 'nonce_str="' . $nonceStr . '",'
                . 'timestamp="' . $timestamp . '",'
                . 'serial_no="' . $this->cert_number . '",'
                . 'signature="' . $sign . '"',
        ];
        try {
            $client = new Client();
            $res = $client->request($this->method, $this->gateway . $this->path, [
                'headers' => $headers,
                'multipart' => $data,
            ]);
            $body = $res->getBody()->getContents();
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
        } catch (GuzzleException $e) {
            $body = $e->getMessage();
        }
        if (json_validate($body)) {
            return json_decode($body, true);
        }
        return $body;
    }

    /**
     * 设置请求路径
     *
     * @param string $path
     */
    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    /**
     * 设置请求方法
     *
     * @param string $method
     */
    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    /**
     * 设置是否查询
     *
     * @param bool $is_query
     */
    public function setIsQuery(bool $is_query): void
    {
        $this->is_query = $is_query;
    }

    /**
     * 设置请求体
     *
     * @param array $requestBody
     */
    public function setRequestBody(array $requestBody): void
    {
        $this->requestBody = $requestBody;
    }

    /**
     * 设置商户号
     *
     * @param string $merch_no
     */
    public function setMerchNo(string $merch_no): void
    {
        $this->merch_no = $merch_no;
    }

    /**
     * 设置API V3密钥
     *
     * @param string $api_v3
     */
    public function setApiV3(string $api_v3): void
    {
        $this->api_v3 = $api_v3;
    }

    /**
     * 设置商户证书序列号
     *
     * @param string $cert_number
     */
    public function setCertNumber(string $cert_number): void
    {
        $this->cert_number = $cert_number;
    }

    /**
     * 设置商户API私钥路径
     *
     * @param string $privateKeyPath
     */
    public function setPrivateKeyPath(string $privateKeyPath): void
    {
        $this->privateKeyPath = $privateKeyPath;
    }

    /**
     * 设置是否异步
     *
     * @param bool $async
     */
    public function setAsync(bool $async): void
    {
        $this->async = $async;
    }

    public function jsonEncode(array $data): string
    {
        return !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : '';
    }

    /**
     * 设置微信支付公钥ID
     *
     * @param string $wxpayPublicKeyId
     */
    public function setWxpayPublicKeyId(string $wxpayPublicKeyId): void
    {
        $this->wxpay_public_key_id = $wxpayPublicKeyId;
    }

    /**
     * 设置微信支付公钥路径
     *
     * @param string $wxpayPublicKeyPath
     */
    public function setWxpayPublicKeyPath(string $wxpayPublicKeyPath): void
    {
        $this->wxpay_public_key_path = $wxpayPublicKeyPath;
    }

    /**
     * 设置是否使用公钥加密
     *
     * @param bool $value
     *
     * @return void
     */
    public function setIsEncryptByPubKey(bool $value): void
    {
        $this->is_encrypt_by_pub_key = $value;
    }

    /**
     * 创建请求实例
     *
     * @param array $param 插件配置参数
     *
     * @return self
     */
    public static function newInstance(array $param): self
    {
        $instance = new self();
        $instance->setMerchNo($param['merch_no']);
        $instance->setApiV3($param['api_v3']);
        $instance->setCertNumber($param['cert_number']);
        $instance->setPrivateKeyPath($param['private_key_path']);
        $instance->setWxpayPublicKeyId($param['wxpay_public_key_id']);
        $instance->setWxpayPublicKeyPath($param['wxpay_public_key_path']);
        return $instance;
    }
}