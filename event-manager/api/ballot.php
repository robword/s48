<?php
// GET: this device's current ballot.  POST {votes: {pitchId: n}}: submit/replace it.
// Identity is the HttpOnly device cookie issued by vote/index.php.
declare(strict_types=1);

require __DIR__ . '/../inc/bootstrap.php';

s48_api(static function () {
    $token = s48_voter_token(false);
    if ($token === null) {
        throw new S48Error('Please reload the voting page (cookies must be enabled).', 401);
    }
    $state = s48_state();
    $file = s48_round_dir($state['round'], 'ballots') . '/' . s48_voter_key($token) . '.json';

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $b = s48_read_json($file);
        s48_json_out(['ok' => true, 'status' => $state['status'], 'votes' => $b['votes'] ?? null]);
        return;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new S48Error('Method not allowed', 405);
    }
    // Custom header forces a CORS preflight, so other sites can't post ballots.
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 's48') {
        throw new S48Error('Bad request', 400);
    }
    if ($state['status'] !== 'open') {
        throw new S48Error($state['status'] === 'closed' ? 'Voting is closed.' : 'Voting has not started yet.', 409);
    }

    $body = s48_json_body(8192);
    $votes = $body['votes'] ?? null;
    if (!is_array($votes)) {
        throw new S48Error('Missing votes');
    }
    $valid = [];
    foreach ($state['pitches'] as $p) {
        $valid[$p['id']] = true;
    }
    $clean = [];
    $sum = 0;
    foreach ($votes as $id => $n) {
        if (!is_int($n) || $n < 0 || $n > S48_MAX_VOTES) {
            throw new S48Error('Invalid vote count');
        }
        if ($n === 0) {
            continue;
        }
        if (!isset($valid[$id])) {
            throw new S48Error('The pitch list changed - please reload.', 409);
        }
        $clean[(string) $id] = $n;
        $sum += $n;
    }
    if ($sum < 1 || $sum > S48_MAX_VOTES) {
        throw new S48Error('Use between 1 and ' . S48_MAX_VOTES . ' votes.');
    }

    s48_record_open($state['round'], $token);
    s48_write_atomic($file, json_encode(['votes' => $clean, 'at' => time()]));
    s48_json_out(['ok' => true, 'votes' => $clean]);
});
