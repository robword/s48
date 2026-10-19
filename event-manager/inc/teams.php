<?php
/**
 * Team selection, after voting closes: the advancing ideas become teams at numbered stations
 * (1..N in pitch order) and the operator steps through the phases. During Choose and Finalize
 * each phone picks one team, with a first name and a skill; picks are one file per device under
 * rounds/<round>/picks/, like ballots, so phones never write the same file.
 */
declare(strict_types=1);

require_once __DIR__ . '/showcase.php';

const S48_PHASES = ['explore', 'choose', 'finalize', 'set'];
const S48_SKILLS = ['build' => 'Build', 'design' => 'Design', 'business' => 'Business', 'other' => 'Other'];

/** Phones can pick or change teams during Choose and Finalize. */
function s48_picks_open(array $state): bool
{
    return $state['status'] === 'teams' && in_array($state['phase'] ?? '', ['choose', 'finalize'], true);
}

/** All picks this round, keyed by device key. Picks for ideas no longer advancing are dropped. */
function s48_picks(array $state): array
{
    $teams = array_flip($state['advance'] ?? []);
    $out = [];
    foreach (glob(s48_round_dir($state['round'], 'picks') . '/*.json') ?: [] as $f) {
        $p = s48_read_json($f);
        if (is_array($p) && isset($teams[$p['team'] ?? ''])) {
            $out[basename($f, '.json')] = $p;
        }
    }
    uasort($out, static function ($a, $b) {
        return ($a['at'] ?? 0) <=> ($b['at'] ?? 0);
    });
    return $out;
}

function s48_pick_file(array $state, string $key): string
{
    if (!preg_match('/^[a-f0-9]{64}$/', $key)) {
        throw new S48Error('Bad pick id');
    }
    return s48_round_dir($state['round'], 'picks') . '/' . $key . '.json';
}

/** Phase lengths in seconds, from Event info. */
function s48_phase_seconds(array $info): array
{
    return [
        'explore' => 60 * (int) $info['phase_explore'],
        'choose' => 60 * (int) $info['phase_choose'],
        'finalize' => 60 * (int) $info['phase_finalize'],
        'set' => 0,
    ];
}

/**
 * The teams with station numbers and live counts. $withRosters adds names (operator only).
 * Each team is flagged under / ok / over against the ideal team size.
 */
function s48_team_board(array $state, bool $withRosters): array
{
    $info = s48_showcase()['info'];
    $adv = s48_advancing($state);
    $picks = s48_picks($state);
    $teams = [];
    foreach ($adv as $i => $p) {
        $teams[$p['id']] = ['id' => $p['id'], 'station' => $i + 1, 'name' => $p['name'], 'count' => 0, 'skills' => array_fill_keys(array_keys(S48_SKILLS), 0)];
        if ($withRosters) {
            $teams[$p['id']]['roster'] = [];
        }
    }
    foreach ($picks as $key => $pk) {
        $t = &$teams[$pk['team']];
        $t['count']++;
        $t['skills'][$pk['skill']] = ($t['skills'][$pk['skill']] ?? 0) + 1;
        if ($withRosters) {
            $t['roster'][] = ['key' => $key, 'name' => $pk['name'], 'skill' => $pk['skill'], 'moved' => !empty($pk['moved'])];
        }
        unset($t);
    }
    $min = (int) $info['team_min'];
    $max = (int) $info['team_max'];
    foreach ($teams as &$t) {
        $t['fit'] = $t['count'] < $min ? 'under' : ($t['count'] > $max ? 'over' : 'ok');
    }
    unset($t);
    $phase = $state['phase'] ?? 'explore';
    return [
        'phase' => $phase,
        'phase_at' => (int) ($state['phase_at'] ?? time()),
        'phase_seconds' => s48_phase_seconds($info)[$phase] ?? 0,
        'server_time' => time(),
        'teams' => array_values($teams),
        'picked' => count($picks),
        'fit' => s48_team_fit((int) $info['participants'], count($adv), $min, $max),
    ];
}

/** A leading = + - @ would be read as a formula by spreadsheets. */
function s48_csv_safe(string $v): string
{
    return preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;
}

/** CSV rosters for the operator: station, team, name, skill. */
function s48_rosters_csv(array $state): string
{
    $board = s48_team_board($state, true);
    $fh = fopen('php://temp', 'w+');
    fputcsv($fh, ['Station', 'Team', 'Name', 'Skill', 'Moved by organizer']);
    foreach ($board['teams'] as $t) {
        if (!$t['roster']) {
            fputcsv($fh, [$t['station'], s48_csv_safe($t['name']), '', '', '']);
        }
        foreach ($t['roster'] as $r) {
            fputcsv($fh, [$t['station'], s48_csv_safe($t['name']), s48_csv_safe($r['name']), S48_SKILLS[$r['skill']] ?? $r['skill'], $r['moved'] ? 'yes' : '']);
        }
    }
    rewind($fh);
    return (string) stream_get_contents($fh);
}
