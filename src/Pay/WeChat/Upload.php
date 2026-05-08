<?php

namespace Wood\Sdk\Pay\WeChat;

use GuzzleHttp\Exception\RequestException;
use Throwable;
use Wood\Sdk\Abstracts\BaseClient;
use Wood\Sdk\Exceptions\HttpRequestException;
use Wood\Sdk\Exceptions\InvalidConfigException;

class Upload extends BaseClient
{
    protected Config $config;

    public function __construct(Config $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    public function getBaseUri(): string
    {
        return 'https://api.mch.weixin.qq.com';
    }

    /**
     * @throws InvalidConfigException
     */
    public function buildHeaders(string $method, string $path, array $options = []): array
    {
        $meta = $options['meta'] ?? '';

        $nonce = strtoupper(uniqid());
        $timestamp = time();

        $private_key = file_get_contents(root_path() . $this->config->get('private_key_path'));

        $sign = (new Signer($private_key))->sign($meta, [
            'method'    => $method,
            'path'      => $path,
            'timestamp' => $timestamp,
            'nonce'     => $nonce,
            'is_query'  => false,
        ]);

        return [
            'Accept'        => 'application/json',
            'Authorization' => 'WECHATPAY2-SHA256-RSA2048 '
                               . 'mchid="' . $this->config->get('merch_no') . '",'
                               . 'nonce_str="' . $nonce . '",'
                               . 'timestamp="' . $timestamp . '",'
                               . 'serial_no="' . $this->config->get('cert_number') . '",'
                               . 'signature="' . $sign . '"',
        ];
    }

    /**
     * 上传图片
     *
     * @param string $path     上传接口路径
     * @param string $image    图片二进制内容
     * @param string $filename 文件名
     *
     * @throws HttpRequestException
     * @return array|string
     */
    public function image(string $path, string $image, string $filename): array|string
    {
        $meta = $this->jsonEncode([
            'filename' => $filename,
            'sha256'   => hash('sha256', $image),
        ]);

        $headers = $this->buildHeaders('POST', $path, ['meta' => $meta]);

        $options = [
            'headers'   => $headers,
            'multipart' => [
                [
                    'name'     => 'meta',
                    'contents' => $meta,
                ],
                [
                    'name'     => 'file',
                    'contents' => $image,
                    'filename' => $filename,
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
