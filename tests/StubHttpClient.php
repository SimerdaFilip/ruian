<?php

declare(strict_types=1);

namespace Simerda\Ruian\Tests;

use Simerda\Ruian\Http\HttpClientInterface;
use Simerda\Ruian\Http\HttpResponse;

final class StubHttpClient implements HttpClientInterface
{
    public ?string $requestedUrl = null;

    public function __construct(
        private readonly int $statusCode = 200,
        private readonly string $body = '',
    ) {
    }

    public function get(string $url): HttpResponse
    {
        $this->requestedUrl = $url;

        return new HttpResponse($this->statusCode, $this->body);
    }
}
