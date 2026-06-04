<?php

declare(strict_types=1);

namespace Simerda\Ruian\Tests;

use PHPUnit\Framework\TestCase;
use Simerda\Ruian\AddressPoint;
use Simerda\Ruian\Code\AdresniMistoCode;
use Simerda\Ruian\Exception\RuianException;
use Simerda\Ruian\RuianClient;

final class RuianClientTest extends TestCase
{
    public function testFindByAddressPointCodeMapsTheFirstFeature(): void
    {
        $http = new StubHttpClient(200, $this->fixture());
        $client = new RuianClient($http);

        $point = $client->findByAddressPointCode(21731491);

        self::assertInstanceOf(AddressPoint::class, $point);
        self::assertSame(21731491, $point->code);
        self::assertSame('Jankovcova', $point->street);
        self::assertSame(1522, $point->houseNumber);
        self::assertSame('53', $point->orientationNumber);
        self::assertSame('17000', $point->zip);
        self::assertSame('Praha', $point->municipality);
        self::assertSame('Holešovice', $point->municipalityPart);
        self::assertSame(50.1041, $point->latitude);
        self::assertSame(14.4453, $point->longitude);
        self::assertSame('Jankovcova 1522/53, 170 00 Praha', $point->formatted);
    }

    public function testFindByAddressPointCodeBuildsTheExpectedQuery(): void
    {
        $http = new StubHttpClient(200, $this->fixture());
        $client = new RuianClient($http);

        $client->findByAddressPointCode(21731491);

        self::assertNotNull($http->requestedUrl);
        // where=Kod=21731491 url-encodes the equals sign as %3D.
        self::assertStringContainsString('where=Kod%3D21731491', $http->requestedUrl);
        self::assertStringContainsString('f=json', $http->requestedUrl);
        self::assertStringContainsString('/1/query?', $http->requestedUrl);
    }

    public function testFindByAddressPointCodeAcceptsValueObject(): void
    {
        $http = new StubHttpClient(200, $this->fixture());
        $client = new RuianClient($http);

        $point = $client->findByAddressPointCode(AdresniMistoCode::fromInt(21731491));

        self::assertInstanceOf(AddressPoint::class, $point);
        self::assertNotNull($http->requestedUrl);
        self::assertStringContainsString('where=Kod%3D21731491', $http->requestedUrl);
    }

    public function testFindByAddressPointCodeReturnsNullWhenNoFeatures(): void
    {
        $client = new RuianClient(new StubHttpClient(200, '{"features": []}'));

        self::assertNull($client->findByAddressPointCode(1));
    }

    public function testSearchMapsTheFeatures(): void
    {
        $http = new StubHttpClient(200, $this->fixture());
        $client = new RuianClient($http);

        $points = $client->search('Jankovcova');

        self::assertCount(2, $points);
        self::assertSame(21731491, $points[0]->code);
        self::assertSame(21731505, $points[1]->code);
    }

    public function testSearchPassesResultRecordCountAndCaps(): void
    {
        $http = new StubHttpClient(200, $this->fixture());
        $client = new RuianClient($http);

        $points = $client->search('Jankovcova', 1);

        self::assertCount(1, $points);
        self::assertNotNull($http->requestedUrl);
        self::assertStringContainsString('resultRecordCount=1', $http->requestedUrl);
    }

    public function testSearchEscapesSingleQuotes(): void
    {
        $http = new StubHttpClient(200, '{"features": []}');
        $client = new RuianClient($http);

        $client->search("Jankovcova' OR '1'='1");

        self::assertNotNull($http->requestedUrl);
        $decoded = urldecode($http->requestedUrl);

        // A lone quote that could close the LIKE literal must not survive; it is
        // doubled into the SQL escape sequence instead.
        self::assertStringNotContainsString("'1'='1", $decoded);
        self::assertStringContainsString("''", $decoded);
    }

    public function testArcGisErrorBodyThrows(): void
    {
        $body = '{"error":{"code":400,"message":"Unable to perform query."}}';
        $client = new RuianClient(new StubHttpClient(200, $body));

        $this->expectException(RuianException::class);
        $this->expectExceptionMessage('Unable to perform query.');

        $client->findByAddressPointCode(1);
    }

    public function testNonSuccessStatusThrows(): void
    {
        $client = new RuianClient(new StubHttpClient(500, 'Internal Server Error'));

        $this->expectException(RuianException::class);

        $client->findByAddressPointCode(1);
    }

    public function testInvalidJsonThrows(): void
    {
        $client = new RuianClient(new StubHttpClient(200, '{ not json'));

        $this->expectException(RuianException::class);

        $client->findByAddressPointCode(1);
    }

    public function testCustomBaseUrlAndLayerAreUsed(): void
    {
        $http = new StubHttpClient(200, '{"features": []}');
        $client = new RuianClient($http, 'https://example.test/MapServer/', 7);

        $client->findByAddressPointCode(1);

        self::assertNotNull($http->requestedUrl);
        self::assertStringStartsWith('https://example.test/MapServer/7/query?', $http->requestedUrl);
    }

    private function fixture(): string
    {
        $json = file_get_contents(__DIR__ . '/fixtures/address_point.json');
        self::assertIsString($json);

        return $json;
    }
}
