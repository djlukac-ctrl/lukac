<?php
require_once __DIR__ . '/../src/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$availability = [];
foreach (db()->query('SELECT year, month, status FROM availability ORDER BY year, month')->fetchAll() as $row) {
    $availability[(string)$row['year']][(string)$row['month']] = $row['status'];
}

$reviews = db()->query('SELECT id, client_name, review_text, rating, review_date FROM reviews WHERE published = 1 ORDER BY display_order ASC, review_date DESC, id DESC LIMIT 3')->fetchAll();

echo json_encode([
    'content' => site_content(),
    'availability' => $availability,
    'reviews' => $reviews,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
