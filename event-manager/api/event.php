<?php
/**
 * Event file: save and load the whole event setup (operator only).
 *   GET                         download: showcase people with headshots, event info, pitch list
 *   POST {action:"check"}       fails if a load can't happen now (voting open), before any upload
 *   POST {action:"photo"}       stage one headshot from a file being loaded; returns its id
 *   POST {action:"import"}      replace everything with the file's contents (photos by staged id)
 * Headshots and sponsor logos go up one per request so no single request is large. Voting state
 * and ballots are never part of the file, and loading is refused while voting is open.
 * A file without a "sponsors" list (made before sponsors existed) leaves the current sponsors alone.
 */
declare(strict_types=1);

require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/showcase.php';

const S48_EVENT_FORMAT = 'startup48-event';

/**
 * Turns the file's people/info/pitches (and sponsors, when present) into validated showcase data
 * and pitch names. Returns [showcase, pitches, photo ids, logo ids]; the logo list is null when
 * the file has no sponsors section.
 */
function s48_event_from_file(array $ev): array
{
    if (($ev['format'] ?? '') !== S48_EVENT_FORMAT || (int) ($ev['version'] ?? 0) !== 1) {
        throw new S48Error('Not a startup48 event file');
    }
    $show = s48_default_showcase();
    $show['info'] = s48_info_fields(is_array($ev['info'] ?? null) ? $ev['info'] : [], $show['info']);
    $photos = [];
    foreach (S48_GROUPS as $g) {
        $list = $ev['people'][$g] ?? [];
        if (!is_array($list) || count($list) > S48_MAX_PEOPLE) {
            throw new S48Error('Too many ' . $g);
        }
        foreach ($list as $p) {
            if (!is_array($p)) {
                throw new S48Error('Bad entry in ' . $g);
            }
            $photo = (string) ($p['photo_ref'] ?? '');
            if ($photo !== '' && !preg_match('/^[a-f0-9]{32}$/', $photo)) {
                throw new S48Error('Bad photo reference');
            }
            if ($photo !== '' && !is_file(s48_photo_dir() . '/' . $photo . '.jpg')) {
                throw new S48Error('A headshot did not upload; please try again');
            }
            $show['people'][$g][] = ['id' => s48_new_id(4)] + s48_person_fields($g, $p) + ['photo' => $photo ?: null];
            if ($photo !== '') {
                $photos[] = $photo;
            }
        }
    }
    $pitches = [];
    foreach (is_array($ev['pitches'] ?? null) ? $ev['pitches'] : [] as $name) {
        $name = s48_clean_name((string) $name);
        if ($name !== '') {
            $pitches[] = ['id' => s48_new_id(4), 'name' => $name];
        }
    }
    if (count($pitches) > S48_MAX_PITCHES) {
        throw new S48Error('Too many pitches');
    }
    $logos = null;
    if (array_key_exists('sponsors', $ev)) {
        $list = $ev['sponsors'];
        if (!is_array($list) || count($list) > S48_MAX_SPONSORS) {
            throw new S48Error('Too many sponsors');
        }
        $logos = [];
        foreach ($list as $sp) {
            if (!is_array($sp)) {
                throw new S48Error('Bad entry in sponsors');
            }
            $logo = (string) ($sp['logo_ref'] ?? '');
            if ($logo !== '' && !preg_match('/^[a-f0-9]{32}$/', $logo)) {
                throw new S48Error('Bad logo reference');
            }
            if ($logo !== '' && s48_logo_file($logo) === null) {
                throw new S48Error('A logo did not upload; please try again');
            }
            $show['sponsors'][] = ['id' => s48_new_id(4)] + s48_sponsor_fields($sp) + ['logo' => $logo ?: null];
            if ($logo !== '') {
                $logos[] = $logo;
            }
        }
    }
    return [$show, $pitches, $photos, $logos];
}

/** Deletes headshots nothing references, leaving recent ones alone (they may be mid-save). */
function s48_require_not_voting(array $state): void
{
    if ($state['status'] === 'open') {
        throw new S48Error('Close voting before loading an event file', 409);
    }
}

/** Like s48_sweep_photos, for sponsor logos (any file type). */
function s48_sweep_logos(array $keep): void
{
    $keep = array_flip($keep);
    foreach (glob(s48_logo_dir() . '/*.*') ?: [] as $f) {
        $id = pathinfo($f, PATHINFO_FILENAME);
        if (!isset($keep[$id]) && filemtime($f) < time() - 600) {
            @unlink($f);
        }
    }
}

function s48_sweep_photos(array $keep): void
{
    $keep = array_flip($keep);
    foreach (glob(s48_photo_dir() . '/*.jpg') ?: [] as $f) {
        $id = basename($f, '.jpg');
        if (!isset($keep[$id]) && filemtime($f) < time() - 600) {
            @unlink($f);
        }
    }
}

s48_api(static function () {
    s48_require_admin_api();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $s = s48_showcase();
        $people = [];
        foreach (S48_GROUPS as $g) {
            $people[$g] = [];
            foreach ($s['people'][$g] as $p) {
                $file = $p['photo'] ? s48_photo_dir() . '/' . $p['photo'] . '.jpg' : '';
                unset($p['id'], $p['photo']);
                if ($file !== '' && is_file($file)) {
                    $p['photo'] = 'data:image/jpeg;base64,' . base64_encode((string) file_get_contents($file));
                }
                $people[$g][] = $p;
            }
        }
        $sponsors = [];
        foreach ($s['sponsors'] as $sp) {
            $row = ['name' => $sp['name'], 'tier' => $sp['tier'] ?? ''];
            $logo = s48_logo_data_url($sp['logo'] ?? null);
            if ($logo !== null) {
                $row['logo'] = $logo;
            }
            $sponsors[] = $row;
        }
        $file = [
            'format' => S48_EVENT_FORMAT,
            'version' => 1,
            'exported_at' => gmdate('c'),
            'info' => $s['info'],
            'pitches' => array_column(s48_state()['pitches'], 'name'),
            'people' => $people,
            'sponsors' => $sponsors,
        ];
        header('Content-Disposition: attachment; filename="startup48-event-' . date('Y-m-d') . '.json"');
        s48_json_out($file);
        return;
    }

    $body = s48_json_body(2500000);
    switch ((string) ($body['action'] ?? '')) {
        case 'check':
            s48_require_not_voting(s48_state());
            s48_json_out(['ok' => true]);
            return;

        case 'photo':
            s48_json_out(['ok' => true, 'id' => s48_save_photo((string) ($body['photo'] ?? ''))]);
            return;

        case 'logo':
            s48_json_out(['ok' => true, 'id' => s48_save_logo((string) ($body['logo'] ?? ''))]);
            return;

        case 'import':
            [$show, $pitches, $photos, $logos] = s48_event_from_file(is_array($body['event'] ?? null) ? $body['event'] : []);
            // Fresh voting round with the file's pitches; old round files stay on disk as with Reset.
            s48_update_state(static function (array &$s) use ($pitches) {
                s48_require_not_voting($s);
                $s = s48_default_state();
                $s['pitches'] = $pitches;
            });
            $old = [];
            $oldLogos = [];
            [$saved] = s48_update_showcase(static function (array &$s) use ($show, $logos, &$old, &$oldLogos) {
                foreach (S48_GROUPS as $g) {
                    foreach ($s['people'][$g] as $p) {
                        $old[] = $p['photo'] ?? null;
                    }
                }
                if ($logos === null) {
                    $show['sponsors'] = $s['sponsors'];   // an older file: keep the sponsors already here
                } else {
                    foreach ($s['sponsors'] as $sp) {
                        $oldLogos[] = $sp['logo'] ?? null;
                    }
                }
                $s = $show;
            });
            foreach (array_diff(array_filter($old), $photos) as $p) {
                s48_delete_photo($p);
            }
            s48_sweep_photos($photos);
            if ($logos !== null) {
                foreach (array_diff(array_filter($oldLogos), $logos) as $l) {
                    s48_delete_logo($l);
                }
                s48_sweep_logos($logos);
            }
            s48_json_out(['ok' => true, 'people' => array_sum(array_map('count', $show['people'])), 'pitches' => count($pitches), 'sponsors' => count($saved['sponsors'])]);
            return;

        default:
            throw new S48Error('Unknown action');
    }
});
