<?php
// Operator API for the showcase. GET: full data. POST {action, ...} with X-CSRF-Token.
declare(strict_types=1);

require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/showcase.php';

function s48_group(array $body): string
{
    $g = (string) ($body['group'] ?? '');
    if (!in_array($g, S48_GROUPS, true)) {
        throw new S48Error('Unknown group');
    }
    return $g;
}

function s48_find_person(array $list, string $id): int
{
    foreach ($list as $i => $p) {
        if ($p['id'] === $id) {
            return $i;
        }
    }
    throw new S48Error('Person not found', 404);
}

function s48_find_sponsor(array $list, string $id): int
{
    foreach ($list as $i => $p) {
        if ($p['id'] === $id) {
            return $i;
        }
    }
    throw new S48Error('Sponsor not found', 404);
}

s48_api(static function () {
    s48_require_admin_api();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        s48_json_out(['ok' => true] + s48_public_showcase(s48_showcase()));
        return;
    }

    // Photos and logos arrive as data URLs of up to a few hundred KB; allow room for them.
    $body = s48_json_body(2500000);
    $action = (string) ($body['action'] ?? '');

    // Store a new photo before taking the lock; it is only referenced if the update succeeds.
    $newPhoto = null;
    $newLogo = null;
    try {
        if (!empty($body['photo'])) {
            $newPhoto = s48_save_photo((string) $body['photo']);
        }
        if (!empty($body['logo'])) {
            $newLogo = s48_save_logo((string) $body['logo']);
        }
    } catch (Throwable $e) {
        s48_delete_photo($newPhoto);
        throw $e;
    }
    $oldPhotos = [];
    $oldLogos = [];

    try {
        [$s] = s48_update_showcase(static function (array &$s) use ($action, $body, $newPhoto, $newLogo, &$oldPhotos, &$oldLogos) {
            switch ($action) {
                case 'add':
                    $g = s48_group($body);
                    if (count($s['people'][$g]) >= S48_MAX_PEOPLE) {
                        throw new S48Error('Limit reached (' . S48_MAX_PEOPLE . ')');
                    }
                    $s['people'][$g][] = ['id' => s48_new_id(4)] + s48_person_fields($g, $body['fields'] ?? []) + ['photo' => $newPhoto];
                    break;

                case 'update':
                    $g = s48_group($body);
                    $i = s48_find_person($s['people'][$g], (string) ($body['id'] ?? ''));
                    $cur = $s['people'][$g][$i];
                    $photo = $cur['photo'] ?? null;
                    if ($newPhoto !== null || !empty($body['remove_photo'])) {
                        $oldPhotos[] = $photo;
                        $photo = $newPhoto;
                    }
                    $s['people'][$g][$i] = ['id' => $cur['id']] + s48_person_fields($g, $body['fields'] ?? []) + ['photo' => $photo];
                    break;

                case 'delete':
                    $g = s48_group($body);
                    $i = s48_find_person($s['people'][$g], (string) ($body['id'] ?? ''));
                    $oldPhotos[] = $s['people'][$g][$i]['photo'] ?? null;
                    array_splice($s['people'][$g], $i, 1);
                    break;

                case 'move':
                    $g = s48_group($body);
                    $i = s48_find_person($s['people'][$g], (string) ($body['id'] ?? ''));
                    $j = $i + ((int) ($body['dir'] ?? 0) < 0 ? -1 : 1);
                    if ($j >= 0 && $j < count($s['people'][$g])) {
                        [$s['people'][$g][$i], $s['people'][$g][$j]] = [$s['people'][$g][$j], $s['people'][$g][$i]];
                    }
                    break;

                case 'sponsor_add':
                    if (count($s['sponsors']) >= S48_MAX_SPONSORS) {
                        throw new S48Error('Limit reached (' . S48_MAX_SPONSORS . ')');
                    }
                    $s['sponsors'][] = ['id' => s48_new_id(4)] + s48_sponsor_fields($body['fields'] ?? []) + ['logo' => $newLogo];
                    break;

                case 'sponsor_update':
                    $i = s48_find_sponsor($s['sponsors'], (string) ($body['id'] ?? ''));
                    $cur = $s['sponsors'][$i];
                    $logo = $cur['logo'] ?? null;
                    if ($newLogo !== null || !empty($body['remove_logo'])) {
                        $oldLogos[] = $logo;
                        $logo = $newLogo;
                    }
                    $s['sponsors'][$i] = ['id' => $cur['id']] + s48_sponsor_fields($body['fields'] ?? []) + ['logo' => $logo];
                    break;

                case 'sponsor_delete':
                    $i = s48_find_sponsor($s['sponsors'], (string) ($body['id'] ?? ''));
                    $oldLogos[] = $s['sponsors'][$i]['logo'] ?? null;
                    array_splice($s['sponsors'], $i, 1);
                    break;

                case 'sponsor_move':
                    $i = s48_find_sponsor($s['sponsors'], (string) ($body['id'] ?? ''));
                    $j = $i + ((int) ($body['dir'] ?? 0) < 0 ? -1 : 1);
                    if ($j >= 0 && $j < count($s['sponsors'])) {
                        [$s['sponsors'][$i], $s['sponsors'][$j]] = [$s['sponsors'][$j], $s['sponsors'][$i]];
                    }
                    break;

                case 'info':
                    $s['info'] = s48_info_fields($body['fields'] ?? [], $s['info']);
                    break;

                default:
                    throw new S48Error('Unknown action');
            }
        });
    } catch (Throwable $e) {
        s48_delete_photo($newPhoto); // the update failed, so nothing references it
        s48_delete_logo($newLogo);
        throw $e;
    }

    foreach ($oldPhotos as $p) {
        s48_delete_photo($p);
    }
    foreach ($oldLogos as $l) {
        s48_delete_logo($l);
    }
    s48_json_out(['ok' => true] + s48_public_showcase($s));
});
