<?php
// Usage: php s48/bin/set-password.php            (prompts)
//        php s48/bin/set-password.php 'secret'   (non-interactive; lands in shell history)
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../inc/bootstrap.php';

$password = $argv[1] ?? null;
if ($password === null) {
    fwrite(STDOUT, 'New operator password: ');
    if (DIRECTORY_SEPARATOR === '/') {
        system('stty -echo');
    }
    $password = rtrim((string) fgets(STDIN), "\r\n");
    if (DIRECTORY_SEPARATOR === '/') {
        system('stty echo');
    }
    fwrite(STDOUT, "\n");
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}
$file = s48_data_dir() . '/admin.json';
s48_write_atomic($file, s48_json_encode(['hash' => password_hash($password, PASSWORD_DEFAULT), 'set_at' => date('c')]));
@chmod($file, 0640);
fwrite(STDOUT, "Saved operator password hash to $file\n");
