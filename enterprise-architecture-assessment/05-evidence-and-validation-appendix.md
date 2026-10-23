# 05 — Evidence and Validation Appendix

**Subject:** Event Manager (repository `robword/s48`)
**Audience:** Reviewers validating this assessment, implementation engineers, auditors, future architects
**Companion documents:** [01 Executive readout](01-executive-readout.md) · [02 Technical assessment](02-technical-architecture-assessment.md) · [03 Risk register](03-risk-register.md) · [04 Adoption roadmap](04-adoption-roadmap.md)

This appendix holds the evidence base for the whole package. Evidence IDs (`EV-nn`) are cited from the other four documents. Risk IDs (`R-nn`) are defined in the risk register. Roadmap work items (`W-nn`) are defined in the roadmap.

---

## A. Repository baseline

| Item | Value | Classification |
|---|---|---|
| Project name | "Event Manager" (built for the Startup 48 event, Eau Claire) | VERIFIED — `README.md:L1-L4`, `event-manager/inc/showcase.php:L27` |
| Repository | `robword/s48` | VERIFIED (git remote / session scope) |
| Revision assessed | `4e33d1c0944f0d278288167aad981e29c9be1c07` (tip of `main`, identical on the assessment branch) | VERIFIED — `git rev-parse` |
| Last commit date | 2026-10-22 02:27 UTC | VERIFIED — `git log` |
| Version information | **None.** No tags, no releases, no changelog, no version constant. The deploy workflow writes a `version.json` containing the short commit SHA at build time only. | VERIFIED — `git tag` empty; `.github/workflows/deploy.yml:L55-L60` |
| Assessment date | 2026-10-23 (as reported by the assessment environment) | Environment-reported |
| Languages | PHP (server, `declare(strict_types=1)` throughout), ES5/ES2017 JavaScript (browser), HTML, CSS | VERIFIED |
| Frameworks | None. No PHP framework, no Composer, no npm, no build step. | VERIFIED — no manifests present; `CONTRIBUTING.md:L7-L9` |
| Runtime requirements (declared) | PHP 8.1+, Apache with `mod_rewrite`, `mod_headers`, `AllowOverride All`; GD extension recommended; HTTPS | VERIFIED as declared — `event-manager/README.md:L177-L179` |
| Size | ~5,200 lines of first-party code and config (PHP, HTML/JS, CSS, YAML), excluding the vendored `qrcode.js` (2,297 lines), fonts, PDF and Markdown | VERIFIED — `wc -l` over tracked files |
| Git history depth | Not shallow; 41 commits, 2026-10-17 → 2026-10-22 | VERIFIED |
| Major components | Operator console (`admin/`), voting page (`vote/`), slide deck (`slides/`), pitch timer (`timer/`), showcase loop (`showcase/`), judging form (`judging/`), JSON API (`api/`), shared PHP (`inc/`), CLI (`bin/`), CI/CD (`.github/`) | VERIFIED — `event-manager/README.md:L8-L21` |

**Scope.** The whole repository is in scope. It is not a monorepo; it contains one application (`event-manager/`) plus repository-level docs and GitHub workflows.

**Scope exclusions.** The hosting environment (named in history as DreamHost shared hosting), the GitHub repository settings (branch protection, environment protection rules, private vulnerability reporting), runtime data under `data/`, and the server-side `inc/config.php` are not in the repository and were not assessed.

**Completeness limitations.** No issue tracker data, pull-request history, release artifacts, or test results exist locally. Upstream community health, current activity and any commercial support cannot be established.

---

## B. Evidence index

Paths are relative to the repository root. Line ranges were taken from the files at the assessed revision.

| Evidence ID | Finding / capability | File and line reference | Classification | Related section (doc 02) |
|---|---|---|---|---|
| EV-01 | Purpose: lightweight tools for an in-person startup/hackathon weekend: pitch timer, slides, audience voting, team selection, showcase, judging form, event save/load | `README.md:L1-L19`; `event-manager/README.md:L1-L21` | VERIFIED | §3 |
| EV-02 | Stack: plain PHP 8.1+ and static HTML/JS, no database, no build step, no CDN; Apache + PHP host | `event-manager/README.md:L3-L5`, `L177-L179`; `CONTRIBUTING.md:L7-L9` | VERIFIED (declared); runtime version compatibility UNVERIFIED | §4 |
| EV-03 | Flat-file persistence: atomic temp-file-and-rename writes; shared state under exclusive `flock`; one file per voter | `event-manager/inc/bootstrap.php:L1-L11`, `L77-L87`, `L139-L156`; `event-manager/README.md:L166-L171` | VERIFIED (implementation); correctness under load UNVERIFIED | §5, §9 |
| EV-04 | Configuration defaults; `data_dir` defaults to `event-manager/data` **inside the web root**; `config.php` override merged with `array_merge` | `event-manager/inc/bootstrap.php:L30-L55` (default at `L41`); `event-manager/inc/config.sample.php:L1-L17` | VERIFIED | §5, §8 |
| EV-05 | Web-tier protections rely on Apache `.htaccess`: HTTPS redirect, deny `data/`, `inc/`, `bin/`, dotfiles; `nosniff`, `Referrer-Policy`, `X-Frame-Options: DENY`; the app writes a deny `.htaccess` into the data dir | `event-manager/.htaccess:L1-L29`; `event-manager/inc/.htaccess`; `event-manager/bin/.htaccess`; `event-manager/inc/bootstrap.php:L17`, `L67-L75` | VERIFIED (config present); effectiveness depends on server config — UNVERIFIED | §8 |
| EV-06 | Operator auth: single password hash; PHP session with `HttpOnly`, `SameSite=Strict`, `Secure` when HTTPS detected; `use_strict_mode`; session ID regenerated at login; per-IP login throttle (5 failures / 10 min); API guard requires session + `X-CSRF-Token` on non-GET | `event-manager/inc/auth.php:L10-L25`, `L56-L67`, `L70-L95`, `L105-L114` | VERIFIED | §8.1 |
| EV-07 | Password set by CLI; `password_hash(PASSWORD_DEFAULT)`; min length 8; password accepted as argv (lands in shell history); hash file chmod 0640 | `event-manager/bin/set-password.php:L1-L31` (`L12`, `L24-L30`) | VERIFIED | §8.1 |
| EV-08 | Login/logout form posts carry a session CSRF token | `event-manager/admin/index.php:L1-L28` | VERIFIED | §8.1 |
| EV-09 | Voter identity = random 128-bit token in an `HttpOnly`, `SameSite=Lax` cookie the server issues to any visitor, valid 7 days by default | `event-manager/inc/bootstrap.php:L219-L238`; `event-manager/inc/config.sample.php:L10-L11` | VERIFIED | §8.1, §8.2 |
| EV-10 | Every load of the voting page without a cookie issues a new token and creates a marker file under `rounds/<round>/opens/` | `event-manager/vote/index.php:L11-L17`; `event-manager/inc/bootstrap.php:L240-L246` | VERIFIED | §8.2, §9 |
| EV-11 | Ballot API: strict integer validation, 1–3 votes total, pitch IDs checked against state, body capped at 8 KB, custom `X-Requested-With` header to force CORS preflight | `event-manager/api/ballot.php:L8-L63` (`L25-L27`, `L32-L58`) | VERIFIED | §8.2 |
| EV-12 | Tally recomputed by scanning every `opens/` and `ballots/` file, cached ~2 s in `tally-cache.json` | `event-manager/inc/bootstrap.php:L252-L297` | VERIFIED | §9 |
| EV-13 | Operator API state machine: add/rename/delete/move pitches, start/unstart/close/reopen voting, advance, team phases, pick moves, reset (requires typed `RESET`) | `event-manager/api/admin.php:L37-L207` | VERIFIED | §5 |
| EV-14 | Team-pick API: same device cookie; picks only during Choose/Finalize; first name ≤40 chars; skill from fixed list | `event-manager/api/pick.php:L8-L50` | VERIFIED | §5, §8.2 |
| EV-15 | Operator moves a pick by rewriting the phone's pick file without a lock shared with the phone's own write | `event-manager/api/admin.php:L163-L181`; `event-manager/api/pick.php:L48` | VERIFIED (code); impact INFERRED | §9 |
| EV-16 | Public status endpoint; `?counts=1` exposes opened/submitted counts to anyone | `event-manager/api/status.php:L9-L39` | VERIFIED | §8.5 |
| EV-17 | Public showcase endpoint returns all people (name, org, title, skills, days, first year, headshot URL) and event info **including the Wi-Fi password** | `event-manager/api/showcase.php:L1-L9`; `event-manager/inc/showcase.php:L161-L176`, `L240-L253` | VERIFIED | §8.5 |
| EV-18 | Headshot pipeline: JPEG data-URL only, magic bytes, ≤1.5 MB, ≤1200×1200, re-encoded by GD when available (strips metadata), random 128-bit file ID; served with `nosniff` and long immutable cache | `event-manager/inc/showcase.php:L205-L237`; `event-manager/api/photo.php:L1-L19` | VERIFIED | §8.2, §8.5 |
| EV-19 | Event file export/import (people + headshots base64, event info, pitch list); import refused while voting open; state and showcase are written under **two separate locks** | `event-manager/api/event.php:L81-L149` (`L122-L144`); `event-manager/README.md:L105-L116` | VERIFIED | §5, §10 |
| EV-20 | Roster CSV export with spreadsheet formula-injection guard and UTF-8 BOM | `event-manager/inc/teams.php:L101-L122`; `event-manager/api/rosters.php:L1-L15` | VERIFIED | §10 |
| EV-21 | Front end writes untrusted strings with `textContent` / DOM creation; the only string-built SVG strips `&<>"` from initials | `event-manager/vote/index.php:L185-L192`; `event-manager/showcase/index.html:L101-L112`; `event-manager/admin/index.php:L710` | VERIFIED (sampled); full-coverage UNVERIFIED | §8.2 |
| EV-22 | Errors: domain errors returned as JSON messages; unexpected exceptions logged via `error_log` and returned as generic "Server error" | `event-manager/inc/bootstrap.php:L377-L400` | VERIFIED | §8.2, §12 |
| EV-23 | HTTPS detection trusts client-supplied `X-Forwarded-Proto`; vote URL (and QR code) falls back to the request `Host` header if `public_base_url` is unset | `event-manager/inc/bootstrap.php:L338-L343`, `L352-L365` | VERIFIED | §8.3 |
| EV-24 | Client IP for throttling = `REMOTE_ADDR` only (no proxy awareness) | `event-manager/inc/bootstrap.php:L402-L405` | VERIFIED | §8.1 |
| EV-25 | Authors' own list of 11 security gaps; app described as "a light-traffic mockup" | `event-manager/README.md:L219-L245` | VERIFIED (the statement exists) | §8 |
| EV-26 | Claim: "Tested locally with 60 simultaneous voters plus 10 page-only visits: every ballot was saved and counted." | `event-manager/README.md:L173` | UNVERIFIED — no test script, harness or results in the repo | §9 |
| EV-27 | CI on PR/push: PHP syntax lint (`php -l`), check that `data/` and `inc/config.php` are not tracked, regex grep for three secret patterns; action pinned to SHA; `contents: read` | `.github/workflows/ci.yml:L1-L33` | VERIFIED (config); CI run results UNKNOWN | §8.4, §9 |
| EV-28 | Deploy workflow: manual `workflow_dispatch`; GitHub `production` environment; required `DEPLOY_KNOWN_HOSTS` (no TOFU); rsync over SSH restricted by `rrsync`; marker-file guard; `--delete` excluding `data/` and `inc/config.php`; post-deploy version and protected-path checks | `.github/workflows/deploy.yml:L1-L128` (`L7-L9`, `L21-L22`, `L68-L91`, `L93-L103`, `L105-L128`) | VERIFIED (config); environment protection rules UNVERIFIED | §8.4, §12 |
| EV-29 | Dependabot configured only for GitHub Actions (there is no other package ecosystem) | `.github/dependabot.yml:L1-L6` | VERIFIED | §8.4 |
| EV-30 | MIT license for code (© 2026 robword and contributors); NOTICE excludes Startup 48 name, logo, palette-as-brand, judging form and deck text/schedule from MIT | `LICENSE:L1-L21`; `NOTICE.md:L1-L33` | VERIFIED (declaration) | §11 |
| EV-31 | Fonts: Poppins and Raleway (SIL OFL 1.1), Ubuntu (Ubuntu Font Licence 1.0); license texts shipped | `event-manager/assets/fonts/licenses/*.txt`; `NOTICE.md:L24-L33` | VERIFIED (texts present); provenance of the subsetted WOFF2 files UNVERIFIED | §11 |
| EV-32 | Vendored `qrcode.js` header: "QR Code Generator for JavaScript, Copyright (c) 2009 Kazuhiko Arase", MIT; README says version 1.4.4 but the file carries no version string | `event-manager/assets/qrcode.js:L1-L5`; `event-manager/README.md:L245` | Header VERIFIED; version UNVERIFIED | §8.4, §11 |
| EV-33 | Contribution policy requires DCO sign-off; vulnerabilities to be reported via GitHub private vulnerability reporting | `CONTRIBUTING.md:L13-L16`, `L27-L39` | VERIFIED (policy text); private reporting enabled UNVERIFIED | §13 |
| EV-34 | History: 41 commits over ~5 days; 40 authored as `Claude <noreply@anthropic.com>` (an AI coding agent identity), 1 by `robword`; **0 commits carry `Signed-off-by`**; no tags | `git log` | VERIFIED | §13 |
| EV-35 | Absent artifacts: no automated tests, no `SECURITY.md`, no CHANGELOG, no releases, no API spec, no architecture docs beyond README, no IaC/containers | Repository file listing (52 tracked files) | VERIFIED (absence in repo) | §9, §13 |
| EV-36 | Organization-specific content in code: default header `STARTUP 48 · EAU CLAIRE · <year>`; event file format `startup48-event`; deck schedule and judging form content | `event-manager/inc/showcase.php:L27`; `event-manager/api/event.php:L16`; `NOTICE.md:L7-L21` | VERIFIED | §11, §15 |
| EV-37 | Old voting rounds (ballots, opens, picks with first names) are never deleted, by design | `event-manager/api/admin.php:L191-L199`; `event-manager/README.md:L139` | VERIFIED | §8.5 |
| EV-38 | Judging notes stored only in the judge's browser `localStorage`; nothing sent to server | `event-manager/judging/index.html:L11`, `L153-L165` | VERIFIED | §8.5, §15 |
| EV-39 | No export of voting results; the only operator downloads are the roster CSV and the event file | `event-manager/admin/index.php:L274`; `event-manager/api/` listing | VERIFIED | §10, §15 |
| EV-40 | Pitch-timer tone settings readable publicly | `event-manager/api/timer.php:L1-L9` | VERIFIED | §8.5 |
| EV-41 | Developed for and documented against shared hosting with `rrsync` | `event-manager/README.md:L5`, `L203-L207` | VERIFIED | §12 |
| EV-42 | Ballot and pick endpoints read state without the state lock, then write; a ballot can land just after "close" | `event-manager/api/ballot.php:L13`, `L28-L30`, `L60-L61`; `event-manager/api/pick.php:L13`, `L31-L33`, `L48` | VERIFIED (code path); practical impact INFERRED | §9 |
| EV-43 | Single-node design: local filesystem state, `flock`, default PHP file sessions (no custom handler) | `event-manager/inc/auth.php:L10-L25`; `event-manager/inc/bootstrap.php:L139-L156` | VERIFIED (code); NFS/cluster behaviour INFERRED | §9, §12 |
| EV-44 | Accessibility signals: all pages declare `lang="en"`; `aria-`/`role` attributes: admin 9, timer 5, vote 2, showcase 1, slides 0, judging 0; English-only strings | grep across `event-manager/*/index.*` | VERIFIED (counts); WCAG conformance UNKNOWN | §15 |
| EV-45 | Clients poll: slides every 3 s, operator console every 3 s, showcase every 5 s, timers every 30 s; showcase reloads on `version.json` change | `event-manager/README.md:L41`, `L74`, `L97-L98`, `L133`; `event-manager/showcase/index.html:L280-L290`, `L320-L323`, `L353` | VERIFIED | §5, §9 |
| EV-46 | Deploy comment says secrets are "limited to main by the setup script"; no such script is in the repository | `.github/workflows/deploy.yml:L21` | UNVERIFIED | §8.4 |
| EV-47 | No privacy notice on the voting/team-pick page; page only says cookies are needed | `event-manager/vote/index.php:L262` (grep for "privacy" returned nothing) | VERIFIED (absence in that file) | §8.5 |
| EV-48 | No HSTS or Content-Security-Policy headers; pages use inline scripts (acknowledged by authors) | `event-manager/.htaccess:L20-L29`; `event-manager/README.md:L237-L238` | VERIFIED | §8.3 |

---

## C. Dependency inventory

This is **not an SBOM**. The application has no package manager. Everything it runs on is listed below.

| Dependency | Type | How included | Architectural significance | Concerns |
|---|---|---|---|---|
| PHP ≥ 8.1 | Runtime | Host-provided | Entire server tier | PHP 8.1 reached end of upstream security support before the assessment date (per the PHP project's published lifecycle; not verifiable from the repo). Compatibility with currently supported PHP versions is UNVERIFIED — CI installs whatever `php-cli` Ubuntu provides (`ci.yml:L20`) and only lints. |
| PHP extensions: `json`, `session`, `hash`; optional `mbstring`, `gd` | Runtime | Host-provided | GD strips photo metadata; without it photos are stored as uploaded after validation (`showcase.php:L218-L226`) | Behaviour degrades silently if GD is missing — metadata (e.g., EXIF/GPS) may be retained. |
| Apache httpd with `mod_rewrite`, `mod_headers`, `AllowOverride All` | Web server | Host-provided | **Security-critical**: storage/code isolation and HTTPS redirect live in `.htaccess` | Non-Apache servers ignore these rules (R-07). |
| Local POSIX filesystem with working `flock` and atomic `rename` | Storage | Host-provided | Sole persistence layer | Network filesystems may not honour `flock`; single node only (R-13). |
| `qrcode.js` (Kazuhiko Arase, MIT) | Vendored JS library | Copied into `assets/` | Renders the vote QR code | No version marker or checksum; no update mechanism (R-19). |
| Poppins, Raleway, Ubuntu fonts (WOFF2 subsets) | Static assets | Copied into `assets/fonts/` | Branding only | License obligations travel with the files (§E). |
| GitHub Actions: `actions/checkout` (pinned SHA, v7.0.1 per comment) | CI/CD | Workflow | Build and deploy | SHA-pinned (good). Dependabot keeps it current. |
| Ubuntu runner packages (`php-cli`, `php-gd`, `rsync`, `ssh`, `curl`, `jq`) | CI/CD | `apt-get` / runner image | Lint and deploy | Unpinned; acceptable for lint, relevant for reproducibility. |
| `rrsync` on target host | Deploy target | Host-provided | Restricts the deploy key to one directory | Host-specific (documented for DreamHost). |
| Browser APIs: XHR/fetch, `localStorage`, Web Audio, `<audio>`, Wake Lock, Fullscreen, `DOMParser` | Client | Browser | Timer audio, judging notes, slides | Older/locked-down browsers may degrade; untested here. |

External network services contacted by the application at runtime: **none observed**. No CDN, analytics, telemetry or third-party API calls were found in the server or client code (VERIFIED for the files inspected; `CONTRIBUTING.md:L7` states "no CDN").

---

## D. Integration inventory

| Interface | Protocol / format | AuthN / AuthZ | Purpose | Stability / documentation |
|---|---|---|---|---|
| `api/status.php` | HTTPS GET, JSON | Public | Voting status, pitches, team board; `?counts=1` participation counts | Internal, undocumented beyond README prose, unversioned |
| `api/ballot.php` | HTTPS GET/POST, JSON | Device cookie; POST needs `X-Requested-With: s48` | Read/submit own ballot | Internal, unversioned |
| `api/pick.php` | HTTPS GET/POST, JSON | Device cookie; POST needs `X-Requested-With: s48` | Read/submit own team pick | Internal, unversioned |
| `api/admin.php` | HTTPS GET/POST, JSON `{action,…}` | Operator session; POST needs `X-CSRF-Token` | Event control and dashboard | Internal, action-dispatch RPC style, unversioned |
| `api/showcase-admin.php` | HTTPS GET/POST, JSON (photos as data URLs, ≤2.5 MB body) | Operator session + CSRF | Manage organizers/judges/mentors and event info | Internal, unversioned |
| `api/event.php` | HTTPS GET (download) / POST | Operator session + CSRF | Event file export/import (format `startup48-event`, `version: 1`) | The only versioned format in the app |
| `api/rosters.php` | HTTPS GET, CSV (UTF-8 BOM) | Operator session | Team roster export | Stable column list in code |
| `api/showcase.php` | HTTPS GET, JSON | Public | Showcase loop data | Internal |
| `api/photo.php?id=` | HTTPS GET, JPEG | Public (unguessable 128-bit ID) | Headshots | Internal |
| `api/timer.php` | HTTPS GET, JSON | Public | Timer tone settings | Internal |
| `version.json` | Static JSON | Public | Deployed commit SHA, used by showcase auto-reload and deploy verification | Generated at deploy |
| Timer deep link `timer/?d=&w=&label=` | URL query | Public | Bookmarkable custom timers | Documented in README |
| GitHub Actions → host | SSH + rsync (`rrsync`) | SSH key restricted to a directory; pinned host key | Deployment | Documented |

**Not present:** SSO (OIDC/SAML/LDAP), SCIM, webhooks, message queues, event streams, OpenAPI/GraphQL/gRPC specs, pagination, rate limiting, idempotency keys, API tokens for machine clients, syslog/SIEM forwarding, metrics endpoints.

---

## E. Licensing inventory

| Item | Declared license | Evidence | Observations | Legal question |
|---|---|---|---|---|
| Application source code | MIT, © 2026 robword and contributors | `LICENSE` | Permissive; modification, internal use and redistribution allowed with notice retention. No network-use (AGPL-style) obligations. | Confirm the copyright holder can license AI-assisted contributions under MIT (40 of 41 commits carry an AI-agent author identity, EV-34). Ownership of AI-generated code is a jurisdiction-dependent legal question. |
| Startup 48 name, logo SVGs, wordmark, palette-as-brand | **Not MIT** — reserved to Startup 48 organizers | `NOTICE.md:L7-L21` | Embedded in UI, CSS tokens, default header text and file-format names (EV-36). | Any adopter must remove/replace branding; trademark review if the event is externally visible. |
| Judging form (`judging/index.html`, `judging/judging-form.pdf`) and slide-deck text/schedule | **Not MIT** | `NOTICE.md:L17-L19` | The judging web page is code *and* reserved content in one file. | Must be replaced, or permission obtained, before use. |
| Poppins, Raleway | SIL OFL 1.1 | `assets/fonts/licenses/` | Permissive for embedding; Reserved Font Name rules apply to modified versions (the WOFF2 files are subsets/conversions). | Confirm subsetted files comply with the RFN clause as published by the font projects. |
| Ubuntu font family | Ubuntu Font Licence 1.0 | `assets/fonts/licenses/Ubuntu-UFL.txt` | Permits use and redistribution; has its own conditions on modified versions. | Same subset/rename question as above. |
| `qrcode.js` | MIT (Kazuhiko Arase) | `assets/qrcode.js:L1-L5` | Compatible with project MIT. "QR Code" is a DENSO WAVE trademark (`NOTICE.md:L29`). | None material. |
| Third-party SaaS / data processors | None observed | — | No outbound data transmission found. | — |

**Inconsistency:** `CONTRIBUTING.md` requires DCO sign-off on every commit (L27-L39), yet none of the 41 commits has a `Signed-off-by` trailer (EV-34) — CONFLICTING EVIDENCE between stated policy and practice. Provenance for contributions therefore rests on authorship metadata, not DCO attestations.

---

## F. Unknowns and validation requirements

| # | Question | Why it matters | Evidence inspected | What would resolve it | Validation method | Responsible role | Blocks production? |
|---|---|---|---|---|---|---|---|
| U-01 | Does the application function correctly on the organization's target PHP version (8.2/8.3/8.4)? | PHP 8.1 is past upstream security support; running unsupported runtimes is a policy violation in most enterprises | `ci.yml:L18-L22` (lint only); README requirement | Successful functional test run on a supported PHP version | Deploy to test host; run the end-to-end event flow; enable `E_ALL` and inspect logs | Platform engineer | **Yes** |
| U-02 | Do the `.htaccess` protections actually apply on the target server? | `data/` holds the operator password hash, ballots and personal data | `.htaccess`, `deploy.yml:L117-L128` | HTTP probes of protected paths return 403/404 | Run the deploy workflow's protected-path checks or equivalent `curl` probes | Platform engineer / security engineer | **Yes** |
| U-03 | Does the concurrency design hold at the organization's expected audience size and on its filesystem? | Event-night failure is unrecoverable (no second chance to vote) | `bootstrap.php`, README `L173` (unverified claim) | Load test with ≥2× expected attendees, from mobile networks | Scripted load test against a staging host; inspect ballot files vs. submitted count | Performance / QA engineer | **Yes** for events above pilot scale |
| U-04 | Is result integrity requirement "advisory" or "binding"? | Determines whether R-01 is acceptable | README `L223-L226` | Business owner decision | Stakeholder workshop | Business sponsor | **Yes** |
| U-05 | Is GitHub private vulnerability reporting enabled and monitored? | Only documented vulnerability intake path | `CONTRIBUTING.md:L13-L16` | Repository settings screenshot / maintainer confirmation | Inspect repo settings via GitHub | Open-source program office (OSPO) | No (pilot); Yes if consuming upstream updates |
| U-06 | Are the `production` environment protection rules and branch protection on `main` actually configured? | Whoever can merge to `main` can deploy (`event-manager/README.md:L207`) | `deploy.yml:L21-L22` (comment refers to an absent "setup script", EV-46) | Settings inspection | GitHub settings review | DevOps / repository admin | Yes if the upstream deploy workflow is reused |
| U-07 | Does the host's default umask / home-directory permissions expose `data/` files to other tenants on shared hosting? | Ballots, names, headshots, password hash | `bootstrap.php:L59-L65` (dirs 0775), `set-password.php:L30` | `ls -l` on the host; tenant isolation documentation | Host inspection | Platform engineer | **Yes** on shared hosting |
| U-08 | Who owns AI-generated contributions, and is the MIT grant effective? | License validity and indemnity posture | `LICENSE`, `git log` authorship | Legal opinion | Qualified legal review | Legal counsel | Yes for redistribution; organizational policy decides for internal use |
| U-09 | Are front-end pages free of DOM XSS in all code paths (not just those sampled)? | Operator console XSS would yield full event control | Sampled sinks (EV-21) | Full review | SAST + manual review of all `innerHTML` sinks; DAST | Security engineer | **Yes** for internet-facing use |
| U-10 | What accessibility level do the voting and team-pick pages meet? | Participants with disabilities must be able to vote; may be a legal obligation | EV-44 | WCAG 2.x audit | Automated + assistive-technology testing | Accessibility specialist | Depends on organizational policy |
| U-11 | Is the upstream project still maintained, and by whom? | Patch supply | Local git history (5 days) | Current upstream activity | Review GitHub activity, issues, maintainer contact | OSPO / architect | No for pilot; Yes for long-term adoption |
| U-12 | Which personal data will actually be entered (headshots, names, employer, skills) and under which legal basis? | Privacy obligations (notice, retention, deletion) | EV-17, EV-37, EV-47 | Data-protection impact screening | DPIA / privacy review | Privacy officer / DPO | **Yes** where privacy law applies |

---

## G. Suggested subsequent technical validation

These are recommendations for later work. None of them was performed during this assessment.

| Activity | Scope and purpose | Linked risks |
|---|---|---|
| SAST and manual secure-code review | All PHP endpoints and every `innerHTML` / string-concatenated HTML sink in `admin/`, `slides/`, `showcase/`, `timer/` | R-09, U-09 |
| Secret scanning | Full git history with a maintained scanner (CI only greps three patterns, `ci.yml:L30-L33`) | R-19 |
| Vendored-library verification | Diff `qrcode.js` against the upstream release it claims to be; record version and checksum | R-19 |
| Dynamic application security testing | Auth bypass, CSRF, session handling, Host/X-Forwarded-Proto manipulation, protected path probes | R-03, R-07, R-10 |
| Abuse / integrity testing | Scripted cookie-minting ballot stuffing and marker-file flooding to quantify R-01/R-02 and tune compensating controls | R-01, R-02 |
| Load and concurrency testing | Realistic burst (everyone votes within ~60 s) at 2× expected audience, over mobile networks, on the target host and filesystem | R-13, U-03 |
| Deployment validation | Fresh install from README; verify `data_dir` outside web root, config, HTTPS, headers | R-07, U-02 |
| Runtime compatibility | Functional test on currently supported PHP versions with full error reporting | U-01 |
| Backup and restore test | Back up `data_dir` mid-vote, restore to a clean host, confirm tally and team picks match | R-05 |
| Failover / rehearsal | Full event dry run, including host loss and operator laptop loss | R-05 |
| Identity integration test | Reverse-proxy SSO in front of operator paths; confirm public paths remain reachable and operator paths are not | R-03 |
| Accessibility review | WCAG 2.x AA on `vote/` and team-pick flow, keyboard and screen reader | R-17 |
| Licensing review | Branding removal, font subsetting compliance, AI-authorship question | R-12, U-08 |
| Upstream governance review | Current repository activity, maintainer responsiveness, security reporting | R-04, U-05, U-11 |

---

## H. Assessment limitations

Static inspection of this repository **cannot** establish:

1. **That the software runs.** Nothing was executed, built, deployed or tested. Syntax validity, runtime behaviour and PHP-version compatibility are unconfirmed.
2. **Performance or capacity.** The README's 60-voter test (EV-26) has no reproducible artifact. No throughput, latency or capacity figures are asserted anywhere in this package.
3. **Absence of vulnerabilities.** Sampling of output sinks and server validation found sound patterns, but no full SAST/DAST was run, and no vulnerability database was consulted.
4. **Effectiveness of web-server controls.** `.htaccess` rules work only on Apache with the right modules and overrides.
5. **GitHub configuration.** Branch protection, environment rules, secret scoping and private vulnerability reporting are settings, not repo content.
6. **Hosting security.** Tenant isolation, file permissions, TLS configuration and backups on the host are unknown.
7. **Upstream health.** Five days of local history cannot show community size, responsiveness, funding or roadmap.
8. **Legal conclusions.** License observations are technical; they are not legal advice.
9. **Organizational fit.** No business requirements, risk appetite, regulatory scope or expected audience size were provided; recommendations are conditional on these.
