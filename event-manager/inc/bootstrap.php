<?php
/**
 * startup48 tools - shared server code.
 *
 * Flat-file storage, no database. Concurrency rules:
 *  - Each voter's ballot is its own file named by a hash of their device token,
 *    written via temp-file + rename (atomic on POSIX). Voters never share a file.
 *  - Shared state (pitch list, voting status) is only changed by the operator,
 *    under an exclusive flock, and also written via temp-file + rename so readers
 *    never see a half-written file.
 */
declare(strict_types=1);

const S48_MAX_VOTES = 3;
const S48_MAX_PITCHES = 100;
const S48_MAX_NAME_LEN = 80;
const S48_DENY_HTACCESS = "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n";

class S48Error extends RuntimeException
{
    public int $status;

    public function __construct(string $message, int $status = 400)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

function s48_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $cfg = [
        // e.g. 'https://example.com/s48/' - recommended in production so the QR
        // code never depends on the request's Host header.
        'public_base_url' => '',
        // Move outside the web root in production, e.g. '/home/user/s48-data'.
        'data_dir' => dirname(__DIR__) . '/data',
        'session_name' => 's48admin',
        'voter_cookie_days' => 7,
        // Optional alternative to bin/set-password.php.
        'admin_password_hash' => '',
    ];
    $file = __DIR__ . '/config.php';
    if (is_file($file)) {
        $override = require $file;
        if (is_array($override)) {
            $cfg = array_merge($cfg, $override);
        }
    }
    return $cfg;
}

/* ---------- filesystem ---------- */

function s48_mkdir(string $dir): string
{
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new S48Error('Server storage is not writable', 500);
    }
    return $dir;
}

function s48_data_dir(): string
{
    $dir = s48_mkdir(rtrim((string) s48_config()['data_dir'], '/'));
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', S48_DENY_HTACCESS);
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

function s48_write_atomic(string $path, string $contents): void
{
    $tmp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (@file_put_contents($tmp, $contents) === false) {
        throw new S48Error('Could not save data', 500);
    }
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        throw new S48Error('Could not save data', 500);
    }
}

function s48_read_json(string $path): ?array
{
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function s48_json_encode($data): string
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

function s48_new_id(int $bytes = 6): string
{
    return bin2hex(random_bytes($bytes));
}

/* ---------- state ---------- */

function s48_default_state(): array
{
    return [
        'status' => 'setup', // setup | open | closed | teams (team selection, after voting closed)
        'round' => s48_new_id(8),
        'pitches' => [],     // [{id, name}] in pitch order
        'advance' => [],     // ids of the ideas advancing to team selection
        'opened_at' => null,
        'closed_at' => null,
        'updated_at' => time(),
    ];
}

function s48_state(): array
{
    $state = s48_read_json(s48_data_dir() . '/state.json');
    if ($state === null) {
        [$state] = s48_update_state(static function (array &$s) {
        });
    }
    return $state;
}

/**
 * Run $fn(&$state) under an exclusive lock and persist the result.
 * Throwing inside $fn aborts without writing.
 * @return array [newState, fnResult]
 */
function s48_update_state(callable $fn): array
{
    $dir = s48_data_dir();
    $lock = fopen($dir . '/state.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        throw new S48Error('Could not lock state', 500);
    }
    try {
        $state = s48_read_json($dir . '/state.json') ?? s48_default_state();
        $result = $fn($state);
        $state['updated_at'] = time();
        s48_write_atomic($dir . '/state.json', s48_json_encode($state));
        return [$state, $result];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function s48_round_dir(string $round, string $sub = ''): string
{
    if (!preg_match('/^[a-f0-9]{8,32}$/', $round)) {
        throw new S48Error('Bad round id', 500);
    }
    $dir = s48_data_dir() . '/rounds/' . $round . ($sub !== '' ? '/' . $sub : '');
    return s48_mkdir($dir);
}

function s48_public_pitches(array $state): array
{
    $out = [];
    foreach ($state['pitches'] as $i => $p) {
        $out[] = ['id' => $p['id'], 'name' => $p['name'], 'n' => $i + 1];
    }
    return $out;
}

/** Ideas advancing to team selection, in pitch order (not by votes). */
function s48_advancing(array $state): array
{
    $ids = array_flip($state['advance'] ?? []);
    return array_values(array_filter(s48_public_pitches($state), static function ($p) use ($ids) {
        return isset($ids[$p['id']]);
    }));
}

/** Ideal-team-size check: can P participants split into n teams all within [min, max]? */
function s48_team_fit(int $participants, int $n, int $min, int $max): array
{
    return [
        'participants' => $participants,
        'teams' => $n,
        'per_team' => $n > 0 && $participants > 0 ? round($participants / $n, 1) : null,
        'ok' => $n > 0 && $participants > 0 && $n * $min <= $participants && $participants <= $n * $max,
        // Number of advancing ideas that would fit, e.g. 36 people at 4-6 -> 6..8.
        'pick_min' => $participants > 0 ? (int) ceil($participants / $max) : null,
        'pick_max' => $participants > 0 ? (int) floor($participants / $min) : null,
        'min' => $min,
        'max' => $max,
    ];
}

function s48_clean_name(string $name): string
{
    $name = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name) ?? '';
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($name, 0, S48_MAX_NAME_LEN);
    }
    return substr($name, 0, S48_MAX_NAME_LEN);
}

/* ---------- voters ---------- */

function s48_voter_key(string $token): string
{
    return hash('sha256', $token);
}

/** Returns the device token from the cookie, issuing one if $issue is true. */
function s48_voter_token(bool $issue): ?string
{
    $token = $_COOKIE['s48v'] ?? '';
    if (is_string($token) && preg_match('/^[a-f0-9]{32}$/', $token)) {
        return $token;
    }
    if (!$issue) {
        return null;
    }
    $token = s48_new_id(16);
    setcookie('s48v', $token, [
        'expires' => time() + 86400 * (int) s48_config()['voter_cookie_days'],
        'path' => s48_base_path(),
        'secure' => s48_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['s48v'] = $token;
    return $token;
}

function s48_record_open(string $round, string $token): void
{
    $f = s48_round_dir($round, 'opens') . '/' . s48_voter_key($token);
    if (!is_file($f)) {
        @touch($f);
    }
}

/**
 * Raw counts for a round, cached for ~2s so frequent polling stays cheap.
 * Only names/ranking are applied fresh from state on each call.
 */
function s48_round_counts(string $round): array
{
    $rd = s48_round_dir($round);
    $cacheFile = $rd . '/tally-cache.json';
    $cache = s48_read_json($cacheFile);
    if ($cache !== null && (microtime(true) - (float) $cache['at']) < 2.0) {
        return $cache;
    }
    $opens = 0;
    foreach (scandir(s48_round_dir($round, 'opens')) ?: [] as $f) {
        if (preg_match('/^[a-f0-9]{64}$/', $f)) {
            $opens++;
        }
    }
    $ballots = 0;
    $totals = [];
    $voters = [];
    $bdir = s48_round_dir($round, 'ballots');
    foreach (scandir($bdir) ?: [] as $f) {
        if (!preg_match('/^[a-f0-9]{64}\.json$/', $f)) {
            continue;
        }
        $b = s48_read_json($bdir . '/' . $f);
        if ($b === null || empty($b['votes'])) {
            continue;
        }
        $ballots++;
        foreach ($b['votes'] as $id => $n) {
            $totals[$id] = ($totals[$id] ?? 0) + (int) $n;
            $voters[$id] = ($voters[$id] ?? 0) + 1;
        }
    }
    $counts = [
        'at' => microtime(true),
        'opened' => max($opens, $ballots),
        'submitted' => $ballots,
        'totals' => $totals,
        'voters' => $voters,
    ];
    try {
        s48_write_atomic($cacheFile, json_encode($counts));
    } catch (S48Error $e) {
        // cache is optional
    }
    return $counts;
}

function s48_results(array $state): array
{
    $c = s48_round_counts($state['round']);
    $rows = [];
    foreach ($state['pitches'] as $i => $p) {
        $rows[] = [
            'id' => $p['id'],
            'name' => $p['name'],
            'n' => $i + 1,
            'votes' => (int) ($c['totals'][$p['id']] ?? 0),
            'voters' => (int) ($c['voters'][$p['id']] ?? 0),
        ];
    }
    usort($rows, static function ($a, $b) {
        return [$b['votes'], $b['voters'], $a['n']] <=> [$a['votes'], $a['voters'], $b['n']];
    });
    $rank = 0;
    $prev = null;
    foreach ($rows as $i => &$r) {
        if ($prev === null || $r['votes'] !== $prev) {
            $rank = $i + 1;
            $prev = $r['votes'];
        }
        $r['rank'] = $rank;
    }
    unset($r);
    $opened = (int) $c['opened'];
    $submitted = (int) $c['submitted'];
    return [
        'opened' => $opened,
        'submitted' => $submitted,
        'pending' => max(0, $opened - $submitted),
        'pct_submitted' => $opened > 0 ? round(100 * $submitted / $opened, 1) : 0,
        'ranking' => $rows,
    ];
}

/* ---------- http ---------- */

function s48_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/** URL path of the s48 root. Every PHP entry point lives one directory below it. */
function s48_base_path(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/x/x.php');
    return rtrim(dirname(dirname($script)), '/') . '/';
}

function s48_base_url(): string
{
    $cfg = trim((string) s48_config()['public_base_url']);
    if ($cfg !== '') {
        return rtrim($cfg, '/') . '/';
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return (s48_is_https() ? 'https' : 'http') . '://' . $host . s48_base_path();
}

function s48_vote_url(): string
{
    return s48_base_url() . 'vote/';
}

function s48_json_out($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Wraps an API handler so errors become JSON responses. */
function s48_api(callable $handler): void
{
    try {
        $handler();
    } catch (S48Error $e) {
        s48_json_out(['ok' => false, 'error' => $e->getMessage()], $e->status);
    } catch (Throwable $e) {
        error_log('s48: ' . $e);
        s48_json_out(['ok' => false, 'error' => 'Server error'], 500);
    }
}

function s48_json_body(int $maxBytes = 65536): array
{
    $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if ($raw === false || strlen($raw) > $maxBytes) {
        throw new S48Error('Request too large', 413);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new S48Error('Invalid JSON');
    }
    return $data;
}

function s48_client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function s48_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
