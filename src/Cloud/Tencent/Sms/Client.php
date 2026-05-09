<?php

namespace Wood\Sdk\Cloud\Tencent\Sms;

use Wood\Sdk\Abstracts\BaseClient;
use Wood\Sdk\Cloud\Tencent\Config;
use Wood\Sdk\Cloud\Tencent\Signer;
use Wood\Sdk\Exceptions\HttpRequestException;
use Wood\Sdk\Exceptions\InvalidConfigException;

class Client extends BaseClient
{
    protected Config $config;

    public function __construct(Config $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    public function getBaseUri(): string
    {
        return 'https://' . $this->config->get('host');
    }

    /**
     * @throws InvalidConfigException
     */
    public function buildHeaders(string $method, string $path, array $options = []): array
    {
        $body = $options['body'] ?? [];
        $timestamp = $options['timestamp'] ?? time();
        $action = $options['action'] ?? 'SendSms';
        $method = strtoupper($method);

        $headers = [
            'Content-Type'   => $options['content_type'] ?? ($method === 'GET' ? 'application/x-www-form-urlencoded' : 'application/json; charset=utf-8'),
            'Host'           => $this->config->get('host'),
            'X-TC-Action'    => $action,
            'X-TC-Timestamp' => (string)$timestamp,
            'X-TC-Version'   => $options['version'] ?? $this->config->get('version'),
            'X-TC-Language' => 'zh-CN',
        ];

        $region = $options['region'] ?? $this->config->get('region');
        if ($region !== '') {
            $headers['X-TC-Region'] = $region;
        }

        $token = $options['token'] ?? $this->config->get('token');
        if ($token !== '') {
            $headers['X-TC-Token'] = $token;
        }

        $signHeaders = [
            'Content-Type' => $headers['Content-Type'],
            'Host'         => $headers['Host'],
            'X-TC-Action'  => $headers['X-TC-Action'],
        ];

        $headers['Authorization'] = (new Signer($this->config->get('secret_key')))->sign(
            $method === 'GET' ? '' : $this->jsonEncode($body),
            [
                'secret_id' => $this->config->get('secret_id'),
                'method'    => $method,
                'path'      => $path,
                'timestamp' => $timestamp,
                'service'   => $this->config->get('service'),
                'headers'   => $signHeaders,
                'query'     => $method === 'GET' ? $body : ($options['query'] ?? []),
            ],
        );

        return $headers;
    }

    /**
     * 发送短信
     *
     * @throws HttpRequestException
     */
    public function send(string|array $phone_numbers, array $template_params = [], array $options = []): array|string
    {
        $body = [
            'PhoneNumberSet'  => is_array($phone_numbers) ? array_values($phone_numbers) : [$phone_numbers],
            'SmsSdkAppId'     => $options['sms_sdk_app_id'] ?? $this->config->get('sms_sdk_app_id'),
            'SignName'        => $this->config->get('sign_name'),
            'TemplateId'      => $options['template_id'] ?? $this->config->get('template_id'),
            'TemplateParamSet' => array_map('strval', $template_params),
        ];

        return $this->request('POST', '/', array_merge($options, [
            'action' => 'SendSms',
            'body'   => $body,
        ]));
    }
}
