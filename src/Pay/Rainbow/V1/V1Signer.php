<?php

namespace Wood\Sdk\Pay\Rainbow\V1;

use Wood\Sdk\Contracts\SignerInterface;

class V1Signer implements SignerInterface
{
    private string $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function sign(string $data, array $info = []): string
    {
        return md5($data . $this->key);
    }

    public function verify(string $data, string $sign): bool
    {
        return md5($data . $this->key) === $sign;
    }
}