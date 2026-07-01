<?php

namespace Wood\Sdk\Pay\Rainbow;

use Wood\Sdk\Abstracts\BaseClient;
use Wood\Sdk\Exceptions\InvalidConfigException;
use Wood\Sdk\Pay\Rainbow\V1\V1Signer;
use Wood\Sdk\Pay\Rainbow\V2\V2Signer;

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
        return $this->config->get('gateway');
    }

    /**
     * @throws InvalidConfigException
     */
    public function buildHeaders(string $method, string $path, array $options = []): array
    {
        $body = $options['body'] ?? [];

        if ($body['sign_type'] === 'md5') {
            $signer = new V1Signer($this->config->get('secret'));
        } else {
            $signer = new V2Signer($this->config->get('private_key'));
        }
        $body['sign'] = $signer->sign($this->generateSignStr($body));
        return [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'new_body'     => $body,
        ];
    }

    /**
     * 生成待签名字符串
     *
     * @param array $param
     *
     * @return string
     */
    public function generateSignStr(array $param): string
    {
        ksort($param);
        $sign_str = '';

        foreach ($param as $k => $v) {
            if ($k != "sign" && $k != "sign_type" && $v != '') {
                $sign_str .= $k . '=' . $v . '&';
            }
        }

        return rtrim($sign_str, '&');
    }
}