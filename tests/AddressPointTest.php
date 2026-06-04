<?php

declare(strict_types=1);

namespace Simerda\Ruian\Tests;

use PHPUnit\Framework\TestCase;
use Simerda\Ruian\AddressPoint;

final class AddressPointTest extends TestCase
{
    public function testMissingAttributesBecomeNull(): void
    {
        $point = AddressPoint::fromFeature([
            'attributes' => ['Kod' => 12345678],
        ]);

        self::assertSame(12345678, $point->code);
        self::assertNull($point->street);
        self::assertNull($point->houseNumber);
        self::assertNull($point->zip);
        self::assertNull($point->latitude);
        self::assertNull($point->longitude);
    }

    public function testEmptyFeatureYieldsZeroCode(): void
    {
        $point = AddressPoint::fromFeature([]);

        self::assertSame(0, $point->code);
        self::assertSame('', $point->formatted);
    }

    public function testAlternativeFieldNamesAreMapped(): void
    {
        $point = AddressPoint::fromFeature([
            'attributes' => [
                'AdresniMistoKod' => 999,
                'NazevUlice' => 'Hlavní',
                'NazevObce' => 'Brno',
            ],
        ]);

        self::assertSame(999, $point->code);
        self::assertSame('Hlavní', $point->street);
        self::assertSame('Brno', $point->municipality);
    }

    public function testZipIsFormattedWithSpace(): void
    {
        $point = AddressPoint::fromFeature([
            'attributes' => [
                'Kod' => 1,
                'Ulice' => 'Hlavní',
                'CisloDomovni' => 10,
                'PSC' => 60200,
                'Obec' => 'Brno',
            ],
        ]);

        self::assertSame('Hlavní 10, 602 00 Brno', $point->formatted);
    }

    public function testFormattedWithoutStreetUsesNumberOnly(): void
    {
        $point = AddressPoint::fromFeature([
            'attributes' => [
                'Kod' => 1,
                'CisloDomovni' => 7,
                'Obec' => 'Lhota',
            ],
        ]);

        self::assertSame('7, Lhota', $point->formatted);
    }
}
