# startup48 event tools

Lightweight tools for the startup48 weekend: a pitch timer, the opening-night slide deck,
and live audience voting. Plain PHP 8.1+ and static HTML/JS: no database, no build step,
no CDN. Runs on DreamHost (or any Apache + PHP host).

| Path | What it is |
|---|---|
| `timer/` | Pitch countdown timer (single self-contained HTML file) |
| `slides/` | Opening-night deck, 16:9, scales to any screen; live voting QR slide |
| `admin/` | Operator console: log pitches, start/close voting, live results |
| `vote/` | Mobile voting page participants reach via the QR code |
| `api/` | JSON endpoints (`status`, `ballot`, `admin`) |
| `inc/` | Shared PHP (not web-accessible) |
| `bin/` | CLI scripts (not web-accessible) |
| `data/` | Runtime storage, created automatically, git-ignored, not web-accessible |
| `assets/theme.css` | **Placeholder** brand palette, one place to swap colors/fonts |

## Pitch timer

Open `timer/`. Presets: **1** practice 1:00 (tones at 0:20, 0:10), **2** pitch 5:00 (1:00, 0:20),
**3** Q&A 5:00 (1:00, 0:30), or **Custom…** (saved in the browser).

- Keys: `Space` start/pause · `R` reset · `1` `2` `3` presets · `F` fullscreen · `M` mute · `H` hide controls
- One beep at the first warning, a double beep at the last warning, and a buzzer at 0:00. After that it counts up in red (`+0:12`).
- The background turns amber after the first warning and red after the last.
- Bookmarkable: `timer/?d=180&w=60,30&label=Demo` (values in seconds).
- Click once (or press any key) before the first pitch so the browser allows sound. Use **Test tone** to check the speakers.
- The timer works from the clock rather than counting ticks, so it stays accurate if the tab is in the background. It also keeps the screen awake where the browser supports it.

## Slides

Open `slides/`, press `F` for fullscreen. `→`/`PageDown`/`Space`/click = next, `←`/`PageUp` = back,
`V` jumps to the voting slide, `B` blanks the screen. Each slide has a URL (`slides/#schedule`, `#vote`).
Slides are plain `<section class="slide" data-id="…">` blocks in `slides/index.html`. Content in `[brackets]` is placeholder.

The **voting slide** checks the server every 3 s. It shows "Voting opens after the last pitch" until the
operator clicks **Start voting**, then shows the QR code, the short link and a live count of voters.

## Voting flow

1. Operator opens `admin/` and logs in.
2. During opening pitches, type each pitch name and press Enter (or paste a list, one per line).
   Click a name to rename it, and use the arrows to reorder.
3. Click **Start voting**. The QR code appears on the slides.
4. Participants scan, tap **+**/**−** to spend up to 3 votes (all on one idea or spread out), and submit.
   They can change their votes until voting closes.
5. The operator console refreshes every 3 s and shows:
   - devices that opened the voting page
   - opened but not submitted
   - submitted
   - % of openers who submitted
   - the live ranking (ties share a rank)
6. Click **Close voting**. **Reopen** is available. **Reset** starts a fresh round. Old round files stay in `data/rounds/` and are never deleted.

### Storage and concurrency

- `data/state.json` holds the pitch list and status. Only the operator changes it, under an exclusive `flock`, with an atomic write (temp file then rename).
- `data/rounds/<round>/ballots/<sha256(device token)>.json` holds one file per voter, written atomically. Voters never write to the same file, so they can't block each other. Re-voting replaces your own file.
- `data/rounds/<round>/opens/<sha256(device token)>` is an empty marker file per device that loaded the voting page.
- Totals are recomputed from the ballot files and cached for 2 s, which is plenty for a few hundred voters.

Tested locally with 60 simultaneous voters plus 10 page-only visits: every ballot was saved and counted.

## Deploying to DreamHost

1. Upload the `s48/` folder (e.g. to `example.com/s48/`). Make sure PHP 8.1+ is selected for the domain.
2. Enable HTTPS (free Let's Encrypt in the DreamHost panel), then uncomment the HTTPS redirect in `.htaccess`.
3. `cp inc/config.sample.php inc/config.php` and set:
   - `public_base_url` => `'https://example.com/s48/'` (so the QR never depends on the Host header)
   - `data_dir` => a folder **outside** the web root, e.g. `'/home/USERNAME/s48-data'`
4. Over SSH: `php s48/bin/set-password.php` to set the operator password.
5. Check that `https://example.com/s48/data/`, `/inc/` and `/bin/` return **403**.
6. Rehearse: add a test pitch, start voting, vote from a phone on cellular data, then **Reset**.

Local development:

```sh
php s48/bin/set-password.php 'devpassword'
php -S localhost:8048 -t s48 s48/bin/dev-router.php
# http://localhost:8048/admin/
```

## ⚠️ Security gaps to resolve before production use

This is a light-traffic mockup. Known gaps, roughly in priority order:

1. **Duplicate voting is easy.** "One ballot per device" is just a cookie. A private window, cleared
   cookies or a second device gets another ballot. Fix: pre-distributed participant codes or verified
   email (the ballot API already keys on a single voter identity, so this is a contained change).
   The "opened" count can be inflated the same way.
2. **No rate limiting on the voting endpoints.** A script can mint cookies and post many ballots, or
   fill `data/` with "opened" marker files. Add per-IP limits (or a WAF/mod_evasive), and cap the file count per round.
3. **HTTPS is not enforced** by default. Without it, the operator password and session cookie travel in plain
   text. Enable TLS and the redirect. Cookies are marked `Secure` automatically once HTTPS is detected.
4. **Data inside the web root.** `data/` is blocked by `.htaccess`, but a misconfiguration (e.g. moving
   to nginx, or `AllowOverride None`) would expose ballots and the password hash. Set `data_dir` outside the web root.
5. **Single shared operator password**, with no per-user accounts, 2FA or audit log of operator actions.
   Login is throttled per IP (5 failures / 10 min), but behind a proxy all users may share one IP.
6. **Host header trust.** If `public_base_url` is unset, the QR URL is built from the request's Host header.
   Always set it in production.
7. **No Content-Security-Policy.** Pages use inline scripts. Output is escaped (DOM `textContent` /
   `htmlspecialchars`), but a CSP would add defense in depth.
8. **No backups or retention policy** for `data/`. Ballots contain no personal data today (only hashed
   random tokens). Adding email IDs later brings privacy obligations (retention, deletion, a notice to participants).
9. **Results are visible only to the operator**, but anyone can see the participation counts via
   `api/status.php?counts=1` (by design, for the slide). Remove `counts` if that's sensitive.
10. **The vendored QR library** (`assets/qrcode.js`, qrcode-generator 1.4.4, MIT) should be tracked for updates.
