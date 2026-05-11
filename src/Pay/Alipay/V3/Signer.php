<?php

namespace Wood\Sdk\Pay\Alipay\V3;

use Wood\Sdk\Exceptions\InvalidConfigException;
use Wood\Sdk\Exceptions\SignException;
use Wood\Sdk\Pay\Alipay\Config;

class Signer
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @throws InvalidConfigException
     */
    public function sign(string $data, array $info = []): string
    {
        $auth_string = $info['auth_string'] ?? '';

        $sign_content = $auth_string . "\n"
                        . $info['method'] . "\n"
                        . $info['path'] . "\n";

        if ($info['is_query'] ?? false) {
            $sign_content .= $this->jsonEncode($info['body']) . "\n";
        } else {
            $sign_content .= $data . "\n";
        }
        $private_key = openssl_pkey_get_private($this->loadPrivateKey());
        if (!$private_key) {
            throw new InvalidConfigException('私钥加载失败: ' . openssl_error_string());
        }

        $sign = '';
        openssl_sign($sign_content, $sign, $private_key, OPENSSL_ALGO_SHA256);
        return base64_encode($sign);
    }

    /**
     * 验证签名
     *
     * @param string|array $data 需要验证的数据
     * @param string       $sign 签名
     *
     * @throws InvalidConfigException
     * @return bool 验证结果
     */
    public function verify(string|array $data, string $sign): bool
    {
        if ($this->config->get('sign_type') == 'cert') {
            $publicRes = $this->loadAlipayPublicCert();
        } else {
            $publicRes = $this->loadAlipayPublicKey();
        }
        $res = openssl_get_publickey($publicRes);
        if (!$res) {
            throw new InvalidConfigException('公钥格式错误');
        }

        if (is_array($data)) {
            $signStr = '';
            ksort($data);
            foreach ($data as $key => $value) {
                if ($key !== 'sign' && $key !== 'sign_type') {
                    $signStr .= $key . '=' . $value . '&';
                }
            }
            $data = rtrim($signStr, '&');
        }

        return openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * 加载商户私钥
     *
     * @return string
     */
    private function loadPrivateKey(): string
    {
        return "-----BEGIN PRIVATE KEY-----\n"
               . wordwrap($this->config->get('private_key'), 64, "\n", true)
               . "\n-----END PRIVATE KEY-----";
    }

    /**
     * 加载支付宝公钥
     *
     * @return string 格式化后的公钥
     */
    private function loadAlipayPublicKey(): string
    {
        return "-----BEGIN PUBLIC KEY-----\n"
               . wordwrap($this->config->get('alipay_public_key'), 64, "\n", true)
               . "\n-----END PUBLIC KEY-----";
    }

    /**
     * 加载支付宝公钥证书
     *
     * @return string 证书内容
     */
    private function loadAlipayPublicCert(): string
    {
        return file_get_contents($this->config->get('alipay_public_cert_path'));
    }

    private function jsonEncode(array $data): string
    {
        return !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : '';
    }
}
