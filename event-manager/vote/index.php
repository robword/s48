<?php
declare(strict_types=1);

require __DIR__ . '/../inc/bootstrap.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');

try {
    $state = s48_state();
    $token = s48_voter_token(true);
    s48_record_open($state['round'], $token);
} catch (Throwable $e) {
    error_log('s48 vote: ' . $e);
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f1222">
<title>startup48 · Vote</title>
<link rel="stylesheet" href="../assets/theme.css">
<style>
  body { margin: 0; background: var(--s48-bg); color: var(--s48-text); font-family: var(--s48-font); -webkit-text-size-adjust: 100%; }
  .wrap { max-width: 560px; margin: 0 auto; padding: 16px 16px 120px; }
  header h1 { font-size: 22px; margin: 4px 0 4px; }
  header p { margin: 0 0 12px; color: var(--s48-muted); font-size: 15px; line-height: 1.4; }
  .bank { position: sticky; top: 0; z-index: 5; background: var(--s48-bg); padding: 10px 0; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid var(--s48-line); }
  .dots { display: flex; gap: 6px; }
  .dot { width: 18px; height: 18px; border-radius: 50%; border: 2px solid var(--s48-accent); }
  .dot.on { background: var(--s48-accent); }
  .bank-label { font-weight: 700; font-size: 16px; }
  .filter { width: 100%; box-sizing: border-box; margin: 12px 0 4px; padding: 12px 14px; font-size: 16px; border-radius: 10px; border: 1px solid var(--s48-line); background: var(--s48-surface); color: var(--s48-text); }
  ul { list-style: none; margin: 8px 0 0; padding: 0; }
  li { display: flex; align-items: center; gap: 10px; padding: 10px 12px; margin: 8px 0; background: var(--s48-surface); border-radius: 12px; border: 2px solid transparent; }
  li.has { border-color: var(--s48-accent); }
  .num { color: var(--s48-muted); font-size: 14px; min-width: 26px; }
  .name { flex: 1; font-size: 17px; line-height: 1.3; word-break: break-word; }
  .ctl { display: flex; align-items: center; gap: 6px; }
  .ctl button { width: 44px; height: 44px; border-radius: 50%; border: 0; font-size: 24px; font-weight: 700; line-height: 1; background: var(--s48-surface-2); color: var(--s48-text); touch-action: manipulation; }
  .ctl button.plus { background: var(--s48-accent); color: var(--s48-on-accent); }
  .ctl button:disabled { opacity: .3; }
  .count { min-width: 22px; text-align: center; font-size: 20px; font-weight: 800; }
  .bar { position: fixed; left: 0; right: 0; bottom: 0; padding: 12px 16px calc(12px + env(safe-area-inset-bottom)); background: var(--s48-bg); border-top: 1px solid var(--s48-line); }
  .bar .inner { max-width: 560px; margin: 0 auto; }
  .submit { width: 100%; padding: 16px; font-size: 18px; font-weight: 800; border: 0; border-radius: 12px; background: var(--s48-accent); color: var(--s48-on-accent); }
  .submit:disabled { opacity: .4; }
  .submit.done, .submit.done:disabled { opacity: 1; background: transparent; color: var(--s48-good); box-shadow: inset 0 0 0 2px var(--s48-good); }
  .msg { text-align: center; font-size: 14px; min-height: 18px; margin-bottom: 8px; color: var(--s48-muted); }
  .msg.ok { color: var(--s48-good); font-weight: 700; }
  .msg.err { color: var(--s48-bad); font-weight: 700; }
  .panel { text-align: center; padding: 48px 12px; }
  .panel h2 { font-size: 24px; margin: 0 0 8px; }
  .panel p { color: var(--s48-muted); margin: 0; }
  .hidden { display: none !important; }
</style>
</head>
<body>
<div class="wrap">
  <header>
    <div class="s48-wordmark" style="font-size:20px">startup<span>48</span></div>
    <h1>Vote for your favorite ideas</h1>
    <p>You have <strong>3 votes</strong>. Put them all on one idea or spread them out. You can change your votes until voting closes.</p>
  </header>

  <div id="waiting" class="panel hidden">
    <h2>Voting opens soon</h2>
    <p>Keep this page open. It will update when voting starts.</p>
  </div>
  <div id="closed" class="panel hidden">
    <h2>Voting is closed</h2>
    <p>Thanks for taking part!</p>
  </div>
  <div id="error" class="panel hidden">
    <h2>Can't reach the voting server</h2>
    <p>Check your connection. Retrying…</p>
  </div>

  <div id="voting" class="hidden">
    <div class="bank">
      <div class="dots" id="dots"></div>
      <div class="bank-label" id="bankLabel">3 votes left</div>
    </div>
    <input id="filter" class="filter hidden" type="search" placeholder="Find an idea…" autocomplete="off">
    <ul id="list"></ul>
  </div>
</div>

<div class="bar hidden" id="bar">
  <div class="inner">
    <div class="msg" id="msg"></div>
    <button class="submit" id="submit" disabled>Submit votes</button>
  </div>
</div>

<script src="../assets/common.js"></script>
<script>
(function () {
  var MAX = 3;
  var state = { status: null, pitches: [], votes: {}, saved: null, dirty: false };
  var pollTimer = null;
  var $ = function (id) { return document.getElementById(id); };

  function used() {
    var n = 0, k;
    for (k in state.votes) { if (state.votes.hasOwnProperty(k)) { n += state.votes[k]; } }
    return n;
  }

  function show(which) {
    var ids = ['waiting', 'closed', 'error', 'voting'];
    for (var i = 0; i < ids.length; i++) { $(ids[i]).className = $(ids[i]).className.replace(/\s*hidden/g, '') + (ids[i] === which ? '' : ' hidden'); }
    $('bar').className = which === 'voting' ? 'bar' : 'bar hidden';
  }

  function setMsg(text, kind) {
    $('msg').textContent = text || '';
    $('msg').className = 'msg' + (kind ? ' ' + kind : '');
  }

  function renderBank() {
    var left = MAX - used(), dots = $('dots'), i;
    dots.innerHTML = '';
    for (i = 0; i < MAX; i++) {
      var d = document.createElement('span');
      d.className = 'dot' + (i < left ? ' on' : '');
      dots.appendChild(d);
    }
    $('bankLabel').textContent = left === 1 ? '1 vote left' : left + ' votes left';
    $('submit').disabled = used() < 1 || !state.dirty;
    var done = state.saved && !state.dirty;
    $('submit').textContent = done ? 'Votes submitted ✓' : (state.saved ? 'Update votes' : 'Submit votes');
    $('submit').className = done ? 'submit done' : 'submit';
  }

  function renderList() {
    var ul = $('list'), q = $('filter').value.toLowerCase(), left = MAX - used();
    ul.innerHTML = '';
    $('filter').className = state.pitches.length > 12 ? 'filter' : 'filter hidden';
    for (var i = 0; i < state.pitches.length; i++) {
      var p = state.pitches[i], n = state.votes[p.id] || 0;
      if (q && p.name.toLowerCase().indexOf(q) === -1 && n === 0) { continue; }
      var li = document.createElement('li');
      li.className = n > 0 ? 'has' : '';
      var num = document.createElement('span'); num.className = 'num'; num.textContent = p.n + '.';
      var name = document.createElement('span'); name.className = 'name'; name.textContent = p.name;
      var ctl = document.createElement('span'); ctl.className = 'ctl';
      var minus = document.createElement('button'); minus.type = 'button'; minus.textContent = '−';
      minus.setAttribute('aria-label', 'Remove a vote from ' + p.name);
      minus.disabled = n === 0;
      var count = document.createElement('span'); count.className = 'count'; count.textContent = n;
      var plus = document.createElement('button'); plus.type = 'button'; plus.className = 'plus'; plus.textContent = '+';
      plus.setAttribute('aria-label', 'Add a vote to ' + p.name);
      plus.disabled = left === 0;
      minus.onclick = adjust(p.id, -1);
      plus.onclick = adjust(p.id, 1);
      ctl.appendChild(minus); ctl.appendChild(count); ctl.appendChild(plus);
      li.appendChild(num); li.appendChild(name); li.appendChild(ctl);
      ul.appendChild(li);
    }
    renderBank();
  }

  function adjust(id, delta) {
    return function () {
      var n = (state.votes[id] || 0) + delta;
      if (n < 0 || (delta > 0 && used() >= MAX)) { return; }
      if (n === 0) { delete state.votes[id]; } else { state.votes[id] = n; }
      state.dirty = true;
      setMsg('');
      renderList();
    };
  }

  function applyPitches(pitches) {
    state.pitches = pitches || [];
    var valid = {}, i, k;
    for (i = 0; i < state.pitches.length; i++) { valid[state.pitches[i].id] = true; }
    for (k in state.votes) { if (state.votes.hasOwnProperty(k) && !valid[k]) { delete state.votes[k]; } }
  }

  function schedule(ms) {
    clearTimeout(pollTimer);
    pollTimer = setTimeout(poll, ms);
  }

  function poll() {
    S48.api('GET', '../api/status.php', null, function (err, res) {
      if (err) { if (!state.status) { show('error'); } schedule(5000); return; }
      MAX = res.max_votes || 3;
      var prev = state.status;
      state.status = res.status;
      if (res.status === 'open') {
        var first = prev !== 'open';
        applyPitches(res.pitches);
        show('voting');
        if (first) { loadBallot(); } else { renderList(); }
        schedule(15000);
      } else if (res.status === 'closed') {
        show('closed');
        schedule(20000);
      } else {
        show('waiting');
        schedule(4000);
      }
    });
  }

  function loadBallot() {
    S48.api('GET', '../api/ballot.php', null, function (err, res) {
      if (!err && res.votes && !state.dirty) {
        state.votes = {};
        for (var k in res.votes) { if (res.votes.hasOwnProperty(k)) { state.votes[k] = res.votes[k]; } }
        applyPitches(state.pitches);
        state.saved = true;
        setMsg('Your votes are in. You can still change them.', 'ok');
      } else if (err && err.status === 401) {
        setMsg('Cookies are needed to vote. Please enable them and reload.', 'err');
      }
      renderList();
    });
  }

  $('submit').onclick = function () {
    if (used() < 1) { return; }
    $('submit').disabled = true;
    setMsg('Sending…');
    S48.api('POST', '../api/ballot.php', { votes: state.votes }, function (err, res) {
      if (err) {
        setMsg(err.message || 'Could not submit. Try again.', 'err');
        if (err.status === 409) { poll(); }
        renderBank();
        return;
      }
      state.saved = true;
      state.dirty = false;
      setMsg('Thanks! Your votes are in. You can change them until voting closes.', 'ok');
      renderList();
    });
  };

  $('filter').oninput = renderList;
  poll();
})();
</script>
</body>
</html>
