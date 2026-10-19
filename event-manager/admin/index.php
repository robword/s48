<?php
declare(strict_types=1);

require __DIR__ . '/../inc/auth.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!s48_check_csrf($_POST['csrf'] ?? null)) {
        $error = 'Session expired. Please try again.';
    } elseif (($_POST['do'] ?? '') === 'logout') {
        s48_logout();
        header('Location: ./');
        exit;
    } else {
        $error = s48_login((string) ($_POST['password'] ?? ''));
        if ($error === null) {
            header('Location: ./');
            exit;
        }
    }
}
$admin = s48_is_admin();
$csrf = s48_csrf_token();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>startup48 · Operator</title>
<link rel="stylesheet" href="../assets/theme.css">
<style>
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--s48-bg); color: var(--s48-text); font-family: var(--s48-font); font-size: 15px; }
  a { color: var(--s48-accent-2); }
  .top { display: flex; align-items: center; gap: 16px; padding: 12px 20px; background: var(--s48-slate); flex-wrap: wrap; }
  .top a { color: #fff; }
  .top .s48-wordmark { font-size: 22px; }
  .top .spacer { flex: 1; }
  .pill { padding: 4px 10px; border-radius: 999px; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: .05em; background: var(--s48-surface-2); }
  .pill.teams { background: var(--s48-accent-2); color: #042; }
  main { display: grid; grid-template-columns: minmax(300px, 420px) 1fr; gap: 20px; padding: 20px; }
  @media (max-width: 860px) {
    main { grid-template-columns: 1fr; }
    /* On phones, once voting has started the controls and live results come first. */
    body[data-status=open] .results, body[data-status=closed] .results { order: -1; }
  }
  section { background: var(--s48-surface); border-radius: 14px; padding: 16px; }
  h2 { font-family: var(--s48-font-heading); font-weight: 700; font-size: 16px; margin: 0 0 12px; text-transform: uppercase; letter-spacing: .08em; color: var(--s48-muted); }
  button, .btn { font: inherit; border: 0; border-radius: 8px; padding: 9px 14px; font-weight: 700; cursor: pointer; background: var(--s48-surface-2); color: var(--s48-text); }
  button.primary { background: var(--s48-accent); color: var(--s48-on-accent); }
  button.big { padding: 14px 22px; font-size: 17px; }
  button.danger { background: transparent; color: var(--s48-bad); border: 1px solid var(--s48-bad); }
  button:disabled { opacity: .4; cursor: default; }
  input[type=text], input[type=password], textarea { font: inherit; width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--s48-line); background: var(--s48-bg); color: var(--s48-text); }
  .row { display: flex; gap: 8px; align-items: center; }
  .row > input { flex: 1; }
  ol.pitches { list-style: none; margin: 10px 0 0; padding: 0; max-height: calc(100vh - 230px); min-height: 120px; overflow: auto; font-size: 14px; }
  ol.pitches li { display: flex; align-items: center; gap: 4px; padding: 1px 2px; border-bottom: 1px solid var(--s48-line); min-height: 25px; }
  ol.pitches .n { width: 26px; color: var(--s48-muted); text-align: right; font-size: 13px; }
  ol.pitches .nm { flex: 1; cursor: text; padding: 2px 4px; border-radius: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  ol.pitches .nm:hover { background: var(--s48-bg); }
  ol.pitches button { padding: 1px 7px; font-size: 12px; line-height: 17px; }
  ol.pitches li:not(:hover) button:not(.danger) { opacity: .45; }
  ol.pitches input { padding: 4px 6px; }
  .empty td { color: var(--s48-muted); text-align: center; padding: 28px 6px; border-bottom: 0; }
  .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 16px; }
  @media (max-width: 600px) { .stats { grid-template-columns: repeat(2, 1fr); } }
  .stat { background: var(--s48-bg); border-radius: 12px; padding: 14px; }
  .stat .v { font-family: var(--s48-font-display); font-size: 38px; font-weight: 700; line-height: 1; }
  .stat .l { color: var(--s48-muted); font-size: 13px; margin-top: 6px; }
  /* Participants per team: red until the advancing count gives teams inside the ideal range. */
  .stat.teamfit { border: 2px solid var(--s48-good); }
  .stat.teamfit.bad { border-color: var(--s48-bad); background: rgba(255, 90, 90, .14); }
  .stat.teamfit.bad .v { color: var(--s48-bad); }
  /* Team selection: phase controls and the balance view */
  .phasebox[hidden], #rosterLink[hidden] { display: none; }
  .phasebox { display: inline-flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .phasebox .clock { font-family: var(--s48-font-display); font-weight: 700; font-size: 26px; font-variant-numeric: tabular-nums; min-width: 4ch; }
  .phasebox .clock.up { color: var(--s48-bad); animation: blink 1s steps(2) infinite; }
  @keyframes blink { 50% { opacity: .35; } }
  .board-head { align-items: baseline; gap: 12px; }
  .board { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 12px; margin-bottom: 20px; }
  .tm { background: var(--s48-bg); border-radius: 12px; padding: 12px; border: 2px solid var(--s48-good); }
  .tm.under { border-color: var(--s48-bad); }
  .tm.explore { border-color: var(--s48-line); }
  .tm.over { border-color: #e8a33d; }
  .tm h3 { margin: 0 0 4px; font-size: 16px; display: flex; gap: 8px; align-items: baseline; }
  .tm h3 .st { font-family: var(--s48-font-display); color: var(--s48-accent-ink); }
  .tm .cnt { font-size: 13px; color: var(--s48-muted); margin-bottom: 6px; }
  .tm .cnt b { color: var(--s48-text); font-size: 16px; }
  .tm.under .cnt b { color: var(--s48-bad); }
  .tm.over .cnt b { color: #e8a33d; }
  .tm ul { list-style: none; margin: 0; padding: 0; font-size: 14px; }
  .tm li { display: flex; align-items: center; gap: 6px; padding: 3px 0; border-top: 1px solid var(--s48-line); }
  .tm li .who { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .tm li .sk { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--s48-muted); }
  .tm li select { font-size: 12px; padding: 2px 4px; max-width: 92px; }
  .tm .skills { font-size: 12px; color: var(--s48-muted); margin-bottom: 4px; }
  td.adv { text-align: center; width: 90px; }
  td.adv input { width: 20px; height: 20px; accent-color: var(--s48-accent); cursor: pointer; }
  td.adv input:disabled { cursor: not-allowed; }
  tr.advancing td:not(.bar) { font-weight: 700; }
  .meter { height: 8px; background: var(--s48-surface-2); border-radius: 4px; margin-top: 8px; overflow: hidden; }
  .meter div { height: 100%; background: var(--s48-good); width: 0; transition: width .4s; }
  table { width: 100%; border-collapse: collapse; }
  td, th { padding: 8px 6px; text-align: left; border-bottom: 1px solid var(--s48-line); }
  th { color: var(--s48-muted); font-weight: 600; font-size: 13px; }
  td.rk { width: 48px; font-family: var(--s48-font-display); font-weight: 700; font-size: 18px; font-variant-numeric: tabular-nums; white-space: nowrap; }
  td.vt { width: 60px; font-family: var(--s48-font-display); font-weight: 700; font-size: 18px; text-align: right; }
  td.vr { width: 80px; color: var(--s48-muted); text-align: right; font-size: 13px; }
  td.bar { width: 35%; }
  .barfill { height: 14px; background: var(--s48-accent); border-radius: 3px; transition: width .4s; }
  .controls { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 16px; }
  .muted { color: var(--s48-muted); font-size: 13px; }
  table.tones { width: auto; margin-bottom: 12px; }
  table.tones td, table.tones th { padding: 6px 18px 6px 0; }
  table.tones label { display: inline-flex; align-items: center; gap: 8px; }
  table.tones input[type=text] { width: 5em; }
  table.tones tr.off-warning td:nth-child(2) input[type=text], table.tones tr.off-danger td:nth-child(3) input[type=text] { opacity: .4; }
  #toneSaved.bad { color: var(--s48-bad); font-weight: 700; }
  #eventMsg.bad { color: var(--s48-bad); font-weight: 700; }
  .toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: var(--s48-bad); color: #fff; padding: 10px 16px; border-radius: 8px; font-weight: 700; display: none; }
  .login { max-width: 360px; margin: 12vh auto; padding: 24px; background: var(--s48-surface); border-radius: 14px; }
  .login .err { color: var(--s48-bad); margin: 8px 0; }
  details { margin-top: 14px; }
  summary { cursor: pointer; color: var(--s48-muted); }
  /* Event showcase (Meet the ... slide loop) */
  .sc-wrap { padding: 0 20px 28px; }
  /* Collapsible page sections */
  details.sect > summary { list-style: none; cursor: pointer; display: flex; align-items: baseline; gap: 14px; flex-wrap: wrap; padding: 16px 20px 4px; user-select: none; }
  details.sect > summary::-webkit-details-marker { display: none; }
  details.sect > summary .ttl { font-family: var(--s48-font-heading); font-weight: 700; font-size: 20px; letter-spacing: .06em; text-transform: uppercase; color: var(--s48-text); }
  details.sect > summary .ttl::before { content: "▸"; display: inline-block; width: 1.1em; color: var(--s48-accent-ink); transition: transform .15s; }
  details.sect[open] > summary .ttl::before { transform: rotate(90deg); }
  details.sect + details.sect { border-top: 1px solid var(--s48-line); }
  .sc-wrap > .sc-info:first-child { margin-top: 8px; }
  .sc-head { display: flex; align-items: baseline; gap: 14px; flex-wrap: wrap; margin: 6px 0 12px; }
  .sc-head h2 { margin: 0; }
  .sc-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; }
  @media (max-width: 1100px) { .sc-grid { grid-template-columns: 1fr; } }
  .sc-col .row { justify-content: space-between; }
  ol.people { list-style: none; margin: 10px 0 0; padding: 0; max-height: 60vh; overflow: auto; font-size: 14px; }
  ol.people li { display: flex; align-items: center; gap: 8px; padding: 4px 2px; border-bottom: 1px solid var(--s48-line); min-height: 44px; }
  ol.people .n { width: 22px; color: var(--s48-muted); text-align: right; font-size: 13px; flex: none; }
  .thumb { width: 34px; height: 34px; border-radius: 50%; flex: none; background: var(--s48-surface-2) center / cover no-repeat; display: grid; place-items: center; font-weight: 700; font-size: 12px; color: var(--s48-muted); }
  ol.people .who { flex: 1; min-width: 0; cursor: pointer; padding: 2px 4px; border-radius: 4px; }
  ol.people .who:hover { background: var(--s48-bg); }
  ol.people .who b, ol.people .who span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  ol.people .who span { color: var(--s48-muted); font-size: 12px; }
  ol.people button { padding: 1px 7px; font-size: 12px; line-height: 17px; }
  ol.people li:not(:hover) button:not(.danger) { opacity: .45; }
  .empty-note { color: var(--s48-muted); font-size: 13px; margin: 10px 0 0; }
  .sc-info { margin-top: 20px; }
  .info-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px 16px; }
  @media (max-width: 1100px) { .info-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 600px) { .info-grid { grid-template-columns: 1fr; } }
  .info-grid label, .modal label.f { display: flex; flex-direction: column; gap: 4px; font-size: 13px; color: var(--s48-muted); }
  .info-grid .wide { grid-column: span 2; }
  .info-grid .range { display: flex; align-items: center; gap: 8px; color: var(--s48-text); }
  .info-grid .range input { width: 0; flex: 1; }
  .info-grid .hint { font-size: 12px; color: var(--s48-muted); }
  @media (max-width: 600px) { .info-grid .wide { grid-column: auto; } }
  select, input[type=number] { font: inherit; padding: 9px 10px; border-radius: 8px; border: 1px solid var(--s48-line); background: var(--s48-bg); color: var(--s48-text); }
  textarea { resize: vertical; }
  /* Person editor dialog */
  .modal { position: fixed; inset: 0; z-index: 20; background: rgba(4, 16, 24, .72); display: flex; align-items: flex-start; justify-content: center; padding: 5vh 16px; overflow: auto; }
  .modal[hidden] { display: none; }
  .modal .card { width: min(720px, 100%); background: var(--s48-surface); border-radius: 14px; padding: 20px; display: grid; grid-template-columns: 240px 1fr; gap: 20px; }
  @media (max-width: 640px) { .modal .card { grid-template-columns: 1fr; } }
  .modal h2 { grid-column: 1 / -1; margin: 0; }
  .crop-box { display: flex; flex-direction: column; gap: 8px; align-items: center; }
  #cropCanvas { width: 220px; height: 220px; border-radius: 50%; background: var(--s48-bg); cursor: grab; touch-action: none; }
  #cropCanvas.dragging { cursor: grabbing; }
  .crop-box .muted { text-align: center; }
  .fields { display: flex; flex-direction: column; gap: 10px; min-width: 0; }
  .f.check { font-size: 14px; color: var(--s48-text); }
  .fields.org-mentor [data-mentor] { margin-left: 24px; }
  .days { display: flex; gap: 16px; font-size: 14px; color: var(--s48-text); }
  .modal .actions { grid-column: 1 / -1; display: flex; gap: 10px; flex-wrap: wrap; }
  .modal .actions .spacer { flex: 1; }
  .modal .err { color: var(--s48-bad); grid-column: 1 / -1; min-height: 1.2em; margin: 0; }
</style>
</head>
<body>
<?php if (!$admin): ?>
  <form class="login" method="post" autocomplete="off">
    <div class="s48-wordmark" style="font-size:28px;margin-bottom:12px">startup<span>48</span></div>
    <h2>Operator login</h2>
    <?php if ($error): ?><div class="err"><?= s48_h($error) ?></div><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= s48_h($csrf) ?>">
    <input type="password" name="password" placeholder="Password" autofocus required>
    <p><button class="primary" type="submit" style="width:100%">Log in</button></p>
  </form>
<?php else: ?>
  <div class="top">
    <div class="s48-wordmark">startup<span>48</span></div>
    <strong>Operator</strong>
    <span class="spacer"></span>
    <a href="../slides/#vote" target="_blank">Slides (QR)</a>
    <a href="../vote/" target="_blank" id="voteLink">Voting page</a>
    <a href="../timer/" target="_blank">Timer</a>
    <a href="../showcase/" target="_blank">Showcase</a>
    <form method="post" style="margin:0">
      <input type="hidden" name="csrf" value="<?= s48_h($csrf) ?>">
      <input type="hidden" name="do" value="logout">
      <button type="submit">Log out</button>
    </form>
  </div>

  <details class="sect" id="secPitches" open>
  <summary><span class="ttl">Opening Night Pitches &amp; Team Selection</span></summary>
  <main>
    <section>
      <h2>Opening pitches (<span id="pitchCount">0</span>)</h2>
      <form class="row" id="addForm">
        <input type="text" id="addName" placeholder="Pitch name, then Enter" maxlength="80" autocomplete="off" autofocus>
        <button class="primary" type="submit">Add</button>
      </form>
      <ol class="pitches" id="pitchList"></ol>
      <p class="muted">Click a name to rename (full name shows on hover). Delete is only available before voting starts.</p>
      <details id="bulkBox">
        <summary>Paste a list (one per line)</summary>
        <textarea id="bulk" rows="6" style="margin-top:8px"></textarea>
        <p><button type="button" id="bulkAdd">Add all</button></p>
      </details>
    </section>

    <section class="results">
      <div class="controls">
        <button class="primary big" id="btnStart">Start voting</button>
        <button class="big" id="btnClose">Close voting</button>
        <button class="big" id="btnReopen">Reopen voting</button>
        <button class="primary big" id="btnTeams">Start team selection</button>
        <span id="phaseBox" class="phasebox" hidden>
          <span class="pill teams" id="phaseName"></span>
          <span class="clock" id="phaseClock"></span>
          <button class="big" id="btnPhaseBack"></button>
          <button class="primary big" id="btnPhaseNext"></button>
        </span>
        <button class="big" id="btnTeamsStop">Stop team selection</button>
        <span class="spacer" style="flex:1"></span>
        <span class="muted" id="updated"></span>
      </div>

      <div class="stats">
        <div class="stat"><div class="v" id="sOpened">0</div><div class="l">Opened voting page</div></div>
        <div class="stat"><div class="v" id="sPending">0</div><div class="l">Opened, not submitted</div></div>
        <div class="stat"><div class="v" id="sSubmitted">0</div><div class="l">Submitted</div></div>
        <div class="stat"><div class="v" id="sPct">0%</div><div class="l">Of openers submitted</div><div class="meter"><div id="sMeter"></div></div></div>
        <div class="stat teamfit" id="teamCard" hidden><div class="v" id="sPerTeam">–</div><div class="l">Participants per team</div><div class="l" id="sTeamNote"></div></div>
      </div>

      <div id="boardBox" hidden>
        <div class="row board-head">
          <h2>Teams</h2>
          <span class="muted" id="boardPicked"></span>
          <span class="spacer" style="flex:1"></span>
          <a href="../api/rosters.php" download id="rosterLink">Download rosters (CSV)</a>
        </div>
        <div class="board" id="board"></div>
      </div>

      <h2>Live ranking</h2>
      <table>
        <thead id="rankHead"><tr><th>#</th><th>Idea</th><th></th><th style="text-align:right">Votes</th><th style="text-align:right">Voters</th><th class="advCol" style="text-align:center">Advance to Team Selection</th></tr></thead>
        <tbody id="ranking"></tbody>
      </table>

      <details>
        <summary>Reset (start a new voting round)</summary>
        <p class="muted">Starts a fresh round with zero votes. Previous round files stay on the server.</p>
        <label><input type="checkbox" id="keepPitches" checked> Keep pitch list</label>
        <p class="row"><input type="text" id="resetConfirm" placeholder="Type RESET"><button class="danger" id="btnReset">Reset</button></p>
      </details>
    </section>
  </main>
  </details>
  <details class="sect" id="secShowcase" open>
  <summary><span class="ttl">Event Showcase</span></summary>
  <div class="sc-wrap" id="showcase">
    <div class="sc-head">
      <span class="muted">Organizers, judges and mentors for the "Meet the…" slide loop.</span>
      <a href="../showcase/" target="_blank">Open showcase slides</a>
    </div>
    <div class="sc-grid">
      <section class="sc-col" data-group="organizers">
        <div class="row"><h2>Organizers (<span class="count">0</span>)</h2><button class="primary add" type="button">Add</button></div>
        <ol class="people"></ol>
      </section>
      <section class="sc-col" data-group="judges">
        <div class="row"><h2>Judges (<span class="count">0</span>)</h2><button class="primary add" type="button">Add</button></div>
        <ol class="people"></ol>
      </section>
      <section class="sc-col" data-group="mentors">
        <div class="row"><h2>Mentors (<span class="count">0</span>)</h2><button class="primary add" type="button">Add</button></div>
        <ol class="people"></ol>
      </section>
    </div>
  </div>
  </details>
  <details class="sect" id="secSettings" open>
  <summary><span class="ttl">Event Info &amp; Settings</span></summary>
  <div class="sc-wrap">
    <section class="sc-info">
      <h2>Event info</h2>
      <form id="infoForm" class="info-grid">
        <label class="wide">Header line<input type="text" id="i-header" maxlength="80"></label>
        <label>Wi-Fi network<input type="text" id="i-wifi_name" maxlength="64"></label>
        <label>Wi-Fi password<input type="text" id="i-wifi_pass" maxlength="64"></label>
        <label class="wide">Mentor hours (shown on mentor and schedule slides)<input type="text" id="i-mentor_hours" maxlength="80" placeholder="Mentors available 9 AM – 3 PM"></label>
        <label>Number of participants<input type="number" id="i-participants" min="0" max="1000" step="1" placeholder="e.g. 36"></label>
        <label>Ideal team size<span class="range"><input type="number" id="i-team_min" min="1" max="50" step="1" aria-label="Smallest team"> to <input type="number" id="i-team_max" min="1" max="50" step="1" aria-label="Largest team"></span></label>
        <label>Team selection minutes<span class="range"><input type="number" id="i-phase_explore" min="1" max="60" step="1" aria-label="Explore minutes" title="Explore"> <input type="number" id="i-phase_choose" min="1" max="60" step="1" aria-label="Choose minutes" title="Choose"> <input type="number" id="i-phase_finalize" min="1" max="60" step="1" aria-label="Finalize minutes" title="Finalize"></span><span class="hint">Explore · Choose · Finalize</span></label>
        <label>Seconds per person<input type="number" id="i-seconds" min="3" max="15" step="1"></label>
        <label>Mentors shown
          <select id="i-mentor_day">
            <option value="auto">By day (Sat / Sun / everyone)</option>
            <option value="sat">Saturday mentors</option>
            <option value="sun">Sunday mentors</option>
            <option value="all">All mentors</option>
          </select>
        </label>
        <label class="wide">Announcement slide title (leave blank to skip)<input type="text" id="i-announce_title" maxlength="60" placeholder="Need something printed?"></label>
        <label class="wide">Announcement text<textarea id="i-announce_body" rows="2" maxlength="300"></textarea></label>
        <div class="row" style="grid-column: 1 / -1"><button class="primary" type="submit">Save event info</button><span class="muted" id="infoSaved"></span></div>
      </form>
    </section>

    <section class="sc-info">
      <h2>Pitch timer tones</h2>
      <p class="muted">When each tone sounds, as time left on the clock. Untick to turn a tone off. With count-up off, the clock stops at 0:00 instead of showing overtime. Open timers pick up changes the next time a timer is chosen or reset.</p>
      <form id="toneForm">
        <table class="tones">
          <thead><tr><th>Timer</th><th>Warning</th><th>Danger</th><th>Count up after 0:00</th></tr></thead>
          <tbody>
            <tr data-timer="opening"><td>Opening Pitch <span class="muted">1:00</span></td><td></td><td></td><td><input type="checkbox" class="up" aria-label="Opening Pitch counts up after 0:00"></td></tr>
            <tr data-timer="final"><td>Final Pitch <span class="muted">5:00</span></td><td></td><td></td><td><input type="checkbox" class="up" aria-label="Final Pitch counts up after 0:00"></td></tr>
            <tr data-timer="qa"><td>Q&amp;A <span class="muted">5:00</span></td><td></td><td></td><td><input type="checkbox" class="up" aria-label="Q&amp;A counts up after 0:00"></td></tr>
          </tbody>
        </table>
        <div class="row"><button class="primary" type="submit">Save timer tones</button><span class="muted" id="toneSaved"></span></div>
      </form>
    </section>

    <section class="sc-info">
      <h2>Event file</h2>
      <p class="muted">Save everything set up here (organizers, judges and mentors with headshots, event info and the pitch list) to a file, or load one, for example into a fresh install.
        Voting results and the operator password are not included.</p>
      <div class="row">
        <button type="button" id="saveEvent">Save event file</button>
        <button type="button" id="loadEvent">Load event file…</button>
        <input type="file" id="eventFile" accept=".json,application/json" hidden>
        <span class="muted" id="eventMsg"></span>
      </div>
    </section>
  </div>

  <div class="modal" id="personModal" hidden>
    <form class="card" id="personForm" autocomplete="off">
      <h2 id="pmTitle">Add mentor</h2>
      <div class="crop-box">
        <canvas id="cropCanvas" width="440" height="440" aria-label="Headshot preview"></canvas>
        <label class="btn" for="photoFile" style="cursor:pointer">Choose photo…</label>
        <input type="file" id="photoFile" accept="image/*" hidden>
        <input type="range" id="cropZoom" min="1" max="3" step="0.01" value="1" aria-label="Zoom">
        <span class="muted">Drag to position, slide to zoom.</span>
        <button type="button" id="removePhoto">Remove photo</button>
      </div>
      <div class="fields">
        <label class="f">Name<input type="text" id="p-name" maxlength="80" required></label>
        <label class="f" data-for="judges mentors" data-mentor>Title<input type="text" id="p-title" maxlength="80"></label>
        <label class="f">Organization<input type="text" id="p-org" maxlength="100"></label>
        <label class="f" data-for="mentors" data-mentor>Skills / focus area<input type="text" id="p-skills" maxlength="160" placeholder="e.g. Marketing, pricing, SaaS"></label>
        <div class="f days" data-for="mentors" data-mentor><label><input type="checkbox" id="p-sat"> Saturday</label><label><input type="checkbox" id="p-sun"> Sunday</label></div>
        <label class="f" data-for="organizers">First Startup 48 (year)<input type="number" id="p-first_year" min="2000" max="2100" placeholder="2017"></label>
        <label class="f check" data-for="organizers"><span><input type="checkbox" id="p-mentoring"> Also mentoring <span class="muted">(shows on the Meet the Mentors slide)</span></span></label>
      </div>
      <p class="err" id="pmErr"></p>
      <div class="actions">
        <button class="primary" type="submit" id="pmSave">Save</button>
        <button type="button" id="pmCancel">Cancel</button>
        <span class="spacer"></span>
        <button type="button" class="danger" id="pmDelete">Delete</button>
      </div>
    </form>
  </div>
  <div class="toast" id="toast"></div>

<script src="../assets/common.js"></script>
<script>
(function () {
  S48.csrf = <?= json_encode($csrf) ?>;
  var $ = function (id) { return document.getElementById(id); };
  var data = null, editing = false, timer = null;

  function toast(msg) {
    var t = $('toast');
    t.textContent = msg;
    t.style.display = 'block';
    clearTimeout(toast.h);
    toast.h = setTimeout(function () { t.style.display = 'none'; }, 3500);
  }

  function handle(err, res) {
    if (err) {
      if (err.status === 401) { location.reload(); return; }
      toast(err.message);
      return;
    }
    data = res;
    render();
  }

  function act(action, extra) {
    var body = extra || {};
    body.action = action;
    S48.api('POST', '../api/admin.php', body, handle);
  }

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) { e.className = cls; }
    if (text !== undefined) { e.textContent = text; }
    return e;
  }

  function renderPitches() {
    if (editing) { return; }
    var ol = $('pitchList');
    ol.innerHTML = '';
    var setup = data.status === 'setup';
    data.pitches.forEach(function (p, i) {
      var li = el('li');
      li.appendChild(el('span', 'n', p.n + '.'));
      var nm = el('span', 'nm', p.name);
      nm.title = p.name + ' (click to rename)';
      nm.onclick = function () { startRename(li, p); };
      li.appendChild(nm);
      var up = el('button', '', '↑'); up.disabled = i === 0; up.title = 'Move up';
      up.onclick = function () { act('move', { id: p.id, dir: -1 }); };
      var dn = el('button', '', '↓'); dn.disabled = i === data.pitches.length - 1; dn.title = 'Move down';
      dn.onclick = function () { act('move', { id: p.id, dir: 1 }); };
      li.appendChild(up); li.appendChild(dn);
      if (setup) {
        var del = el('button', 'danger', '✕'); del.title = 'Delete';
        del.onclick = function () { if (confirm('Delete "' + p.name + '"?')) { act('delete', { id: p.id }); } };
        li.appendChild(del);
      }
      ol.appendChild(li);
    });
    $('pitchCount').textContent = data.pitches.length;
  }

  function startRename(li, p) {
    editing = true;
    li.innerHTML = '';
    var inp = el('input');
    inp.type = 'text'; inp.value = p.name; inp.maxLength = 80;
    var done = function (save) {
      if (!editing) { return; }
      editing = false;
      if (save && inp.value.trim() && inp.value !== p.name) { act('rename', { id: p.id, name: inp.value }); } else { renderPitches(); }
    };
    inp.onkeydown = function (e) { if (e.key === 'Enter') { done(true); } else if (e.key === 'Escape') { done(false); } };
    inp.onblur = function () { done(true); };
    li.appendChild(inp);
    inp.focus();
    inp.select();
  }

  function render() {
    var st = data.status, r = data.results;
    document.body.setAttribute('data-status', st);
    $('btnStart').style.display = st === 'setup' ? '' : 'none';
    $('btnClose').style.display = st === 'open' ? '' : 'none';
    $('btnReopen').style.display = st === 'closed' ? '' : 'none';
    $('btnTeams').style.display = st === 'closed' ? '' : 'none';
    $('btnTeamsStop').style.display = st === 'teams' ? '' : 'none';
    $('btnTeams').disabled = !data.advance.length;
    renderTeamCard(st);
    renderBoard(st);
    $('btnStart').disabled = data.pitches.length === 0;
    $('voteLink').href = data.vote_url;

    $('sOpened').textContent = r.opened;
    $('sPending').textContent = r.pending;
    $('sSubmitted').textContent = r.submitted;
    $('sPct').textContent = Math.round(r.pct_submitted) + '%';
    $('sMeter').style.width = r.pct_submitted + '%';

    var tb = $('ranking'), max = 1;
    r.ranking.forEach(function (x) { if (x.votes > max) { max = x.votes; } });
    tb.innerHTML = '';
    $('rankHead').style.display = st === 'setup' ? 'none' : '';
    if (st === 'setup') {
      var er = el('tr', 'empty'), ec = el('td', '', 'Ranking appears once voting starts.');
      ec.colSpan = 6;
      er.appendChild(ec);
      tb.appendChild(er);
    }
    var perRank = {};
    r.ranking.forEach(function (x) { perRank[x.rank] = (perRank[x.rank] || 0) + 1; });
    var showAdv = st === 'closed' || st === 'teams', adv = {};
    data.advance.forEach(function (id) { adv[id] = true; });
    document.querySelectorAll('.advCol').forEach(function (c) { c.style.display = showAdv ? '' : 'none'; });
    if (st !== 'setup') r.ranking.forEach(function (x) {
      var tr = el('tr', adv[x.id] ? 'advancing' : '');
      // Ties show as T2; ideas with no votes aren't ranked.
      tr.appendChild(el('td', 'rk', x.votes === 0 ? '–' : (perRank[x.rank] > 1 ? 'T' : '') + x.rank));
      tr.appendChild(el('td', '', x.n + '. ' + x.name));
      var bar = el('td', 'bar'), fill = el('div', 'barfill');
      fill.style.width = (100 * x.votes / max) + '%';
      bar.appendChild(fill);
      tr.appendChild(bar);
      tr.appendChild(el('td', 'vt', x.votes));
      tr.appendChild(el('td', 'vr', x.voters));
      if (showAdv) {
        // Ticked while voting is closed; locked once team selection has started.
        var td = el('td', 'adv'), cb = el('input');
        cb.type = 'checkbox'; cb.checked = !!adv[x.id]; cb.disabled = st !== 'closed';
        cb.title = st === 'teams' ? 'Stop team selection to change' : 'Advance "' + x.name + '" to team selection';
        cb.onchange = function () { act('advance', { id: x.id, on: cb.checked }); };
        td.appendChild(cb); tr.appendChild(td);
      }
      tb.appendChild(tr);
    });
    $('updated').textContent = 'Updated ' + new Date().toLocaleTimeString();
    renderPitches();
  }

  /* ---------- team selection phases and balance view ---------- */
  var PHASES = ['explore', 'choose', 'finalize', 'set'];
  var PHASE_LABEL = { explore: 'Explore', choose: 'Choose', finalize: 'Finalize', set: 'Teams are set' };
  var NEXT_LABEL = { explore: 'Start choosing', choose: 'Start finalize', finalize: 'Teams are set' };
  var SKILL = { build: 'Build', design: 'Design', business: 'Business', other: 'Other' };
  var clockOffset = 0, boardKey = null;

  function renderBoard(st) {
    var b = data.board, on = st === 'teams' && b;
    $('phaseBox').hidden = !on;
    $('boardBox').hidden = !on;
    if (!on) { boardKey = null; return; }
    clockOffset = b.server_time * 1000 - Date.now();
    var i = PHASES.indexOf(b.phase);
    $('phaseName').textContent = PHASE_LABEL[b.phase];
    $('btnPhaseNext').style.display = NEXT_LABEL[b.phase] ? '' : 'none';
    $('btnPhaseNext').textContent = NEXT_LABEL[b.phase] || '';
    $('btnPhaseBack').style.display = i > 0 ? '' : 'none';
    $('btnPhaseBack').textContent = i > 0 ? 'Back to ' + PHASE_LABEL[PHASES[i - 1]] : '';
    tickPhase();
    var f = b.fit, exploring = b.phase === 'explore';
    // While exploring nobody can pick yet: no counts, tags or rosters, just the stations.
    $('rosterLink').hidden = exploring;
    $('boardPicked').textContent = exploring ? b.teams.length + ' teams · picking opens in Choose' : b.picked + (f.participants ? ' of ' + f.participants : '') + ' picked a team · ideal ' + f.min + '–' + f.max + ' per team';
    // Rebuild the cards only when something changed, so an open move menu isn't reset every poll.
    var key = JSON.stringify([b.phase, b.teams]);
    if (key === boardKey || (document.activeElement && document.activeElement.tagName === 'SELECT' && $('board').contains(document.activeElement))) { return; }
    boardKey = key;
    var box = $('board');
    box.innerHTML = '';
    b.teams.forEach(function (t) {
      var card = el('div', 'tm ' + (exploring ? 'explore' : t.fit)), h = el('h3');
      h.appendChild(el('span', 'st', '#' + t.station)); h.appendChild(el('span', '', t.name));
      card.appendChild(h);
      if (exploring) { box.appendChild(card); return; }
      var cnt = el('div', 'cnt'); cnt.appendChild(el('b', '', t.count)); cnt.appendChild(document.createTextNode(t.fit === 'under' ? ' · needs people' : t.fit === 'over' ? ' · over ' + f.max : ' · good'));
      card.appendChild(cnt);
      card.appendChild(el('div', 'skills', Object.keys(SKILL).filter(function (k) { return t.skills[k]; }).map(function (k) { return SKILL[k] + ' ' + t.skills[k]; }).join(' · ') || 'No picks yet'));
      var ul = el('ul');
      t.roster.forEach(function (r) {
        var li = el('li');
        li.appendChild(el('span', 'who', r.name + (r.moved ? ' ↔' : '')));
        li.appendChild(el('span', 'sk', SKILL[r.skill] || r.skill));
        var sel = el('select'); sel.title = 'Move ' + r.name;
        var o0 = el('option', '', 'Move…'); o0.value = '#'; sel.appendChild(o0);
        b.teams.forEach(function (o) { if (o.id !== t.id) { var op = el('option', '', '→ #' + o.station + ' ' + o.name); op.value = o.id; sel.appendChild(op); } });
        var rm = el('option', '', 'Remove pick'); rm.value = ''; sel.appendChild(rm);
        sel.onchange = function () {
          if (sel.value === '#') { return; }
          if (sel.value === '' && !confirm('Remove ' + r.name + '\'s pick?')) { sel.value = '#'; return; }
          act('pick_move', { key: r.key, team: sel.value });
          sel.blur();
        };
        li.appendChild(sel);
        ul.appendChild(li);
      });
      card.appendChild(ul);
      box.appendChild(card);
    });
  }
  // Phase countdown in the console, matching the slide: holds at 0:00 and flashes.
  function tickPhase() {
    var b = data && data.board, c = $('phaseClock');
    if (!b || data.status !== 'teams' || !b.phase_seconds) { c.textContent = ''; return; }
    var left = Math.max(0, Math.ceil(b.phase_at + b.phase_seconds - (Date.now() + clockOffset) / 1000));
    c.textContent = Math.floor(left / 60) + ':' + ('0' + left % 60).slice(-2);
    c.className = 'clock' + (left === 0 ? ' up' : '');
  }
  setInterval(tickPhase, 500);

  function renderTeamCard(st) {
    var card = $('teamCard'), t = data.team;
    card.hidden = !(st === 'closed' || st === 'teams');
    if (card.hidden) { return; }
    card.className = 'stat teamfit' + (t.ok ? '' : ' bad');
    $('sPerTeam').textContent = t.per_team === null ? '–' : String(t.per_team);
    var note;
    if (!t.participants) { note = 'Set the number of participants in Event info'; }
    else {
      note = t.participants + ' participants · ' + t.teams + ' advancing';
      note += t.pick_min <= t.pick_max ? ' · pick ' + (t.pick_min === t.pick_max ? t.pick_min : t.pick_min + '–' + t.pick_max) + ' for teams of ' + t.min + '–' + t.max
        : ' · no count gives teams of ' + t.min + '–' + t.max;
    }
    $('sTeamNote').textContent = note;
  }

  // Sections start open; each browser remembers the ones collapsed.
  document.querySelectorAll('details.sect').forEach(function (d) {
    try { if (localStorage.getItem('s48sect.' + d.id) === '0') { d.open = false; } } catch (e) { }
    d.addEventListener('toggle', function () { try { localStorage.setItem('s48sect.' + d.id, d.open ? '1' : '0'); } catch (e) { } });
  });

  function poll() {
    S48.api('GET', '../api/admin.php', null, function (err, res) {
      handle(err, res);
      clearTimeout(timer);
      timer = setTimeout(poll, 3000);
    });
  }

  $('addForm').onsubmit = function (e) {
    e.preventDefault();
    var v = $('addName').value;
    if (!v.trim()) { return; }
    $('addName').value = '';
    act('add', { names: v });
    $('addName').focus();
  };
  $('bulkAdd').onclick = function () {
    if (!$('bulk').value.trim()) { return; }
    act('add', { names: $('bulk').value });
    $('bulk').value = '';
    $('bulkBox').open = false;
  };
  $('btnStart').onclick = function () {
    if (confirm('Start voting with ' + data.pitches.length + ' pitches? The QR code will appear on the slides.')) { act('start'); }
  };
  $('btnClose').onclick = function () { if (confirm('Close voting? Phones will stop accepting votes.')) { act('close'); } };
  $('btnReopen').onclick = function () { act('reopen'); };
  $('btnTeams').onclick = function () {
    var t = data.team, n = data.advance.length;
    var msg = 'Start team selection with ' + n + ' idea' + (n === 1 ? '' : 's') + '? Their names appear on the "Form your team" slide, and the list is locked until you stop team selection.';
    if (!t.ok) {
      msg = (t.participants ? 'Team sizes are outside ' + t.min + '–' + t.max + (t.per_team ? ' (about ' + t.per_team + ' per team)' : '') + '.'
        : 'The number of participants isn\'t set, so team sizes can\'t be checked.') + '\n\nStart anyway?\n\n' + msg;
    }
    if (confirm(msg)) { act('teams_start'); }
  };
  $('btnTeamsStop').onclick = function () {
    if (confirm('Stop team selection? The slide goes back to the usual bullets and phones stop showing teams. Picks are kept if you start again.')) { act('teams_stop'); }
  };
  $('btnPhaseNext').onclick = function () {
    var i = PHASES.indexOf(data.board.phase), next = PHASES[i + 1];
    if (next === 'set' && !confirm('Teams are set? Phones stop accepting changes and the slide shows the final teams.')) { return; }
    act('teams_phase', { phase: next });
  };
  $('btnPhaseBack').onclick = function () { act('teams_phase', { phase: PHASES[PHASES.indexOf(data.board.phase) - 1] }); };
  $('btnReset').onclick = function () {
    act('reset', { confirm: $('resetConfirm').value, keep_pitches: $('keepPitches').checked });
    $('resetConfirm').value = '';
  };

  poll();
})();
</script>
<script>
/* Event showcase: organizers, judges, mentors and event info for the "Meet the..." loop. */
(function () {
  var $ = function (id) { return document.getElementById(id); };
  var LABEL = { organizers: 'organizer', judges: 'judge', mentors: 'mentor' };
  var data = null, edit = null; // edit: { group, person|null }

  function toast(msg) { var t = $('toast'); t.textContent = msg; t.style.display = 'block'; clearTimeout(toast.h); toast.h = setTimeout(function () { t.style.display = 'none'; }, 3500); }
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (text !== undefined) { e.textContent = text; } return e; }
  function initials(name) { return name.split(/\s+/).filter(Boolean).slice(0, 2).map(function (w) { return w[0].toUpperCase(); }).join(''); }

  function api(body, cb) {
    S48.api(body ? 'POST' : 'GET', '../api/showcase-admin.php', body, function (err, res) {
      if (err) { if (err.status === 401) { location.reload(); return; } if (cb) { cb(err); } else { toast(err.message); } return; }
      data = res; render(); if (cb) { cb(null, res); }
    });
  }

  function subtitle(g, p) {
    var days = g === 'mentors' || p.mentoring ? [p.sat ? 'Sat' : '', p.sun ? 'Sun' : ''].filter(Boolean).join('/') : '';
    if (g === 'organizers') { return [p.org, p.first_year ? 'since ' + p.first_year : '', p.mentoring ? 'Mentor' + (days ? ' ' + days : '') : ''].filter(Boolean).join(' · '); }
    return [p.title, p.org, days].filter(Boolean).join(' · ');
  }

  function render() {
    ['organizers', 'judges', 'mentors'].forEach(function (g) {
      var col = document.querySelector('.sc-col[data-group="' + g + '"]'), ol = col.querySelector('ol'), list = data.people[g];
      col.querySelector('.count').textContent = list.length;
      ol.innerHTML = '';
      if (!list.length) { var li0 = el('li'); li0.appendChild(el('span', 'empty-note', 'No ' + g + ' yet. Use Add.')); ol.appendChild(li0); }
      list.forEach(function (p, i) {
        var li = el('li');
        li.appendChild(el('span', 'n', (i + 1) + '.'));
        var th = el('span', 'thumb');
        if (p.photo_url) { th.style.backgroundImage = 'url("' + p.photo_url + '")'; } else { th.textContent = initials(p.name); }
        li.appendChild(th);
        var who = el('span', 'who'); who.title = 'Click to edit';
        who.appendChild(el('b', '', p.name)); who.appendChild(el('span', '', subtitle(g, p)));
        who.onclick = function () { openEditor(g, p); };
        li.appendChild(who);
        var up = el('button', '', '↑'); up.type = 'button'; up.disabled = i === 0; up.title = 'Move up'; up.onclick = function () { api({ action: 'move', group: g, id: p.id, dir: -1 }); };
        var dn = el('button', '', '↓'); dn.type = 'button'; dn.disabled = i === list.length - 1; dn.title = 'Move down'; dn.onclick = function () { api({ action: 'move', group: g, id: p.id, dir: 1 }); };
        var del = el('button', 'danger', '✕'); del.type = 'button'; del.title = 'Delete';
        del.onclick = function () { if (confirm('Delete ' + p.name + '?')) { api({ action: 'delete', group: g, id: p.id }); } };
        li.appendChild(up); li.appendChild(dn); li.appendChild(del);
        ol.appendChild(li);
      });
      if (g === 'mentors') {
        var om = data.people.organizers.filter(function (p) { return p.mentoring; });
        if (om.length) {
          var note = el('li'); note.appendChild(el('span', 'empty-note', 'Also mentoring (from Organizers): ' + om.map(function (p) { return p.name; }).join(', ')));
          ol.appendChild(note);
        }
        col.querySelector('.count').textContent = list.length + (om.length ? ' + ' + om.length : '');
      }
    });
    if (!document.activeElement || !$('infoForm').contains(document.activeElement)) {
      for (var k in data.info) { if (data.info.hasOwnProperty(k) && $('i-' + k)) { $('i-' + k).value = data.info[k]; } }
    }
    renderTones();
  }

  document.querySelectorAll('.sc-col .add').forEach(function (b) {
    b.onclick = function () { openEditor(b.closest('.sc-col').getAttribute('data-group'), null); };
  });

  /* ---------- pitch timer tones ---------- */
  var TONES = ['warning', 'danger'];
  function mmss(sec) { return Math.floor(sec / 60) + ':' + ('0' + sec % 60).slice(-2); }
  function parseMmss(v) {
    v = String(v).replace(/\s+/g, '');
    var m = /^(\d+):([0-5]\d)$/.exec(v);
    return m ? +m[1] * 60 + +m[2] : (/^\d+$/.test(v) ? +v : NaN);
  }
  document.querySelectorAll('#toneForm tr[data-timer]').forEach(function (tr) {
    TONES.forEach(function (tone, i) {
      var td = tr.children[i + 1], lb = el('label'), cb = el('input'), at = el('input');
      cb.type = 'checkbox'; cb.className = 'on'; cb.setAttribute('data-tone', tone); cb.title = 'Sound the ' + tone + ' tone';
      at.type = 'text'; at.className = 'at'; at.setAttribute('data-tone', tone); at.placeholder = 'm:ss'; at.setAttribute('aria-label', tone + ' time left');
      cb.onchange = function () { tr.classList.toggle('off-' + tone, !cb.checked); };
      lb.appendChild(cb); lb.appendChild(at); td.appendChild(lb);
    });
  });
  function renderTones() {
    if (document.activeElement && $('toneForm').contains(document.activeElement)) { return; }
    var t = data.info.timers || {};
    document.querySelectorAll('#toneForm tr[data-timer]').forEach(function (tr) {
      TONES.forEach(function (tone) {
        var v = (t[tr.getAttribute('data-timer')] || {})[tone] || { on: false, at: 0 };
        tr.querySelector('.on[data-tone="' + tone + '"]').checked = v.on;
        tr.querySelector('.at[data-tone="' + tone + '"]').value = mmss(v.at);
        tr.classList.toggle('off-' + tone, !v.on);
      });
      tr.querySelector('.up').checked = ((t[tr.getAttribute('data-timer')] || {}).count_up) !== false;
    });
  }
  $('toneForm').onsubmit = function (e) {
    e.preventDefault();
    var timers = {}, bad = null;
    document.querySelectorAll('#toneForm tr[data-timer]').forEach(function (tr) {
      var k = tr.getAttribute('data-timer'); timers[k] = { count_up: tr.querySelector('.up').checked };
      TONES.forEach(function (tone) {
        var at = parseMmss(tr.querySelector('.at[data-tone="' + tone + '"]').value);
        if (isNaN(at) && !bad) { bad = tr.children[0].firstChild.textContent.trim() + ': enter the ' + tone + ' time as m:ss'; }
        timers[k][tone] = { on: tr.querySelector('.on[data-tone="' + tone + '"]').checked, at: at };
      });
    });
    var msg = $('toneSaved');
    if (bad) { msg.className = 'bad'; msg.textContent = bad; return; }
    document.activeElement.blur();
    api({ action: 'info', fields: { timers: timers } }, function (err) {
      msg.className = err ? 'bad' : 'muted';
      msg.textContent = err ? err.message : 'Saved ' + new Date().toLocaleTimeString();
    });
  };

  $('infoForm').onsubmit = function (e) {
    e.preventDefault();
    var f = {};
    ['header', 'wifi_name', 'wifi_pass', 'mentor_hours', 'participants', 'team_min', 'team_max', 'phase_explore', 'phase_choose', 'phase_finalize', 'seconds', 'mentor_day', 'announce_title', 'announce_body'].forEach(function (k) { f[k] = $('i-' + k).value; });
    api({ action: 'info', fields: f }, function (err) { $('infoSaved').textContent = err ? err.message : 'Saved ' + new Date().toLocaleTimeString(); });
  };

  /* ---------- headshot cropper: drag to position, slide to zoom, exported as 600x600 JPEG ---------- */
  var cv = $('cropCanvas'), cx = cv.getContext('2d'), crop = { img: null, zoom: 1, x: 0, y: 0, changed: false, removed: false };

  function baseScale() { return crop.img ? Math.max(cv.width / crop.img.naturalWidth, cv.height / crop.img.naturalHeight) : 1; }
  function clampCrop() {
    if (!crop.img) { return; }
    var s = baseScale() * crop.zoom, w = crop.img.naturalWidth * s, h = crop.img.naturalHeight * s;
    var mx = Math.max(0, (w - cv.width) / 2), my = Math.max(0, (h - cv.height) / 2);
    crop.x = Math.max(-mx, Math.min(mx, crop.x)); crop.y = Math.max(-my, Math.min(my, crop.y));
  }
  function drawCrop(ctx, size) {
    var k = size / cv.width, s = baseScale() * crop.zoom * k;
    ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--s48-surface-2') || '#2f617d';
    ctx.fillRect(0, 0, size, size);
    if (!crop.img) {
      ctx.fillStyle = '#b9cfdb'; ctx.font = (size * 0.2) + 'px sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText(initials($('p-name').value || '?'), size / 2, size / 2);
      return;
    }
    var w = crop.img.naturalWidth * s, h = crop.img.naturalHeight * s;
    ctx.drawImage(crop.img, size / 2 - w / 2 + crop.x * k, size / 2 - h / 2 + crop.y * k, w, h);
  }
  function redraw() { clampCrop(); drawCrop(cx, cv.width); }
  function loadImage(src, fresh) {
    var img = new Image();
    img.onload = function () { crop.img = img; crop.zoom = 1; crop.x = 0; crop.y = 0; $('cropZoom').value = 1; crop.changed = fresh; crop.removed = false; redraw(); };
    img.onerror = function () { $('pmErr').textContent = 'That image could not be opened. Use a JPEG or PNG.'; };
    img.src = src;
  }
  $('photoFile').onchange = function () {
    var f = this.files && this.files[0];
    if (!f) { return; }
    if (!/^image\//.test(f.type)) { $('pmErr').textContent = 'Choose an image file.'; return; }
    var r = new FileReader();
    r.onload = function () { $('pmErr').textContent = ''; loadImage(r.result, true); };
    r.readAsDataURL(f);
    this.value = '';
  };
  $('cropZoom').oninput = function () { crop.zoom = +this.value; crop.changed = !!crop.img; redraw(); };
  $('removePhoto').onclick = function () { crop.img = null; crop.changed = false; crop.removed = true; redraw(); };
  (function () {
    var drag = null;
    cv.addEventListener('pointerdown', function (e) { if (!crop.img) { return; } drag = { x: e.clientX, y: e.clientY, ox: crop.x, oy: crop.y }; cv.setPointerCapture(e.pointerId); cv.classList.add('dragging'); });
    cv.addEventListener('pointermove', function (e) {
      if (!drag) { return; }
      var k = cv.width / cv.clientWidth;
      crop.x = drag.ox + (e.clientX - drag.x) * k; crop.y = drag.oy + (e.clientY - drag.y) * k; crop.changed = true; redraw();
    });
    function end() { drag = null; cv.classList.remove('dragging'); }
    cv.addEventListener('pointerup', end); cv.addEventListener('pointercancel', end);
  })();
  function exportPhoto() {
    var out = document.createElement('canvas'); out.width = 600; out.height = 600;
    drawCrop(out.getContext('2d'), 600);
    return out.toDataURL('image/jpeg', 0.85);
  }

  /* ---------- person editor ---------- */
  function openEditor(g, p) {
    edit = { group: g, person: p };
    $('pmTitle').textContent = (p ? 'Edit ' : 'Add ') + LABEL[g];
    $('p-name').value = p ? p.name : ''; $('p-title').value = p && p.title ? p.title : ''; $('p-org').value = p ? p.org : '';
    $('p-skills').value = p && p.skills ? p.skills : ''; $('p-sat').checked = !!(p && p.sat); $('p-sun').checked = !!(p && p.sun);
    $('p-first_year').value = p && p.first_year ? p.first_year : '';
    $('p-mentoring').checked = !!(p && p.mentoring);
    showFields();
    $('pmDelete').style.display = p ? '' : 'none';
    $('pmErr').textContent = '';
    crop = { img: null, zoom: 1, x: 0, y: 0, changed: false, removed: false }; $('cropZoom').value = 1;
    if (p && p.photo_url) { loadImage(p.photo_url, false); } else { redraw(); }
    $('personModal').hidden = false;
    $('p-name').focus();
  }
  // A field shows when it belongs to the group, or it's a mentor field and this organizer also mentors.
  // For organizers the mentor fields sit under the "Also mentoring" box; elsewhere they go back in place.
  var mentorFields = [].slice.call(document.querySelectorAll('#personForm [data-mentor]')).map(function (n) {
    return { node: n, home: n.nextElementSibling };
  });
  function showFields() {
    var g = edit.group, m = g === 'organizers' && $('p-mentoring').checked;
    $('personForm').querySelector('.fields').classList.toggle('org-mentor', g === 'organizers');
    if (g === 'organizers') {
      mentorFields.forEach(function (f) { $('personForm').querySelector('.fields').appendChild(f.node); });
    } else {
      mentorFields.slice().reverse().forEach(function (f) { f.home.parentNode.insertBefore(f.node, f.home); }); // reverse, since a field's home can be the next mentor field
    }
    document.querySelectorAll('#personForm [data-for]').forEach(function (n) {
      var on = n.getAttribute('data-for').split(' ').indexOf(g) >= 0 || (m && n.hasAttribute('data-mentor'));
      n.style.display = on ? '' : 'none';
    });
  }
  $('p-mentoring').onchange = showFields;
  function closeEditor() { $('personModal').hidden = true; edit = null; }
  $('pmCancel').onclick = closeEditor;
  $('personModal').addEventListener('click', function (e) { if (e.target === this) { closeEditor(); } });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !$('personModal').hidden) { closeEditor(); } });
  $('p-name').addEventListener('input', function () { if (!crop.img) { redraw(); } });
  $('pmDelete').onclick = function () {
    if (!edit || !edit.person || !confirm('Delete ' + edit.person.name + '?')) { return; }
    api({ action: 'delete', group: edit.group, id: edit.person.id }, function (err) { if (err) { $('pmErr').textContent = err.message; } else { closeEditor(); } });
  };
  $('personForm').onsubmit = function (e) {
    e.preventDefault();
    var fields = { name: $('p-name').value, title: $('p-title').value, org: $('p-org').value, skills: $('p-skills').value, sat: $('p-sat').checked, sun: $('p-sun').checked, first_year: $('p-first_year').value, mentoring: $('p-mentoring').checked };
    var body = edit.person ? { action: 'update', group: edit.group, id: edit.person.id, fields: fields } : { action: 'add', group: edit.group, fields: fields };
    if (crop.img && crop.changed) { body.photo = exportPhoto(); } else if (edit.person && crop.removed) { body.remove_photo = true; }
    $('pmSave').disabled = true; $('pmErr').textContent = '';
    api(body, function (err) { $('pmSave').disabled = false; if (err) { $('pmErr').textContent = err.message; } else { closeEditor(); } });
  };

  /* ---------- event file ---------- */
  $('saveEvent').onclick = function () { location.href = '../api/event.php'; };   // served as a download
  $('loadEvent').onclick = function () { $('eventFile').value = ''; $('eventFile').click(); };
  $('eventFile').onchange = function () {
    var file = this.files[0], msg = $('eventMsg');
    if (!file) { return; }
    msg.textContent = '';
    var rd = new FileReader();
    rd.onload = function () {
      var ev;
      try { ev = JSON.parse(rd.result); } catch (e) { ev = null; }
      if (!ev || ev.format !== 'startup48-event' || !ev.people) { msg.className = 'bad'; msg.textContent = 'That file is not an event file.'; alert(msg.textContent); return; }
      var groups = ['organizers', 'judges', 'mentors'], people = [];
      groups.forEach(function (g) { (ev.people[g] || []).forEach(function (p) { people.push(p); }); });
      var pitches = (ev.pitches || []).length;
      // Headshots go up one at a time, then the rest in one request that swaps everything over.
      var withPhoto = people.filter(function (p) { return p.photo; }), done = 0;
      $('loadEvent').disabled = true; msg.className = 'muted'; msg.textContent = 'Checking…';
      // Ask first whether a load can happen now (not while voting is open), before the confirm.
      S48.api('POST', '../api/event.php', { action: 'check' }, function (err) {
        if (err) { fail(err); return; }
        if (!confirm('Replace everything with this file?\n\n' + people.length + ' people, ' + pitches + ' pitches.\n\n' +
          'Current organizers, judges, mentors, event info and pitches are removed, and voting starts a fresh round.')) {
          $('loadEvent').disabled = false; msg.textContent = ''; return;
        }
        next();
      });
      function next() {
        if (done < withPhoto.length) {
          msg.textContent = 'Uploading headshots ' + (done + 1) + ' of ' + withPhoto.length + '…';
          S48.api('POST', '../api/event.php', { action: 'photo', photo: withPhoto[done].photo }, function (err, res) {
            if (err) { fail(err); return; }
            withPhoto[done].photo_ref = res.id; done++; next();
          });
          return;
        }
        var body = { format: ev.format, version: ev.version, info: ev.info || {}, pitches: ev.pitches || [], people: {} };
        groups.forEach(function (g) {
          body.people[g] = (ev.people[g] || []).map(function (p) { var q = {}; for (var k in p) { if (k !== 'photo') { q[k] = p[k]; } } return q; });
        });
        msg.textContent = 'Loading…';
        S48.api('POST', '../api/event.php', { action: 'import', event: body }, function (err, res) {
          if (err) { fail(err); return; }
          $('loadEvent').disabled = false;
          msg.className = 'muted'; msg.textContent = 'Loaded ' + res.people + ' people and ' + res.pitches + ' pitches.';
          api(null);
        });
      }
      function fail(err) {
        var text = 'Load failed: ' + err.message.replace(/\.?$/, '.') + ' Nothing was changed.';
        $('loadEvent').disabled = false; msg.className = 'bad'; msg.textContent = text; alert(text);
      }
    };
    rd.readAsText(file);
  };

  api(null);
})();
</script>
<?php endif; ?>
</body>
</html>
