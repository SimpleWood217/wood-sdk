<?php

namespace Wood\Sdk\Pay\Alipay;

use Wood\Sdk\Abstracts\BaseConfig;
use Wood\Sdk\Exceptions\InvalidConfigException;

class Config extends BaseConfig
{
    protected array  $essential_config = [
        'appid',
        'sign_type',
    ];
    protected string $appid;
    protected string $sign_type;
    protected string $private_key;
    protected string $alipay_public_key;
    protected string $alipay_app_cert;
    protected string $alipay_public_cert;
    protected string $alipay_root_cert;
    protected string $gateway;

    /**
     * @param array $config
     *
     * @throws InvalidConfigException
     */
    public function __construct(array $config)
    {
        $this->check($config);
        $this->appid = $config['appid'];
        $this->sign_type = $config['sign_type'] ?? 'rsa';
        $this->private_key = $config['private_key'] ?? '';
        $this->alipay_public_key = $config['alipay_public_key'] ?? '';
        $this->alipay_app_cert = $config['alipay_app_cert'] ?? '';
        $this->alipay_public_cert = $config['alipay_public_cert'] ?? '';
        $this->alipay_root_cert = $config['alipay_root_cert'] ?? '';
        $this->gateway = $config['gateway'] ?? 'https://openapi.alipay.com';

        if ($this->sign_type == 'rsa') {
            $this->essential_config = array_merge($this->essential_config, [
                'private_key',
                'alipay_public_key',
            ]);
            $this->check($config);
        } else {
            $this->essential_config = array_merge($this->essential_config, [
                'private_key',
                'alipay_app_cert',
                'alipay_public_cert',
                'alipay_root_cert',
            ]);
            $this->check($config);
        }
    }
}
