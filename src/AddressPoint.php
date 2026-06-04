<?php

declare(strict_types=1);

namespace Simerda\Ruian;

final class AddressPoint
{
    public function __construct(
        public readonly int $code,
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
     * The layer's field names are not contractual, so every attribute is looked
     * up through a small list of aliases and missing values fall back to null.
     *
     * @param array{attributes?: array<string, mixed>, geometry?: array<string, mixed>} $feature
     */
    public static function fromFeature(array $feature): self
    {
        $attributes = is_array($feature['attributes'] ?? null) ? $feature['attributes'] : [];
        $geometry = is_array($feature['geometry'] ?? null) ? $feature['geometry'] : [];

        $code = self::int($attributes, ['Kod', 'KOD', 'kod', 'AdresniMistoKod']) ?? 0;
        $street = self::string($attributes, ['Ulice', 'ULICE', 'NazevUlice']);
        $municipality = self::string($attributes, ['Obec', 'OBEC', 'NazevObce']);
        $municipalityPart = self::string($attributes, ['CastObce', 'CASTOBCE', 'NazevCastiObce']);
        $houseNumber = self::int($attributes, ['CisloDomovni', 'CISLODOMOVNI', 'CD']);
        $orientationNumber = self::string($attributes, ['CisloOrientacni', 'CISLOORIENTACNI', 'CO']);
        $zip = self::string($attributes, ['PSC', 'Psc', 'psc']);

        return new self(
            code: $code,
            street: $street,
            houseNumber: $houseNumber,
            orientationNumber: $orientationNumber,
            zip: $zip,
            municipality: $municipality,
            municipalityPart: $municipalityPart,
            latitude: self::float($geometry, ['y', 'Y', 'latitude']),
            longitude: self::float($geometry, ['x', 'X', 'longitude']),
            formatted: self::compose($street, $houseNumber, $orientationNumber, $zip, $municipality),
        );
    }

    /**
     * Build the usual Czech one-line address: "Ulice 53/1522, 170 00 Praha".
     */
    private static function compose(
        ?string $street,
        ?int $houseNumber,
        ?string $orientationNumber,
        ?string $zip,
        ?string $municipality,
    ): string {
        $number = (string) ($houseNumber ?? '');
        if ($orientationNumber !== null && $orientationNumber !== '') {
            $number = $number === '' ? $orientationNumber : $number . '/' . $orientationNumber;
        }

        $line = trim(($street ?? '') . ' ' . $number);

        $place = trim(self::formatZip($zip) . ' ' . ($municipality ?? ''));

        return implode(', ', array_filter([$line, $place], static fn (string $part): bool => $part !== ''));
    }

    private static function formatZip(?string $zip): string
    {
        if ($zip === null) {
            return '';
        }

        $digits = preg_replace('/\s+/', '', $zip) ?? $zip;

        // Czech postal codes are printed as "NNN NN"; leave anything unexpected untouched.
        return preg_match('/^\d{5}$/', $digits) === 1
            ? substr($digits, 0, 3) . ' ' . substr($digits, 3)
            : $zip;
    }

    /**
     * @param array<string, mixed> $source
     * @param list<string> $keys
     */
    private static function string(array $source, array $keys): ?string
    {
        $value = self::pick($source, $keys);
        if ($value === null || !is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $source
     * @param list<string> $keys
     */
    private static function int(array $source, array $keys): ?int
    {
        $value = self::pick($source, $keys);

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param array<string, mixed> $source
     * @param list<string> $keys
     */
    private static function float(array $source, array $keys): ?float
    {
        $value = self::pick($source, $keys);

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param array<string, mixed> $source
     * @param list<string> $keys
     */
    private static function pick(array $source, array $keys): mixed
    {
        foreach ($keys as $key) {
            // ArcGIS sends nulls for empty optional fields; skip them so a later alias can win.
            if (array_key_exists($key, $source) && $source[$key] !== null) {
                return $source[$key];
            }
        }

        return null;
    }
}
