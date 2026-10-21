# Event Manager

Lightweight tools for running an in-person startup or hackathon weekend, built for
[Startup 48](NOTICE.md) and usable for yours:

- **Pitch timer** with warning tones you can tune per timer
- **Opening-night slide deck** with a live voting QR code
- **Audience voting** on phones, with a live ranking for the operator
- **Team selection**: advancing ideas at numbered stations, then phones pick a team
- **"Meet the organizers, judges and mentors"** slide loop with headshots
- **Judging form**
- **Save/load the whole event setup** as one file

Plain PHP 8.1+ and static HTML/JS. No database, no build step, no CDN. Data lives in flat files
written safely for many simultaneous phones. It runs on any Apache + PHP host.

Everything is in [`event-manager/`](event-manager/). Start with its
[README](event-manager/README.md) for the features, how a voting night runs, deployment and the
known security gaps to resolve before production use.

## Quick start

```sh
php event-manager/bin/set-password.php 'devpassword'
php -S localhost:8048 -t event-manager event-manager/bin/dev-router.php
# open http://localhost:8048/admin/
```

## Contributing and license

Pull requests are welcome; see [CONTRIBUTING.md](CONTRIBUTING.md). The code is
[MIT-licensed](LICENSE). Startup 48's name, logo and judging form are not, and the bundled fonts and
QR library keep their own licenses: see [NOTICE.md](NOTICE.md).
