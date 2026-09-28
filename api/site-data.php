<?php
require_once __DIR__ . '/../src/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$reservedDates = db()
    ->query("SELECT event_date FROM reserved_dates WHERE event_date >= date('now','localtime') ORDER BY event_date")
    ->fetchAll(PDO::FETCH_COLUMN);

$reviews = db()->query('SELECT id, client_name, review_text, rating, review_date FROM reviews WHERE published = 1 ORDER BY display_order ASC, review_date DESC, id DESC LIMIT 3')->fetchAll();

echo json_encode([
    'content' => site_content(),
    'reserved_dates' => array_values($reservedDates),
    'reviews' => $reviews,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
