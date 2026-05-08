<?php

namespace Wood\Sdk\Pay\WeChat;

use Wood\Sdk\Abstracts\BaseClient;
use Wood\Sdk\Exceptions\InvalidConfigException;

class Client extends BaseClient
{
    protected Config $config;

    public function __construct(Config $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    public function getBaseUri(): string
    {
        return 'https://api.mch.weixin.qq.com';
    }

    /**
     * @throws InvalidConfigException
     */
    public function buildHeaders(string $method, string $path, array $options = []): array
    {
        $body = $options['body'] ?? [];

        $nonce = strtoupper(uniqid());
        $timestamp = time();

        $private_key = file_get_contents(root_path() . $this->config->get('private_key_path'));

        $sign = (new Signer($private_key))->sign($this->jsonEncode($body), [
            'method'    => $method,
            'path'      => $path,
            'timestamp' => $timestamp,
            'nonce'     => $nonce,
            'is_query'  => $options['is_query'] ?? false,
            'body'      => $body,
        ]);

        $headers = [
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'WECHATPAY2-SHA256-RSA2048 '
                               . 'mchid="' . $this->config->get('merch_no') . '",'
                               . 'nonce_str="' . $nonce . '",'
                               . 'timestamp="' . $timestamp . '",'
                               . 'serial_no="' . $this->config->get('cert_number') . '",'
                               . 'signature="' . $sign . '"',
        ];

        if ($options['if_encrypt_by_pub_key'] ?? false) {
            $headers = array_merge($headers, [
                'Wechatpay-Serial' => $this->config->get('wxpay_public_key_id'),
            ]);
        }

        return $headers;
    }
}