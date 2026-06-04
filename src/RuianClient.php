<?php

declare(strict_types=1);

namespace Simerda\Ruian;

use JsonException;
use Simerda\Ruian\Code\AdresniMistoCode;
use Simerda\Ruian\Exception\RuianException;
use Simerda\Ruian\Http\CurlHttpClient;
use Simerda\Ruian\Http\HttpClientInterface;

/**
 * Thin client over the public ČÚZK ArcGIS REST service for RÚIAN data.
 *
 * Only the read-only `query` operation is used. The default layer points at the
 * address-point layer ("Adresní místa"); both the base URL and the layer id are
 * overridable because ČÚZK reorganizes the published map service from time to
 * time.
 */
final class RuianClient
{
    private const DEFAULT_BASE_URL =
        'https://ags.cuzk.cz/arcgis/rest/services/RUIAN/Prohlizeci_sluzba_nad_daty_RUIAN/MapServer';

    /**
     * Layer id of "Adresní místa" in the RÚIAN viewing service. ČÚZK exposes the
     * address points on layer 1 of this MapServer; override it if the service
     * is republished with a different layout.
     */
    private const DEFAULT_LAYER_ID = 1;

    /**
     * The AdresniMisto attributes worth pulling back. `adresa` is the
     * authoritative display string; `ulice` is the numeric street code.
     */
    private const OUT_FIELDS = 'kod,ulice,cislodomovni,cisloorientacni,cisloorientacnipismeno,psc,adresa';

    private readonly HttpClientInterface $http;
    private readonly string $baseUrl;

    public function __construct(
        ?HttpClientInterface $http = null,
        string $baseUrl = self::DEFAULT_BASE_URL,
        private readonly int $layerId = self::DEFAULT_LAYER_ID,
    ) {
        $this->http = $http ?? new CurlHttpClient();
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function findByAddressPointCode(int|AdresniMistoCode $code): ?AddressPoint
    {
        $value = $code instanceof AdresniMistoCode ? $code->value : $code;

        $features = $this->query([
            'where' => sprintf('kod=%d', $value),
            'outFields' => self::OUT_FIELDS,
            'returnGeometry' => 'true',
            'outSR' => '4326',
            'f' => 'json',
        ]);

        $first = $features[0] ?? null;

        return is_array($first) ? AddressPoint::fromFeature($first) : null;
    }

    /**
     * Free-text search over the human-readable address string.
     *
     * @return list<AddressPoint>
     */
    public function search(string $query, int $limit = 10): array
    {
        $limit = max(1, $limit);
        $needle = self::escapeLikeValue($query);

        $features = $this->query([
            // `adresa` is the only free-text field on the layer; a substring
            // LIKE over it covers street, part and city in one go.
            'where' => sprintf("UPPER(adresa) LIKE UPPER('%%%s%%')", $needle),
            'outFields' => self::OUT_FIELDS,
            'returnGeometry' => 'true',
            'outSR' => '4326',
            'resultRecordCount' => (string) $limit,
            'f' => 'json',
        ]);

        $points = [];
        foreach ($features as $feature) {
            if (is_array($feature)) {
                $points[] = AddressPoint::fromFeature($feature);
            }
            if (count($points) >= $limit) {
                break;
            }
        }

        return $points;
    }

    /**
     * @param array<string, string> $params
     * @return list<mixed> the `features` array from the ArcGIS response
     */
    private function query(array $params): array
    {
        $url = sprintf('%s/%d/query?%s', $this->baseUrl, $this->layerId, http_build_query($params));

        $response = $this->http->get($url);
        if ($response->statusCode < 200 || $response->statusCode >= 300) {
            throw new RuianException(
                sprintf('The ČÚZK service returned an unexpected HTTP status %d.', $response->statusCode),
            );
        }

        try {
            $data = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuianException('Unable to decode the ČÚZK response as JSON: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($data)) {
            throw new RuianException('The ČÚZK response is not a JSON object.');
        }

        // ArcGIS answers a malformed query with HTTP 200 and an {"error": {...}} body.
        if (isset($data['error']) && is_array($data['error'])) {
            $message = is_scalar($data['error']['message'] ?? null)
                ? (string) $data['error']['message']
                : 'unknown error';

            throw new RuianException(sprintf('The ČÚZK service rejected the query: %s', $message));
        }

        $features = $data['features'] ?? [];

        return is_array($features) ? array_values($features) : [];
    }

    /**
     * Neutralize single quotes so user input cannot break out of the SQL-like
     * `where` clause ArcGIS evaluates. Doubling the quote is the SQL escape, and
     * stripping backslashes removes the other obvious escape vector.
     */
    private static function escapeLikeValue(string $value): string
    {
        return str_replace(["\\", "'"], ['', "''"], trim($value));
    }
}
