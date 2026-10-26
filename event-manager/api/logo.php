<?php
// Serves a stored sponsor logo. Ids are random and change whenever a logo is replaced, so they cache well.
declare(strict_types=1);

require __DIR__ . '/../inc/showcase.php';

$l = s48_logo_file((string) ($_GET['id'] ?? ''));
if ($l === null) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Not found';
    exit;
}
header('Content-Type: ' . S48_LOGO_MIME[$l['type']]);
header('Content-Length: ' . filesize($l['file']));
header('Cache-Control: public, max-age=604800, immutable');
header('X-Content-Type-Options: nosniff');
readfile($l['file']);
