<?php

namespace Wood\Sdk\Pay\Alipay\V3;

use Exception;
use GuzzleHttp\Exception\RequestException;
use Throwable;
use Wood\Sdk\Exceptions\HttpRequestException;
use Wood\Sdk\Exceptions\InvalidConfigException;
use Wood\Sdk\Pay\Alipay\Config;

class Upload extends Client
{
    public function __construct(Config $config)
    {
        parent::__construct($config);
    }

    public function getBaseUri(): string
    {
        return 'https://openapi.alipay.com';
    }

    /**
     * @throws InvalidConfigException|Exception
     */
    public function buildHeaders(string $method, string $path, array $options = []): array
    {
        $sign_data = $options['sign_data'] ?? '';

        $nonce = strtoupper(uniqid());
        $timestamp = time();

        $auth_string = $this->buildAuthString($nonce, $timestamp);

        $sign = (new Signer($this->config))->sign($sign_data, [
            'method'      => $method,
            'path'        => $path,
            'timestamp'   => $timestamp,
            'nonce'       => $nonce,
            'auth_string' => $auth_string,
        ]);

        return [
            'Authorization' => 'ALIPAY-SHA256withRSA ' . $auth_string . ',sign=' . $sign,
        ];
    }

    /**
     * 上传图片
     *
     * @param string $path   上传接口路径
     * @param string $image  图片二进制内容
     * @param string $suffix 图片后缀名
     *
     * @throws HttpRequestException
     * @throws InvalidConfigException
     * @return array|string
     */
    public function image(string $path, string $image, string $suffix): array|string
    {
        $sign_data = $this->jsonEncode([
            'image_type' => $suffix,
        ]);

        $headers = $this->buildHeaders('POST', $path, ['sign_data' => $sign_data]);

        $options = [
            'headers'   => $headers,
            'multipart' => [
                [
                    'name'     => 'data',
                    'contents' => $sign_data,
                    'headers'  => [
                        'Content-Type' => 'application/json',
                    ],
                ],
                [
                    'name'     => 'image_content',
                    'contents' => $image,
                    'headers'  => [
                        'Content-Type' => 'image/' . $suffix,
                    ],
                ],
            ],
        ];

        $error = false;
        try {
            $response = $this->httpClient->request('POST', $this->getBaseUri() . $path, $options);
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
                $exception->setMethod('POST');
                $exception->setUrl($this->getBaseUri() . $path);
                throw $exception;
            }
        }
        throw new HttpRequestException('未知请求错误');
    }
}
