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
| `showcase/` | Self-running "Meet the Organizers / Judges / Mentors" loop with event-info slides (public) |
| `judging/` | Judging form for final pitches (web version of `judging/judging-form.pdf`) |
| `api/` | JSON endpoints (`status`, `ballot`, `admin`, `showcase`, `showcase-admin`, `photo`) |
| `inc/` | Shared PHP (not web-accessible) |
| `bin/` | CLI scripts (not web-accessible) |
| `data/` | Runtime storage, created automatically, git-ignored, not web-accessible |
| `assets/theme.css` | Brand palette and fonts from startup48.co, all in one place |
| `assets/fonts/`, `assets/logo-mark*.svg` | Self-hosted fonts; hexagon logo mark (color and white outline) |

## Look and feel

Matched to startup48.co: orange `#ee512b`, teal `#369cba` and slate `#4c778b` (the site's three tiles),
with navy `#204761` and cream `#faf1ed`. Fonts are Poppins (headlines), Raleway (section titles) and Ubuntu (body).
They are self-hosted in `assets/fonts/` (Latin subsets; Poppins and Raleway under the SIL Open Font
License, Ubuntu under the Ubuntu Font Licence), so nothing loads from a CDN on venue Wi-Fi.

- **Slides and operator console:** dark navy. The title and closing slides use the site's cream hero and the three-color tile band.
- **Phone voting page:** light (cream), like the website.
- **Logo:** `logo-mark.svg` is recreated from the hexagon PNG; `logo-mark-outline.svg` is the white version for dark backgrounds,
  as in the site header. The STARTUP48 lettering is set in Poppins, an approximation of the official wordmark.
  Drop in official SVGs with the same names to replace them.

## Pitch timer

Open `timer/`. Presets: **1** Opening Pitch 1:00 (tones at 0:20, 0:10), **2** Final Pitch 5:00 (1:00, 0:30),
**3** Q&A 5:00 (1:00, 0:30). Presets can't be switched while the timer runs; pause first.
Tone times (shown above as defaults) are set per timer in the operator console under **Pitch timer tones**: Warning and Danger can each be moved or switched off, and counting up after 0:00 can be turned off (the clock then holds at 0:00, flashing red).
Open timers check for changes every 30 s and apply them the next time a timer is chosen or reset, never mid-countdown. The last settings are kept in the browser for offline use, and they're part of the event file.
Any other length can be opened by link, e.g. `timer/?d=180&w=60,30&label=Demo` (seconds).
The gear button shows or hides the options row: sound on/off, volume, end sound, test sounds, fullscreen and hide. Each laptop remembers whether the row is open.

- Keys: `Space` start/pause · `R` reset · `1` `2` `3` presets · `F` fullscreen · `M` mute · `H` hide controls · `D` audio diagnostics
- Sounds are re-created from pitchtimer.com's cues (synthesized in the browser, no audio files):
  - **First warning:** a soft sine "bonk" (C5).
  - **Last warning:** a bright bell-like "ping".
  - **0:00:** the **End sound** chosen on the timer: singing bowl (default), air horn, 8-bit level complete, power-down sweep, or the original noise burst. Choosing one plays it; each laptop remembers its own choice.
  After 0:00 the timer counts up in red (`+0:12`). For a timer opened by link, the last warning time gets the ping and earlier ones the bonk.
- The background turns amber after the first warning, orange after the last, and red in overtime.
- Bookmarkable: `timer/?d=180&w=60,30&label=Demo` (values in seconds).
- Click once (or press any key) before the first pitch so the browser allows sound. Use **Test sounds** (plays both warnings and the chosen end sound, and turns sound on if it was off) and the **Volume** slider to check levels.
- How sound works (for the widest browser support): the three cues are generated once as WAV audio in plain JavaScript and played through ordinary `<audio>` elements when each time is reached, the same approach pitchtimer.com uses. Those elements are unlocked on your first click or key press, which iPhones and iPads require. `<audio>` also plays when an iPhone/iPad is on silent. If a browser refuses `<audio>`, Web Audio is used instead.
- Keep the timer tab visible (as it is when projected). Browsers slow down background tabs, so a cue can be up to about a second late there; a cue more than 5 s overdue is skipped rather than played late.
- A "Sound is off" note shows on the timer whenever sound is muted (`M` toggles it). Press `D` for a one-line audio diagnostics readout: playback method, Web Audio state, volume, last cue and last error.
- The timer works from the clock rather than counting ticks, so it stays accurate if the tab is in the background. It also keeps the screen awake where the browser supports it.

## Judging form

Open `judging/`. It's the organizers' judging sheet as a web page: Team name and Product, then Validation, Execution &
Design and Business Model, each with its prompt questions and a notes box. It works on a phone, tablet or laptop.

- Notes save automatically in **that browser only** (no server storage). **Start next team** clears the form.
- **Print / Save PDF** (or Ctrl+P) gives a one-page landscape copy laid out like the original.
- The original PDF can be downloaded from the page.

## Slides

Open `slides/`, press `F` for fullscreen. `→`/`PageDown`/`Space`/click = next, `←`/`PageUp` = back,
`V` jumps to the voting slide, `B` blanks the screen. Each slide has a URL (`slides/#schedule`, `#vote`).
Slides are plain `<section class="slide" data-id="…">` blocks in `slides/index.html`. Content in `[brackets]` is placeholder.

The **voting slide** checks the server every 3 s. It shows "Voting opens after the last pitch" until the
operator clicks **Start voting**, then shows the QR code, the short link and a live count of voters.

## Showcase loop (organizers, judges, mentors)

**Editing (operator console, "Showcase" row):**
- Organizers, Judges and Mentors each have a box. **+ Add** opens a form. Click a person to edit or delete them, and use the arrows to reorder.
- An organizer who is also mentoring gets the **Also mentoring** box. Ticking it adds the mentor fields (title, skills, Saturday/Sunday).
  They then appear on Meet the Mentors after the regular mentors, and the Mentors box lists them.
- Headshots are cropped to a square in the browser: drag to position, use the slider to zoom.
  The browser uploads a 600×600 JPEG. The server re-encodes it with GD when available (stripping metadata) and stores it as `data/photos/<random id>.jpg`.
  Replacing or deleting a person removes the old file.
- The **Event info** form sets the header line, Wi-Fi name and password, mentor hours, an announcement, seconds per person (3–15) and which mentors to show.
  - **Auto** shows Saturday's mentors on Saturday, Sunday's on Sunday, and everyone otherwise.
    A mentor with neither day ticked shows on both days.
  - **Saturday**, **Sunday** and **All** override that.

**Showing:** open `showcase/` on the venue screen and press `F` for fullscreen.
- The loop runs: Meet the Organizers → Wi-Fi → Meet the Judges → schedule → Meet the Mentors → announcement.
- Any step with no content is skipped.
- On the people slides, each headshot flies from the grid into the spotlight with its caption.
- The schedule is read from the opening-night deck (`slides/`), and today's column is highlighted.
- Under the schedule, a live **Time Until Final Pitches** countdown (`HH:MM:SS`) runs to the "Final Pitches" row's day and time, by the screen's clock. After that time on the day it reads "Final Pitches are underway!". It's hidden if the schedule has no such row.
- It checks for console changes every 5 s. If the slide on screen is affected, it redraws in place, keeping the same person in the spotlight. Other changes show when their slide comes up.
- After a deploy it reloads itself at the next slide change (it watches `version.json`).
- Keys: `→` skips to the next slide, `Space` pauses.

**Storage:** everything lives in `data/showcase.json` (locked and written atomically like `state.json`) plus `data/photos/`.
Both are outside the deploy sync, so a deploy never deletes them.
`showcase/` and `api/showcase.php` are public by design (the loop runs without a login), so don't enter anything there you wouldn't put on the screen.

## Event file (save / load)

The **Event file** section at the bottom of the operator console saves the whole setup as one JSON file (`startup48-event-<date>.json`):
organizers, judges and mentors with their headshots (cropped JPEGs, base64), event info, and the opening-pitch list.

**Load event file…** replaces all of that in the current install (after a confirm prompt), for example to set up a fresh install or roll back.
- Headshots upload one at a time, then a single request swaps everything over, so a failed load leaves the install unchanged.
- Loading starts a fresh voting round with the file's pitches. Old round files stay on disk, as with Reset.
- Loading is refused while voting is open.

Not included: voting status and ballots, the operator password, and `inc/config.php`.
The file does hold names, headshots and the Wi-Fi password, so share it accordingly.

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
   - the live ranking (ties share a rank, shown as T2; ideas with no votes show –)
6. Click **Close voting**. **Reopen** is available. **Reset** starts a fresh round. Old round files stay in `data/rounds/` and are never deleted.

### Team selection

1. In **Event info**, set the **Number of participants** and the **Ideal team size** (default 4 to 6).
2. After **Close voting**, tick the ideas to advance in the **Advance to Team Selection** column of the live ranking.
   - The **Participants per team** card shows participants ÷ advancing ideas.
   - It stays red until every team can fall inside the ideal range, and it says how many ideas to pick (36 people at 4–6 → pick 6–9).
3. Click **Start team selection**.
   - If the sizes are out of range, it asks before going ahead.
   - The advancing list locks. **Stop team selection** unlocks it.
   - The slide deck jumps to **Form your team**, which lists the advancing ideas in pitch order (not by votes) with a line like "7 teams · 5–6 people per team".
   - The voting page keeps showing "Voting closed".
4. Reopening voting needs team selection stopped first. **Reset** clears the advancing list.

### Storage and concurrency

- `data/state.json` holds the pitch list and status. Only the operator changes it, under an exclusive `flock`, with an atomic write (temp file then rename).
- `data/rounds/<round>/ballots/<sha256(device token)>.json` holds one file per voter, written atomically. Voters never write to the same file, so they can't block each other. Re-voting replaces your own file.
- `data/rounds/<round>/opens/<sha256(device token)>` is an empty marker file per device that loaded the voting page.
- Totals are recomputed from the ballot files and cached for 2 s, which is plenty for a few hundred voters.

Tested locally with 60 simultaneous voters plus 10 page-only visits: every ballot was saved and counted.

## Deploying to DreamHost

Dev target: **https://slop.brynelsen.com/s48/**, deployed by GitHub Actions
(`.github/workflows/deploy-s48.yml`). A push to `main` that touches `s48/` rsyncs this folder
over SSH, then checks that the live `version.json` matches the commit and that protected paths
return 403/404. You can also run it manually from the Actions tab, with a dry-run option.

The deploy key is locked by `rrsync` to `/home/<user>/slop.brynelsen.com/s48`. It can only
rsync into that folder: no shell access, and no access to the rest of the site.
`--delete` keeps the folder an exact copy of `s48/`, **except** `data/` (live votes and the
password hash) and `inc/config.php`, which exist only on the server and are never uploaded or deleted.

One-time setup:

1. In the DreamHost panel, create a **shell** (SSH) user that owns `slop.brynelsen.com`.
   Make sure PHP 8.1+ is selected and HTTPS (Let's Encrypt) is on for the domain.
2. On your laptop, run the setup script `dh-deploy-setup.sh` (kept outside the repo; it needs
   `gh`, `ssh` and `rsync`). It creates the deploy key, installs it on the server and stores
   the repo secrets and the `SITE_URL` variable. Re-run it to rotate the key, or after DreamHost moves
   the account to a new server (deploys then fail with "host identification has changed").
3. Merge to `main` (or run the workflow manually) to deploy.
4. Log in with the shell user's password and do the server-only steps once:
   ```sh
   cd ~/slop.brynelsen.com/s48
   cp inc/config.sample.php inc/config.php   # then edit it:
   #   'public_base_url' => 'https://slop.brynelsen.com/s48/',
   #   'data_dir'        => '/home/USERNAME/s48-data',   (outside the web root)
   php bin/set-password.php
   ```
5. Rehearse: add a test pitch, start voting, vote from a phone on cellular data, then **Reset**.

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
3. **HTTPS depends on the domain's certificate.** `.htaccess` redirects every request to HTTPS, so make sure
   Let's Encrypt is active for the domain. Cookies are marked `Secure` automatically once HTTPS is detected.
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
10. **Showcase is public.** `api/showcase.php` returns all names, titles, the Wi-Fi password and headshot URLs to anyone.
    Fine for a slide that's on screen anyway; gate it behind a login if that changes.
11. **The vendored QR library** (`assets/qrcode.js`, qrcode-generator 1.4.4, MIT) should be tracked for updates.
