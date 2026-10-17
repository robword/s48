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

  w.S48 = S48;
})(window);
