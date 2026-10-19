<?php
// Public voting status. Used by the voting page and the slide deck's QR and "Form your team" slides.
// ?counts=1 adds participation counts (never rankings).
declare(strict_types=1);

require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/showcase.php';

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
        // For the "Form your team" slide: advancing ideas in pitch order, plus the size estimate.
        $info = s48_showcase()['info'];
        $adv = s48_advancing($state);
        $out['teams'] = array_column($adv, 'name');
        $out['team'] = s48_team_fit((int) $info['participants'], count($adv), (int) $info['team_min'], (int) $info['team_max']);
    }
    if (!empty($_GET['counts'])) {
        $c = s48_round_counts($state['round']);
        $out['counts'] = ['opened' => $c['opened'], 'submitted' => $c['submitted']];
    }
    s48_json_out($out);
});
