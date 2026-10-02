/* Kai$erFinance public performance block.
 * Receives percentages, plus real amounts only if Settings → "Public amounts" allows.
 * Visitor choices (language, currency, benchmark, theme) are remembered in this browser;
 * ?lang=de and ?ccy=CHF in the address also set them. */
(function () {
  'use strict';

  var I18N = {
    en: {
      locale: 'en-GB',
      portfolio: 'Portfolio',
      samePeriod: '{b}, same period',
      since: 'Tracking since',
      from: 'From',
      ahead: '{n} percentage points ahead of {b}.',
      behind: '{n} percentage points behind {b}.',
      legendPortfolio: 'Kai$erFinance',
      vs: 'vs',
      ranges: { '7D': '7D', '30D': '30D', '90D': '90D', 'YTD': 'YTD', '1Y': '1Y', 'All': 'All' },
      periods: 'Returns by period',
      noHistory: 'Not enough history yet',
      rangeLabel: 'Time range',
      langLabel: 'Language',
      ccyLabel: 'Currency',
      benchLabel: 'Compare with',
      themeLabel: 'Appearance',
      light: 'Light',
      dark: 'Dark',
      login: 'Log in',
      tvOpen: 'Chart on TradingView (opens in a new tab)',
      youtube: 'Kai$erFinance on YouTube',
      instagram: 'Kai$erFinance on Instagram',
      allocation: 'Allocation',
      cash: 'Cash',
      value: 'Account value',
      gain: 'Gain / loss',
      netIn: 'Paid in (net)',
      invested: 'Invested (net)',
      holdings: 'Holdings',
      qty: 'Quantity',
      valueCol: 'Value',
      weight: 'Weight',
      chartLabel: 'Portfolio return versus the benchmark over time',
      foot: 'Time-weighted return of the whole account, cash included. Deposits and withdrawals don\'t count as gains or losses. Benchmarks are tracked through funds (S&P 500 via SPY) over the same period. In CHF, the effect of the dollar against the franc is included.',
      asOf: 'Prices as of {d}.',
      empty: 'Performance appears here once the first trade is logged.',
      emptyChart: 'The chart fills in after the first full day of prices.',
      eyebrow: 'Spot portfolio · {ccy}',
      headline: 'How the portfolio stacks up against the S&P 500',
      lede: 'The return of the whole account, cash included, against the S&P 500 and other benchmarks over exactly the same days. Money paid in or taken out doesn\'t count as performance.',
      disclaimer: 'Past performance is no guarantee of future results. Not investment advice.'
    },
    de: {
      locale: 'de-CH',
      portfolio: 'Portfolio',
      samePeriod: '{b}, gleicher Zeitraum',
      since: 'Erfasst seit',
      from: 'Ab',
      ahead: '{n} Prozentpunkte besser als {b}.',
      behind: '{n} Prozentpunkte schlechter als {b}.',
      legendPortfolio: 'Kai$erFinance',
      vs: 'vs.',
      ranges: { '7D': '7T', '30D': '30T', '90D': '90T', 'YTD': 'YTD', '1Y': '1J', 'All': 'Alle' },
      periods: 'Rendite nach Zeitraum',
      noHistory: 'Noch nicht genug Verlauf',
      rangeLabel: 'Zeitraum',
      langLabel: 'Sprache',
      ccyLabel: 'Währung',
      benchLabel: 'Vergleichen mit',
      themeLabel: 'Darstellung',
      light: 'Hell',
      dark: 'Dunkel',
      login: 'Anmelden',
      tvOpen: 'Chart auf TradingView (öffnet in neuem Tab)',
      youtube: 'Kai$erFinance auf YouTube',
      instagram: 'Kai$erFinance auf Instagram',
      allocation: 'Aufteilung',
      cash: 'Liquidität',
      value: 'Kontowert',
      gain: 'Gewinn / Verlust',
      netIn: 'Eingezahlt (netto)',
      invested: 'Investiert (netto)',
      holdings: 'Positionen',
      qty: 'Menge',
      valueCol: 'Wert',
      weight: 'Anteil',
      chartLabel: 'Portfolio-Rendite im Vergleich zum Index über die Zeit',
      foot: 'Zeitgewichtete Rendite des ganzen Kontos inklusive Liquidität. Ein- und Auszahlungen zählen nicht als Gewinn oder Verlust. Vergleichsindizes über Fonds (S&P 500 über SPY) im selben Zeitraum. In CHF ist der Effekt des Dollars gegenüber dem Franken enthalten.',
      asOf: 'Kurse vom {d}.',
      empty: 'Die Performance erscheint hier, sobald der erste Trade erfasst ist.',
      emptyChart: 'Der Chart erscheint nach dem ersten vollen Tag mit Kursen.',
      eyebrow: 'Spot-Portfolio · {ccy}',
      headline: 'Wie sich das Portfolio gegen den S&P 500 schlägt',
      lede: 'Die Rendite des ganzen Kontos inklusive Liquidität, verglichen mit dem S&P 500 und weiteren Indizes über genau dieselben Tage. Ein- und Auszahlungen zählen nicht als Performance.',
      disclaimer: 'Vergangene Performance ist keine Garantie für die Zukunft. Keine Anlageberatung.'
    }
  };

  /* ---------- remembered choices ---------- */

  function stored(key) { try { return localStorage.getItem(key); } catch (e) { return null; } }
  function store(key, v) { try { localStorage.setItem(key, v); } catch (e) { /* not saved: fine */ } }
  function fromUrl(name, allowed) {
    var m = new RegExp('[?&]' + name + '=([^&#]+)', 'i').exec(location.search);
    if (!m) return null;
    var v = decodeURIComponent(m[1]);
    if (allowed.indexOf(v) >= 0) return v;
    if (allowed.indexOf(v.toUpperCase()) >= 0) return v.toUpperCase();
    if (allowed.indexOf(v.toLowerCase()) >= 0) return v.toLowerCase();
    return null;
  }

  var lang = fromUrl('lang', ['en', 'de']) || (function (v) { return v === 'en' || v === 'de' ? v : 'en'; })(stored('kf-lang'));
  var ccy = fromUrl('ccy', ['USD', 'CHF']) || (stored('kf-ccy') === 'CHF' ? 'CHF' : 'USD');
  function t(key) { return I18N[lang][key]; }

  /* ---------- helpers ---------- */

  function el(tag, attrs, html) {
    var n = document.createElement(tag);
    if (attrs) Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); });
    if (html != null) n.innerHTML = html;
    return n;
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }
  function num(v, digits) {
    return Math.abs(v).toLocaleString(t('locale'), { minimumFractionDigits: digits, maximumFractionDigits: digits });
  }
  function pct(v) {
    if (v == null || !isFinite(v)) return '—';
    return (v >= 0 ? '+' : '−') + num(v, 2) + '%';
  }
  function money(v, opts) {
    if (v == null || !isFinite(v)) return '—';
    var digits = Math.abs(v) >= 10000 ? 0 : 2;
    var s = Math.abs(v).toLocaleString(t('locale'), { style: 'currency', currency: ccy, currencyDisplay: 'narrowSymbol', minimumFractionDigits: digits, maximumFractionDigits: digits });
    return (v < 0 ? '−' : (opts && opts.sign && v > 0 ? '+' : '')) + s;
  }
  function qtyFmt(v) {
    return v == null ? '' : Number(v).toLocaleString(t('locale'), { maximumFractionDigits: 6 });
  }
  function cls(v) { return v > 0 ? 'kf-up' : v < 0 ? 'kf-down' : ''; }
  function fmtDate(d) {
    return new Date(d + 'T12:00:00Z').toLocaleDateString(t('locale'), { day: 'numeric', month: 'short', year: 'numeric' });
  }
  function niceStep(r) {
    var p = Math.pow(10, Math.floor(Math.log10(r || 1)));
    var n = r / p;
    return (n < 1.5 ? 1 : n < 3 ? 2 : n < 7 ? 5 : 10) * p;
  }
  function reducedMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  /* ---------- chart ---------- */

  /* pts: [{d, p, b: {id: value}}]; ids: benchmark ids to draw. */
  function benchVar(id) { return 'var(--kf-b-' + id.split('/')[0].toLowerCase() + ', var(--kf-index))'; }
  function drawChart(box, pts, ids, names) {
    if (pts.length < 2) {
      box.innerHTML = '<p class="kf-empty">' + esc(t('emptyChart')) + '</p>';
      return;
    }
    // Draw at the box's real width so labels stay readable on phones.
    var W = Math.max(320, Math.min(1000, Math.round(box.clientWidth || 900)));
    var H = W < 600 ? 240 : 300, L = 46, R = 12, T = 14, B = 30;
    var n = pts.length;
    var vals = [];
    pts.forEach(function (p) {
      if (p.p != null) vals.push(p.p);
      ids.forEach(function (id) { if (p.b[id] != null) vals.push(p.b[id]); });
    });
    if (!vals.length) vals = [0];
    var lo = Math.min(0, Math.min.apply(null, vals)), hi = Math.max(0, Math.max.apply(null, vals));
    var pad = (hi - lo) * 0.1 || 1; lo -= pad; hi += pad;
    var x = function (i) { return L + i / (n - 1) * (W - L - R); };
    var y = function (v) { return T + (hi - v) / (hi - lo) * (H - T - B); };

    var svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="' + esc(t('chartLabel')) + '">';
    var step = niceStep((hi - lo) / 5);
    for (var v = Math.ceil(lo / step) * step; v <= hi + 1e-9; v += step) {
      var zero = Math.abs(v) < 1e-9;
      svg += '<line x1="' + L + '" x2="' + (W - R) + '" y1="' + y(v) + '" y2="' + y(v) + '" stroke="var(--kf-line)" stroke-width="' + (zero ? 1.5 : 1) + '"/>';
      svg += '<text x="' + (L - 8) + '" y="' + (y(v) + 4) + '" text-anchor="end">' + (v < -1e-9 ? '−' : '') + num(v, step < 1 ? 1 : 0) + '%</text>';
    }
    function path(get) {
      var d = '', started = false;
      pts.forEach(function (p, i) {
        var val = get(p);
        if (val == null) { started = false; return; }
        d += (started ? 'L' : 'M') + x(i).toFixed(1) + ',' + y(val).toFixed(1);
        started = true;
      });
      return d;
    }
    var getP = function (p) { return p.p; };
    var first = -1, lastI = -1;
    pts.forEach(function (p, i) { if (p.p != null) { if (first < 0) first = i; lastI = i; } });
    if (first >= 0) {
      var area = path(getP) + 'L' + x(lastI).toFixed(1) + ',' + y(0) + 'L' + x(first).toFixed(1) + ',' + y(0) + 'Z';
      svg += '<path d="' + area + '" fill="var(--kf-gold)" fill-opacity="0.10"/>';
    }
    var last = pts[n - 1];
    ids.forEach(function (id) {
      svg += '<path d="' + path(function (p) { return p.b[id]; }) + '" fill="none" stroke="' + benchVar(id) + '" stroke-width="2" stroke-linejoin="round"/>';
      if (last.b[id] != null) svg += '<circle cx="' + x(n - 1) + '" cy="' + y(last.b[id]) + '" r="4" fill="' + benchVar(id) + '"/>';
    });
    svg += '<path d="' + path(getP) + '" fill="none" stroke="var(--kf-gold)" stroke-width="2.6" stroke-linejoin="round"/>';
    if (last.p != null) svg += '<circle cx="' + x(n - 1) + '" cy="' + y(last.p) + '" r="4.5" fill="var(--kf-gold)"/>';
    svg += '<text x="' + L + '" y="' + (H - 8) + '">' + esc(fmtDate(pts[0].d)) + '</text>';
    svg += '<text x="' + (W - R) + '" y="' + (H - 8) + '" text-anchor="end">' + esc(fmtDate(last.d)) + '</text>';
    svg += '</svg>';
    box.innerHTML = svg;
    attachTooltip(box, pts, ids, names || {}, { W: W, H: H, L: L, R: R, T: T, B: B, x: x, y: y });
  }

  /* Hover / tap / arrow keys: a guide line plus the date and every line's value. */
  function attachTooltip(box, pts, ids, names, g) {
    var svgEl = box.querySelector('svg');
    if (!svgEl || pts.length < 2) return;
    var NS = 'http://www.w3.org/2000/svg';
    var guide = document.createElementNS(NS, 'line');
    guide.setAttribute('y1', g.T); guide.setAttribute('y2', g.H - g.B);
    guide.setAttribute('stroke', 'var(--kf-muted)'); guide.setAttribute('stroke-dasharray', '3 3');
    guide.setAttribute('opacity', '0');
    svgEl.appendChild(guide);
    var marks = [];
    function mark(color) {
      var c = document.createElementNS(NS, 'circle');
      c.setAttribute('r', '4.5'); c.setAttribute('fill', color);
      c.setAttribute('stroke', 'var(--kf-panel)'); c.setAttribute('stroke-width', '2'); c.setAttribute('opacity', '0');
      svgEl.appendChild(c); marks.push(c); return c;
    }
    var rows = ids.map(function (id) { return { id: id, color: benchVar(id), dot: mark(benchVar(id)) }; });
    var own = { color: 'var(--kf-gold)', dot: mark('var(--kf-gold)') };
    var tip = el('div', { 'class': 'kf-tip', role: 'status', 'aria-live': 'polite' });
    tip.hidden = true;
    box.appendChild(tip);
    svgEl.setAttribute('tabindex', '0');
    var n = pts.length, current = n - 1;

    function show(i) {
      current = Math.max(0, Math.min(n - 1, i));
      var p = pts[current], px = g.x(current);
      guide.setAttribute('x1', px); guide.setAttribute('x2', px); guide.setAttribute('opacity', '0.7');
      var place = function (dot, v) {
        if (v == null) { dot.setAttribute('opacity', '0'); return; }
        dot.setAttribute('cx', px); dot.setAttribute('cy', g.y(v)); dot.setAttribute('opacity', '1');
      };
      place(own.dot, p.p);
      rows.forEach(function (r) { place(r.dot, p.b[r.id]); });
      var line = function (color, name, v) {
        return '<div class="kf-tip-row"><i style="background:' + color + '"></i><span>' + esc(name) + '</span><b class="' + cls(v) + '">' + pct(v) + '</b></div>';
      };
      tip.innerHTML = '<div class="kf-tip-date">' + esc(fmtDate(p.d)) + '</div>' +
        line(own.color, t('legendPortfolio'), p.p) +
        rows.map(function (r) { return line(r.color, names[r.id] || r.id, p.b[r.id]); }).join('');
      tip.hidden = false;
      var scale = svgEl.getBoundingClientRect().width / g.W;
      var left = px * scale, tw = tip.offsetWidth, bw = box.clientWidth;
      tip.style.left = Math.max(0, Math.min(bw - tw, left > bw / 2 ? left - tw - 12 : left + 12)) + 'px';
    }
    function hide() {
      tip.hidden = true;
      guide.setAttribute('opacity', '0');
      marks.forEach(function (m) { m.setAttribute('opacity', '0'); });
    }
    function fromPointer(ev) {
      var rect = svgEl.getBoundingClientRect();
      var vx = (ev.clientX - rect.left) / (rect.width / g.W);
      show(Math.round((vx - g.L) / (g.W - g.L - g.R) * (n - 1)));
    }
    svgEl.addEventListener('pointermove', fromPointer);
    svgEl.addEventListener('pointerdown', fromPointer);
    svgEl.addEventListener('pointerleave', function (ev) { if (ev.pointerType === 'mouse') hide(); });
    svgEl.addEventListener('focus', function () { show(current); });
    svgEl.addEventListener('blur', hide);
    svgEl.addEventListener('keydown', function (ev) {
      var step = ev.shiftKey ? 7 : 1;
      if (ev.key === 'ArrowLeft') { ev.preventDefault(); show(current - step); }
      else if (ev.key === 'ArrowRight') { ev.preventDefault(); show(current + step); }
      else if (ev.key === 'Home') { ev.preventDefault(); show(0); }
      else if (ev.key === 'End') { ev.preventDefault(); show(n - 1); }
      else if (ev.key === 'Escape') { hide(); }
    });
  }

  /* ---------- language/currency: text fades out, swaps, fades back in ---------- */

  function fadeSwap(swap) {
    var html = document.documentElement;
    if (reducedMotion()) { swap(); return; }
    html.classList.add('kf-text-out');
    clearTimeout(fadeSwap._t);
    fadeSwap._t = setTimeout(function () {
      swap(); // new text is inserted while still hidden
      requestAnimationFrame(function () {
        requestAnimationFrame(function () { html.classList.remove('kf-text-out'); });
      });
    }, 170);
  }

  /* A small segmented switch: options = [[value, label html, title?]]. */
  function segmented(cls, label, options, current, onPick) {
    var group = el('div', { 'class': cls, role: 'group', 'aria-label': label });
    options.forEach(function (o) {
      var attrs = { type: 'button', 'aria-pressed': o[0] === current ? 'true' : 'false' };
      if (o[2]) { attrs.title = o[2]; attrs['aria-label'] = o[2]; }
      var b = el('button', attrs, o[1]);
      b.addEventListener('click', function () {
        if (b.getAttribute('aria-pressed') === 'true') return;
        Array.prototype.forEach.call(group.children, function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
        onPick(o[0], b);
      });
      group.appendChild(b);
    });
    return group;
  }

  /* ---------- theme: soft glow from the switch, then the new colours fade in ---------- */

  var THEME_BG = { light: '#f3f4f1', dark: '#0e1217' }; // keep in sync with --site-bg in site.css
  var washing = false;
  function setTheme(mode, origin) {
    var html = document.documentElement;
    var apply = function () {
      if (mode === 'dark') html.setAttribute('data-theme', 'dark');
      else html.removeAttribute('data-theme');
    };
    store('kf-theme', mode);
    if (reducedMotion() || !origin || !document.body.animate || washing) { apply(); return; }

    var r = origin.getBoundingClientRect();
    var cx = r.left + r.width / 2, cy = r.top + r.height / 2;
    var cover = Math.hypot(Math.max(cx, innerWidth - cx), Math.max(cy, innerHeight - cy));
    var size = 400, solid = 0.55;
    var endScale = (cover / (size / 2)) / solid * 1.02;

    var wash = document.createElement('div');
    wash.className = 'kf-wash';
    wash.setAttribute('aria-hidden', 'true');
    wash.style.cssText = 'left:' + (cx - size / 2) + 'px;top:' + (cy - size / 2) + 'px;width:' + size + 'px;height:' + size + 'px;' +
      'background:radial-gradient(circle closest-side,' + THEME_BG[mode] + ' 0%,' + THEME_BG[mode] + ' ' + (solid * 100) + '%,' + THEME_BG[mode] + '00 100%)';
    document.body.appendChild(wash);
    washing = true;
    wash.animate(
      [{ transform: 'scale(0.06)', opacity: 0.6 }, { transform: 'scale(' + endScale + ')', opacity: 1 }],
      { duration: 480, easing: 'cubic-bezier(.45, 0, .2, 1)', fill: 'forwards' }
    ).finished.then(function () {
      apply();
      return wash.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 380, easing: 'ease-out', fill: 'forwards' }).finished;
    }).catch(function () { apply(); }).then(function () {
      wash.remove();
      washing = false;
    });
  }

  var ICONS = {
    light: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>',
    dark: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>'
  };
  function currentTheme() { return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'; }

  /* ---------- page text outside the block (standalone homepage) ---------- */

  function applyChrome() {
    document.documentElement.setAttribute('lang', lang);
    Array.prototype.forEach.call(document.querySelectorAll('[data-kf-t]'), function (n) {
      var v = I18N[lang][n.getAttribute('data-kf-t')];
      if (typeof v === 'string') n.textContent = v.replace('{ccy}', ccy);
    });
    Array.prototype.forEach.call(document.querySelectorAll('[data-kf-t-label]'), function (n) {
      var v = I18N[lang][n.getAttribute('data-kf-t-label')];
      if (typeof v === 'string') { n.setAttribute('aria-label', v); n.setAttribute('title', v); }
    });
  }

  /* ---------- the block ---------- */

  function render(root) {
    // The numbers come in a <script type="application/json"> inside the block (older pages: the data-kf attribute).
    // Read once and kept, because the block redraws itself on resize, language and currency changes.
    if (!root._kfData) {
      var src = root.querySelector('script.kf-data');
      try { root._kfData = JSON.parse((src ? src.textContent : root.getAttribute('data-kf')) || '{}'); } catch (e) { root._kfData = {}; }
    }
    var data = root._kfData;
    var dates = data.dates || [];
    var hasChf = !!(data.port && data.port.CHF);
    if (!hasChf) ccy = 'USD';
    var benches = data.bench || [];
    var byId = {};
    benches.forEach(function (b) { byId[b.id] = b; });
    // Benchmarks the visitor has switched on (S&P 500 by default), in catalog order.
    var picked;
    try { picked = JSON.parse(stored('kf-benches') || 'null'); } catch (e) { picked = null; }
    if (!Array.isArray(picked)) picked = benches.length ? [benches[0].id] : [];
    var selected = benches.map(function (b) { return b.id; }).filter(function (id) { return picked.indexOf(id) >= 0; });
    var bench = selected.length ? byId[selected[0]] : null; // the one the headline numbers compare with
    var bname = function (b) { return b ? b[lang] : ''; };

    root.innerHTML = '';
    root.classList.add('kf-ready');
    root.setAttribute('lang', lang);
    var rerender = function () { render(root); };
    applyChrome();

    // Header controls (standalone page), or a row on top of the block (shortcode).
    var controls = el('div', { 'class': 'kf-controls' });
    var slot = document.getElementById('kf-lang-slot');
    if (slot) {
      controls.appendChild(segmented('kf-theme', t('themeLabel'),
        [['light', ICONS.light, t('light')], ['dark', ICONS.dark, t('dark')]], currentTheme(),
        function (mode, btn) { setTheme(mode, btn); }));
    }
    if (hasChf) {
      controls.appendChild(segmented('kf-lang kf-ccy', t('ccyLabel'), [['USD', 'USD'], ['CHF', 'CHF']], ccy,
        function (v) { ccy = v; store('kf-ccy', v); fadeSwap(rerender); }));
    }
    controls.appendChild(segmented('kf-lang', t('langLabel'), [['en', 'EN'], ['de', 'DE']], lang,
      function (v) { lang = v; store('kf-lang', v); fadeSwap(rerender); }));
    if (slot) {
      slot.innerHTML = '';
      slot.appendChild(controls);
    } else {
      var top = el('div', { 'class': 'kf-top' });
      top.appendChild(controls);
      root.appendChild(top);
    }

    if (!dates.length) {
      root.appendChild(el('p', { 'class': 'kf-empty' }, esc(t('empty'))));
      return;
    }

    var portArr = data.port[ccy] || data.port.USD;
    var series = dates.map(function (d, i) {
      var b = {};
      benches.forEach(function (bb) { var arr = bb[ccy] || bb.USD; b[bb.id] = arr && arr[i] != null ? arr[i] : null; });
      return { d: d, p: portArr[i], b: b };
    });
    var primary = bench ? bench.id : null;

    var stats = el('div', { 'class': 'kf-stats' });
    root.appendChild(stats);
    var verdict = el('div', { 'class': 'kf-verdict' });
    root.appendChild(verdict);

    /* Returns within a window: rebase the cumulative series to the window's first day. */
    function rebase(pts) {
      var p0 = null, b0 = {};
      for (var i = 0; i < pts.length; i++) {
        if (p0 === null && pts[i].p != null) p0 = 1 + pts[i].p / 100;
        for (var id in pts[i].b) if (!(id in b0) && pts[i].b[id] != null) b0[id] = 1 + pts[i].b[id] / 100;
      }
      return pts.map(function (q) {
        var b = {};
        for (var id in q.b) b[id] = (q.b[id] == null || !(id in b0)) ? null : ((1 + q.b[id] / 100) / b0[id] - 1) * 100;
        return { d: q.d, p: (q.p == null || p0 === null) ? null : ((1 + q.p / 100) / p0 - 1) * 100, b: b };
      });
    }
    function updateStats(pts, whole) {
      var last = pts[pts.length - 1];
      var lb = primary ? last.b[primary] : null;
      stats.innerHTML =
        '<div class="kf-stat"><span class="kf-label">' + esc(t('portfolio')) + '</span><span class="kf-big kf-num ' + cls(last.p) + '">' + pct(last.p) + '</span></div>' +
        (primary ? '<div class="kf-stat"><span class="kf-label">' + esc(t('samePeriod').replace('{b}', bname(bench))) + '</span><span class="kf-big kf-num ' + cls(lb) + '">' + pct(lb) + '</span></div>' : '') +
        '<div class="kf-stat"><span class="kf-label">' + esc(t(whole ? 'since' : 'from')) + '</span><span class="kf-big">' + esc(fmtDate(pts[0].d)) + '</span></div>';
      if (last.p != null && lb != null) {
        var diff = last.p - lb;
        verdict.hidden = false;
        verdict.textContent = t(diff >= 0 ? 'ahead' : 'behind').replace('{n}', num(diff, 2)).replace('{b}', bname(bench));
      } else {
        verdict.hidden = true;
      }
    }

    // Real amounts, if published.
    var am = data.amounts;
    if (am) {
      var cards = el('div', { 'class': 'kf-stats kf-amounts' });
      cards.innerHTML =
        '<div class="kf-stat"><span class="kf-label">' + esc(t('value')) + '</span><span class="kf-big kf-num">' + money(am.value[ccy]) + '</span></div>' +
        '<div class="kf-stat"><span class="kf-label">' + esc(t('gain')) + '</span><span class="kf-big kf-num ' + cls(am.gain[ccy]) + '">' + money(am.gain[ccy], { sign: true }) + '</span></div>' +
        '<div class="kf-stat"><span class="kf-label">' + esc(t(am.trades_only ? 'invested' : 'netIn')) + '</span><span class="kf-big kf-num">' + money(am.net_in[ccy]) + '</span></div>';
      root.appendChild(cards);
    }

    var bar = el('div', { 'class': 'kf-bar' });
    var legend = el('div', { 'class': 'kf-legend' });
    legend.innerHTML = '<span class="kf-legend-own"><i style="background:var(--kf-gold)"></i>' + esc(t('legendPortfolio')) + '</span>';
    bar.appendChild(legend);
    if (benches.length) {
      var chips = el('div', { 'class': 'kf-chips', role: 'group', 'aria-label': t('benchLabel') });
      chips.appendChild(el('span', { 'class': 'kf-chips-label' }, esc(t('vs'))));
      benches.forEach(function (b) {
        var on = selected.indexOf(b.id) >= 0;
        var chip = el('button', { type: 'button', 'class': 'kf-chip', 'aria-pressed': on ? 'true' : 'false' },
          '<i style="background:' + benchVar(b.id) + '"></i>' + esc(b[lang]));
        chip.addEventListener('click', function () {
          var now = selected.slice();
          var at = now.indexOf(b.id);
          if (at >= 0) now.splice(at, 1); else now.push(b.id);
          store('kf-benches', JSON.stringify(now));
          rerender();
        });
        chips.appendChild(chip);
      });
      root.appendChild(bar);
      bar.appendChild(chips);
    }
    var ranges = el('div', { 'class': 'kf-ranges', role: 'group', 'aria-label': t('rangeLabel') });
    if (!bar.parentNode) root.appendChild(bar);
    var rangeRow = el('div', { 'class': 'kf-range-row' });
    rangeRow.appendChild(ranges);
    root.appendChild(rangeRow);

    var chart = el('div', { 'class': 'kf-chart' });
    root.appendChild(chart);

    var lastDay = new Date(dates[dates.length - 1] + 'T12:00:00Z');
    function since(days) { var d = new Date(lastDay); d.setUTCDate(d.getUTCDate() - days); return d.toISOString().slice(0, 10); }
    var opts = [
      ['7D', since(7)], ['30D', since(30)], ['90D', since(90)], ['YTD', lastDay.getUTCFullYear() + '-01-01'], ['1Y', since(365)], ['All', '0000']
    ];
    function periodReturn(from) {
      var l = series[series.length - 1];
      if (from === '0000') return { p: l.p, b: l.b };
      var baseIdx = -1;
      for (var i = series.length - 1; i >= 0; i--) { if (series[i].d < from) { baseIdx = i; break; } }
      if (baseIdx < 0) {
        // Started this year: year-to-date is simply the return since the start.
        if (from.slice(5) === '01-01' && series[0].d >= from) return { p: l.p, b: l.b };
        return null;
      }
      var v = rebase(series.slice(baseIdx));
      var last = v[v.length - 1];
      return { p: last.p, b: last.b };
    }
    function show(from, btn) {
      var pts = series.filter(function (p) { return p.d >= from; });
      var whole = pts.length === series.length || pts.length < 2;
      if (pts.length < 2) pts = series;
      var idx = series.indexOf(pts[0]);
      if (!whole && idx > 0) pts = [series[idx - 1]].concat(pts);
      var view = whole ? series : rebase(pts);
      var names = {};
      selected.forEach(function (id) { names[id] = bname(byId[id]); });
      drawChart(chart, view, selected, names);
      updateStats(view, whole);
      Array.prototype.forEach.call(ranges.children, function (b) { b.setAttribute('aria-pressed', b === btn ? 'true' : 'false'); });
      root.setAttribute('data-kf-range', from);
    }
    var current = root.getAttribute('data-kf-range') || '0000';
    var currentBtn = null;
    opts.forEach(function (o) {
      var b = el('button', { type: 'button', 'aria-pressed': 'false' }, esc(t('ranges')[o[0]]));
      b.addEventListener('click', function () { show(o[1], b); });
      ranges.appendChild(b);
      if (o[1] === current) currentBtn = b;
    });
    show(currentBtn ? current : '0000', currentBtn || ranges.lastChild);

    // Returns by period. Wide screens: one row per line, one column per period.
    // Phones: turned on its side (one row per period), so it fits without sideways scrolling.
    var lines = [{ name: t('legendPortfolio'), color: 'var(--kf-gold)', id: null }].concat(selected.map(function (id) {
      return { name: bname(byId[id]), color: benchVar(id), id: id };
    }));
    var rets = opts.map(function (o) { return { label: t('ranges')[o[0]], r: periodReturn(o[1]) }; });
    var cell = function (line, pr) {
      var v = pr.r ? (line.id === null ? pr.r.p : pr.r.b[line.id]) : null;
      var title = pr.r ? '' : ' title="' + esc(t('noHistory')) + '"';
      return '<td class="kf-num ' + cls(v) + '"' + title + '>' + pct(v) + '</td>';
    };
    var swatch = function (line) { return '<i style="background:' + line.color + '"></i>'; };
    var narrow = root.clientWidth > 0 && root.clientWidth < 560;
    var thead, tbody;
    if (narrow) {
      thead = '<th></th>' + lines.map(function (l) { return '<th scope="col">' + swatch(l) + esc(l.name) + '</th>'; }).join('');
      tbody = rets.map(function (pr) {
        return '<tr><th scope="row">' + esc(pr.label) + '</th>' + lines.map(function (l) { return cell(l, pr); }).join('') + '</tr>';
      }).join('');
    } else {
      thead = '<th></th>' + rets.map(function (pr) { return '<th scope="col">' + esc(pr.label) + '</th>'; }).join('');
      tbody = lines.map(function (l) {
        return '<tr><th scope="row">' + swatch(l) + esc(l.name) + '</th>' + rets.map(function (pr) { return cell(l, pr); }).join('') + '</tr>';
      }).join('');
    }
    root.appendChild(el('div', { 'class': 'kf-periods' + (narrow ? ' kf-periods-narrow' + (lines.length > 3 ? ' kf-periods-many' : '') : '') },
      '<table><caption>' + esc(t('periods')) + '</caption><thead><tr>' + thead + '</tr></thead><tbody>' + tbody + '</tbody></table>'));

    // Holdings with amounts, or the allocation bars.
    var nameOf = function (n) { return n === '__cash__' ? t('cash') : n; };
    // Name links to the TradingView chart when there is one (plain link, opens in a new tab).
    var tvLink = function (label, url) {
      if (!url || !/^https:\/\/www\.tradingview\.com\//.test(url)) return label;
      var tip = esc(t('tvOpen'));
      return '<a class="kf-tv" href="' + esc(url) + '" target="_blank" rel="noopener noreferrer" title="' + tip + '">' + label +
        '<svg viewBox="0 0 12 12" aria-hidden="true"><path d="M4.5 2.5h5v5M9.5 2.5 3 9" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
        '<span class="kf-sr">' + tip + '</span></a>';
    };
    if (am && am.holdings && am.holdings.length) {
      var rows = am.holdings.map(function (h) {
        return '<tr><th scope="row">' + tvLink(esc(nameOf(h.name)) + (h.symbol ? ' <small>' + esc(h.symbol) + '</small>' : ''), h.tv) + '</th>' +
          '<td class="kf-num">' + esc(qtyFmt(h.qty)) + '</td>' +
          '<td class="kf-num">' + money(h.value[ccy]) + '</td>' +
          '<td class="kf-num">' + num(h.pct, 1) + '%</td></tr>';
      }).join('');
      root.appendChild(el('div', { 'class': 'kf-periods kf-holdings' },
        '<table><caption>' + esc(t('holdings')) + '</caption><thead><tr><th></th><th scope="col">' + esc(t('qty')) + '</th><th scope="col">' +
        esc(t('valueCol')) + '</th><th scope="col">' + esc(t('weight')) + '</th></tr></thead><tbody>' + rows + '</tbody></table>'));
    } else if (data.allocation && data.allocation.length) {
      var alloc = el('div', { 'class': 'kf-alloc' }, '<span class="kf-label">' + esc(t('allocation')) + '</span>');
      data.allocation.forEach(function (a) {
        alloc.insertAdjacentHTML('beforeend',
          '<div class="kf-alloc-row"><span>' + tvLink(esc(nameOf(a.name)), a.tv) + '</span><div class="kf-alloc-track"><div class="kf-alloc-fill" style="width:' +
          Math.max(0, Math.min(100, a.pct)) + '%"></div></div><span class="kf-num">' + num(a.pct, 1) + '%</span></div>');
      });
      root.appendChild(alloc);
    }

    var foot = esc(t('foot'));
    if (data.updated) {
      foot += ' ' + esc(t('asOf').replace('{d}', new Date(data.updated * 1000).toLocaleString(t('locale'), { dateStyle: 'medium', timeStyle: 'short' })));
    }
    root.appendChild(el('p', { 'class': 'kf-foot' }, foot));

    if (!root._kfResize) {
      var timer = null, lastW = chart.clientWidth;
      root._kfResize = function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
          var c = root.querySelector('.kf-chart');
          if (c && Math.abs(c.clientWidth - lastW) > 40) { lastW = c.clientWidth; render(root); }
        }, 200);
      };
      window.addEventListener('resize', root._kfResize);
    }
  }

  function init() {
    Array.prototype.forEach.call(document.querySelectorAll('.kf[data-kf]'), render);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
