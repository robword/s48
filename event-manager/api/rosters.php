<?php
// Operator download: team rosters as CSV (station, team, name, skill).
declare(strict_types=1);

require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/teams.php';

s48_api(static function () {
    s48_require_admin_api();
    $state = s48_state();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="startup48-teams-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    echo "\xEF\xBB\xBF" . s48_rosters_csv($state);   // BOM so Excel reads UTF-8 names
});
