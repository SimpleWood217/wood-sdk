<?php

namespace Wood\Sdk\Abstracts;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Client\ClientInterface;
use Throwable;
use Wood\Sdk\Exceptions\HttpRequestException;

abstract class BaseClient
{
    public static string      $defaultHttpDriver = 'guzzle';
    protected ClientInterface $httpClient;

    public function __construct(protected int $timeout = 15)
    {
        if (self::$defaultHttpDriver == 'swoole') {
            $client = new \Wood\Sdk\Http\Swoole\Client(['timeout' => $timeout]);
        } else {
            $client = new Client(['timeout' => $timeout]);
        }
        $this->httpClient = $client;
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
    ): mixed {
        $body = $options['body'] ?? [];
        $headers = $this->buildHeaders($method, $path, $options);
        if (isset($headers['new_body'])) {
            $body = $headers['new_body'];
            unset($headers['new_body']);
        }

        $isMultipart = isset($options['multipart']);
        $url = str_starts_with($path, 'http') ? $path : $this->getBaseUri() . $path;

        $error = false;
        $ex = null;
        try {
            if (!$isMultipart && $method != 'GET') {
                if ($headers['Content-Type'] == 'application/x-www-form-urlencoded') {
                    $body = http_build_query($body);
                } else {
                    $body = $this->jsonEncode($body);
                }
            }
            $response = $this->httpClient->request($method, $url, array_merge($isMultipart
                ? ['headers' => $headers, 'multipart' => $options['multipart']]
                : ['headers' => $headers, $method === 'GET' ? 'query' : 'body' => $body], $options['request'] ?? []),
            );
            $content = $response->getBody()->getContents();

            return json_validate($content) ? json_decode($content, true) : $content;
        } catch (RequestException $e) {
            $error = true;
            $ex = new HttpRequestException('请求失败' . $e->getMessage());
            $response = $e->getResponse();
            dump($response->getBody()->getContents());
            $handler_context = $e->getHandlerContext();
            $res_body = $response ? $response->getBody()->getContents() : $e->getMessage();
            $http_code = $response ? $response->getStatusCode() : ($handler_context['http_code'] ??
                                                                   $e->getCode() ?: 599);
        } catch (HttpRequestException $ex) {
            $error = true;
            $http_code = $ex->getHttpCode();
            $res_body = $ex->getResBody();
        } catch (ConnectException $e) {
            $error = true;
            $ex = new HttpRequestException('请求超时:' . $e->getMessage());
            $res_body = $e->getMessage();
            $http_code = 504;
        } catch (Throwable $e) {
            $error = true;
            $ex = new HttpRequestException('未知请求错误' . $e->getMessage());
            $res_body = $e->getMessage();
            $http_code = 500;
        } finally {
            if ($error) {
                $ex->setHttpCode($http_code ?? 500);
                $ex->setRequestOptions($options);
                $ex->setResBody($res_body ?? '');
                $ex->setMethod($method);
                $ex->setUrl($url);
                throw $ex;
            }
        }
        throw new HttpRequestException('FatalError');
    }
}
