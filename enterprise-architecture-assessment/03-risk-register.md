# 03 — Risk Register

**Subject:** Event Manager (`robword/s48`) at revision `4e33d1c`
**Audience:** Security, IT risk management, architecture governance, implementation owners
**Evidence:** `EV-nn` IDs are defined in [05 — Evidence and Validation Appendix](05-evidence-and-validation-appendix.md). Remediation work items `W-nn` are defined in [04 — Adoption Roadmap](04-adoption-roadmap.md).

## How to read this register

- **Severity** uses the ARB scale (Critical / High / Medium / Low / Informational). It reflects the effect on *responsible enterprise adoption*, not the number of findings.
- **Likelihood** is stated for the realistic deployment pattern of this software: a public HTTPS site that attendees reach from personal phones during a live event. Where likelihood depends on use case, the register says so.
- **Confidence** reflects the strength of repository evidence (High = directly in code; Medium = code plus inference; Low = largely inferred).
- The register contains only findings with repository evidence. Unverified assumptions and open questions are listed separately in [Evidence gaps and open questions](#evidence-gaps-and-open-questions).
- **No Critical risks were identified.** No exploitable vulnerability that grants unauthorized operator access or data disclosure beyond what the design intentionally exposes was found in the code paths inspected. Several High risks are *design* limitations the authors themselves acknowledge (`EV-25`).

Adoption gate key: G1 Security & vulnerability management · G2 Identity & access · G3 Licensing & legal · G4 Data protection & privacy · G5 Operational supportability & recovery · G6 Sustainable maintenance · G7 Architecture & integration · G8 Functional suitability.

## Summary register (prioritized)

| ID | Domain | Risk / finding | Severity | Likelihood | Confidence | Evidence | Mitigation | Responsible role | Adoption gate |
|---|---|---|---|---|---|---|---|---|---|
| R-01 | Security (integrity) | Voting and team-pick identity is a self-issued device cookie; anyone can obtain unlimited ballots by clearing cookies, using private windows, extra devices, or a script | **High** | High for any contested outcome; Low for a friendly advisory poll | High | EV-09, EV-10, EV-11, EV-14, EV-25 | Limit to advisory use; or implement participant codes / SSO-bound voter identity (W-08); anomaly review of ballot timestamps (W-16) | Business sponsor (acceptance); application owner (fix) | G1, G8 |
| R-02 | Security (availability) | Unauthenticated endpoints create files with no rate limit or quota; each cookieless page load writes a marker file, and the tally rescans every file | **High** | Medium (requires intent; trivially scriptable) | High | EV-10, EV-12, EV-25 | WAF / reverse-proxy rate limiting (W-09); per-round file cap and disk quota (W-10); `data_dir` on its own volume | Platform engineer; security engineer | G1, G5 |
| R-03 | Security (identity) | Single shared operator password: no named accounts, roles, MFA, SSO, session timeout policy, or audit log; throttle keyed on `REMOTE_ADDR` | **High** | Medium | High | EV-06, EV-07, EV-24, EV-25 | Put operator paths behind an enterprise SSO reverse proxy with MFA (W-06); access logging at proxy (W-13); rotate password per event (W-07) | Identity/IAM engineer; application owner | G2 |
| R-04 | Sustainability | Project is ~5 days old, has one human maintainer, 40 of 41 commits authored under an AI-agent identity, no tests, releases, tags, changelog or security policy; authors call it "a light-traffic mockup" | **High** | High (that upstream will not provide enterprise-grade maintenance) | High (repo facts); Low (upstream future) | EV-25, EV-34, EV-35 | Treat as internally owned code: fork, assign an owner, own patching (W-04, W-20) | Architecture owner; engineering manager | G6 |
| R-05 | Operations (recovery) | No backup, restore, retention or HA mechanism; all live data is files on one host; event-file export covers setup only (not ballots/picks) | **High** for business-critical events; Medium otherwise | Medium | High | EV-03, EV-19, EV-25, EV-28, EV-43 | Host-level snapshots / scheduled copy of `data_dir` during events (W-11); documented restore drill (W-17); paper fallback for the vote | Platform engineer; event operations lead | G5 |
| R-06 | Engineering quality | No automated tests; CI is syntax lint plus a narrow secret grep; the only load-test claim is unverifiable | Medium | High (that regressions go undetected) | High | EV-26, EV-27, EV-35 | Add an end-to-end smoke test and concurrency test before each event (W-05, W-15) | Application owner / QA | G6, G5 |
| R-07 | Security (configuration) | Protection of `data/`, `inc/`, `bin/` and HTTPS enforcement depend on Apache `.htaccess`; default `data_dir` is inside the web root | Medium | Low on correctly configured Apache; High on nginx/IIS/containers with `AllowOverride None` | High | EV-04, EV-05, EV-28 | Mandate `data_dir` outside web root (W-03); verify protected paths after every deploy (W-12) | Platform engineer | G1, G4 |
| R-08 | Privacy | Personal data (names, employers, titles, headshots, first names + skills of participants) has no retention or deletion mechanism; old rounds kept forever; public showcase API returns all showcase data including the Wi-Fi password; no participant privacy notice | Medium (High where GDPR-type law applies) | Medium | High | EV-17, EV-18, EV-37, EV-47 | DPIA screening (W-02); retention/purge procedure (W-14); privacy notice on voting page (W-14); keep Wi-Fi password out of the API if it is not public | Privacy officer; application owner | G4 |
| R-09 | Security (hardening) | No Content-Security-Policy or HSTS; pages rely on inline scripts; front-end output encoding verified only by sampling | Medium | Low | Medium | EV-21, EV-48 | Add HSTS at the proxy now; CSP with hashes/nonces after review of inline scripts (W-18); full sink review (W-05) | Security engineer | G1 |
| R-10 | Security (configuration) | HTTPS detection trusts client `X-Forwarded-Proto`; if `public_base_url` is unset, the QR/vote URL is built from the `Host` header | Low | Low (needs misrouted vhost or cache) | High | EV-23 | Make `public_base_url` mandatory (W-03); strip/overwrite `X-Forwarded-Proto` at the proxy | Platform engineer | G1 |
| R-11 | Integration | No SSO, SCIM, webhooks, documented or versioned API, or results export; integration is limited to a roster CSV and an event JSON file | Medium | High (that integration needs require custom code) | High | EV-20, EV-39, §D of doc 05 | Accept as standalone tool; add results CSV export if records are required (W-19) | Solution architect | G7 |
| R-12 | Licensing / branding | Startup 48 branding, judging form and deck content are not MIT-licensed and are embedded in the UI and defaults; DCO policy stated but no commit is signed off; AI-authorship of most code raises an ownership question | Medium | High (that rebranding is needed) | High | EV-30, EV-33, EV-34, EV-36 | Rebrand and replace reserved content (W-07); legal review (W-01) | Legal counsel; application owner | G3 |
| R-13 | Scalability / architecture | Single-node design (local files, `flock`, file sessions); cannot be load-balanced or made highly available without redesign | Medium (High for HA requirements) | High (that it cannot meet HA) | High (code); Medium (NFS behaviour inferred) | EV-03, EV-43 | Size a single host generously; do not place `data_dir` on network storage; reject HA use cases | Solution architect | G7, G5 |
| R-14 | Operations (observability) | Only `error_log` on unexpected exceptions; no operator audit trail, request logs, metrics or health endpoint beyond what the web server provides | Medium | High | High | EV-22 | Proxy/web-server access logs to SIEM (W-13); synthetic check on `api/status.php` (W-13) | SecOps; platform engineer | G5 |
| R-15 | Runtime lifecycle | Declared baseline is PHP 8.1; compatibility with currently supported PHP versions is untested (CI lints with whatever Ubuntu ships) | Medium | Medium | Medium | EV-02, EV-27 | Validate on a supported PHP version (W-05) | Platform engineer | G1, G6 |
| R-16 | Data integrity (concurrency) | Ballots/picks check status without the state lock, so a ballot can land just after "close"; operator "move" and a phone's own re-pick can overwrite each other | Low | Low | High (code) / Medium (impact) | EV-15, EV-42 | Accept for advisory use; or take a shared lock in ballot/pick writes (W-16) | Application owner | G8 |
| R-17 | Accessibility / functional | Little ARIA on slides and judging pages; English only; no WCAG evidence | Low (Medium where accessibility is a legal duty) | Medium | Medium | EV-44 | Accessibility review of `vote/` (W-14) | Accessibility specialist | G8 |
| R-18 | Data integrity | Event import writes state and showcase under two separate locks; README says a failed load leaves the install unchanged, which holds only for validation failures | Low | Low | High | EV-19 | Document; snapshot `data_dir` before import (W-11) | Application owner | G5 |
| R-19 | Supply chain | Vendored `qrcode.js` has no version marker or checksum; no SBOM; secret scanning is a three-pattern grep. (Positive: no package dependencies; Actions pinned by SHA; Dependabot for Actions) | Low | Low | High | EV-27, EV-29, EV-32 | Record version/checksum and verify against upstream (W-05); proper secret scanning in CI (W-05) | Security engineer | G1 |
| R-20 | Credential hygiene | `set-password.php` accepts the password as a CLI argument (shell history); password minimum 8 chars, no complexity or rotation; hosting tenant isolation of `data/` unknown | Low | Low | High | EV-07, U-07 | Use the interactive prompt only; long random password per event (W-07); verify host permissions (W-03) | Application owner; platform engineer | G2 |
| R-21 | Information exposure | Public `api/status.php?counts=1` and `api/timer.php` expose participation counts and settings by design | Informational | — | High | EV-16, EV-40 | Accept, or remove `counts` if sensitive | Application owner | G4 |
| R-22 | Governance | Deploy workflow says secrets are restricted to `main` by a "setup script" that is not in the repository; whoever can merge to `main` can deploy | Informational (Medium if the upstream workflow is reused) | — | Medium | EV-28, EV-46 | Reuse the workflow only with verified environment protection and required reviews (W-12) | DevOps lead | G6 |

---

## Expanded entries — High risks

### R-01 — Vote and team-pick integrity depends on a self-issued device cookie

| Field | Detail |
|---|---|
| **Description** | The system intends "one ballot per device" but has no voter authentication. The server issues a random token to whoever loads the voting page and accepts one ballot per token. |
| **Observed condition** | `s48_voter_token(true)` issues a new 128-bit token whenever the cookie is absent or malformed (`inc/bootstrap.php:L219-L238`). `vote/index.php:L11-L17` calls it on every page load. `api/ballot.php` accepts any valid token and replaces that token's ballot (`L8-L63`). The same token identifies team picks (`api/pick.php:L8-L50`). |
| **Evidence** | EV-09, EV-10, EV-11, EV-14; acknowledged by the authors as gap #1 (`event-manager/README.md:L223-L226`). |
| **Threat / failure scenario** | A participant with a motive (their idea is pitching) opens the page in private windows or runs a short script that fetches `vote/`, captures the cookie, and posts three votes; repeated N times, this adds 3N votes. The operator console shows inflated "opened" and "submitted" counts but cannot distinguish fake ballots. For team picks, fake entries with invented first names can fill a team's count. |
| **Business and technical consequences** | Incorrect advancing ideas and team composition; reputational harm and participant disputes; if outcomes carry prizes, funding, hiring or performance consequences, potential fairness complaints. No technical way to audit after the fact (ballot files record only votes and a timestamp). |
| **Exploitability** | High — no special skill required; the API is simple and the anti-CSRF header (`X-Requested-With`) is not an authentication control. |
| **Existing mitigating factors** | The vote is in-person, short (minutes), and visible; anomalies (more submissions than attendees) are noticeable on the console. Vote counts are validated strictly (1–3, known pitch IDs). Results are only shown to the operator. |
| **Recommended remediation** | Bind voter identity to something scarce: one-time participant codes printed on badges, or enterprise SSO for internal events (the ballot API keys on a single identity, so this is a contained change per `README.md:L224-L225`). |
| **Compensating controls** | Use only for advisory outcomes with a human decision; restrict access to the venue network where feasible; compare "submitted" to headcount and void the vote if it exceeds attendance; rate limit per IP (R-02). |
| **Accountable role** | Business sponsor (risk acceptance); application owner (remediation). |
| **Validation / acceptance criteria** | Either (a) a signed risk acceptance stating the vote is advisory, or (b) a test showing that a second ballot without a valid participant credential is rejected. |
| **Dependencies** | W-08 depends on the decision in W-02/U-04 (advisory vs. binding). |
| **Residual risk** | With participant codes: code sharing remains possible (Low). With advisory-only acceptance: risk is accepted, not reduced. |
| **Block production?** | **Yes** for any vote whose result is binding or consequential. **No** for a pilot with an explicitly advisory vote. |

### R-02 — Unbounded, unauthenticated file creation and no rate limiting

| Field | Detail |
|---|---|
| **Description** | Public endpoints write to disk with no quota, and the tally scans all files. |
| **Observed condition** | Each cookieless `GET vote/` touches a new file in `rounds/<round>/opens/` (`inc/bootstrap.php:L240-L246`). Ballot and pick POSTs each write one file per token. `s48_round_counts` scans the `opens/` and `ballots/` directories and parses each ballot whenever the 2-second cache has expired (`L252-L297`); the console and slides poll every 3 s (EV-45). There is no per-IP limit, CAPTCHA, or file cap. |
| **Evidence** | EV-10, EV-12, EV-45; authors' gap #2 (`README.md:L227-L228`). |
| **Threat / failure scenario** | A script issues hundreds of thousands of cookieless requests during the event. Inode or disk quota on shared hosting is exhausted; `s48_write_atomic` then fails and every ballot and state change returns "Could not save data". Even before exhaustion, directory scans slow the tally and the console. |
| **Business and technical consequences** | Event-night outage at the moment of highest visibility; the vote cannot be re-run with the same audience attention; shared-hosting account suspension is possible. |
| **Exploitability** | Medium — needs intent and a simple script; no authentication required. |
| **Existing mitigating factors** | Request bodies are capped (8 KB ballot, 4 KB pick); filenames are fixed-length hashes, so no path traversal; rounds are short-lived. |
| **Recommended remediation** | Rate limit `vote/`, `api/ballot.php`, `api/pick.php` per client IP at a reverse proxy/WAF; add a per-round file-count cap in `s48_record_open` and ballot/pick writes; place `data_dir` on a dedicated volume with alerting. |
| **Compensating controls** | Venue-network-only access; CDN/WAF in front of the host; monitor disk usage during the event. |
| **Accountable role** | Platform engineer (proxy/WAF); security engineer (limits). |
| **Validation / acceptance criteria** | Load/abuse test: a flood from one source is throttled while legitimate voters from other addresses still submit successfully; disk usage stays bounded. |
| **Dependencies** | W-09 depends on the hosting decision (W-03). |
| **Residual risk** | Distributed abuse from many IPs (Low for the typical audience). |
| **Block production?** | **Yes** for an internet-reachable deployment without a rate-limiting proxy. No for a venue-network-only pilot. |

### R-03 — Single shared operator credential, no SSO, MFA, roles or audit

| Field | Detail |
|---|---|
| **Description** | All operator capabilities sit behind one password. |
| **Observed condition** | `s48_admin_hash()` reads one hash (`inc/auth.php:L27-L34`); login sets `$_SESSION['s48_admin'] = true` (`L91-L93`); there is no user identity, role, or record of who did what. Throttling uses `REMOTE_ADDR` (`bootstrap.php:L402-L405`), so behind a proxy all clients share one bucket — an attacker can lock out operators, and the throttle does not protect against a distributed guesser. |
| **Positive controls** | bcrypt via `password_hash`, constant-time CSRF comparison, `SameSite=Strict` + `HttpOnly` session cookie, strict session mode, session ID regeneration at login, CSRF header on every state-changing API call, 300 ms delay on failure (EV-06, EV-08). These are sound for a single-credential design. |
| **Evidence** | EV-06, EV-07, EV-24; authors' gap #5 (`README.md:L233-L234`). |
| **Threat / failure scenario** | The password is shared among organizers by chat or email and remains valid after the event; a former helper or an attacker with the leaked password resets the vote, moves people between teams, or downloads the event file (names, headshots, Wi-Fi password). Nothing records who acted. |
| **Business and technical consequences** | Unattributable changes to outcomes; personal-data exposure; inability to meet audit expectations (SOC 2 / ISO 27001 access-control and logging criteria) if the tool falls in scope. |
| **Exploitability** | Medium — depends on password handling practices. |
| **Recommended remediation** | Front the operator surface (`admin/`, `api/admin.php`, `api/showcase-admin.php`, `api/event.php`, `api/rosters.php`) with an SSO-enforcing reverse proxy (OIDC/SAML) requiring MFA and group membership, and log identities at the proxy. Keep the app password as a second factor of defense, rotated per event. Long term, add per-user identity from a trusted proxy header. |
| **Compensating controls** | Proxy allow-list; per-event password rotation; review proxy logs after each event. |
| **Accountable role** | IAM engineer; application owner. |
| **Validation / acceptance criteria** | Unauthenticated requests to each operator path are redirected to the IdP; public paths (`vote/`, `slides/`, `showcase/`, `timer/`, `api/status.php`, `api/ballot.php`, `api/pick.php`, `api/showcase.php`, `api/photo.php`, `api/timer.php`) still work without SSO; proxy logs show the named operator for each POST. |
| **Dependencies** | Requires a reverse proxy in front of the host (often not available on shared hosting — W-03). |
| **Residual risk** | Within the app, all authenticated operators remain equal (no roles). Low for a small organizer team. |
| **Block production?** | **Conditional.** Not a blocker for a pilot with a rotated password and a small, named operator group. Blocker where organizational policy forbids shared credentials or requires SSO/MFA for any system holding personal data. |

### R-04 — Project maturity and maintainer concentration

| Field | Detail |
|---|---|
| **Description** | The project is a very young, single-maintainer codebase without release engineering. |
| **Observed condition** | 41 commits from 2026-10-17 to 2026-10-22; author identities: 40 × `Claude <noreply@anthropic.com>`, 1 × `robword`; no tags or releases; no tests; no `SECURITY.md` (only a sentence in CONTRIBUTING); no changelog or compatibility policy; README calls the app "a light-traffic mockup" with 11 open security gaps. |
| **Evidence** | EV-25, EV-34, EV-35. |
| **Threat / failure scenario** | A vulnerability or PHP-version incompatibility is found; no upstream fix, advisory, or versioned release follows; the adopter has no notification mechanism. Alternatively, upstream changes behaviour (e.g., data layout) without migration notes. |
| **Business and technical consequences** | The adopting organization effectively becomes the maintainer. Without assigned ownership the deployment silently decays. |
| **Likelihood** | High that enterprise-grade maintenance will not come from upstream (based on repo evidence; current upstream state is UNKNOWN, U-11). |
| **Mitigating factors** | The codebase is small (~5,200 lines first-party), dependency-free, readable, consistently structured and MIT-licensed, so an internal fork is cheap to own. That substantially lowers the cost of this risk. |
| **Recommended remediation** | Fork into an internal repository; assign an accountable owner; pin to a reviewed commit; review upstream changes before pulling them. |
| **Accountable role** | Architecture owner (decision); engineering manager (staffing). |
| **Validation / acceptance criteria** | Named owner, internal repository, documented patch procedure, and a reviewed baseline commit. |
| **Residual risk** | Low–Medium: depends on the internal owner's continuity. |
| **Block production?** | **Yes**, until internal ownership is assigned. Not a blocker for a pilot. |

### R-05 — No backup, restore or high availability

| Field | Detail |
|---|---|
| **Description** | All live state — state file, ballots, picks, showcase, headshots, password hash — sits in `data_dir` on one host. |
| **Observed condition** | No backup tooling, scripts or documentation; `data/` is deliberately excluded from deploys (`deploy.yml:L93-L103`); the event-file export excludes voting status, ballots and picks (`README.md:L115`); the authors list "No backups or retention policy" as gap #8. |
| **Evidence** | EV-03, EV-19, EV-25, EV-28, EV-43. |
| **Threat / failure scenario** | Host outage, disk-full (see R-02) or operator error (e.g., wrong `data_dir` in `config.php`) during the vote. Ballots cannot be recovered and the audience cannot be asked to vote again with the same context. |
| **Business and technical consequences** | Lost or disputed outcomes; event disruption. For a one-evening tool, RTO is effectively "minutes or never". |
| **Mitigating factors** | Small data volume; flat files are easy to copy; "Reset" keeps old rounds on disk rather than deleting them. |
| **Recommended remediation** | Scheduled copy of `data_dir` (every few minutes during an event) to separate storage; documented restore procedure; a rehearsed manual fallback (paper or show-of-hands). |
| **Accountable role** | Platform engineer; event operations lead. |
| **Validation / acceptance criteria** | Restore drill: restore a mid-vote snapshot onto a clean host and obtain the same tally and rosters. |
| **Residual risk** | Ballots cast between the last copy and the failure are lost (Low–Medium). |
| **Block production?** | **Yes** for business-critical events; no for a pilot with a manual fallback. |

---

## Evidence gaps and open questions

These are **not** confirmed risks. They are areas where repository evidence was insufficient; each is detailed (with validation method and owner) in doc 05 §F.

| ID | Open question | Potential impact if unfavorable | Status |
|---|---|---|---|
| U-01 | Does the app work on currently supported PHP versions? | Production on an unsupported runtime, or failure on upgrade | UNVERIFIED |
| U-02 | Are `.htaccess` protections effective on the target server? | Exposure of password hash, ballots, personal data | UNVERIFIED |
| U-03 | Does the file-based concurrency design hold at the expected audience size and filesystem? | Lost ballots under load | UNVERIFIED (README claim EV-26 has no artifact) |
| U-04 | Is the vote advisory or binding? | Determines acceptability of R-01 | UNKNOWN (business decision) |
| U-05 | Is private vulnerability reporting enabled and monitored upstream? | No intake for security issues | UNVERIFIED |
| U-06 | Are branch protection and environment rules configured as the deploy workflow assumes? | Unreviewed code deployed | UNVERIFIED / CONFLICTING (EV-46) |
| U-07 | Does shared hosting expose `data/` to other tenants? | Personal data and hash disclosure | UNKNOWN |
| U-08 | Ownership/licensing of AI-generated contributions | License validity for redistribution | UNKNOWN (legal) |
| U-09 | Are all front-end HTML sinks safe (beyond the sample)? | Operator-console XSS → full event control | UNVERIFIED |
| U-10 | Accessibility conformance of the voting flow | Exclusion of participants; legal exposure | UNKNOWN |
| U-11 | Current upstream activity and maintainers | Patch supply | UNKNOWN |
| U-12 | Which personal data will actually be collected, and under which legal basis? | Privacy compliance obligations | UNKNOWN |
