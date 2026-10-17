<?php
/** Operator authentication: password login, PHP session, CSRF token, login throttle. */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const S48_LOGIN_MAX_FAILS = 5;
const S48_LOGIN_WINDOW = 600; // seconds

function s48_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name((string) s48_config()['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => s48_base_path(),
        'secure' => s48_is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

function s48_admin_hash(): string
{
    $file = s48_read_json(s48_data_dir() . '/admin.json');
    if (!empty($file['hash'])) {
        return (string) $file['hash'];
    }
    return (string) s48_config()['admin_password_hash'];
}

function s48_is_admin(): bool
{
    s48_session_start();
    return !empty($_SESSION['s48_admin']);
}

function s48_csrf_token(): string
{
    s48_session_start();
    if (empty($_SESSION['s48_csrf'])) {
        $_SESSION['s48_csrf'] = s48_new_id(16);
    }
    return $_SESSION['s48_csrf'];
}

function s48_check_csrf(?string $token): bool
{
    return is_string($token) && $token !== '' && hash_equals(s48_csrf_token(), $token);
}

function s48_throttle_file(): string
{
    return s48_mkdir(s48_data_dir() . '/throttle') . '/' . hash('sha256', s48_client_ip()) . '.json';
}

function s48_login_blocked(): bool
{
    $t = s48_read_json(s48_throttle_file());
    return $t !== null
        && (time() - (int) $t['first']) < S48_LOGIN_WINDOW
        && (int) $t['fails'] >= S48_LOGIN_MAX_FAILS;
}

/** @return string|null error message, or null on success */
function s48_login(string $password): ?string
{
    $hash = s48_admin_hash();
    if ($hash === '') {
        return 'No operator password is set. Run: php s48/bin/set-password.php';
    }
    if (s48_login_blocked()) {
        return 'Too many failed attempts. Try again in a few minutes.';
    }
    if (!password_verify($password, $hash)) {
        $file = s48_throttle_file();
        $t = s48_read_json($file);
        if ($t === null || (time() - (int) $t['first']) >= S48_LOGIN_WINDOW) {
            $t = ['first' => time(), 'fails' => 0];
        }
        $t['fails']++;
        s48_write_atomic($file, json_encode($t));
        usleep(300000);
        return 'Wrong password.';
    }
    @unlink(s48_throttle_file());
    session_regenerate_id(true);
    $_SESSION['s48_admin'] = true;
    $_SESSION['s48_csrf'] = s48_new_id(16);
    return null;
}

function s48_logout(): void
{
    s48_session_start();
    $_SESSION = [];
    session_regenerate_id(true);
}

/** For API endpoints: 401 unless logged in; POSTs also need the CSRF header. */
function s48_require_admin_api(): void
{
    if (!s48_is_admin()) {
        throw new S48Error('Not logged in', 401);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'
        && !s48_check_csrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        throw new S48Error('Bad CSRF token', 403);
    }
}
