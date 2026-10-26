<?php
// Sponsor API tests: logo checks, add/update/move/delete, file cleanup, limits, event file.
// Run through tests/run.sh, which passes: base URL, data folder, operator password.
declare(strict_types=1);

[, $base, $dataDir, $password] = $argv;
$cookie = '';
$csrf = '';
$passed = 0;
$failed = 0;

function http(string $method, string $path, ?string $body = null, array $headers = []): array
{
    global $base, $cookie;
    $h = $headers;
    if ($cookie !== '') {
        $h[] = 'Cookie: ' . $cookie;
    }
    $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $h), 'content' => $body ?? '', 'ignore_errors' => true, 'follow_location' => 0]]);
    $out = file_get_contents($base . $path, false, $ctx);
    $status = 0;
    foreach ($http_response_header as $line) {
        if (preg_match('#^HTTP/\S+ (\d+)#', $line, $m)) {
            $status = (int) $m[1];
        }
        if (preg_match('/^Set-Cookie: ([^;]+)/i', $line, $m)) {
            $cookie = $m[1];
        }
    }
    return [$status, (string) $out, $http_response_header];
}

function post(string $path, array $obj): array
{
    global $csrf;
    [$st, $body] = http('POST', $path, json_encode($obj), ['Content-Type: application/json', 'X-CSRF-Token: ' . $csrf]);
    return [$st, json_decode($body, true)];
}

function check(string $name, bool $ok, $detail = null): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "PASS $name\n";
    } else {
        $failed++;
        echo "FAIL $name " . json_encode($detail) . "\n";
    }
}

function data_url(string $mime, string $bin): string
{
    return 'data:' . $mime . ';base64,' . base64_encode($bin);
}

function png(int $w = 300, int $h = 120): string
{
    $im = imagecreatetruecolor($w, $h);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagefilledellipse($im, intdiv($w, 2), intdiv($h, 2), $w - 20, $h - 20, imagecolorallocate($im, 238, 81, 43));
    ob_start();
    imagepng($im);
    return (string) ob_get_clean();
}

function jpg(): string
{
    $im = imagecreatetruecolor(200, 200);
    imagefill($im, 0, 0, imagecolorallocate($im, 30, 100, 160));
    ob_start();
    imagejpeg($im);
    return (string) ob_get_clean();
}

function sponsors(): array
{
    return json_decode(http('GET', 'api/showcase.php')[1], true)['sponsors'];
}

function logo_files(): array
{
    global $dataDir;
    $f = glob($dataDir . '/logos/*.*') ?: [];
    sort($f);
    return $f;
}

function by_name(array $list, string $name): array
{
    foreach ($list as $s) {
        if ($s['name'] === $name) {
            return $s;
        }
    }
    return [];
}

// Log in like the console does: the login form's CSRF token, then the console's.
[, $page] = http('GET', 'admin/');
preg_match('/name="csrf" value="([a-f0-9]+)"/', $page, $m);
http('POST', 'admin/', http_build_query(['csrf' => $m[1], 'password' => $password]), ['Content-Type: application/x-www-form-urlencoded']);
[, $page] = http('GET', 'admin/');
if (!preg_match('/S48\.csrf = "([a-f0-9]+)"/', $page, $m)) {
    fwrite(STDERR, "login failed\n");
    exit(1);
}
$csrf = $m[1];
$api = 'api/showcase-admin.php';

// --- adding, and what the public API shows
[$st, $r] = post($api, ['action' => 'sponsor_add', 'fields' => ['name' => 'Acme PNG', 'tier' => 'Gold'], 'logo' => data_url('image/png', png())]);
check('add with a PNG logo', $st === 200 && count($r['sponsors']) === 1, $r);
[$st] = post($api, ['action' => 'sponsor_add', 'fields' => ['name' => 'Beta JPG', 'tier' => 'Gold', 'dark' => true], 'logo' => data_url('image/jpeg', jpg())]);
check('add with a JPEG logo and a dark card', $st === 200 && by_name(sponsors(), 'Beta JPG')['dark'] === true);
[$st] = post($api, ['action' => 'sponsor_add', 'fields' => ['name' => 'No Logo']]);
check('add without a logo', $st === 200 && by_name(sponsors(), 'No Logo')['logo_url'] === null && by_name(sponsors(), 'No Logo')['dark'] === false);
check('3 sponsors, 2 logo files', count(sponsors()) === 3 && count(logo_files()) === 2, logo_files());

$types = [];
foreach (sponsors() as $s) {
    if ($s['logo_url']) {
        [$st, , $hdr] = http('GET', ltrim($s['logo_url'], '/'));
        $types[] = $st . ' ' . implode(',', preg_grep('/^(Content-Type|X-Content-Type-Options):/i', $hdr));
    }
}
check('logos are served with their type and nosniff', $types === ['200 Content-Type: image/png,X-Content-Type-Options: nosniff', '200 Content-Type: image/jpeg,X-Content-Type-Options: nosniff'], $types);
[$st] = http('GET', 'api/logo.php?id=../../inc/config');
check('logo.php refuses a bad id', $st === 404);

// --- refusals leave nothing behind
$bad = [
    'an SVG logo' => data_url('image/svg+xml', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><rect width="1" height="1"/></svg>'),
    'a GIF' => data_url('image/gif', 'GIF89a'),
    'text called a PNG' => data_url('image/png', 'not a png at all'),
    'a JPEG called a PNG' => data_url('image/png', jpg()),
    'a PNG over 2000 px' => data_url('image/png', png(2100, 50)),
    'a logo over the size limit' => data_url('image/png', "\x89PNG\r\n\x1a\n" . str_repeat('x', 700000)),
    'a non-data URL' => 'https://example.com/logo.png',
];
foreach ($bad as $what => $logo) {
    [$st, $r] = post($api, ['action' => 'sponsor_add', 'fields' => ['name' => 'Bad'], 'logo' => $logo]);
    check("refuse $what", $st === 400 && $r['ok'] === false, [$st, $r]);
}
[$st] = post($api, ['action' => 'sponsor_add', 'fields' => ['name' => '   '], 'logo' => data_url('image/png', png())]);
check('refuse an empty name, without keeping its logo', $st === 400 && count(logo_files()) === 2, logo_files());
check('still 3 sponsors', count(sponsors()) === 3);

// --- update, move, delete
$id = array_column(sponsors(), 'id', 'name');
[$st] = post($api, ['action' => 'sponsor_update', 'id' => $id['No Logo'], 'fields' => ['name' => 'Now Logo', 'tier' => 'Silver'], 'logo' => data_url('image/png', png(80, 80))]);
check('update adds a logo', $st === 200 && by_name(sponsors(), 'Now Logo')['logo_url'] !== null && count(logo_files()) === 3);
$before = by_name(sponsors(), 'Acme PNG')['logo_url'];
[$st] = post($api, ['action' => 'sponsor_update', 'id' => $id['Acme PNG'], 'fields' => ['name' => 'Acme', 'tier' => 'Gold'], 'logo' => data_url('image/png', png(90, 90))]);
check('update replaces a logo and removes the old file', $st === 200 && by_name(sponsors(), 'Acme')['logo_url'] !== $before && count(logo_files()) === 3);
[$st] = post($api, ['action' => 'sponsor_update', 'id' => $id['Beta JPG'], 'fields' => ['name' => 'Beta', 'tier' => 'Gold'], 'remove_logo' => true]);
check('remove_logo deletes the file and clears dark when unticked', $st === 200 && by_name(sponsors(), 'Beta')['logo_url'] === null && !by_name(sponsors(), 'Beta')['dark'] && count(logo_files()) === 2);
[$st] = post($api, ['action' => 'sponsor_update', 'id' => $id['Acme PNG'], 'fields' => ['name' => 'Acme', 'tier' => 'Platinum']]);
check('update without a logo keeps the logo', $st === 200 && by_name(sponsors(), 'Acme')['logo_url'] !== null && count(logo_files()) === 2);
$names = array_column(sponsors(), 'name');
[$st, $r] = post($api, ['action' => 'sponsor_move', 'id' => $id['No Logo'], 'dir' => -1]);
check('move up', array_column($r['sponsors'], 'name') === [$names[0], $names[2], $names[1]], array_column($r['sponsors'], 'name'));
[$st, $r2] = post($api, ['action' => 'sponsor_move', 'id' => $r['sponsors'][0]['id'], 'dir' => -1]);
check('moving the first one up changes nothing', $st === 200 && array_column($r2['sponsors'], 'name') === array_column($r['sponsors'], 'name'));
[$st] = post($api, ['action' => 'sponsor_delete', 'id' => $id['Acme PNG']]);
check('delete removes its logo file', $st === 200 && count(sponsors()) === 2 && count(logo_files()) === 1);
[$st] = post($api, ['action' => 'sponsor_delete', 'id' => 'nope']);
check('deleting an unknown sponsor is a 404', $st === 404);
[$st] = http('POST', $api, json_encode(['action' => 'sponsor_delete', 'id' => $id['Beta JPG']]), ['Content-Type: application/json']);
check('changes need the CSRF token', $st === 403);

// --- leftover files from a request that died part-way are swept after the next sponsor change
$old = $dataDir . '/logos/' . str_repeat('a', 32) . '.png';
$recent = $dataDir . '/logos/' . str_repeat('b', 32) . '.png';
file_put_contents($old, png());
touch($old, time() - 3600);
file_put_contents($recent, png());
post($api, ['action' => 'sponsor_move', 'id' => $id['Beta JPG'], 'dir' => 1]);
check('old unreferenced logo swept, recent one kept', !is_file($old) && is_file($recent));
unlink($recent);

// --- limit
for ($i = count(sponsors()); $i < 41; $i++) {
    [$st, $r] = post($api, ['action' => 'sponsor_add', 'fields' => ['name' => 'S' . $i]]);
}
check('at most 40 sponsors', count(sponsors()) === 40 && $st === 400, $r);
foreach (sponsors() as $s) {
    if (preg_match('/^S\d+$/', $s['name'])) {
        post($api, ['action' => 'sponsor_delete', 'id' => $s['id']]);
    }
}

// --- event file: export, then load back
[$st, $raw] = http('GET', 'api/event.php');
$ev = json_decode($raw, true);
check('export lists sponsors with logo, tier and card color', $st === 200 && array_column($ev['sponsors'], 'name') === array_column(sponsors(), 'name')
    && count(array_filter(array_column($ev['sponsors'], 'logo'))) === 1 && array_key_exists('dark', $ev['sponsors'][0]), $ev['sponsors'] ?? null);
$ev['sponsors'][] = ['name' => 'From File', 'tier' => 'Gold', 'dark' => true, 'logo' => data_url('image/png', png(60, 60))];
$body = ['format' => $ev['format'], 'version' => $ev['version'], 'info' => $ev['info'], 'pitches' => [], 'people' => $ev['people'], 'sponsors' => []];
foreach ($ev['sponsors'] as $s) {
    $q = ['name' => $s['name'], 'tier' => $s['tier'], 'dark' => $s['dark']];
    if (!empty($s['logo'])) {
        [, $r] = post('api/event.php', ['action' => 'logo', 'logo' => $s['logo']]);
        $q['logo_ref'] = $r['id'];
    }
    $body['sponsors'][] = $q;
}
$filesBefore = logo_files();
[$st, $r] = post('api/event.php', ['action' => 'import', 'event' => $body]);
check('load replaces sponsors', $st === 200 && $r['sponsors'] === 3 && by_name(sponsors(), 'From File')['dark'] === true, $r);
check('load removes the logos it replaced', count(array_intersect($filesBefore, logo_files())) === 2 && count(logo_files()) === 2, logo_files());
$older = $body;
unset($older['sponsors']);
[$st, $r] = post('api/event.php', ['action' => 'import', 'event' => $older]);
check('a file from before sponsors keeps the current ones', $st === 200 && $r['sponsors'] === 3 && count(logo_files()) === 2, $r);
[$st, $r] = post('api/event.php', ['action' => 'import', 'event' => ['sponsors' => []] + $body]);
check('an empty sponsor list clears them and their logos', $st === 200 && $r['sponsors'] === 0 && logo_files() === [], [$r, logo_files()]);
[$st] = post('api/event.php', ['action' => 'import', 'event' => ['sponsors' => [['name' => 'X', 'logo_ref' => str_repeat('f', 32)]]] + $body]);
check('a logo that did not upload is refused', $st === 400);
[$st] = post('api/event.php', ['action' => 'import', 'event' => ['sponsors' => [['name' => 'X', 'logo_ref' => '../x']]] + $body]);
check('a bad logo reference is refused', $st === 400);
[$st] = post('api/event.php', ['action' => 'logo', 'logo' => data_url('image/svg+xml', '<svg xmlns="http://www.w3.org/2000/svg"/>')]);
check('event file logos are checked too', $st === 400);

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
