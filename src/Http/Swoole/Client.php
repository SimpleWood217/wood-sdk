<?php

namespace Wood\Sdk\Http\Swoole;

use co;
use Exception;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Wood\Sdk\Exceptions\HttpRequestException;

class Client implements ClientInterface
{
    private int $timeout;

    public function __construct(array $options = [])
    {
        $this->timeout = $options['timeout'] ?? 5;
    }

    /**
     * @inheritDoc
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return new Response();
    }

    /**
     * @throws HttpRequestException
     */
    public function request(string $method, $url = '', array $options = []): Response
    {
        if (Co::getCid() === -1) {
            throw new RuntimeException('Not in a coroutine context');
        }

        if (!str_starts_with($url, 'http')) {
            throw new InvalidArgumentException('URI must start with http:// or https://');
        }

        $parsed = parse_url($url);
        if (!$parsed) throw new InvalidArgumentException('invalid uri');
        $host = $parsed['host'];
        $port = $parsed['port'] ?? ($parsed['scheme'] === 'https' ? 443 : 80);
        $path = $parsed['path'] ?? '/';
        $uri = $path;
        $headers = $options['headers'] ?? [];
        $method = strtoupper($method);
        $req_data = '';
        if (isset($options['query'])) {
            $uri .= '?' . http_build_query($options['query']);
        }
        if (isset($options['body'])) {
            $content_type = $headers['Content-Type'] ?? 'application/json';
            if ($content_type === 'application/json') {
                $req_data = json_encode($options['body'], JSON_UNESCAPED_UNICODE);
            } elseif ($content_type === 'application/x-www-form-urlencoded') {
                $req_data = http_build_query($options['body']);
            }
        }
        if (isset($options['multipart'])) {
            $multi = buildMultipartBody($options['multipart']);
            $req_data = $multi['body'];
            $headers['Content-Type'] = $multi['content_type'];
        }
        $client = new \Swoole\Coroutine\Http\Client($host, $port, $port === 443);
        $client->set([
            'timeout' => $this->timeout,
        ]);
        $client->setHeaders($headers);
        $client->setMethod($method);
        $client->setData($req_data);
        $client->execute($uri);
        $body = $client->getBody();
        $client->close();

        if ($client->getStatusCode() < 0 || $client->getStatusCode() >= 300) {
            $e = new HttpRequestException($client->errMsg ?? "[$uri]请求失败");
            $e->setHttpCode($client->getStatusCode());
            $e->setResBody($body);
            throw $e;
        }

        $response = new Response($client->getStatusCode(), $client->getHeaders(), $body);

        return $response;
    }
}