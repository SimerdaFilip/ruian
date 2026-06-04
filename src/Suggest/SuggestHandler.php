<?php

declare(strict_types=1);

namespace Simerda\Ruian\Suggest;

use Simerda\Ruian\RuianClient;

/**
 * Transport-agnostic backend for an address autocomplete. It turns a raw query
 * string into a list of {@see Suggestion} objects ready to be JSON-encoded by
 * whatever HTTP layer the host application uses; it never touches the output
 * buffer or headers itself.
 */
final class SuggestHandler
{
    private const MIN_QUERY_LENGTH = 2;

    public function __construct(
        private readonly RuianClient $client,
    ) {
    }

    /**
     * @return list<Suggestion>
     */
    public function suggest(string $query, int $limit = 10): array
    {
        $query = trim($query);

        // Short prefixes match half the country; skip the round trip entirely.
        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        $suggestions = [];
        foreach ($this->client->search($query, $limit) as $point) {
            $suggestions[] = Suggestion::fromAddressPoint($point);
        }

        return $suggestions;
    }
}
