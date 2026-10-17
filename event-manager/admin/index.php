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
  .top { display: flex; align-items: center; gap: 16px; padding: 12px 20px; border-bottom: 1px solid var(--s48-line); flex-wrap: wrap; }
  .top .s48-wordmark { font-size: 22px; }
  .top .spacer { flex: 1; }
  .pill { padding: 4px 10px; border-radius: 999px; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: .05em; background: var(--s48-surface-2); }
  .pill.open { background: var(--s48-good); color: #062; }
  .pill.closed { background: var(--s48-bad); color: #400; }
  main { display: grid; grid-template-columns: minmax(300px, 420px) 1fr; gap: 20px; padding: 20px; }
  @media (max-width: 860px) { main { grid-template-columns: 1fr; } }
  section { background: var(--s48-surface); border-radius: 14px; padding: 16px; }
  h2 { font-size: 16px; margin: 0 0 12px; text-transform: uppercase; letter-spacing: .06em; color: var(--s48-muted); }
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
  .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
  @media (max-width: 600px) { .stats { grid-template-columns: repeat(2, 1fr); } }
  .stat { background: var(--s48-bg); border-radius: 12px; padding: 14px; }
  .stat .v { font-size: 38px; font-weight: 900; line-height: 1; }
  .stat .l { color: var(--s48-muted); font-size: 13px; margin-top: 6px; }
  .meter { height: 8px; background: var(--s48-surface-2); border-radius: 4px; margin-top: 8px; overflow: hidden; }
  .meter div { height: 100%; background: var(--s48-good); width: 0; transition: width .4s; }
  table { width: 100%; border-collapse: collapse; }
  td, th { padding: 8px 6px; text-align: left; border-bottom: 1px solid var(--s48-line); }
  th { color: var(--s48-muted); font-weight: 600; font-size: 13px; }
  td.rk { width: 40px; font-weight: 900; font-size: 18px; }
  td.vt { width: 60px; font-weight: 900; font-size: 18px; text-align: right; }
  td.vr { width: 80px; color: var(--s48-muted); text-align: right; font-size: 13px; }
  td.bar { width: 35%; }
  .barfill { height: 14px; background: var(--s48-accent); border-radius: 3px; transition: width .4s; }
  .controls { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 16px; }
  .muted { color: var(--s48-muted); font-size: 13px; }
  .toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: var(--s48-bad); color: #fff; padding: 10px 16px; border-radius: 8px; font-weight: 700; display: none; }
  .login { max-width: 360px; margin: 12vh auto; padding: 24px; background: var(--s48-surface); border-radius: 14px; }
  .login .err { color: var(--s48-bad); margin: 8px 0; }
  details { margin-top: 14px; }
  summary { cursor: pointer; color: var(--s48-muted); }
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
    <span class="pill" id="statusPill">…</span>
    <span class="spacer"></span>
    <a href="../slides/#vote" target="_blank">Slides (QR)</a>
    <a href="../vote/" target="_blank" id="voteLink">Voting page</a>
    <a href="../timer/" target="_blank">Timer</a>
    <form method="post" style="margin:0">
      <input type="hidden" name="csrf" value="<?= s48_h($csrf) ?>">
      <input type="hidden" name="do" value="logout">
      <button type="submit">Log out</button>
    </form>
  </div>

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

    <section>
      <div class="controls">
        <button class="primary big" id="btnStart">Start voting</button>
        <button class="big" id="btnClose">Close voting</button>
        <button class="big" id="btnReopen">Reopen</button>
        <span class="spacer" style="flex:1"></span>
        <span class="muted" id="updated"></span>
      </div>

      <div class="stats">
        <div class="stat"><div class="v" id="sOpened">0</div><div class="l">Opened voting page</div></div>
        <div class="stat"><div class="v" id="sPending">0</div><div class="l">Opened, not submitted</div></div>
        <div class="stat"><div class="v" id="sSubmitted">0</div><div class="l">Submitted</div></div>
        <div class="stat"><div class="v" id="sPct">0%</div><div class="l">Of openers submitted</div><div class="meter"><div id="sMeter"></div></div></div>
      </div>

      <h2>Live ranking</h2>
      <table>
        <thead id="rankHead"><tr><th>#</th><th>Idea</th><th></th><th style="text-align:right">Votes</th><th style="text-align:right">Voters</th></tr></thead>
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
    $('statusPill').textContent = st === 'setup' ? 'Not started' : st;
    $('statusPill').className = 'pill ' + st;
    $('btnStart').style.display = st === 'setup' ? '' : 'none';
    $('btnClose').style.display = st === 'open' ? '' : 'none';
    $('btnReopen').style.display = st === 'closed' ? '' : 'none';
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
      ec.colSpan = 5;
      er.appendChild(ec);
      tb.appendChild(er);
    }
    if (st !== 'setup') r.ranking.forEach(function (x) {
      var tr = el('tr');
      tr.appendChild(el('td', 'rk', x.rank));
      tr.appendChild(el('td', '', x.n + '. ' + x.name));
      var bar = el('td', 'bar'), fill = el('div', 'barfill');
      fill.style.width = (100 * x.votes / max) + '%';
      bar.appendChild(fill);
      tr.appendChild(bar);
      tr.appendChild(el('td', 'vt', x.votes));
      tr.appendChild(el('td', 'vr', x.voters));
      tb.appendChild(tr);
    });
    $('updated').textContent = 'Updated ' + new Date().toLocaleTimeString();
    renderPitches();
  }

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
  $('btnReset').onclick = function () {
    act('reset', { confirm: $('resetConfirm').value, keep_pitches: $('keepPitches').checked });
    $('resetConfirm').value = '';
  };

  poll();
})();
</script>
<?php endif; ?>
</body>
</html>
