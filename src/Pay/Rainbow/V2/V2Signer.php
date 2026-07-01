<?php

namespace Wood\Sdk\Pay\Rainbow\V2;

use Wood\Sdk\Contracts\SignerInterface;
use Wood\Sdk\Exceptions\InvalidConfigException;

class V2Signer implements SignerInterface
{
    private string $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    /**
     * @throws InvalidConfigException
     */
    public function sign(string $data, array $info = []): string
    {
        $private_key = openssl_pkey_get_private($this->loadPrivateKey());
        if (!$private_key) {
            throw new InvalidConfigException('私钥加载失败');
        }

        $sign = '';
        openssl_sign($data, $sign, $private_key, OPENSSL_ALGO_SHA256);
        return base64_encode($sign);
    }

    /**
     * @throws InvalidConfigException
     */
    public function verify(string $data, string $sign): bool
    {
        $public_key = openssl_pkey_get_public($this->loadPlatformPublicKey());
        if (!$public_key) {
            throw new InvalidConfigException('公钥加载失败');
        }

        return (bool)openssl_verify($data, base64_decode($sign), $public_key, OPENSSL_ALGO_SHA256);
    }

    /**
     * 加载商户私钥
     *
     * @return string
     */
    private function loadPrivateKey(): string
    {
        return "-----BEGIN PRIVATE KEY-----\n"
               . wordwrap($this->key, 64, "\n", true)
               . "\n-----END PRIVATE KEY-----";
    }

    /**
     * 加载平台公钥
     *
     * @return string 格式化后的公钥
     */
    private function loadPlatformPublicKey(): string
    {
        return "-----BEGIN PUBLIC KEY-----\n"
               . wordwrap($this->key, 64, "\n", true)
               . "\n-----END PUBLIC KEY-----";
    }
}