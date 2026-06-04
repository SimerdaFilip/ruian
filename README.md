# simerda/ruian

[![Packagist Version](https://img.shields.io/packagist/v/simerda/ruian.svg)](https://packagist.org/packages/simerda/ruian)
[![Total Downloads](https://img.shields.io/packagist/dt/simerda/ruian.svg)](https://packagist.org/packages/simerda/ruian)
[![PHP Version](https://img.shields.io/packagist/php-v/simerda/ruian.svg)](https://packagist.org/packages/simerda/ruian)
[![CI](https://github.com/SimerdaFilip/ruian/actions/workflows/ci.yml/badge.svg)](https://github.com/SimerdaFilip/ruian/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

RÚIAN (Registr územní identifikace, adres a nemovitostí) is the Czech state registry of territorial
identification, addresses and real estate, maintained by ČÚZK.

Malá knihovna pro práci s RÚIAN kódy a geokódování adresních míst přes veřejnou službu ČÚZK.

This library gives you two things:

- **Code value objects** — small, validated types for the numeric RÚIAN identifiers
  (municipality, municipality part, street, address point, building).
- **Address-point geocoding** — a thin client over the public ČÚZK ArcGIS REST service that turns an
  address-point code or a free-text query into an `AddressPoint` with GPS coordinates.

## Install

```bash
composer require simerda/ruian
```

Requires PHP 8.1+ with the `curl` and `json` extensions. No other runtime dependencies.

## Quick start

Look up an address point by its RÚIAN code:

```php
use Simerda\Ruian\RuianClient;

$ruian = new RuianClient();

$point = $ruian->findByAddressPointCode(21731491);

if ($point !== null) {
    echo $point->formatted, "\n";          // "Jankovcova 1522/53, 170 00 Praha"
    echo $point->latitude, ' ', $point->longitude, "\n";  // 50.1041 14.4453
}
```

Free-text search (street or municipality name):

```php
foreach ($ruian->search('Jankovcova', limit: 5) as $point) {
    printf("%-40s  %s\n", $point->formatted, $point->code);
}
```

`AddressPoint` is a readonly DTO: `code`, `street`, `houseNumber`, `orientationNumber`, `zip`,
`municipality`, `municipalityPart`, `latitude`, `longitude` and a ready-made `formatted` one-liner.
Any field the service omits is `null`.

## Code value objects

The codes are plain numeric keys. The value objects validate the format only — a positive integer of
at most nine digits — because **RÚIAN codes carry no public check digit**, so there is nothing else to
verify offline. They are distinct types, so an `ObecCode` cannot be passed where an `UliceCode` is
expected.

```php
use Simerda\Ruian\Code\AdresniMistoCode;
use Simerda\Ruian\Code\ObecCode;

$obec = ObecCode::fromString('554782');
$misto = AdresniMistoCode::fromInt(21731491);

echo $obec->value;       // 554782 (int)
echo (string) $misto;    // "21731491"

// Pass the code straight into the client:
$point = (new RuianClient())->findByAddressPointCode($misto);

ObecCode::fromInt(0);    // throws \InvalidArgumentException
```

Available types: `ObecCode`, `CastObceCode`, `UliceCode`, `AdresniMistoCode`, `StavebniObjektCode`.

## Custom HTTP client

The client talks to the network through `Simerda\Ruian\Http\HttpClientInterface`. The default
`CurlHttpClient` works out of the box; pass your own implementation for logging, a proxy, a cache, or
a stub in tests.

```php
$ruian = new RuianClient(new MyHttpClient());
```

## Error handling

Every failure is a `Simerda\Ruian\Exception\RuianException` (a `RuntimeException`): non-2xx HTTP
responses, undecodable JSON, transport errors, and ArcGIS error bodies (which the service returns with
HTTP 200 and an `{"error": {...}}` payload). Malformed input to a code value object throws
`\InvalidArgumentException`.

## Overriding the service location

The ČÚZK map service is occasionally republished, and the layer field names are **not** a stable
contract. The DTO maps attributes defensively (it accepts a few field-name aliases), but if ČÚZK
changes the layout you can point the client somewhere else without touching the code:

```php
$ruian = new RuianClient(
    http: null,
    baseUrl: 'https://ags.cuzk.cz/arcgis/rest/services/RUIAN/Prohlizeci_sluzba_nad_daty_RUIAN/MapServer',
    layerId: 1, // "Adresní místa"
);
```

The default base URL is the RÚIAN viewing service and the default layer is `1` (address points).

## License

MIT © Filip Šimerda
