<?php

namespace Wood\Sdk\Oss\Qiniu;

use Wood\Sdk\Abstracts\BaseClient;
use Wood\Sdk\Exceptions\FileNonExistent;
use Wood\Sdk\Exceptions\HttpRequestException;
use Wood\Sdk\Oss\Qiniu\Signer\Download;
use Wood\Sdk\Oss\Qiniu\Signer\Management;
use Wood\Sdk\Oss\Qiniu\Signer\Upload;

class Client extends BaseClient
{
    protected Config $config;

    public function __construct(Config $config, int $timeout = 60)
    {
        parent::__construct($timeout);
        $this->config = $config;
    }

    public function getBaseUri(): string
    {
        return $this->config->get('rs_domain');
    }

    public function buildHeaders(string $method, string $path, array $options = []): array
    {
        // multipart 上传不需要 Management 鉴权头（token 在表单中）
        if (isset($options['multipart'])) {
            return [];
        }

        $body = $options['body'] ?? '';
        $method = strtoupper($method);

        // 处理完整 URL（如列举 API 使用 rsf 域名），提取 host 和签名用的 path
        if (str_starts_with($path, 'http')) {
            $parsed = parse_url($path);
            $host = $parsed['host'];
            $sign_path = $parsed['path'] . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
        } else {
            $host = parse_url($this->config->get('rs_domain'), PHP_URL_HOST);
            $sign_path = $path;
        }

        $content_type = $options['headers']['Content-Type'] ?? 'application/x-www-form-urlencoded';

        // 将 body 转为签名字符串所需格式
        if (is_array($body)) {
            $body = $method === 'GET'
                ? http_build_query($body)
                : ($content_type === 'application/x-www-form-urlencoded'
                    ? http_build_query($body)
                    : json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        // X-Qiniu-Date: ISO 8601 UTC 时间
        $qiniu_date = gmdate('Ymd\THis\Z');
        $request_headers = array_merge($options['headers'] ?? [], [
            'X-Qiniu-Date' => $qiniu_date,
        ]);

        $signer = new Management($this->config);
        $access_token = $signer->sign($body, [
            'method'       => $method,
            'path'         => $sign_path,
            'host'         => $host,
            'content_type' => $content_type,
            'headers'      => $request_headers,
        ]);

        $headers = [
            'Authorization' => 'Qiniu ' . $access_token,
            'X-Qiniu-Date'  => $qiniu_date,
        ];

        if ($content_type) {
            $headers['Content-Type'] = $content_type;
        }
        return $headers;
    }

    /**
     * @throws HttpRequestException
     */
    public function getBuckets(string $bucket_name)
    {
        return $this->request('GET', "/buckets", [
        ]);
    }

    /**
     * 直传文件到七牛云对象存储（Form Upload）
     *
     * @param string $key      资源名（对象键），如 "images/example.jpg"
     * @param string $filePath 本地文件路径
     * @param array  $options  可选参数：
     *                         - 上传策略字段：deadline, returnBody, callbackUrl, fsizeLimit, mimeLimit 等
     *                         - crc32: 文件 CRC32 校验值
     *
     * @throws HttpRequestException
     * @throws FileNonExistent
     * @return mixed 成功返回 ["hash" => ..., "key" => ...]，失败抛出异常
     */
    public function upload(
        string   $key,
        string   $filePath,
        array    $options = [],
        callable $progress_callback = null,
        bool     $body_as_string = true
    ): mixed {
        $bucket = $this->config->get('bucket');

        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new FileNonExistent($filePath);
        }

        $file = fopen($filePath, 'rb');
        if ($file === false) {
            throw new FileNonExistent($filePath);
        }

        // 生成上传凭证
        $signer = new Upload($this->config, $key);
        $token = $signer->sign($bucket, $options);

        // 构建 multipart 表单字段
        $multipart = [
            ['name' => 'token', 'contents' => $token],
            ['name' => 'key', 'contents' => $key],
            ['name' => 'file', 'contents' => $file, 'filename' => basename($filePath)],
        ];

        if (isset($options['crc32'])) {
            $multipart[] = ['name' => 'crc32', 'contents' => $options['crc32']];
        }

        return $this->request('POST', $this->config->get('upload_domain'), [
            'multipart' => $multipart,
            'request'   => [
                'expect'          => !$body_as_string,
                '_body_as_string' => $body_as_string,
                'progress'        => $progress_callback,
            ],
        ]);
    }

    /**
     * 列举指定空间里的所有文件条目
     *
     * @param array $params 可选参数：
     *                      - marker: 上一次列举返回的位置标记，默认空字符串
     *                      - limit: 本次列举的条目数，范围 1-1000，默认 1000
     *                      - prefix: 指定前缀，只有资源名匹配该前缀的资源会被列出
     *                      - delimiter: 目录分隔符，用于模拟列出目录效果
     *
     * @throws HttpRequestException
     * @return array 返回 ["marker" => ..., "commonPrefixes" => [...], "items" => [...]]
     */
    public function getList(array $params = []): array
    {
        $params = array_merge([
            'marker'    => '',
            'delimiter' => '/',
            'limit'     => 1000,
        ], $params);
        $params['bucket'] = $this->config->get('bucket');

        $url = $this->config->get('rsf_domain') . '/list';

        return $this->request('GET', $url, [
            'body' => $params,
        ]);
    }

    /**
     * 查询资源元信息
     *
     * @param string $key 资源名（对象键）
     *
     * @throws HttpRequestException
     * @return FileNonExistent|array
     */
    public function stat(string $key): FileNonExistent|array
    {
        try {
            return $this->request('GET', '/stat/' . $this->encodeEntryURI($key));
        } catch (HttpRequestException $e) {
            if ($e->getHttpCode() === 612) return new FileNonExistent();
            throw $e;
        }
    }

    /**
     * 删除指定资源
     *
     * @param string $key 资源名（对象键）
     *
     * @throws HttpRequestException
     * @return void
     */
    public function delete(string $key): void
    {
        $this->request('POST', '/delete/' . $this->encodeEntryURI($key));
    }

    /**
     * 移动/重命名资源
     *
     * @param string $srcKey     源资源名
     * @param string $destKey    目标资源名
     * @param bool   $force      强制覆盖已存在的目标文件，默认 false
     * @param string $destBucket 目标空间，默认同源空间
     *
     * @throws HttpRequestException
     * @return void
     */
    public function move(string $srcKey, string $destKey, bool $force = false, string $destBucket = ''): void
    {
        $srcBucket = $this->config->get('bucket');
        $destBucket = $destBucket ?: $srcBucket;

        $path = '/move/' . $this->encodeEntryURI($srcKey, $srcBucket)
                . '/' . $this->encodeEntryURI($destKey, $destBucket)
                . '/force/' . ($force ? 'true' : 'false');

        $this->request('POST', $path);
    }


    /**
     * 修改资源元信息（MIME 类型、自定义元数据、缓存策略等）
     *
     * @param string $key      资源名（对象键）
     * @param array  $options  可选参数：
     *                         - mime: 新的 MIME 类型
     *                         - metadata: 自定义元数据，如 ["key1" => "val1", "key2" => "val2"]
     *                         - cacheControl: 缓存行为
     *                         - contentDisposition: 文件展示形式
     *                         - contentLanguage: 文件语言
     *                         - contentEncoding: 文件编码方式
     *                         - expires: 缓存失效时间
     *                         - cond: 条件修改，如 ["hash" => "xxx", "fsize" => 1024]
     *
     * @throws HttpRequestException
     * @return mixed 成功返回空
     */
    public function chgm(string $key, array $options = []): mixed
    {
        $path = '/chgm/' . $this->encodeEntryURI($key);

        $segments = ['mime', 'cacheControl', 'contentDisposition', 'contentLanguage', 'contentEncoding', 'expires'];
        foreach ($segments as $seg) {
            if (isset($options[$seg])) {
                $path .= '/' . $seg . '/' . $this->urlsafeBase64Encode($options[$seg]);
            }
        }

        // 自定义元数据: x-qn-meta-<key>/<EncodedValue>
        if (isset($options['metadata']) && is_array($options['metadata'])) {
            foreach ($options['metadata'] as $metaKey => $metaValue) {
                $path .= '/x-qn-meta-' . $metaKey . '/' . $this->urlsafeBase64Encode($metaValue);
            }
        }

        // 条件修改
        if (isset($options['cond']) && is_array($options['cond'])) {
            $cond_parts = [];
            foreach ($options['cond'] as $condKey => $condVal) {
                $cond_parts[] = $condKey . '=' . $condVal;
            }
            $path .= '/cond/' . $this->urlsafeBase64Encode(implode('&', $cond_parts));
        }

        return $this->request('POST', $path);
    }

    /**
     * 修改资源存储类型
     *
     * @param string $key  资源名（对象键）
     * @param int    $type 存储类型：0=标准 1=低频 2=归档 3=深度归档 4=归档直读 5=智能分层
     *
     * @throws HttpRequestException
     * @return void
     */
    public function chtype(string $key, int $type): void
    {
        $path = '/chtype/' . $this->encodeEntryURI($key) . '/type/' . $type;
        $this->request('POST', $path);
    }

    /**
     * 生成私有资源的下载链接（带下载凭证）
     *
     * @param string $key     资源名（对象键）
     * @param int    $expires 过期时间（秒），默认 3600
     * @param array  $params  额外的 URL 查询参数，如 ["imageView2/1/w/200/h/200" => ""]
     *
     * @return string 带下载凭证的完整下载链接
     */
    public function downloadUrl(string $key, int $expires = 3600, array $params = []): string
    {
        $signer = new Download($this->config, $key);
        return $signer->sign($this->config->get('download_domain'), [
            'expires' => $expires,
            'params'  => $params,
        ]);
    }

    /**
     * URL 安全的 Base64 编码
     */
    private function urlsafeBase64Encode(string $data): string
    {
        return str_replace(['+', '/'], ['-', '_'], base64_encode($data));
    }

    /**
     * 编码 EncodedEntryURI（URL 安全的 Base64）
     */
    private function encodeEntryURI(string $key, ?string $bucket = null): string
    {
        $bucket = $bucket ?: $this->config->get('bucket');
        return $this->urlsafeBase64Encode($bucket . ':' . $key);
    }
}
