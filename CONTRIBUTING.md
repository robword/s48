# Contributing

Thanks for helping make event nights run smoother. Issues and pull requests are welcome.

## Ground rules

- **Plain PHP 8.1+ and static HTML/JS.** No database, no build step, no CDN. If a change needs a
  dependency, open an issue first.
- **Concurrency matters.** Phones vote at the same moment. Anything written during an event must use
  one file per writer, or an exclusive `flock` plus an atomic temp-file-and-rename write. See
  "Storage and concurrency" in [`event-manager/README.md`](event-manager/README.md).
- **Security.** Escape all output, keep `data/`, `inc/` and `bin/` unreachable from the web, and don't
  put secrets in the repo. Report vulnerabilities privately through the repository's **Security**
  tab (private vulnerability reporting), not in a public issue.
- **Branding.** Startup 48's logo, name and judging form aren't MIT-licensed (see [`NOTICE.md`](NOTICE.md)).
  Don't add other organizations' branding either, unless you have the right to.

## Try it locally

Or push a branch: every push to a branch other than `main` runs CI, and a branch that passes gets its own
preview site (see "Branch previews" in `event-manager/README.md`).

```sh
php event-manager/bin/set-password.php 'devpassword'
php -S localhost:8048 -t event-manager event-manager/bin/dev-router.php
# http://localhost:8048/admin/
```

Run `php -l` on any PHP file you change; CI does the same for every pull request.

## Sign off your commits (DCO)

By contributing you certify the [Developer Certificate of Origin](https://developercertificate.org/):
that you wrote the change, or have the right to submit it, under this project's MIT license.
Add a sign-off line to each commit with `git commit -s`:

```
Signed-off-by: Your Name <you@example.com>
```

You keep the copyright in your contribution. It is licensed to everyone under the same MIT terms as
the rest of the project.
