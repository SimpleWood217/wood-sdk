<?php

namespace Wood\Sdk\Exceptions;

use Exception;

/**
 * HTTP 请求异常类
 *
 * 用于封装 HTTP 请求过程中发生的异常信息，包括请求参数、响应内容、HTTP 状态码等。
 * 提供多种信息输出方式：
 * - `getDetailedInfo()`: 终端调试输出，包含完整请求/响应详情
 * - `getSimpleInfo()`: 前端展示，简洁的一句话异常描述
 * - `getMessage()`: 基础异常消息
 *
 * 使用示例:
 * ```php
 * try {
 *     $client->request('POST', '/v3/pay/transactions/native', ['body' => $data]);
 * } catch (HttpRequestException $e) {
 *     // 终端调试
 *     echo $e->getDetailedInfo();
 *
 *     // 前端展示
 *     return ['error' => $e->getSimpleInfo()];
 *
 *     // 获取原始响应
 *     $resBody = $e->getResBody();
 * }
 * ```
 *
 * @author  WooD
 * @package Wood\Sdk\Exceptions
 */
class HttpRequestException extends Exception
{
    /**
     * HTTP 响应体内容
     *
     * @var string|null
     */
    private ?string $res_body = null;

    /**
     * HTTP 响应状态码
     *
     * @var int
     */
    private int $http_code = 0;

    /**
     * 原始请求选项数组
     * 包含 headers、body、query 等 Guzzle 请求参数
     *
     * @var array
     */
    private array $request_options = [];

    /**
     * HTTP 请求方法 (GET/POST/PUT/DELETE 等)
     *
     * @var string
     */
    private string $method = '';

    /**
     * 完整的请求 URL
     *
     * @var string
     */
    private string $url = '';

    /**
     * @return string
     */
    public function getResBody(): string
    {
        return $this->res_body;
    }

    /**
     * @param string $res_body
     */
    public function setResBody(string $res_body): void
    {
        $this->res_body = $res_body;
    }

    /**
     * @param int $http_code
     */
    public function setHttpCode(int $http_code): void
    {
        $this->http_code = $http_code;
    }

    /**
     * @return int
     */
    public function getHttpCode(): int
    {
        return $this->http_code;
    }

    /**
     * @param array $request_options
     */
    public function setRequestOptions(array $request_options): void
    {
        $this->request_options = $request_options;
    }

    /**
     * @return array
     */
    public function getRequestOptions(): array
    {
        return $this->request_options;
    }

    /**
     * @param string $method
     */
    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @param string $url
     */
    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * 输出详细的异常信息
     *
     * @param bool $include_body 是否包含请求体内容
     *
     * @return string
     */
    public function getDetailedInfo(bool $include_body = true): string
    {
        $lines = [
            '========== HTTP Request Exception ==========',
            'Message:    ' . $this->getMessage(),
            'HTTP Code:  ' . $this->http_code,
            'Method:     ' . strtoupper($this->method),
            'URL:        ' . $this->url,
            'Response:   ' . $this->formatResponse($this->res_body),
        ];

        if ($include_body && !empty($this->request_options['body'])) {
            $body = $this->request_options['body'];
            $lines[] = 'Request:    ' . (is_array($body) ? json_encode($body, JSON_UNESCAPED_UNICODE |
                                                                              JSON_UNESCAPED_SLASHES) : (string)$body);
        }

        $lines[] = 'Trace:      ' . $this->getTraceAsString();
        $lines[] = '====================================================';

        return implode("\n", $lines);
    }

    /**
     * 格式化响应内容
     */
    private function formatResponse(string $response): string
    {
        if (empty($response)) {
            return '(empty)';
        }

        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }

        return $response;
    }

    /**
     * 返回简短的异常信息，适合前端展示
     *
     * @return string
     */
    public function getSimpleInfo(): string
    {
        $msg = "HTTP请求异常：{$this->getMessage()} (状态码: $this->http_code)";

        $decoded = json_decode($this->res_body, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['message'])) {
            $msg .= "，原因: {$decoded['message']}";
        }

        return $msg;
    }
}