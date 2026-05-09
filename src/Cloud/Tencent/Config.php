<?php

namespace Wood\Sdk\Cloud\Tencent;

use Wood\Sdk\Abstracts\BaseConfig;
use Wood\Sdk\Exceptions\InvalidConfigException;

class Config extends BaseConfig
{
    protected array  $essential_config = [
        'secret_id',
        'secret_key',
        'sms_sdk_app_id',
        'template_id',
    ];
    protected string $secret_id        = '';
    protected string $secret_key       = '';
    protected string $sms_sdk_app_id   = '';
    protected string $sign_name        = '';
    protected string $template_id      = '';
    protected string $region           = 'ap-guangzhou'; // guangzhou beijing nanjing
    protected string $version          = '2021-01-11';
    protected string $service          = 'sms';
    protected string $host             = 'sms.tencentcloudapi.com';
    protected string $token            = '';

    /**
     * @throws InvalidConfigException
     */
    public function __construct(array $config)
    {
        $this->check($config);
        $this->secret_id = $config['secret_id'];
        $this->secret_key = $config['secret_key'];
        $this->sms_sdk_app_id = $config['sms_sdk_app_id'];
        $this->sign_name = $config['sign_name'] ?? '';
        $this->template_id = $config['template_id'];
        $this->region = $config['region'] ?? $this->region;
        $this->version = $config['version'] ?? $this->version;
        $this->service = $config['service'] ?? $this->service;
        $this->host = $config['host'] ?? $this->host;
        $this->token = $config['token'] ?? $this->token;

        if ($this->sign_name === '') {
            throw new InvalidConfigException('缺少必要配置项: sign_name');
        }
    }
}
