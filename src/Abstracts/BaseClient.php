<?php

namespace Wood\Sdk\Abstracts;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Throwable;
use Wood\Sdk\Exceptions\HttpRequestException;

abstract class BaseClient
{
    protected Client $httpClient;

    public function __construct()
    {
        $this->httpClient = new Client(['timeout' => 5]);
    }

    abstract public function getBaseUri(): string;

    abstract public function buildHeaders(string $method, string $path, array $options): array;

    public function jsonEncode(array $data): string
    {
        return !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : '';
    }

    /**
     * 发送请求
     *
     * @param string $method  请求方法
     * @param string $path    请求路径
     * @param array  $options 请求参数
     *
     * @throws HttpRequestException 请求异常
     * @return mixed|string
     */
    public function request(
        string $method,
        string $path,
        array  $options = [],
    ) {
        $body = $options['body'] ?? [];
        $headers = $this->buildHeaders($method, $path, $options);

        $error = false;
        try {
            $response = $this->httpClient->request($method, $this->getBaseUri() . $path, [
                'headers'                            => $headers,
                $method === 'GET' ? 'query' : 'body' => $method === 'GET' ? $body : $this->jsonEncode($body),
            ]);
            $content = $response->getBody()->getContents();

            return json_validate($content) ? json_decode($content, true) : $content;
        } catch (RequestException $e) {
            $error = true;
            $res_body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            $message = '请求网关失败';
            $http_code = $e->getResponse()->getStatusCode();
        } catch (Throwable $e) {
            $error = true;
            $res_body = $e->getMessage();
            $message = '未知请求错误';
            $http_code = 500;
        } finally {
            if ($error) {
                $exception = new HttpRequestException($message ?? '未知请求错误');
                $exception->setHttpCode($http_code ?? 500);
                $exception->setRequestOptions($options);
                $exception->setResBody($res_body ?? '');
                $exception->setMethod($method);
                $exception->setUrl($this->getBaseUri() . $path);
                throw $exception;
            }
        }
        throw new HttpRequestException('未知请求错误');
    }
}