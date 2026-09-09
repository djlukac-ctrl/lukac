<?php
require_once __DIR__ . '/../src/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$availability = [];
foreach (db()->query('SELECT year, month, status FROM availability ORDER BY year, month')->fetchAll() as $row) {
    $availability[(string)$row['year']][(string)$row['month']] = $row['status'];
}

echo json_encode([
    'content' => site_content(),
    'availability' => $availability,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
