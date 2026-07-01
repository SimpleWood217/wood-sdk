<?php

namespace Wood\Sdk\Cloud\Aliyun\Sms;

use Wood\Sdk\Abstracts\BaseClient;
use Wood\Sdk\Cloud\Aliyun\Config;
use Wood\Sdk\Cloud\Aliyun\Signer;
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
        return 'https://' . $this->config->get('gateway');
    }

    /**
     * @throws InvalidConfigException
     */
    public function buildHeaders(string $method, string $path, array $options = []): array
    {
        $body = $options['body'] ?? [];
        $method = strtoupper($method);

        $headers = [
            'Content-Type'          => $options['content_type'] ?? 'application/x-www-form-urlencoded',
            'host'                  => $this->config->get('gateway'),
            'x-acs-action'          => $options['action'] ?? 'SendSms',
            'x-acs-version'         => $options['version'] ?? $this->config->get('version'),
            'x-acs-date'            => gmdate('Y-m-d\TH:i:s\Z'),
            'x-acs-signature-nonce' => uniqid(),
            'x-acs-content-sha256'  => hash('sha256', $method === 'GET' ? '' : http_build_query($body)),
        ];

        $signHeaders = array_filter($headers, function ($key) {
            return str_starts_with($key, 'x-acs-') || $key === 'host' || $key === 'Content-Type';
        }, ARRAY_FILTER_USE_KEY);

        $headers['Authorization'] = (new Signer($this->config->get('access_key_secret')))->sign(
            $method === 'GET' ? '' : http_build_query($body),
            [
                'access_key_id' => $this->config->get('access_key_id'),
                'method'        => $method,
                'path'          => $path,
                'headers'       => $signHeaders,
                'query'         => $options['query'] ?? '',
            ],
        );

        return $headers;
    }

    /**
     * 发送短信
     *
     * @param string|array $phone_numbers  手机号，支持单个或数组
     * @param array        $template_param 模板参数
     * @param array        $options        额外选项
     *
     * @throws HttpRequestException
     */
    public function send(string|array $phone_numbers, array $template_param = [], array $options = []): array|string
    {
        $body = [
            'PhoneNumbers' => is_array($phone_numbers) ? implode(',', $phone_numbers) : $phone_numbers,
            'SignName'     => $this->config->get('sign_name'),
            'TemplateCode' => $options['template_code'] ?? $this->config->get('template_code'),
        ];

        if (!empty($template_param)) {
            $body['TemplateParam'] = json_encode($template_param, JSON_UNESCAPED_UNICODE);
        }

        return $this->request('POST', '/', array_merge($options, [
            'action' => 'SendSms',
            'body'   => $body,
        ]));
    }
}
