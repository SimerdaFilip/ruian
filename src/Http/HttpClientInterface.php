<?php

declare(strict_types=1);

namespace Simerda\Ruian\Http;

use Simerda\Ruian\Exception\RuianException;

interface HttpClientInterface
{
    /**
     * @throws RuianException on a transport-level failure (connection, timeout, ...)
     */
    public function get(string $url): HttpResponse;
}
