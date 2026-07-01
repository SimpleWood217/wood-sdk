<?php

namespace Wood\Sdk\Pay\Rainbow;

use Wood\Sdk\Abstracts\BaseConfig;
use Wood\Sdk\Exceptions\InvalidConfigException;

class Config extends BaseConfig
{
    protected array  $essential_config = [
        'gateway',
        'sign_type',
    ];
    protected string $secret;          // 商户密钥
    protected string $private_key;    // 商户私钥
    protected string $gateway;      // 网关地址

    /**
     * @param array $config
     *
     * @throws InvalidConfigException
     */
    public function __construct(array $config)
    {
        $this->gateway = $config['gateway'];
        if ($config['sign_type'] === 'md5') {
            $this->essential_config = array_merge($this->essential_config, ['secret']);
            $this->secret = $config['secret'];
        } else {
            $this->essential_config = array_merge($this->essential_config, ['private_key']);
            $this->private_key = $config['private_key'];
        }
        $this->check($config);
    }
}
