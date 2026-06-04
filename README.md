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

$point = $ruian->findByAddressPointCode(25958895);

if ($point !== null) {
    echo $point->formatted, "\n";          // "Jankovcova 1522/53, Holešovice, 17000 Praha 7"
    echo $point->latitude, ' ', $point->longitude, "\n";  // 50.1085 14.4530
}
```

Free-text search over the address line:

```php
foreach ($ruian->search('Jankovcova', limit: 5) as $point) {
    printf("%-50s  %s\n", $point->formatted, $point->code);
}
```

`AddressPoint` is a readonly DTO: `code`, `streetCode`, `street`, `houseNumber`, `orientationNumber`,
`zip`, `municipality`, `municipalityPart`, `latitude`, `longitude` and the `formatted` one-liner.

The ČÚZK `AdresniMisto` layer returns the address as a single composed `adresa` string plus a numeric
street code (`ulice`) — it does **not** expose the street/municipality/part as separate text fields.
So `formatted` (the verbatim `adresa`) is the source of truth and `streetCode` is the numeric `ulice`.
The broken-out `street`, `municipality` and `municipalityPart` are parsed back out of that line on a
best-effort basis and are `null` whenever the shape is ambiguous; `zip` and the coordinates come from
the reliable numeric attributes.

## Code value objects

The codes are plain numeric keys. The value objects validate the format only — a positive integer of
at most nine digits — because **RÚIAN codes carry no public check digit**, so there is nothing else to
verify offline. They are distinct types, so an `ObecCode` cannot be passed where an `UliceCode` is
expected.

```php
use Simerda\Ruian\Code\AdresniMistoCode;
use Simerda\Ruian\Code\ObecCode;

$obec = ObecCode::fromString('554782');
$misto = AdresniMistoCode::fromInt(25958895);

echo $obec->value;       // 554782 (int)
echo (string) $misto;    // "25958895"

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

The ČÚZK map service is occasionally republished. The DTO maps the layer attributes defensively
(missing values fall back to `null`), but if ČÚZK changes the layout you can point the client
somewhere else without touching the code:

```php
$ruian = new RuianClient(
    http: null,
    baseUrl: 'https://ags.cuzk.cz/arcgis/rest/services/RUIAN/Prohlizeci_sluzba_nad_daty_RUIAN/MapServer',
    layerId: 1, // "Adresní místa"
);
```

The default base URL is the RÚIAN viewing service and the default layer is `1` (address points).

## Frontend

The JavaScript address autocomplete widget lives in a separate npm package,
[`@simerda/ruian-autocomplete`](https://github.com/SimerdaFilip/ruian-autocomplete).

This PHP package can act as the optional proxy backend it calls.
`Simerda\Ruian\Suggest\SuggestHandler` turns a query into flat, JSON-serializable
`Suggestion` objects and stays out of the HTTP layer (no `echo`, no headers), so
it drops into any framework or plain PHP. Queries shorter than two characters
return an empty list without hitting the network.

```php
use Simerda\Ruian\RuianClient;
use Simerda\Ruian\Suggest\SuggestHandler;

$handler = new SuggestHandler(new RuianClient());

// Plain PHP endpoint (see examples/suggest.php):
header('Content-Type: application/json; charset=utf-8');
echo json_encode($handler->suggest($_GET['q'] ?? '', (int) ($_GET['limit'] ?? 10)));
```

In a framework return the array straight from a controller (Laravel
`response()->json($handler->suggest(...))`, Symfony `JsonResponse`, Slim, ...) —
`Suggestion` implements `JsonSerializable`.

`GET ?q=<query>&limit=<n>` returns a JSON array; each item is one suggestion:

```json
{
  "code": 25958895,
  "label": "Jankovcova 1522/53, Holešovice, 17000 Praha 7",
  "streetCode": 449423,
  "street": "Jankovcova",
  "houseNumber": 1522,
  "orientationNumber": "53",
  "zip": "170 00",
  "municipality": "Praha 7",
  "municipalityPart": "Holešovice",
  "lat": 50.10849611774761,
  "lng": 14.452959927874597
}
```

`label` (the ČÚZK `adresa` line) is always reliable; the broken-out
`street`/`municipality`/`municipalityPart` are parsed best-effort and may be `null`.

## License

MIT © Filip Šimerda
