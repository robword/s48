<?php
// Copy to config.php (git-ignored) and adjust. Every key is optional.
return [
    // Absolute URL of the app's folder. Set this in production so the QR code
    // never depends on the incoming Host header.
    'public_base_url' => '',          // e.g. 'https://events.example.com/'

    // Where ballots/state live. Move OUTSIDE the web root in production.
    // 'data_dir' => '/home/USERNAME/event-data',

    // How long a voter's device token cookie lasts.
    'voter_cookie_days' => 7,

    // Alternative to `php bin/set-password.php` (which stores data/admin.json).
    // Generate with: php -r 'echo password_hash("secret", PASSWORD_DEFAULT), "\n";'
    'admin_password_hash' => '',
];
