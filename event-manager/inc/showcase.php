<?php
/**
 * Event showcase: organizers, judges and mentors (with headshots) plus the event-info text
 * shown on the "Meet the..." slide loop. Stored in data/showcase.json; headshots in data/photos/.
 * Only operators change it, under an exclusive lock, written atomically like the voting state.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const S48_GROUPS = ['organizers', 'judges', 'mentors'];
const S48_MAX_PEOPLE = 60;
const S48_MAX_PHOTO_BYTES = 1500000;

function s48_default_showcase(): array
{
    return [
        'people' => ['organizers' => [], 'judges' => [], 'mentors' => []],
        'info' => [
            'header' => 'STARTUP 48 · EAU CLAIRE · ' . date('Y'),
            'wifi_name' => '',
            'wifi_pass' => '',
            'mentor_hours' => '',
            'announce_title' => '',
            'announce_body' => '',
            'seconds' => 5,
            'mentor_day' => 'auto', // auto | sat | sun | all
        ],
        'updated_at' => time(),
    ];
}

function s48_showcase(): array
{
    $s = s48_read_json(s48_data_dir() . '/showcase.json');
    return $s === null ? s48_default_showcase() : array_replace_recursive(s48_default_showcase(), $s);
}

/** Run $fn(&$showcase) under an exclusive lock and persist it. Throwing aborts without writing. */
function s48_update_showcase(callable $fn): array
{
    $dir = s48_data_dir();
    $lock = fopen($dir . '/showcase.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        throw new S48Error('Could not lock showcase data', 500);
    }
    try {
        $s = s48_showcase();
        $result = $fn($s);
        $s['updated_at'] = time();
        s48_write_atomic($dir . '/showcase.json', s48_json_encode($s));
        return [$s, $result];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function s48_photo_dir(): string
{
    return s48_mkdir(s48_data_dir() . '/photos');
}

function s48_photo_url(?string $id): ?string
{
    return $id ? s48_base_path() . 'api/photo.php?id=' . $id : null;
}

function s48_clean_text($v, int $max): string
{
    $v = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $v) ?? '';
    $v = trim(preg_replace('/\s+/u', ' ', $v) ?? '');
    return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
}

/** Validates and normalizes the editable fields of one person for a group. */
function s48_person_fields(string $group, array $in): array
{
    $p = [
        'name' => s48_clean_text($in['name'] ?? '', 80),
        'org' => s48_clean_text($in['org'] ?? '', 100),
    ];
    if ($p['name'] === '') {
        throw new S48Error('Name is required');
    }
    if ($group !== 'organizers') {
        $p['title'] = s48_clean_text($in['title'] ?? '', 80);
    }
    if ($group === 'mentors') {
        $p['skills'] = s48_clean_text($in['skills'] ?? '', 160);
        $p['sat'] = !empty($in['sat']);
        $p['sun'] = !empty($in['sun']);
    }
    if ($group === 'organizers') {
        $y = trim((string) ($in['first_year'] ?? ''));
        if ($y !== '' && (!ctype_digit($y) || (int) $y < 2000 || (int) $y > 2100)) {
            throw new S48Error('First Startup 48 must be a year like 2017');
        }
        $p['first_year'] = $y === '' ? null : (int) $y;
    }
    return $p;
}

function s48_info_fields(array $in, array $cur): array
{
    $day = (string) ($in['mentor_day'] ?? $cur['mentor_day']);
    $sec = (int) ($in['seconds'] ?? $cur['seconds']);
    return [
        'header' => s48_clean_text($in['header'] ?? $cur['header'], 80),
        'wifi_name' => s48_clean_text($in['wifi_name'] ?? $cur['wifi_name'], 64),
        'wifi_pass' => s48_clean_text($in['wifi_pass'] ?? $cur['wifi_pass'], 64),
        'mentor_hours' => s48_clean_text($in['mentor_hours'] ?? $cur['mentor_hours'], 80),
        'announce_title' => s48_clean_text($in['announce_title'] ?? $cur['announce_title'], 60),
        'announce_body' => s48_clean_text($in['announce_body'] ?? $cur['announce_body'], 300),
        'seconds' => max(3, min(15, $sec)),
        'mentor_day' => in_array($day, ['auto', 'sat', 'sun', 'all'], true) ? $day : 'auto',
    ];
}

/**
 * Accepts a data: URL of a JPEG (already cropped and resized in the browser), checks it really is
 * a small JPEG, re-encodes it when GD is available (drops any metadata), and stores it under a
 * fresh random id. Returns the id.
 */
function s48_save_photo(string $dataUrl): string
{
    if (!preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
        throw new S48Error('Photo must be a JPEG image');
    }
    $bin = base64_decode($m[1], true);
    if ($bin === false || strlen($bin) > S48_MAX_PHOTO_BYTES || substr($bin, 0, 3) !== "\xFF\xD8\xFF") {
        throw new S48Error('Photo is not a valid JPEG or is too large');
    }
    $info = @getimagesizefromstring($bin);
    if (!$info || $info[2] !== IMAGETYPE_JPEG || $info[0] > 1200 || $info[1] > 1200) {
        throw new S48Error('Photo must be a JPEG up to 1200×1200');
    }
    if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
        $img = @imagecreatefromstring($bin);
        if ($img !== false) {
            ob_start();
            imagejpeg($img, null, 85);
            $bin = (string) ob_get_clean();
            imagedestroy($img);
        }
    }
    $id = s48_new_id(16);
    s48_write_atomic(s48_photo_dir() . '/' . $id . '.jpg', $bin);
    return $id;
}

function s48_delete_photo(?string $id): void
{
    if ($id && preg_match('/^[a-f0-9]{32}$/', $id)) {
        @unlink(s48_photo_dir() . '/' . $id . '.jpg');
    }
}

/** What the public slide loop needs: no internal ids beyond what's displayed. */
function s48_public_showcase(array $s): array
{
    $out = ['people' => [], 'info' => $s['info'], 'updated_at' => $s['updated_at']];
    foreach (S48_GROUPS as $g) {
        $out['people'][$g] = [];
        foreach ($s['people'][$g] as $p) {
            $q = $p;
            unset($q['photo']);
            $q['photo_url'] = s48_photo_url($p['photo'] ?? null);
            $out['people'][$g][] = $q;
        }
    }
    return $out;
}
