<?php

namespace Wood\Sdk\Http\Swoole;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class Request implements RequestInterface
{

    /**
     * @inheritDoc
     */
    public function getProtocolVersion(): string
    {

    }

    /**
     * @inheritDoc
     */
    public function withProtocolVersion(string $version): MessageInterface
    {

    }

    /**
     * @inheritDoc
     */
    public function getHeaders(): array
    {

    }

    /**
     * @inheritDoc
     */
    public function hasHeader(string $name): bool
    {

    }

    /**
     * @inheritDoc
     */
    public function getHeader(string $name): array
    {

    }

    /**
     * @inheritDoc
     */
    public function getHeaderLine(string $name): string
    {

    }

    /**
     * @inheritDoc
     */
    public function withHeader(string $name, $value): MessageInterface
    {

    }

    /**
     * @inheritDoc
     */
    public function withAddedHeader(string $name, $value): MessageInterface
    {

    }

    /**
     * @inheritDoc
     */
    public function withoutHeader(string $name): MessageInterface
    {

    }

    /**
     * @inheritDoc
     */
    public function getBody(): StreamInterface
    {

    }

    /**
     * @inheritDoc
     */
    public function withBody(StreamInterface $body): MessageInterface
    {

    }

    /**
     * @inheritDoc
     */
    public function getRequestTarget(): string
    {

    }

    /**
     * @inheritDoc
     */
    public function withRequestTarget(string $requestTarget): RequestInterface
    {

    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {

    }

    /**
     * @inheritDoc
     */
    public function withMethod(string $method): RequestInterface
    {

    }

    /**
     * @inheritDoc
     */
    public function getUri(): UriInterface
    {

    }

    /**
     * @inheritDoc
     */
    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
    {

    }
}