<?php

namespace Wood\Sdk\Cloud\Aliyun;

use Wood\Sdk\Contracts\SignerInterface;
use Wood\Sdk\Exceptions\InvalidConfigException;

class Signer implements SignerInterface
{
    private const string ALGORITHM = 'ACS3-HMAC-SHA256';

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
        foreach (['method', 'path', 'headers'] as $key) {
            if (!isset($info[$key])) {
                throw new InvalidConfigException("缺少签名参数: $key");
            }
        }

        [$canonicalHeaders, $signedHeaders] = $this->buildCanonicalHeaders($info['headers']);
        $query = $info['query'] ?? '';

        $canonicalRequest = implode("\n", [
            strtoupper($info['method']),
            $info['path'],
            $query,
            $canonicalHeaders,
            $signedHeaders,
            hash('sha256', $data),
        ]);

        $stringToSign = self::ALGORITHM . "\n" . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $stringToSign, $this->key);

        return self::ALGORITHM
               . ' Credential=' . ($info['access_key_id'] ?? '')
               . ',SignedHeaders=' . $signedHeaders
               . ',Signature=' . $signature;
    }

    public function verify(string $data, string $sign): bool
    {
        return false;
    }

    private function buildCanonicalHeaders(array $headers): array
    {
        $canonicalHeaders = [];
        foreach ($headers as $key => $value) {
            $canonicalHeaders[strtolower($key)] = trim((string)$value);
        }
        ksort($canonicalHeaders);

        $signedHeaders = implode(';', array_keys($canonicalHeaders));
        $canonicalHeaderString = '';
        foreach ($canonicalHeaders as $key => $value) {
            $canonicalHeaderString .= $key . ':' . $value . "\n";
        }

        return [$canonicalHeaderString, $signedHeaders];
    }
}
