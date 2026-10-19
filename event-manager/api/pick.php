<?php
// GET: this device's team pick.  POST {team, name, skill}: pick or change it (Choose and Finalize only).
// Identity is the same HttpOnly device cookie as voting.
declare(strict_types=1);

require __DIR__ . '/../inc/teams.php';

s48_api(static function () {
    $token = s48_voter_token(false);
    if ($token === null) {
        throw new S48Error('Please reload this page (cookies must be enabled).', 401);
    }
    $state = s48_state();
    $file = s48_round_dir($state['round'], 'picks') . '/' . s48_voter_key($token) . '.json';
    $mine = static function () use ($file, $state) {
        $p = s48_read_json($file);
        return is_array($p) && in_array($p['team'] ?? '', $state['advance'] ?? [], true)
            ? ['team' => $p['team'], 'name' => $p['name'], 'skill' => $p['skill'], 'moved' => !empty($p['moved'])] : null;
    };

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        s48_json_out(['ok' => true, 'pick' => $mine()]);
        return;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new S48Error('Method not allowed', 405);
    }
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 's48') {
        throw new S48Error('Bad request', 400);
    }
    if (!s48_picks_open($state)) {
        throw new S48Error(($state['phase'] ?? '') === 'set' ? 'Teams are set.' : 'Picking a team isn\'t open right now.', 409);
    }
    $body = s48_json_body(4096);
    $team = (string) ($body['team'] ?? '');
    if (!in_array($team, $state['advance'] ?? [], true)) {
        throw new S48Error('That team isn\'t in the list - please reload.', 409);
    }
    $name = s48_clean_name((string) ($body['name'] ?? ''));
    $name = function_exists('mb_substr') ? mb_substr($name, 0, 40) : substr($name, 0, 40);
    if ($name === '') {
        throw new S48Error('Enter your first name');
    }
    $skill = (string) ($body['skill'] ?? '');
    if (!isset(S48_SKILLS[$skill])) {
        throw new S48Error('Choose what you bring: Build, Design, Business or Other');
    }
    s48_write_atomic($file, json_encode(['team' => $team, 'name' => $name, 'skill' => $skill, 'at' => time()]));
    s48_json_out(['ok' => true, 'pick' => $mine()]);
});
