<?php

declare(strict_types=1);

namespace Simerda\Ruian\Tests;

use PHPUnit\Framework\TestCase;
use Simerda\Ruian\AddressPoint;

final class AddressPointTest extends TestCase
{
    public function testMapsTheRealAdresniMistoFields(): void
    {
        $point = AddressPoint::fromFeature([
            'attributes' => [
                'kod' => 25958895,
                'ulice' => 449423,
                'cislodomovni' => 1522,
                'cisloorientacni' => 53,
                'cisloorientacnipismeno' => null,
                'psc' => 17000,
                'adresa' => 'Jankovcova 1522/53, Holešovice, 17000 Praha 7',
            ],
            'geometry' => ['x' => 14.452959927874597, 'y' => 50.10849611774761],
        ]);

        self::assertSame(25958895, $point->code);
        self::assertSame(449423, $point->streetCode);
        self::assertSame(1522, $point->houseNumber);
        self::assertSame('53', $point->orientationNumber);
        self::assertSame('170 00', $point->zip);
        self::assertEqualsWithDelta(50.1085, $point->latitude, 0.0001);
        self::assertEqualsWithDelta(14.4530, $point->longitude, 0.0001);
        self::assertSame('Jankovcova 1522/53, Holešovice, 17000 Praha 7', $point->formatted);

        // Best-effort parse of the composed `adresa` line.
        self::assertSame('Jankovcova', $point->street);
        self::assertSame('Praha 7', $point->municipality);
        self::assertSame('Holešovice', $point->municipalityPart);
    }

    public function testOrientationNumberComposesTrailingLetter(): void
    {
        $point = AddressPoint::fromFeature([
            'attributes' => [
                'kod' => 1,
                'cisloorientacni' => 53,
                'cisloorientacnipismeno' => 'a',
            ],
        ]);

        self::assertSame('53a', $point->orientationNumber);
    }

    public function testMissingAttributesBecomeNull(): void
    {
        $point = AddressPoint::fromFeature([
            'attributes' => ['kod' => 12345678],
        ]);

        self::assertSame(12345678, $point->code);
        self::assertNull($point->streetCode);
        self::assertNull($point->street);
        self::assertNull($point->houseNumber);
        self::assertNull($point->orientationNumber);
        self::assertNull($point->zip);
        self::assertNull($point->municipality);
        self::assertNull($point->municipalityPart);
        self::assertNull($point->latitude);
        self::assertNull($point->longitude);
        self::assertSame('', $point->formatted);
    }

    public function testEmptyFeatureYieldsZeroCode(): void
    {
        $point = AddressPoint::fromFeature([]);

        self::assertSame(0, $point->code);
        self::assertSame('', $point->formatted);
        self::assertNull($point->street);
    }

    public function testTwoSegmentAddressLeavesMunicipalityPartNull(): void
    {
        $point = AddressPoint::fromFeature([
            'attributes' => [
                'kod' => 1,
                'adresa' => 'Hlavní 10, 60200 Brno',
            ],
        ]);

        self::assertSame('Hlavní', $point->street);
        self::assertSame('Brno', $point->municipality);
        self::assertNull($point->municipalityPart);
    }

    public function testZipIsFormattedWithSpace(): void
    {
        $point = AddressPoint::fromFeature([
            'attributes' => ['kod' => 1, 'psc' => 60200],
        ]);

        self::assertSame('602 00', $point->zip);
    }
}
