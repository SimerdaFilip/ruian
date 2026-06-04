<?php

declare(strict_types=1);

namespace Simerda\Ruian\Http;

final class HttpResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
    ) {
    }
}
