<?php
// Public read of the pitch timer's tone settings (set in the operator console).
declare(strict_types=1);

require __DIR__ . '/../inc/showcase.php';

s48_api(static function () {
    s48_json_out(['ok' => true, 'timers' => s48_showcase()['info']['timers']]);
});
