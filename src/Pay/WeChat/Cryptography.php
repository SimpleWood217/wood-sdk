<?php

namespace Wood\Sdk\Pay\WeChat;

use OpenSSLAsymmetricKey;
use Wood\Sdk\Exceptions\CryptoException;
use Wood\Sdk\Exceptions\InvalidConfigException;

class Cryptography
{
    private Config $config;
    /**
     * 进程级/静态内存缓存池 (哈希表)
     * 结构: ['文件路径md5' => OpenSSLAsymmetricKey实例]
     * 作用: 无论实例化多少次，同一个 Worker 进程内，同一个密钥只解析一次，且多商户完美隔离
     */
    private static array $privateKeyMap = [];
    private static array $publicKeyMap  = [];


    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @throws InvalidConfigException
     */
    private function getPrivateKey(): OpenSSLAsymmetricKey
    {
        $path = $this->config->get('private_key_path');

        // 使用文件路径生成唯一缓存 Key
        $cacheKey = md5($path);

        // 如果当前 Worker 进程的内存池里已经有了这个商户的私钥指针，直接返回
        if (isset(self::$privateKeyMap[$cacheKey])) {
            return self::$privateKeyMap[$cacheKey];
        }

        // 没有缓存，走严格的物理加载逻辑
        if (!is_file($path)) {
            throw new InvalidConfigException("商户私钥文件不存在或无法读取: $path");
        }

        $key = openssl_pkey_get_private(file_get_contents($path));
        if (!$key) {
            throw new InvalidConfigException('商户私钥解析失败: ' . openssl_error_string());
        }

        // 存入当前进程的静态哈希表，并返回
        return self::$privateKeyMap[$cacheKey] = $key;
    }

    /**
     * @throws InvalidConfigException
     */
    private function getPublicKey(): OpenSSLAsymmetricKey
    {
        $path = $this->config->get('wxpay_public_key_path');
        $cacheKey = md5($path);

        if (isset(self::$publicKeyMap[$cacheKey])) {
            return self::$publicKeyMap[$cacheKey];
        }

        if (!is_file($path)) {
            throw new InvalidConfigException("微信支付公钥文件不存在或无法读取: $path");
        }

        $key = openssl_pkey_get_public(file_get_contents($path));
        if (!$key) {
            throw new InvalidConfigException('微信支付公钥解析失败: ' . openssl_error_string());
        }

        return self::$publicKeyMap[$cacheKey] = $key;
    }

    /**
     * 使用商户API私钥解密数据
     *
     * @param string $ciphertext
     *
     * @throws CryptoException 解密失败
     * @throws InvalidConfigException 配置错误
     * @return string
     */
    public function decrypt(string $ciphertext): string
    {
        $decrypted = '';
        $success = openssl_private_decrypt(
            base64_decode($ciphertext),
            $decrypted,
            $this->getPrivateKey(),
            OPENSSL_PKCS1_OAEP_PADDING
        );

        if (!$success) {
            throw new CryptoException('RSA 私钥解密失败: ' . openssl_error_string());
        }

        return $decrypted;
    }

    /**
     * 使用API V3密钥解密数据
     *
     * @param array $info 解密参数
     *                    - ciphertext: 加密后的字符串
     *                    - associated_data: 关联数据
     *                    - nonce: 随机数
     *
     * @throws CryptoException 解密失败
     * @return string 解密后的字符串
     */
    public function decryptByV3(array $info): string
    {
        $ciphertext = base64_decode($info['ciphertext']);
        // 微信 V3 规范：密文 = 真实密文 + 16字节的 Authentication Tag
        $tag = substr($ciphertext, -16);
        $ctext = substr($ciphertext, 0, -16);

        $result = openssl_decrypt(
            $ctext,
            'aes-256-gcm',
            $this->config->get('api_v3'),
            OPENSSL_RAW_DATA,
            $info['nonce'],
            $tag,
            $info['associated_data']
        );

        if ($result === false) {
            throw new CryptoException('AES-256-GCM 解密失败，数据可能被篡改或 API V3 密钥错误: ' .
                                      openssl_error_string());
        }

        return $result;
    }

    /**
     * 使用微信支付公钥加密数据
     *
     * @param string $data
     *
     * @throws InvalidConfigException
     * @throws CryptoException
     * @return string
     */
    public function encryptByPubKey(string $data): string
    {
        $encrypted = '';
        $success = openssl_public_encrypt(
            $data,
            $encrypted,
            $this->getPublicKey(),
            OPENSSL_PKCS1_OAEP_PADDING
        );

        if (!$success) {
            throw new CryptoException('RSA 公钥加密失败: ' . openssl_error_string());
        }

        return base64_encode($encrypted);
    }
}