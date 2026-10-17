<?php
// Local dev only: php -S localhost:8048 -t s48 s48/bin/dev-router.php
// Mimics the .htaccess rules that PHP's built-in server ignores.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
if (preg_match('#^/(data|inc|bin)(/|$)#', $path)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}
return false;
