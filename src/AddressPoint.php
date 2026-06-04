<?php

declare(strict_types=1);

namespace Simerda\Ruian;

final class AddressPoint
{
    public function __construct(
        public readonly int $code,
        public readonly ?int $streetCode,
        public readonly ?string $street,
        public readonly ?int $houseNumber,
        public readonly ?string $orientationNumber,
        public readonly ?string $zip,
        public readonly ?string $municipality,
        public readonly ?string $municipalityPart,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly string $formatted,
    ) {
    }

    /**
     * Map a single ArcGIS feature (attributes + geometry) onto the DTO.
     *
     * The AdresniMisto layer does not expose the street, municipality and
     * municipality part as separate text attributes — it only carries a numeric
     * street code (`ulice`) and one already-composed human-readable `adresa`
     * string. `adresa` is therefore the authoritative display value; the broken
     * out `street` / `municipality` / `municipalityPart` are parsed back out of
     * it on a best-effort basis and stay null whenever the shape is ambiguous.
     *
     * @param array{attributes?: array<string, mixed>, geometry?: array<string, mixed>} $feature
     */
    public static function fromFeature(array $feature): self
    {
        $attributes = is_array($feature['attributes'] ?? null) ? $feature['attributes'] : [];
        $geometry = is_array($feature['geometry'] ?? null) ? $feature['geometry'] : [];

        $code = self::int($attributes, 'kod') ?? 0;
        $streetCode = self::int($attributes, 'ulice');
        $houseNumber = self::int($attributes, 'cislodomovni');
        $orientationNumber = self::orientationNumber($attributes);
        $zip = self::formatZip(self::int($attributes, 'psc'));
        $formatted = self::string($attributes, 'adresa') ?? '';

        $parsed = self::parseAddress($formatted);

        return new self(
            code: $code,
            streetCode: $streetCode,
            street: $parsed['street'],
            houseNumber: $houseNumber,
            orientationNumber: $orientationNumber,
            zip: $zip,
            municipality: $parsed['municipality'],
            municipalityPart: $parsed['municipalityPart'],
            latitude: self::float($geometry, 'y'),
            longitude: self::float($geometry, 'x'),
            formatted: $formatted,
        );
    }

    /**
     * Compose the orientation number from its numeric part and optional trailing
     * letter, e.g. 53 + "a" -> "53a". Returns null when no number is present.
     *
     * @param array<string, mixed> $attributes
     */
    private static function orientationNumber(array $attributes): ?string
    {
        $number = self::int($attributes, 'cisloorientacni');
        $letter = self::string($attributes, 'cisloorientacnipismeno');

        if ($number === null) {
            // A bare letter without a number is meaningless, so drop it too.
            return null;
        }

        return (string) $number . ($letter ?? '');
    }

    /**
     * Best-effort split of the composed `adresa` string into its named parts.
     *
     * The service formats the line as "<street numbers>, <part?>, <psc city>",
     * e.g. "Jankovcova 1522/53, Holešovice, 17000 Praha 7". The first segment is
     * the street with its house/orientation numbers stripped off the end, the
     * last segment after the PSČ digits is the city, and a middle segment (when
     * present) is the municipality part. Anything that does not match this shape
     * is left null rather than guessed.
     *
     * @return array{street: ?string, municipality: ?string, municipalityPart: ?string}
     */
    private static function parseAddress(string $address): array
    {
        $none = ['street' => null, 'municipality' => null, 'municipalityPart' => null];

        $address = trim($address);
        if ($address === '') {
            return $none;
        }

        $segments = array_map('trim', explode(',', $address));
        $segments = array_values(array_filter($segments, static fn (string $s): bool => $s !== ''));

        if ($segments === []) {
            return $none;
        }

        // Street is the first segment with the trailing house/orientation number
        // (e.g. "1522/53" or "53a") removed; if nothing is left it stays null.
        $street = trim((string) preg_replace('/\s+\d+\S*$/u', '', $segments[0]));
        $street = $street === '' ? null : $street;

        // City is the last segment after the leading PSČ digits, if any.
        $last = (string) end($segments);
        $city = trim((string) preg_replace('/^\d{5}\s*/u', '', $last));
        $municipality = $city === '' ? null : $city;

        // With exactly three segments the middle one is the municipality part;
        // the array_filter above already dropped empty segments.
        $municipalityPart = count($segments) === 3 ? $segments[1] : null;

        return [
            'street' => $street,
            'municipality' => $municipality,
            'municipalityPart' => $municipalityPart,
        ];
    }

    /**
     * Czech postal codes are stored as a 5-digit integer and printed as "NNN NN".
     */
    private static function formatZip(?int $psc): ?string
    {
        if ($psc === null) {
            return null;
        }

        $digits = (string) $psc;

        return strlen($digits) === 5
            ? substr($digits, 0, 3) . ' ' . substr($digits, 3)
            : $digits;
    }

    /**
     * @param array<string, mixed> $source
     */
    private static function string(array $source, string $key): ?string
    {
        $value = $source[$key] ?? null;
        if ($value === null || !is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $source
     */
    private static function int(array $source, string $key): ?int
    {
        $value = $source[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param array<string, mixed> $source
     */
    private static function float(array $source, string $key): ?float
    {
        $value = $source[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }
}
