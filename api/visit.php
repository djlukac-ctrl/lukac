<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

try {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

    if ($ip === '') {
        echo json_encode(['ok' => true]);
        exit;
    }

    $keyFile = STORAGE_DIR . '/analytics.key';
    if (!is_file($keyFile)) {
        $key = bin2hex(random_bytes(32));
        file_put_contents($keyFile, $key, LOCK_EX);
        @chmod($keyFile, 0600);
    }

    $key = trim((string) file_get_contents($keyFile));
    if ($key === '') {
        throw new RuntimeException('Analytics key unavailable');
    }

    $today = date('Y-m-d');
    $visitorHash = hash_hmac('sha256', $today . '|' . $ip . '|' . $userAgent, $key);

    $stmt = db()->prepare('INSERT OR IGNORE INTO site_visitors(visit_date, visitor_hash) VALUES(:date, :hash)');
    $stmt->execute([
        ':date' => $today,
        ':hash' => $visitorHash,
    ]);

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(204);
}
