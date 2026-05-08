<?php

namespace Wood\Sdk\Pay\WeChat;

use Wood\Sdk\Abstracts\BaseConfig;
use Wood\Sdk\Exceptions\InvalidConfigException;

class Config extends BaseConfig
{
    protected array  $essential_config = [
        'merch_no',
        'api_v3',
        'cert_number',
        'private_key_path',
        'wxpay_public_key_id',
    ];
    protected string $merch_no;
    protected string $api_v3;
    protected string $cert_number;
    protected string $private_key_path;
    protected string $wxpay_public_key_id;
    protected string $wxpay_public_key_path;

    /**
     * @param array $config
     *
     * @throws InvalidConfigException
     */
    public function __construct(array $config)
    {
        $this->check($config);
        $this->merch_no = $config['merch_no'];
        $this->api_v3 = $config['api_v3'];
        $this->cert_number = $config['cert_number'];
        $this->private_key_path = $config['private_key_path'];
        $this->wxpay_public_key_id = $config['wxpay_public_key_id'];
        $this->wxpay_public_key_path = $config['wxpay_public_key_path'] ?? '';

        if (!file_exists(root_path() . $this->private_key_path)) {
            throw new InvalidConfigException('商户API私钥不存在');
        }
    }
}