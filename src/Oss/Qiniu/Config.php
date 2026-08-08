<?php

namespace Wood\Sdk\Oss\Qiniu;

use Wood\Sdk\Abstracts\BaseConfig;
use Wood\Sdk\Exceptions\InvalidConfigException;

/**
 * 七牛云配置
 *
 * 各区域域名参考：https://developer.qiniu.com/kodo/1671/region-endpoint-fq
 */
class Config extends BaseConfig
{
    protected array $essential_config = [
        'access_key',
        'secret_key',
        'bucket',
    ];

    protected string $access_key;      // AK
    protected string $secret_key;      // SK
    protected string $bucket;          // 空间名称

    // 各服务域名，按区域手动配置
    protected string $uc_domain;       // 空间管理，默认 uc.qiniuapi.com
    protected string $upload_domain;   // 源站上传
    protected string $download_domain; // 源站下载
    protected string $rs_domain;       // 对象管理
    protected string $rsf_domain;      // 对象列举

    /**
     * @param array $config
     *
     * @throws InvalidConfigException
     */
    public function __construct(array $config)
    {
        $this->access_key = $config['access_key'];
        $this->secret_key = $config['secret_key'];
        $this->bucket     = $config['bucket'];

        $this->uc_domain       = $config['uc_domain'] ?? 'uc.qiniuapi.com';
        $this->upload_domain   = $config['upload_domain'] ?? '';
        $this->download_domain = $config['download_domain'] ?? '';
        $this->rs_domain       = $config['rs_domain'] ?? '';
        $this->rsf_domain      = $config['rsf_domain'] ?? '';

        $this->check($config);
    }
}
