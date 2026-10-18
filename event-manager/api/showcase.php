<?php
// Public read of the showcase (organizers, judges, mentors, event info) for the slide loop.
declare(strict_types=1);

require __DIR__ . '/../inc/showcase.php';

s48_api(static function () {
    s48_json_out(['ok' => true] + s48_public_showcase(s48_showcase()));
});
