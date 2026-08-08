<?php

namespace Wood\Sdk\Oss\Qiniu\Signer;

use Wood\Sdk\Oss\Qiniu\Config;

class Upload
{
    private Config $config;
    private string $key;

    /**
     * @param Config $config 七牛云配置（含 access_key、secret_key）
     * @param string $key    上传的资源名（对象键），用于构造 scope
     */
    public function __construct(Config $config, string $key)
    {
        $this->config = $config;
        $this->key = $key;
    }

    /**
     * 生成上传凭证（Upload Token）
     *
     * 上传凭证格式：AccessKey:EncodedSign:EncodedPutPolicy
     *
     * @param string $data 目标空间名称（Bucket）
     * @param array  $info 可选的上传策略字段，支持：
     *                     - deadline: 凭证有效截止时间（Unix 时间戳，秒），默认 3600 秒后
     *                     - returnBody: 自定义返回内容
     *                     - returnUrl: 303 重定向地址
     *                     - callbackUrl / callbackHost / callbackBody / callbackBodyType: 回调配置
     *                     - saveKey / forceSaveKey: 自定义文件名
     *                     - fsizeMin / fsizeLimit: 文件大小限制
     *                     - mimeLimit: 文件 MIME 类型限制
     *                     - fileType: 存储类型（0=标准 1=低频 2=归档 3=深度归档）
     *                     - insertOnly: 仅允许新增
     *                     - endUser: 唯一属主标识
     *                     - detectMime: 开启 MIME 侦测
     *                     - trafficLimit: 上传限速
     *                     - deleteAfterDays: 自动删除天数
     *                     - persistentOps / persistentNotifyUrl / persistentPipeline / persistentType: 持久化处理
     *                     - isPrefixalScope: 前缀匹配模式
     *
     * @return string 上传凭证
     */
    public function sign(string $data, array $info = []): string
    {
        $bucket = $data;
        $deadline = $info['deadline'] ?? (time() + 3600);

        // 构造上传策略
        $putPolicy = [
            'scope'    => $bucket . ':' . $this->key,
            'deadline' => $deadline,
        ];

        // 合并可选的上传策略字段
        $allowedFields = [
            'returnBody',
            'returnUrl',
            'callbackUrl',
            'callbackHost',
            'callbackBody',
            'callbackBodyType',
            'saveKey',
            'forceSaveKey',
            'fsizeMin',
            'fsizeLimit',
            'mimeLimit',
            'fileType',
            'insertOnly',
            'endUser',
            'detectMime',
            'trafficLimit',
            'deleteAfterDays',
            'persistentOps',
            'persistentNotifyUrl',
            'persistentPipeline',
            'persistentType',
            'isPrefixalScope',
        ];

        foreach ($allowedFields as $field) {
            if (isset($info[$field])) {
                $putPolicy[$field] = $info[$field];
            }
        }

        // 1. 将上传策略序列化为 JSON 并做 URL 安全的 Base64 编码
        $putPolicyJson = json_encode($putPolicy, JSON_UNESCAPED_UNICODE);
        $encodedPutPolicy = $this->urlsafeBase64Encode($putPolicyJson);

        // 2. 使用 SecretKey 对 EncodedPutPolicy 做 HMAC-SHA1 签名
        $sign = hash_hmac('sha1', $encodedPutPolicy, $this->config->get('secret_key'), true);

        // 3. 对签名做 URL 安全的 Base64 编码
        $encodedSign = $this->urlsafeBase64Encode($sign);

        // 4. 拼接 AccessKey:EncodedSign:EncodedPutPolicy
        $accessKey = $this->config->get('access_key');

        return $accessKey . ':' . $encodedSign . ':' . $encodedPutPolicy;
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