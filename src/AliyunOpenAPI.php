<?php

namespace Wood\Sdk;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * Plugin Name:     阿里云OpenAPI
 * Plugin Type:     SMS
 *
 * @author          JMPay
 * @since           2025-9-29
 */
class AliyunOpenAPI
{
    private string $gateway         = 'dysmsapi.aliyuncs.com';
    private string $method          = 'POST';
    private string $path            = '/';
    private string $query           = '';
    private string $action          = '';
    private string $version         = '';
    private array  $requestData     = [];
    private string $accessKeyId     = '';
    private string $accessKeySecret = '';


    /**
     * 发起请求
     *
     * @return array|string 执行结果
     */
    public function execute(): array|string
    {
        $requestData = http_build_query($this->requestData);

        $headers = [
            'host' => $this->gateway,
            'x-acs-action' => $this->action,
            'x-acs-version' => $this->version,
            'x-acs-content-sha256' => hash('sha256', $requestData),
            'x-acs-date' => gmdate('Y-m-d\TH:i:s\Z'),
            'x-acs-signature-nonce' => uniqid()
        ];

        $sign = $this->sign($requestData, $headers);
        $headers['Authorization'] = $sign;

        $client = new Client();
        try {
            $response = $client->request($this->method,
                'https://' . $this->gateway . $this->path . '?' . $this->query,
                [
                    'headers' => $headers,
                    'form_params' => $this->requestData,
                ]);
            $body = $response->getBody()->getContents();
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
        } catch (GuzzleException $e) {
            $body = $e->getMessage();
        }
        return json_validate($body) ? json_decode($body, true) : $body;
    }

    /**
     * 生成签名
     *
     * @param string $requestData 请求数据的字符串
     * @param array  $headers     请求头
     *
     * @return string 签名
     */
    private function sign(string $requestData, array $headers): string
    {
        $headers = array_filter($headers, function ($key) {
            return str_starts_with($key, 'x-acs-')
                || $key === 'host'
                || $key === 'Content-Type';
        }, ARRAY_FILTER_USE_KEY);
        ksort($headers);
        $canonicalHeaders = '';
        $signHeaders = '';
        foreach ($headers as $key => $value) {
            $canonicalHeaders .= strtolower($key) . ':' . $value . "\n";
            $signHeaders .= strtolower($key) . ';';
        }
        $signHeaders = rtrim($signHeaders, ';');

        $canonicalRequest = $this->method . "\n"
            . $this->path . "\n"
            . $this->query . "\n"
            . $canonicalHeaders . "\n"
            . $signHeaders . "\n"
            . hash('sha256', $requestData);

        $stringToSign = "ACS3-HMAC-SHA256\n"
            . hash('sha256', $canonicalRequest);

        $signature = hash_hmac('sha256', $stringToSign, $this->accessKeySecret);
        return 'ACS3-HMAC-SHA256 '
            . 'Credential=' . $this->accessKeyId
            . ',SignedHeaders=' . $signHeaders
            . ',Signature=' . $signature;
    }

    /**
     * 设置AccessKeyId
     *
     * @param string $accessKeyId
     *
     * @return void
     */
    public function setAccessKeyId(string $accessKeyId): void
    {
        $this->accessKeyId = $accessKeyId;
    }

    /**
     * 设置AccessKeySecret
     *
     * @param string $accessKeySecret
     *
     * @return void
     */
    public function setAccessKeySecret(string $accessKeySecret): void
    {
        $this->accessKeySecret = $accessKeySecret;
    }

    /**
     * 设置Path
     *
     * @param string $path
     *
     * @return void
     */
    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    /**
     * 设置Action
     *
     * @param string $action
     *
     * @return void
     */
    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    /**
     * 设置版本
     *
     * @param string $version
     *
     * @return void
     */
    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    /**
     * 设置Query
     *
     * @param string $query
     *
     * @return void
     */
    public function setQuery(string $query): void
    {
        $this->query = $query;
    }

    /**
     * 设置Method
     *
     * @param string $method
     *
     * @return void
     */
    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    /**
     * 设置请求数据
     *
     * @param array $requestData
     *
     * @return void
     */
    public function setRequestData(array $requestData): void
    {
        $this->requestData = $requestData;
    }
}