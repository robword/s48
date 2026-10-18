<?php
// Serves a stored headshot. Ids are random and change whenever a photo is replaced, so they cache well.
declare(strict_types=1);

require __DIR__ . '/../inc/showcase.php';

$id = (string) ($_GET['id'] ?? '');
$file = preg_match('/^[a-f0-9]{32}$/', $id) ? s48_photo_dir() . '/' . $id . '.jpg' : '';
if ($file === '' || !is_file($file)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Not found';
    exit;
}
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=604800, immutable');
header('X-Content-Type-Options: nosniff');
readfile($file);
