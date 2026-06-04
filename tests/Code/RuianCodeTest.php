<?php

declare(strict_types=1);

namespace Simerda\Ruian\Tests\Code;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Simerda\Ruian\Code\AdresniMistoCode;
use Simerda\Ruian\Code\CastObceCode;
use Simerda\Ruian\Code\ObecCode;
use Simerda\Ruian\Code\StavebniObjektCode;
use Simerda\Ruian\Code\UliceCode;

final class RuianCodeTest extends TestCase
{
    public function testFromIntExposesValue(): void
    {
        $code = AdresniMistoCode::fromInt(21731491);

        self::assertSame(21731491, $code->value);
        self::assertSame('21731491', (string) $code);
    }

    public function testFromStringParsesDigits(): void
    {
        $code = ObecCode::fromString('554782');

        self::assertSame(554782, $code->value);
        self::assertSame('554782', (string) $code);
    }

    public function testEveryElementTypeConstructs(): void
    {
        self::assertSame(1, ObecCode::fromInt(1)->value);
        self::assertSame(2, CastObceCode::fromInt(2)->value);
        self::assertSame(3, UliceCode::fromInt(3)->value);
        self::assertSame(4, AdresniMistoCode::fromInt(4)->value);
        self::assertSame(5, StavebniObjektCode::fromInt(5)->value);
    }

    public function testZeroIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ObecCode::fromInt(0);
    }

    public function testNegativeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ObecCode::fromInt(-5);
    }

    public function testTooLongIntegerIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AdresniMistoCode::fromInt(1234567890);
    }

    public function testNonNumericStringIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UliceCode::fromString('12a');
    }

    public function testLeadingZeroStringIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UliceCode::fromString('007');
    }

    public function testTooLongStringIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AdresniMistoCode::fromString('1234567890');
    }

    public function testStringIsTrimmed(): void
    {
        self::assertSame(42, StavebniObjektCode::fromString(' 42 ')->value);
    }
}
