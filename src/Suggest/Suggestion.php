<?php

declare(strict_types=1);

namespace Simerda\Ruian\Suggest;

use JsonSerializable;
use Simerda\Ruian\AddressPoint;

/**
 * A flat, JSON-serializable view of an AddressPoint tailored for an autocomplete
 * dropdown: a stable `code`, a display `label` (the authoritative `adresa`
 * string), and the raw parts a caller may want to store alongside the selection
 * (street code, GPS, ZIP, ...).
 *
 * The broken-out street / municipality / municipalityPart are parsed best-effort
 * from the address line, so they stay nullable; `label` is always reliable.
 */
final class Suggestion implements JsonSerializable
{
    public function __construct(
        public readonly int $code,
        public readonly string $label,
        public readonly ?int $streetCode,
        public readonly ?string $street,
        public readonly ?int $houseNumber,
        public readonly ?string $orientationNumber,
        public readonly ?string $zip,
        public readonly ?string $municipality,
        public readonly ?string $municipalityPart,
        public readonly ?float $lat,
        public readonly ?float $lng,
    ) {
    }

    public static function fromAddressPoint(AddressPoint $point): self
    {
        return new self(
            code: $point->code,
            label: $point->formatted,
            streetCode: $point->streetCode,
            street: $point->street,
            houseNumber: $point->houseNumber,
            orientationNumber: $point->orientationNumber,
            zip: $point->zip,
            municipality: $point->municipality,
            municipalityPart: $point->municipalityPart,
            lat: $point->latitude,
            lng: $point->longitude,
        );
    }

    /**
     * @return array{
     *     code: int,
     *     label: string,
     *     streetCode: ?int,
     *     street: ?string,
     *     houseNumber: ?int,
     *     orientationNumber: ?string,
     *     zip: ?string,
     *     municipality: ?string,
     *     municipalityPart: ?string,
     *     lat: ?float,
     *     lng: ?float
     * }
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'label' => $this->label,
            'streetCode' => $this->streetCode,
            'street' => $this->street,
            'houseNumber' => $this->houseNumber,
            'orientationNumber' => $this->orientationNumber,
            'zip' => $this->zip,
            'municipality' => $this->municipality,
            'municipalityPart' => $this->municipalityPart,
            'lat' => $this->lat,
            'lng' => $this->lng,
        ];
    }

    /**
     * @return array{
     *     code: int,
     *     label: string,
     *     streetCode: ?int,
     *     street: ?string,
     *     houseNumber: ?int,
     *     orientationNumber: ?string,
     *     zip: ?string,
     *     municipality: ?string,
     *     municipalityPart: ?string,
     *     lat: ?float,
     *     lng: ?float
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
