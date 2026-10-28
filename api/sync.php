<?php
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/services.php';
$u = require_login(null, true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST only.'], 405);
csrf_verify(true);
$raw = file_get_contents('php://input', false, null, 0, 262144);
$data = json_decode($raw ?: '', true);
if (!is_array($data) || !is_array($data['items'] ?? null)) json_out(['error' => 'Bad request.'], 400);
$results = [];
foreach (array_slice($data['items'], 0, 50) as $it) {
    $results[] = is_array($it) ? process_sync_item($u, $it) : ['ok' => false, 'error' => 'Invalid item.'];
}
json_out(['results' => $results]);
