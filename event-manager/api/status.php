<?php
// Public voting status. Used by the voting page and the slide deck's QR and "Form your team" slides.
// ?counts=1 adds participation counts (never rankings).
declare(strict_types=1);

require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/teams.php';

s48_api(static function () {
    $state = s48_state();
    $out = [
        'ok' => true,
        'status' => $state['status'],
        'round' => $state['round'],
        'vote_url' => s48_vote_url(),
        'max_votes' => S48_MAX_VOTES,
        'pitch_count' => count($state['pitches']),
    ];
    if ($state['status'] !== 'setup') {
        $out['pitches'] = s48_public_pitches($state);
    }
    if ($state['status'] === 'teams') {
        // For the "Form your team" slide and phones: teams by station, phase and countdown, live counts.
        $board = s48_team_board($state, false);
        if ($board['phase'] === 'explore') {
            foreach ($board['teams'] as &$t) {
                unset($t['count'], $t['skills'], $t['fit']);   // no counts while people are just exploring
            }
            unset($t);
            unset($board['picked']);
        }
        $out['board'] = $board;
    }
    if (!empty($_GET['counts'])) {
        $c = s48_round_counts($state['round']);
        $out['counts'] = ['opened' => $c['opened'], 'submitted' => $c['submitted']];
    }
    s48_json_out($out);
});
