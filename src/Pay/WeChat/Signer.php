<?php

namespace Wood\Sdk\Pay\WeChat;

use Wood\Sdk\Contracts\SignerInterface;
use Wood\Sdk\Exceptions\InvalidConfigException;

class Signer implements SignerInterface
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
        if ($info['method'] === 'GET' && $info['is_query']) {
            $info['path'] .= '?' . http_build_query($info['body']);
        }

        $signStr = $info['method'] . "\n"
                   . $info['path'] . "\n"
                   . $info['timestamp'] . "\n"
                   . $info['nonce'] . "\n";
        if ($info['is_query']) {
            $signStr .= "\n";
        } else {
            $signStr .= $data . "\n";
        }

        $private_key = openssl_pkey_get_private($this->key);
        if (!$private_key) {
            throw new InvalidConfigException('私钥加载失败');
        }

        $sign = '';
        openssl_sign($signStr, $sign, $private_key, OPENSSL_ALGO_SHA256);
        return base64_encode($sign);
    }

    /**
     * @throws InvalidConfigException
     */
    public function verify(string $data, string $sign): bool
    {
        $public_key = openssl_pkey_get_public($this->key);
        if (!$public_key) {
            throw new InvalidConfigException('公钥加载失败');
        }

        return (bool)openssl_verify($data, base64_decode($sign), $public_key, OPENSSL_ALGO_SHA256);
    }
}