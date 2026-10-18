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

s48_api(static function () {
    s48_require_admin_api();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        s48_json_out(['ok' => true] + s48_public_showcase(s48_showcase()));
        return;
    }

    // Photos arrive as ~100 KB data URLs; allow room for them.
    $body = s48_json_body(2500000);
    $action = (string) ($body['action'] ?? '');

    // Store a new photo before taking the lock; it is only referenced if the update succeeds.
    $newPhoto = null;
    if (!empty($body['photo'])) {
        $newPhoto = s48_save_photo((string) $body['photo']);
    }
    $oldPhotos = [];

    try {
        [$s] = s48_update_showcase(static function (array &$s) use ($action, $body, $newPhoto, &$oldPhotos) {
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

                case 'info':
                    $s['info'] = s48_info_fields($body['fields'] ?? [], $s['info']);
                    break;

                default:
                    throw new S48Error('Unknown action');
            }
        });
    } catch (Throwable $e) {
        s48_delete_photo($newPhoto); // the update failed, so nothing references it
        throw $e;
    }

    foreach ($oldPhotos as $p) {
        s48_delete_photo($p);
    }
    s48_json_out(['ok' => true] + s48_public_showcase($s));
});
