<?php

namespace Wood\Sdk\Oss\Qiniu\Signer;

use Wood\Sdk\Oss\Qiniu\Config;

class Download
{
    private Config $config;
    private string $key;

    /**
     * @param Config $config 七牛云配置（含 access_key、secret_key）
     * @param string $key    要下载的资源名（对象键）
     */
    public function __construct(Config $config, string $key)
    {
        $this->config = $config;
        $this->key = $key;
    }

    /**
     * 生成带下载凭证的完整私有下载链接
     *
     * 签名流程：
     *   1. 原始下载 URL + 过期时间戳 e
     *   2. 对完整 URL 做 HMAC-SHA1 签名
     *   3. URL 安全 Base64 编码签名
     *   4. 拼接 AccessKey:EncodedSign 作为 token
     *   5. 拼回 URL 得到最终下载链接
     *
     * @param string $data 下载域名（如 https://download.example.com）
     * @param array  $info 可选参数：
     *                     - expires: 过期时间（秒），默认 3600
     *                     - params: 额外的 URL 查询参数，如 ["imageView2/1/w/200/h/200" => ""]
     *
     * @return string 带下载凭证的完整 URL
     */
    public function sign(string $data, array $info = []): string
    {
        $baseUrl = rtrim($data, '/') . '/' . ltrim($this->key, '/');
        $expires = $info['expires'] ?? 3600;
        $deadline = time() + $expires;

        // 拼接额外查询参数（需参与签名）
        $extraParams = $info['params'] ?? [];
        $queryParts = [];
        foreach ($extraParams as $k => $v) {
            $queryParts[] = $v === '' ? $k : $k . '=' . $v;
        }

        // 拼接过期时间
        $separator = (str_contains($baseUrl, '?') || $queryParts) ? '&' : '?';
        $expireUrl = $baseUrl;
        if ($queryParts) {
            $expireUrl .= '?' . implode('&', $queryParts);
        }
        $expireUrl .= $separator . 'e=' . $deadline;

        // HMAC-SHA1 签名
        $sign = hash_hmac('sha1', $expireUrl, $this->config->get('secret_key'), true);
        $encodedSign = $this->urlsafeBase64Encode($sign);

        // 拼接 token
        $token = $this->config->get('access_key') . ':' . $encodedSign;

        return $expireUrl . '&token=' . $token;
    }

    /**
     * URL 安全的 Base64 编码
     */
    private function urlsafeBase64Encode(string $data): string
    {
        return str_replace(['+', '/'], ['-', '_'], base64_encode($data));
    }
}
