<?php

declare(strict_types=1);

namespace Simerda\Ruian\Tests\Suggest;

use PHPUnit\Framework\TestCase;
use Simerda\Ruian\RuianClient;
use Simerda\Ruian\Suggest\Suggestion;
use Simerda\Ruian\Suggest\SuggestHandler;
use Simerda\Ruian\Tests\StubHttpClient;

final class SuggestHandlerTest extends TestCase
{
    public function testSuggestMapsAddressPointsToSuggestions(): void
    {
        $handler = new SuggestHandler(new RuianClient(new StubHttpClient(200, $this->fixture())));

        $suggestions = $handler->suggest('Jankovcova');

        self::assertCount(2, $suggestions);
        self::assertContainsOnlyInstancesOf(Suggestion::class, $suggestions);

        $first = $suggestions[0];
        self::assertSame(25958895, $first->code);
        self::assertSame('Jankovcova 1522/53, Holešovice, 17000 Praha 7', $first->label);
        self::assertSame(449423, $first->streetCode);
        self::assertSame('Jankovcova', $first->street);
        self::assertSame(1522, $first->houseNumber);
        self::assertSame('53', $first->orientationNumber);
        self::assertSame('170 00', $first->zip);
        self::assertSame('Praha 7', $first->municipality);
        self::assertSame('Holešovice', $first->municipalityPart);
        self::assertEqualsWithDelta(50.1085, $first->lat, 0.0001);
        self::assertEqualsWithDelta(14.4530, $first->lng, 0.0001);

        self::assertSame(25958909, $suggestions[1]->code);
    }

    public function testSuggestReindexesAsAList(): void
    {
        $handler = new SuggestHandler(new RuianClient(new StubHttpClient(200, $this->fixture())));

        $suggestions = $handler->suggest('Jankovcova');

        self::assertSame([0, 1], array_keys($suggestions));
    }

    public function testQueryShorterThanTwoCharsReturnsEmptyAndSkipsHttp(): void
    {
        $http = new StubHttpClient(200, $this->fixture());
        $handler = new SuggestHandler(new RuianClient($http));

        self::assertSame([], $handler->suggest('J'));
        self::assertSame([], $handler->suggest('  a '));
        self::assertSame([], $handler->suggest(''));

        // No round trip should have been attempted for a too-short prefix.
        self::assertNull($http->requestedUrl);
    }

    public function testWhitespaceIsTrimmedBeforeSearching(): void
    {
        $http = new StubHttpClient(200, $this->fixture());
        $handler = new SuggestHandler(new RuianClient($http));

        $handler->suggest('  Jankovcova  ');

        self::assertNotNull($http->requestedUrl);
        // The trimmed needle is what reaches the service, not the padded input.
        self::assertStringContainsString('Jankovcova', urldecode($http->requestedUrl));
    }

    public function testLimitIsHonored(): void
    {
        $http = new StubHttpClient(200, $this->fixture());
        $handler = new SuggestHandler(new RuianClient($http));

        $suggestions = $handler->suggest('Jankovcova', 1);

        self::assertCount(1, $suggestions);
        self::assertNotNull($http->requestedUrl);
        self::assertStringContainsString('resultRecordCount=1', $http->requestedUrl);
    }

    public function testSuggestionJsonEncodesToTheDocumentedShape(): void
    {
        $handler = new SuggestHandler(new RuianClient(new StubHttpClient(200, $this->fixture())));

        $json = json_encode($handler->suggest('Jankovcova', 1), JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);
        self::assertSame([
            'code' => 25958895,
            'label' => 'Jankovcova 1522/53, Holešovice, 17000 Praha 7',
            'streetCode' => 449423,
            'street' => 'Jankovcova',
            'houseNumber' => 1522,
            'orientationNumber' => '53',
            'zip' => '170 00',
            'municipality' => 'Praha 7',
            'municipalityPart' => 'Holešovice',
            'lat' => 50.10849611774761,
            'lng' => 14.452959927874597,
        ], $decoded[0]);
    }

    private function fixture(): string
    {
        $json = file_get_contents(__DIR__ . '/../fixtures/address_point.json');
        self::assertIsString($json);

        return $json;
    }
}
