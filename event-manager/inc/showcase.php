<?php
/**
 * Event showcase: organizers, judges and mentors (with headshots), sponsors (with logos) plus the
 * event-info text shown on the "Meet the..." slide loop. Stored in data/showcase.json; headshots in
 * data/photos/, sponsor logos in data/logos/.
 * Only operators change it, under an exclusive lock, written atomically like the voting state.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const S48_GROUPS = ['organizers', 'judges', 'mentors'];
const S48_MAX_PEOPLE = 60;
const S48_MAX_PHOTO_BYTES = 1500000;
const S48_MAX_SPONSORS = 40;
const S48_MAX_LOGO_BYTES = 600000;   // PNG/JPEG after the browser has resized it
const S48_MAX_SVG_BYTES = 200000;
const S48_LOGO_TYPES = ['png', 'jpg', 'svg'];
// The pitch timer's presets: length in seconds, and when the Warning and Danger tones sound by default
// (seconds left). Each can also count up past 0:00 (on by default). Keep in step with PRESETS in timer/index.html.
const S48_TIMERS = [
    'opening' => ['label' => 'Opening Pitch', 'duration' => 60, 'warning' => 20, 'danger' => 10],
    'final' => ['label' => 'Final Pitch', 'duration' => 300, 'warning' => 60, 'danger' => 30],
    'qa' => ['label' => 'Q&A', 'duration' => 300, 'warning' => 60, 'danger' => 30],
];

function s48_default_showcase(): array
{
    return [
        'people' => ['organizers' => [], 'judges' => [], 'mentors' => []],
        'sponsors' => [],
        'info' => [
            'header' => 'STARTUP 48 · EAU CLAIRE · ' . date('Y'),
            'wifi_name' => '',
            'wifi_pass' => '',
            'mentor_hours' => '',
            'announce_title' => '',
            'announce_body' => '',
            'seconds' => 5,
            'mentor_day' => 'auto', // auto | sat | sun | all
            'timers' => s48_default_timers(),
            'participants' => 0,   // for team sizing; 0 = not set
            'team_min' => 4,
            'team_max' => 6,
            'phase_explore' => 5,  // team selection phase lengths, minutes
            'phase_choose' => 5,
            'phase_finalize' => 5,
        ],
        'updated_at' => time(),
    ];
}

function s48_default_timers(): array
{
    $t = [];
    foreach (S48_TIMERS as $k => $d) {
        $t[$k] = ['warning' => ['on' => true, 'at' => $d['warning']], 'danger' => ['on' => true, 'at' => $d['danger']], 'count_up' => true];
    }
    return $t;
}

/** Validates each timer's settings: tones on/off plus seconds left (inside the timer, Danger after Warning), and count-up. */
function s48_timer_fields($in, array $cur): array
{
    if (!is_array($in)) {
        return $cur;
    }
    $out = [];
    foreach (S48_TIMERS as $k => $d) {
        foreach (['warning', 'danger'] as $tone) {
            $t = $in[$k][$tone] ?? $cur[$k][$tone];
            $at = (int) ($t['at'] ?? 0);
            if ($at < 1 || $at >= $d['duration']) {
                throw new S48Error($d['label'] . ': ' . ucfirst($tone) . ' must be between 0:01 and ' . s48_mmss($d['duration'] - 1));
            }
            $out[$k][$tone] = ['on' => !empty($t['on']), 'at' => $at];
        }
        $out[$k]['count_up'] = (bool) ($in[$k]['count_up'] ?? $cur[$k]['count_up'] ?? true);
        if ($out[$k]['warning']['on'] && $out[$k]['danger']['on'] && $out[$k]['danger']['at'] >= $out[$k]['warning']['at']) {
            throw new S48Error($d['label'] . ': Danger must come after Warning (less time left)');
        }
    }
    return $out;
}

function s48_mmss(int $sec): string
{
    return intdiv($sec, 60) . ':' . str_pad((string) ($sec % 60), 2, '0', STR_PAD_LEFT);
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
    // Organizers can also mentor; they then carry the mentor fields too.
    $mentoring = $group === 'organizers' && !empty($in['mentoring']);
    if ($group !== 'organizers' || $mentoring) {
        $p['title'] = s48_clean_text($in['title'] ?? '', 80);
    }
    if ($group === 'organizers') {
        $p['mentoring'] = $mentoring;
    }
    if ($group === 'mentors' || $mentoring) {
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
        'timers' => s48_timer_fields($in['timers'] ?? null, $cur['timers'] ?? s48_default_timers()),
    ] + s48_team_fields($in, $cur);
}

function s48_team_fields(array $in, array $cur): array
{
    $p = (int) ($in['participants'] ?? $cur['participants'] ?? 0);
    $min = (int) ($in['team_min'] ?? $cur['team_min'] ?? 4);
    $max = (int) ($in['team_max'] ?? $cur['team_max'] ?? 6);
    if ($p < 0 || $p > 1000) {
        throw new S48Error('Number of participants must be between 0 and 1000');
    }
    if ($min < 1 || $max > 50 || $min > $max) {
        throw new S48Error('Ideal team size: enter a range like 4 to 6');
    }
    $out = ['participants' => $p, 'team_min' => $min, 'team_max' => $max];
    foreach (['explore' => 'Explore', 'choose' => 'Choose', 'finalize' => 'Finalize'] as $k => $label) {
        $m = (int) ($in['phase_' . $k] ?? $cur['phase_' . $k] ?? 5);
        if ($m < 1 || $m > 60) {
            throw new S48Error($label . ' phase must be 1 to 60 minutes');
        }
        $out['phase_' . $k] = $m;
    }
    return $out;
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

function s48_logo_dir(): string
{
    return s48_mkdir(s48_data_dir() . '/logos');
}

/** The stored logo file for an id, or null. Logos are stored as <id>.png, .jpg or .svg. */
function s48_logo_file(?string $id): ?array
{
    if (!$id || !preg_match('/^[a-f0-9]{32}$/', $id)) {
        return null;
    }
    foreach (S48_LOGO_TYPES as $t) {
        $f = s48_logo_dir() . '/' . $id . '.' . $t;
        if (is_file($f)) {
            return ['file' => $f, 'type' => $t];
        }
    }
    return null;
}

function s48_logo_url(?string $id): ?string
{
    return $id ? s48_base_path() . 'api/logo.php?id=' . $id : null;
}

function s48_delete_logo(?string $id): void
{
    $l = s48_logo_file($id);
    if ($l) {
        @unlink($l['file']);
    }
}

/** Sponsor name (required) and optional tier label, e.g. "Gold". */
function s48_sponsor_fields(array $in): array
{
    $p = ['name' => s48_clean_text($in['name'] ?? '', 80), 'tier' => s48_clean_text($in['tier'] ?? '', 40)];
    if ($p['name'] === '') {
        throw new S48Error('Name is required');
    }
    return $p;
}

/**
 * Accepts a data: URL of a PNG, JPEG or SVG logo, checks it really is that and small enough,
 * re-encodes raster images when GD is available (drops metadata), cleans SVGs, and stores it
 * under a fresh random id. Returns the id.
 */
function s48_save_logo(string $dataUrl): string
{
    if (!preg_match('#^data:image/(png|jpeg|svg\+xml);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
        throw new S48Error('Logo must be a PNG, JPG or SVG image');
    }
    $bin = base64_decode($m[2], true);
    if ($bin === false || $bin === '') {
        throw new S48Error('Logo could not be read');
    }
    if ($m[1] === 'svg+xml') {
        $bin = s48_clean_svg($bin);
        $ext = 'svg';
    } else {
        $isPng = $m[1] === 'png';
        $sig = $isPng ? "\x89PNG\r\n\x1a\n" : "\xFF\xD8\xFF";
        if (strlen($bin) > S48_MAX_LOGO_BYTES || strncmp($bin, $sig, strlen($sig)) !== 0) {
            throw new S48Error('Logo is not a valid ' . ($isPng ? 'PNG' : 'JPEG') . ' or is too large');
        }
        $info = @getimagesizefromstring($bin);
        if (!$info || $info[2] !== ($isPng ? IMAGETYPE_PNG : IMAGETYPE_JPEG) || $info[0] > 2000 || $info[1] > 2000 || $info[0] < 1 || $info[1] < 1) {
            throw new S48Error('Logo must be a PNG or JPEG up to 2000×2000');
        }
        if (function_exists('imagecreatefromstring') && function_exists('imagepng') && function_exists('imagejpeg')) {
            $img = @imagecreatefromstring($bin);
            if ($img !== false) {
                ob_start();
                if ($isPng) {
                    imagesavealpha($img, true);
                    imagepng($img, null, 6);
                } else {
                    imagejpeg($img, null, 90);
                }
                $out = (string) ob_get_clean();
                imagedestroy($img);
                if ($out !== '') {
                    $bin = $out;
                }
            }
        }
        $ext = $isPng ? 'png' : 'jpg';
    }
    $id = s48_new_id(16);
    s48_write_atomic(s48_logo_dir() . '/' . $id . '.' . $ext, $bin);
    return $id;
}

/**
 * Validates an uploaded SVG and returns a cleaned copy. Only drawing elements are allowed, no
 * scripts, event handlers, foreign objects or external references (an internal #id or an embedded
 * raster image is fine). It is also only ever shown through an <img>, which never runs scripts.
 */
function s48_clean_svg(string $svg): string
{
    if (strlen($svg) > S48_MAX_SVG_BYTES) {
        throw new S48Error('SVG logo is too large (limit ' . intdiv(S48_MAX_SVG_BYTES, 1000) . ' KB)');
    }
    if (!class_exists('DOMDocument')) {
        throw new S48Error('SVG logos are not supported on this server; use PNG or JPG');
    }
    if (stripos($svg, '<!DOCTYPE') !== false || stripos($svg, '<!ENTITY') !== false) {
        throw new S48Error('SVG logo uses a DOCTYPE or entities, which are not allowed. Re-export it as plain SVG');
    }
    $allowed = array_flip(['svg', 'g', 'defs', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'tspan',
        'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask', 'pattern', 'symbol', 'use', 'image', 'title', 'desc', 'style', 'metadata']);
    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $ok = $doc->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $ok ? $doc->documentElement : null;
    if (!$root || strtolower($root->localName) !== 'svg') {
        throw new S48Error('That file is not a valid SVG');
    }
    $bad = static function (string $why): void {
        throw new S48Error('SVG logo not accepted: ' . $why);
    };
    // Attributes are checked on every element, the root <svg> included.
    $attrs = static function (DOMElement $e) use ($bad): void {
        foreach (iterator_to_array($e->attributes) as $a) {
            $an = strtolower($a->nodeName);
            $v = trim($a->value);
            if (strncmp($an, 'on', 2) === 0) {
                $bad('it has an event handler (' . $a->nodeName . ')');
            }
            if (preg_match('/javascript:|expression\s*\(|@import/i', $v)) {
                $bad('it contains script-like content');
            }
            if ($an === 'href' || substr($an, -5) === ':href') {
                if ($v !== '' && $v[0] !== '#' && !preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $v)) {
                    $bad('it links to something external');
                }
            }
            if ($an !== 'd' && preg_match('/url\(\s*+[\'"]?+\s*+(?!#|data:image\/)/i', $v)) {
                $bad('it references something external');
            }
        }
    };
    $walk = static function (DOMNode $n) use (&$walk, $allowed, $bad, $attrs): void {
        foreach (iterator_to_array($n->childNodes) as $c) {
            if ($c instanceof DOMProcessingInstruction) {
                $c->parentNode->removeChild($c);
                continue;
            }
            if (!($c instanceof DOMElement)) {
                continue;
            }
            $name = strtolower($c->localName);
            if (!isset($allowed[$name])) {
                $bad('it contains <' . $c->localName . '>, which is not allowed');
            }
            if ($name === 'style' && preg_match('/@import|url\(\s*+[\'"]?+\s*+(?!#|data:image\/)|expression\s*\(|javascript:/i', $c->textContent)) {
                $bad('its style block loads something external');
            }
            $attrs($c);
            $walk($c);
        }
    };
    $attrs($root);
    $walk($root);
    if (!$root->hasAttribute('viewBox')) {
        $w = (float) $root->getAttribute('width');
        $h = (float) $root->getAttribute('height');
        if ($w <= 0 || $h <= 0) {
            $bad('it needs a viewBox (or a width and height)');
        }
        $root->setAttribute('viewBox', '0 0 ' . $w . ' ' . $h);
    }
    $out = $doc->saveXML($root);
    if ($out === false || $out === '') {
        throw new S48Error('That file is not a valid SVG');
    }
    return $out;
}

/** The logo as a data: URL (for the event file), or null. */
function s48_logo_data_url(?string $id): ?string
{
    $l = s48_logo_file($id);
    if (!$l) {
        return null;
    }
    $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'svg' => 'image/svg+xml'][$l['type']];
    return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($l['file']));
}

/** What the public slide loop needs: no internal ids beyond what's displayed. */
function s48_public_showcase(array $s): array
{
    $out = ['people' => [], 'sponsors' => [], 'info' => $s['info'], 'updated_at' => $s['updated_at']];
    foreach ($s['sponsors'] ?? [] as $sp) {
        $out['sponsors'][] = ['id' => $sp['id'], 'name' => $sp['name'], 'tier' => $sp['tier'] ?? '', 'logo_url' => s48_logo_url($sp['logo'] ?? null)];
    }
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
