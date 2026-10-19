<?php
// Operator API. GET ?action=dashboard; POST {action, ...} with X-CSRF-Token header.
declare(strict_types=1);

require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/teams.php';

function s48_find_pitch(array $state, string $id): int
{
    foreach ($state['pitches'] as $i => $p) {
        if ($p['id'] === $id) {
            return $i;
        }
    }
    throw new S48Error('Pitch not found', 404);
}

function s48_dashboard(array $state): array
{
    $info = s48_showcase()['info'];
    return [
        'ok' => true,
        'status' => $state['status'],
        'round' => $state['round'],
        'opened_at' => $state['opened_at'],
        'closed_at' => $state['closed_at'],
        'pitches' => s48_public_pitches($state),
        'vote_url' => s48_vote_url(),
        'results' => s48_results($state),
        'advance' => array_column(s48_advancing($state), 'id'),
        'team' => s48_team_fit((int) $info['participants'], count(s48_advancing($state)), (int) $info['team_min'], (int) $info['team_max']),
        'board' => $state['status'] === 'teams' ? s48_team_board($state, true) : null,
        'server_time' => time(),
    ];
}

s48_api(static function () {
    s48_require_admin_api();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        s48_json_out(s48_dashboard(s48_state()));
        return;
    }

    $body = s48_json_body();
    $action = (string) ($body['action'] ?? '');

    [$state] = s48_update_state(static function (array &$s) use ($action, $body) {
        switch ($action) {
            case 'add':
                // One name per line, so a pasted list works too.
                $lines = preg_split('/\r\n|\r|\n/', (string) ($body['names'] ?? '')) ?: [];
                $added = 0;
                foreach ($lines as $line) {
                    $name = s48_clean_name($line);
                    if ($name === '') {
                        continue;
                    }
                    if (count($s['pitches']) >= S48_MAX_PITCHES) {
                        throw new S48Error('Pitch limit reached (' . S48_MAX_PITCHES . ')');
                    }
                    $s['pitches'][] = ['id' => s48_new_id(4), 'name' => $name];
                    $added++;
                }
                if ($added === 0) {
                    throw new S48Error('Enter a pitch name');
                }
                break;

            case 'rename':
                $name = s48_clean_name((string) ($body['name'] ?? ''));
                if ($name === '') {
                    throw new S48Error('Name cannot be empty');
                }
                $s['pitches'][s48_find_pitch($s, (string) ($body['id'] ?? ''))]['name'] = $name;
                break;

            case 'delete':
                if ($s['status'] !== 'setup') {
                    throw new S48Error('Pitches can only be deleted before voting starts (reset to delete).', 409);
                }
                $i = s48_find_pitch($s, (string) ($body['id'] ?? ''));
                $s['advance'] = array_values(array_diff($s['advance'] ?? [], [$s['pitches'][$i]['id']]));
                array_splice($s['pitches'], $i, 1);
                break;

            case 'move':
                $i = s48_find_pitch($s, (string) ($body['id'] ?? ''));
                $j = $i + ((int) ($body['dir'] ?? 0) < 0 ? -1 : 1);
                if ($j >= 0 && $j < count($s['pitches'])) {
                    [$s['pitches'][$i], $s['pitches'][$j]] = [$s['pitches'][$j], $s['pitches'][$i]];
                }
                break;

            case 'start':
            case 'reopen':
                if ($s['status'] === 'teams') {
                    throw new S48Error('Stop team selection before reopening voting', 409);
                }
                if (count($s['pitches']) === 0) {
                    throw new S48Error('Add at least one pitch first');
                }
                $s['status'] = 'open';
                $s['opened_at'] = $s['opened_at'] ?? time();
                $s['closed_at'] = null;
                break;

            case 'unstart':
                // Back from Vote to Pitches: only while nobody has voted (counted now, not from the tally cache).
                if ($s['status'] !== 'open') {
                    throw new S48Error('Voting is not open', 409);
                }
                if (glob(s48_round_dir($s['round'], 'ballots') . '/*.json') ?: []) {
                    throw new S48Error('People have already voted. Close voting, or use Reset to start a new round.', 409);
                }
                $s['status'] = 'setup';
                $s['opened_at'] = null;
                break;

            case 'close':
                if ($s['status'] !== 'open') {
                    throw new S48Error('Voting is not open', 409);
                }
                $s['status'] = 'closed';
                $s['closed_at'] = time();
                break;

            case 'advance':
                // Ticking ideas to advance: only between closing the vote and starting team selection.
                if ($s['status'] !== 'closed') {
                    throw new S48Error($s['status'] === 'teams' ? 'Stop team selection to change the advancing ideas' : 'Close voting first', 409);
                }
                $id = $s['pitches'][s48_find_pitch($s, (string) ($body['id'] ?? ''))]['id'];
                $adv = array_values(array_diff($s['advance'] ?? [], [$id]));
                if (!empty($body['on'])) {
                    $adv[] = $id;
                }
                $s['advance'] = $adv;
                break;

            case 'teams_start':
                if ($s['status'] !== 'closed') {
                    throw new S48Error('Close voting first', 409);
                }
                if (count(s48_advancing($s)) === 0) {
                    throw new S48Error('Tick at least one idea to advance');
                }
                $s['status'] = 'teams';
                $s['phase'] = 'explore';
                $s['phase_at'] = time();
                break;

            case 'teams_phase':
                // Explore -> Choose -> Finalize -> Teams are set; the operator can also step back.
                $phase = (string) ($body['phase'] ?? '');
                if ($s['status'] !== 'teams' || !in_array($phase, S48_PHASES, true)) {
                    throw new S48Error('Team selection has not started', 409);
                }
                $s['phase'] = $phase;
                $s['phase_at'] = time();
                break;

            case 'pick_move':
                // Organizer moves someone to another team ('' removes their pick).
                if ($s['status'] !== 'teams') {
                    throw new S48Error('Team selection has not started', 409);
                }
                $file = s48_pick_file($s, (string) ($body['key'] ?? ''));
                $pick = s48_read_json($file);
                if (!is_array($pick)) {
                    throw new S48Error('That pick no longer exists', 404);
                }
                $team = (string) ($body['team'] ?? '');
                if ($team === '') {
                    @unlink($file);
                } elseif (!in_array($team, $s['advance'] ?? [], true)) {
                    throw new S48Error('Unknown team');
                } else {
                    s48_write_atomic($file, json_encode(['team' => $team, 'moved' => true] + $pick));
                }
                break;

            case 'teams_stop':
                if ($s['status'] !== 'teams') {
                    throw new S48Error('Team selection has not started', 409);
                }
                $s['status'] = 'closed';
                $s['phase'] = null;
                break;

            case 'reset':
                // Starts a fresh round. Old round files are kept on disk (not deleted).
                if (($body['confirm'] ?? '') !== 'RESET') {
                    throw new S48Error('Type RESET to confirm');
                }
                $keep = !empty($body['keep_pitches']) ? $s['pitches'] : [];
                $s = s48_default_state();
                $s['pitches'] = $keep;
                break;

            default:
                throw new S48Error('Unknown action');
        }
    });

    s48_json_out(s48_dashboard($state));
});
