<?php

namespace Wood\Sdk\Pay\Alipay\V3;

use Wood\Sdk\Exceptions\CryptoException;
use Wood\Sdk\Pay\Alipay\Config;

class Cryptography
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * 获取证书序列号
     *
     * @param string $cert_path 证书路径
     *
     * @throws CryptoException
     * @return string
     */
    public function getCertSN(string $cert_path): string
    {
        $cert_content = file_get_contents($cert_path);

        $ssl_data = openssl_x509_parse($cert_content);
        if (!$ssl_data) {
            throw new CryptoException("证书解析失败");
        }

        $issuer_parts = [];
        $issuer = array_merge([], $ssl_data['issuer']);
        foreach (array_reverse($issuer) as $key => $value) {
            $issuer_parts[] = $key . '=' . $value;
        }
        $issuer_string = implode(',', $issuer_parts);

        $raw_sn = $issuer_string . $ssl_data['serialNumber'];
        return md5($raw_sn);
    }
}
