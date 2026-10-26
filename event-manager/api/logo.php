<?php
// Serves a stored sponsor logo. Ids are random and change whenever a logo is replaced, so they cache well.
// SVGs are only ever shown through <img>; the headers below also stop one running if opened directly.
declare(strict_types=1);

require __DIR__ . '/../inc/showcase.php';

$l = s48_logo_file((string) ($_GET['id'] ?? ''));
if ($l === null) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Not found';
    exit;
}
$mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'svg' => 'image/svg+xml'][$l['type']];
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($l['file']));
header('Cache-Control: public, max-age=604800, immutable');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox");
readfile($l['file']);
