<?php

namespace Wood\Sdk\Oss\Qiniu\Signer;

use Wood\Sdk\Oss\Qiniu\Config;

class Management
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function sign(string $data, array $info = []): string
    {
        $signing_str = $this->buildSigningStr($data, $info);
        $sign = hash_hmac('sha1', $signing_str, $this->config->get('secret_key'), true);
        $encoded_sign = $this->urlsafeBase64Encode($sign);
        $access_key = $this->config->get('access_key');
        return $access_key . ':' . $encoded_sign;
    }

    /**
     * 构造待签名字符串
     *
     * @param string $data 请求体内容
     * @param array  $info 签名所需信息
     *
     * @return string
     */
    private function buildSigningStr(string $data, array $info): string
    {
        $method = strtoupper($info['method'] ?? 'GET');
        $path = $info['path'] ?? '';
        $host = $info['host'] ?? '';
        $content_type = $info['content_type'] ?? '';

        $signing_str = $method . ' ' . $path;

        // GET 请求时 data 即为 query 参数
        if ($method === 'GET' && $data !== '') {
            $signing_str .= '?' . $data;
        }

        $signing_str .= "\nHost: " . $host;

        // Content-Type
        if ($content_type) {
            $signing_str .= "\nContent-Type: " . $content_type;
        }

        // X-Qiniu-* 自定义头（首字母及 - 后字母大写，按 ASCII 升序排列）
        $qiniu_headers = [];
        foreach ($info['headers'] ?? [] as $header_key => $header_value) {
            if (stripos($header_key, 'X-Qiniu-') === 0) {
                $formatted_key = implode('-', array_map(function ($part) {
                    return ucfirst(strtolower($part));
                }, explode('-', $header_key)));
                $qiniu_headers[$formatted_key] = $header_value;
            }
        }
        ksort($qiniu_headers);
        foreach ($qiniu_headers as $header_key => $header_value) {
            $signing_str .= "\n" . $header_key . ": " . $header_value;
        }

        // 两个连续换行符
        $signing_str .= "\n\n";

        // Body 参与签名（非 GET 且 Content-Type 不为 application/octet-stream）
        if ($method !== 'GET' && $data !== '' && $content_type !== 'application/octet-stream') {
            $signing_str .= $data;
        }

        return $signing_str;
    }

    /**
     * URL 安全的 Base64 编码
     *
     * @param string $data 二进制数据
     *
     * @return string
     */
    private function urlsafeBase64Encode(string $data): string
    {
        return str_replace(['+', '/'], ['-', '_'], base64_encode($data));
    }
}