<?php

use Wood\Sdk\Abstracts\BaseClient;

/**
 * 将 multipart 请求数组转化为 multipart 请求载体
 *
 * 将 Guzzle 风格的 multipart 数组转换为 multipart/form-data 格式的请求体字符串，
 * 适用于需要手动构建 multipart 请求体的场景（如签名计算、curl 直接发送等）。
 *
 * 输入数组格式示例：
 * [
 *     ['name' => 'token',    'contents' => 'abc123'],
 *     ['name' => 'key',      'contents' => 'images/photo.jpg'],
 *     ['name' => 'file',     'contents' => fopen('/path/to/file.jpg', 'r'), 'filename' => 'photo.jpg'],
 *     ['name' => 'metadata', 'contents' => json_encode($meta), 'headers' => ['Content-Type' => 'application/json']],
 * ]
 *
 * @param array       $multipart multipart 请求数组
 * @param string|null $boundary  自定义分隔符，不传则自动生成
 *
 * @return array ['body' => string, 'boundary' => string, 'content_type' => string]
 */
function buildMultipartBody(array $multipart, ?string $boundary = null): array
{
    $boundary = $boundary ?: '----FormBoundary' . uniqid();
    $body = '';

    foreach ($multipart as $part) {
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"{$part['name']}\"";

        if (isset($part['filename'])) {
            $body .= "; filename=\"{$part['filename']}\"";
        }
        $body .= "\r\n";

        // 自定义请求头（如 Content-Type）
        if (isset($part['headers']) && is_array($part['headers'])) {
            foreach ($part['headers'] as $header_name => $header_value) {
                $body .= "{$header_name}: {$header_value}\r\n";
            }
        }

        $body .= "\r\n";

        $contents = $part['contents'];
        if (is_resource($contents)) {
            $body .= stream_get_contents($contents);
            rewind($contents);
        } else {
            $body .= $contents;
        }
        $body .= "\r\n";
    }

    $body .= "--{$boundary}--\r\n";

    return [
        'body'         => $body,
        'boundary'     => $boundary,
        'content_type' => "multipart/form-data; boundary={$boundary}",
    ];
}

function setDefaultHttpDriver(string $driver): void
{
    BaseClient::$defaultHttpDriver = $driver;
}