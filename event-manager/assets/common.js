/* startup48 shared client helpers (ES5, no dependencies). */
(function (w) {
  var S48 = w.S48 || {};

  /**
   * JSON request. cb(err, data). err = {status, message} on HTTP/network errors.
   * Extra headers: S48.csrf (operator) is sent when set.
   */
  S48.api = function (method, url, body, cb) {
    var xhr = new XMLHttpRequest();
    xhr.open(method, url + (method === 'GET' ? (url.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now() : ''), true);
    xhr.setRequestHeader('X-Requested-With', 's48');
    if (S48.csrf) { xhr.setRequestHeader('X-CSRF-Token', S48.csrf); }
    if (body !== null && body !== undefined) { xhr.setRequestHeader('Content-Type', 'application/json'); }
    xhr.timeout = 15000;
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) { return; }
      var data = null;
      try { data = JSON.parse(xhr.responseText); } catch (e) { data = null; }
      if (xhr.status >= 200 && xhr.status < 300 && data) {
        cb(null, data);
      } else {
        cb({ status: xhr.status, message: (data && data.error) || (xhr.status ? 'Error ' + xhr.status : 'Network error') }, data);
      }
    };
    xhr.ontimeout = function () { /* onreadystatechange fires with status 0 */ };
    xhr.send(body !== null && body !== undefined ? JSON.stringify(body) : null);
  };

  /** Render a QR code for `text` as an SVG string (needs assets/qrcode.js). */
  S48.qrSvg = function (text, darkColor, lightColor) {
    var qr = w.qrcode(0, 'M');
    qr.addData(text);
    qr.make();
    var n = qr.getModuleCount(), m = 4, size = n + m * 2, d = '', r, c;
    for (r = 0; r < n; r++) {
      for (c = 0; c < n; c++) {
        if (qr.isDark(r, c)) { d += 'M' + (c + m) + ' ' + (r + m) + 'h1v1h-1z'; }
      }
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + ' ' + size + '" shape-rendering="crispEdges" role="img" aria-label="QR code">' +
      '<rect width="100%" height="100%" fill="' + (lightColor || '#fff') + '"/>' +
      '<path d="' + d + '" fill="' + (darkColor || '#000') + '"/></svg>';
  };

  /* ---------- sponsors ---------- */

  /**
   * Sponsors grouped by tier, in the order each tier first appears in the list, with untiered
   * sponsors last. Group size: the first tier is large, the second medium, the rest small. When
   * there's only one group its size follows how many logos it has. opts.flat: one group, no headings.
   */
  S48.sponsorGroups = function (list, opts) {
    var order = [], by = {}, none = [];
    (list || []).forEach(function (sp) {
      var t = sp.tier || '';
      if (!t) { none.push(sp); return; }
      if (!by[t]) { by[t] = []; order.push(t); }
      by[t].push(sp);
    });
    var groups = order.map(function (t) { return { tier: t, items: by[t] }; });
    if (none.length) { groups.push({ tier: '', items: none }); }
    if ((opts && opts.flat) || !groups.length) {
      return groups.length ? [{ tier: '', items: list.slice(), size: (opts && opts.size) || 'm' }] : [];
    }
    if (groups.length === 1) {
      var n = groups[0].items.length;
      groups[0].size = n <= 6 ? 'l' : n <= 14 ? 'm' : 's';
      groups[0].tier = '';
    } else {
      groups.forEach(function (g, i) { g.size = g.tier === '' ? 's' : (i === 0 ? 'l' : i === 1 ? 'm' : 's'); });
    }
    return groups;
  };

  /** Splits the sponsor list into pages of at most `per` logos, keeping the list order. */
  S48.sponsorPages = function (list, per) {
    var pages = [];
    for (var i = 0; i < list.length; i += per) { pages.push(list.slice(i, i + per)); }
    return pages;
  };

  /** Fills `box` with logo cards (names alone for sponsors without a logo). */
  S48.renderSponsors = function (box, list, opts) {
    box.textContent = '';
    box.className = (box.className.replace(/\bsp\b/g, '') + ' sp').trim();
    box.style.setProperty('--sp-scale', '1');
    S48.sponsorGroups(list, opts).forEach(function (g) {
      var gd = document.createElement('div'), row = document.createElement('div');
      gd.className = 'sp-group'; gd.setAttribute('data-size', g.size);
      if (g.tier) { var h = document.createElement('div'); h.className = 'sp-tier'; h.textContent = g.tier; gd.appendChild(h); }
      row.className = 'sp-row';
      g.items.forEach(function (sp) {
        var c = document.createElement('div');
        c.className = 'sp-card' + (sp.logo_url ? '' : ' txt');
        if (sp.logo_url) { var im = document.createElement('img'); im.alt = sp.name; im.src = sp.logo_url; c.appendChild(im); }
        else { c.textContent = sp.name; }
        row.appendChild(c);
      });
      gd.appendChild(row); box.appendChild(gd);
    });
  };

  /**
   * Shrinks the cards until the block fits in maxH pixels tall. The box must be on screen
   * (not display:none). Waits for the logos to load first, since their widths change the wrapping.
   */
  S48.fitSponsors = function (box, maxH, done) {
    var imgs = [].slice.call(box.querySelectorAll('img')), left = imgs.length, fired = false;
    function fit() {
      if (fired) { return; } fired = true;
      for (var k = 1; k >= 0.3; k -= 0.05) {
        box.style.setProperty('--sp-scale', String(Math.round(k * 100) / 100));
        if (box.scrollHeight <= maxH) { break; }
      }
      if (done) { done(); }
    }
    if (!left) { fit(); return; }
    imgs.forEach(function (im) {
      if (im.complete) { if (--left === 0) { fit(); } return; }
      im.onload = im.onerror = function () { if (--left === 0) { fit(); } };
    });
    setTimeout(fit, 4000);
  };

  w.S48 = S48;
})(window);
