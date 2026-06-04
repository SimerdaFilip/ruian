<?php

declare(strict_types=1);

/**
 * Minimal JSON proxy endpoint for an address autocomplete.
 *
 *   GET suggest.php?q=Jankovcova&limit=10   ->  [{ code, label, lat, lng, ... }, ...]
 *
 * This is the optional backend the separate frontend package
 * `@simerda/ruian-autocomplete` can call: it wires RuianClient + SuggestHandler
 * and emits the suggestion list as JSON. It hits the live public ČÚZK ArcGIS
 * service over the network; there is no offline mode here on purpose, the
 * package's tests cover the mapping with a stubbed HTTP client.
 */

require __DIR__ . '/../vendor/autoload.php';

use Simerda\Ruian\RuianClient;
use Simerda\Ruian\Suggest\SuggestHandler;

$query = isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '';
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int) $_GET['limit'] : 10;

$handler = new SuggestHandler(new RuianClient());

header('Content-Type: application/json; charset=utf-8');

try {
    echo json_encode($handler->suggest($query, $limit), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
