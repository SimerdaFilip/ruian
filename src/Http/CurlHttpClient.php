<?php

declare(strict_types=1);

namespace Simerda\Ruian\Http;

use Simerda\Ruian\Exception\RuianException;

final class CurlHttpClient implements HttpClientInterface
{
    private const USER_AGENT = 'simerda-ruian/1.0 (+https://github.com/SimerdaFilip/ruian)';

    public function __construct(
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
    ) {
    }

    public function get(string $url): HttpResponse
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuianException('Unable to initialize a curl handle.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $body = curl_exec($handle);
        $error = curl_error($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        // With CURLOPT_RETURNTRANSFER a non-string body means the request never produced
        // a response at all (DNS, TLS, timeout) rather than an empty 200.
        if (!is_string($body)) {
            throw new RuianException(
                sprintf('Request to the ČÚZK service failed: %s', $error !== '' ? $error : 'unknown error'),
            );
        }

        return new HttpResponse($status, $body);
    }
}
