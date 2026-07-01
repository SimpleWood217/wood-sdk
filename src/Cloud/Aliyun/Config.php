<?php

namespace Wood\Sdk\Cloud\Aliyun;

use Wood\Sdk\Abstracts\BaseConfig;
use Wood\Sdk\Exceptions\InvalidConfigException;

class Config extends BaseConfig
{
    protected array  $essential_config  = [
        'access_key_id',
        'access_key_secret',
        'template_code',
    ];
    protected string $access_key_id     = '';
    protected string $access_key_secret = '';
    protected string $sign_name         = '';
    protected string $template_code     = '';
    protected string $gateway           = 'dysmsapi.aliyuncs.com';
    protected string $version           = '2017-05-25';

    /**
     * @throws InvalidConfigException
     */
    public function __construct(array $config)
    {
        $this->check($config);
        $this->access_key_id = $config['access_key_id'];
        $this->access_key_secret = $config['access_key_secret'];
        $this->sign_name = $config['sign_name'] ?? '';
        $this->template_code = $config['template_code'];
        $this->gateway = $config['gateway'] ?? $this->gateway;
        $this->version = $config['version'] ?? $this->version;

        if ($this->sign_name === '') {
            throw new InvalidConfigException('缺少必要配置项: sign_name');
        }
    }
}
