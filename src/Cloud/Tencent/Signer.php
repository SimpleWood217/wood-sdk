<?php

namespace Wood\Sdk\Cloud\Tencent;

use Wood\Sdk\Contracts\SignerInterface;
use Wood\Sdk\Exceptions\InvalidConfigException;

class Signer implements SignerInterface
{
    private const string ALGORITHM  = 'TC3-HMAC-SHA256';
    private const string TERMINATOR = 'tc3_request';

    private string $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    /**
     * @throws InvalidConfigException
     */
    public function sign(string $data, array $info = []): string
    {
        foreach (['secret_id', 'method', 'path', 'timestamp', 'service', 'headers'] as $key) {
            if (!isset($info[$key])) {
                throw new InvalidConfigException("缺少签名参数: $key");
            }
        }

        $timestamp = (int)$info['timestamp'];
        $date = gmdate('Y-m-d', $timestamp);
        $service = (string)$info['service'];
        $credentialScope = $date . '/' . $service . '/' . self::TERMINATOR;

        [$canonicalHeaders, $signedHeaders] = $this->buildCanonicalHeaders($info['headers']);
        $canonicalRequest = implode("\n", [
            strtoupper($info['method']),
            $this->getCanonicalUri($info['path']),
            $this->buildCanonicalQueryString($info['query'] ?? [], $info['path']),
            $canonicalHeaders,
            $signedHeaders,
            hash('sha256', $data),
        ]);

        $stringToSign = implode("\n", [
            self::ALGORITHM,
            (string)$timestamp,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $signature = $this->signature($date, $service, $stringToSign);

        return self::ALGORITHM
               . ' Credential=' . $info['secret_id'] . '/' . $credentialScope
               . ', SignedHeaders=' . $signedHeaders
               . ', Signature=' . $signature;
    }

    public function verify(string $data, string $sign): bool
    {
        return false;
    }

    private function buildCanonicalHeaders(array $headers): array
    {
        $canonicalHeaders = [];
        foreach ($headers as $key => $value) {
            $canonicalHeaders[strtolower(trim($key))] = strtolower(trim((string)$value));
        }
        ksort($canonicalHeaders);

        $signedHeaders = implode(';', array_keys($canonicalHeaders));
        $canonicalHeaderString = '';
        foreach ($canonicalHeaders as $key => $value) {
            $canonicalHeaderString .= $key . ':' . $value . "\n";
        }

        return [$canonicalHeaderString, $signedHeaders];
    }

    private function buildCanonicalQueryString(array|string $query, string $path): string
    {
        if ($query === []) {
            $urlQuery = parse_url($path, PHP_URL_QUERY);
            if (is_string($urlQuery) && $urlQuery !== '') {
                parse_str($urlQuery, $query);
            }
        }

        if (is_string($query)) {
            return ltrim($query, '?');
        }

        ksort($query);

        return http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function getCanonicalUri(string $path): string
    {
        return parse_url($path, PHP_URL_PATH) ?: '/';
    }

    private function signature(string $date, string $service, string $stringToSign): string
    {
        $secretDate = hash_hmac('sha256', $date, 'TC3' . $this->key, true);
        $secretService = hash_hmac('sha256', $service, $secretDate, true);
        $secretSigning = hash_hmac('sha256', self::TERMINATOR, $secretService, true);

        return hash_hmac('sha256', $stringToSign, $secretSigning);
    }
}
