<?php

namespace Wood\Sdk\Pay\Alipay\V3;

use Exception;
use Wood\Sdk\Abstracts\BaseClient;
use Wood\Sdk\Exceptions\InvalidConfigException;
use Wood\Sdk\Pay\Alipay\Config;

class Client extends BaseClient
{
    protected Config       $config;
    protected Cryptography $cryptography;

    public function __construct(Config $config)
    {
        parent::__construct();
        $this->config = $config;
        $this->cryptography = new Cryptography($config);
    }

    public function getBaseUri(): string
    {
        return $this->config->get('gateway');
    }

    /**
     * @throws InvalidConfigException|Exception
     */
    public function buildHeaders(string $method, string $path, array $options = []): array
    {
        $body = $options['body'] ?? [];

        $nonce = strtoupper(uniqid());
        $timestamp = time();

        $auth_string = $this->buildAuthString($nonce, $timestamp);

        $sign = (new Signer($this->config))->sign($this->jsonEncode($body), [
            'method'      => $method,
            'path'        => $path,
            'timestamp'   => $timestamp,
            'nonce'       => $nonce,
            'is_query'    => $options['is_query'] ?? false,
            'body'        => $body,
            'auth_string' => $auth_string,
        ]);

        return [
            'Content-Type'  => 'application/json',
            'Authorization' => 'ALIPAY-SHA256withRSA ' . $auth_string . ',sign=' . $sign,
        ];
    }

    /**
     * 构建认证字符串
     *
     * @param string $nonce     随机数
     * @param int    $timestamp 时间戳
     *
     * @throws Exception
     * @return string
     */
    protected function buildAuthString(string $nonce, int $timestamp): string
    {
        $auth_string = 'app_id=' . $this->config->get('appid')
                       . ',timestamp=' . $timestamp
                       . ',nonce=' . $nonce;

        if ($this->config->get('sign_type') == 'cert') {
            $app_cert_sn = $this->cryptography->getCertSN(root_path() . $this->config->get('alipay_app_cert'));
            $auth_string .= ',app_cert_sn=' . $app_cert_sn;
        }

        return $auth_string;
    }
}
