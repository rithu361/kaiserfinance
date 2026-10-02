/* Kai$erFinance admin: instrument search with exchange picker. */
(function () {
  'use strict';
  var q = document.getElementById('kf-q');
  var list = document.getElementById('kf-results');
  if (!q || !list || !window.KF_ADMIN) return;

  var $ = function (id) { return document.getElementById(id); };
  var fields = {
    symbol: $('kf-symbol'), exchange: $('kf-exchange'), name: $('kf-name'), currency: $('kf-currency'),
    mode: $('kf-mode'), mic: $('kf-mic'), country: $('kf-country'), type: $('kf-type')
  };
  var picked = $('kf-picked');
  var hint = $('kf-price-hint');
  var priceInput = $('kf-price');
  var lastPrice = null;

  function fmt(n, cur) {
    var digits = Math.abs(n) < 10 ? 4 : 2;
    return n.toLocaleString('de-CH', { minimumFractionDigits: 2, maximumFractionDigits: digits }) + ' ' + cur;
  }
  /* Price hint and available cash under the price field. */
  function updateHint() {
    if (!hint) return;
    var cur = (fields.currency.value || 'USD').toUpperCase();
    var parts = [];
    if (lastPrice) parts.push('Last price: ' + fmt(lastPrice.price, cur));
    var cash = KF_ADMIN.cash;
    var short = false;
    if (cash && $('kf-side').value === 'buy') {
      var avail = cash[cur] || 0;
      parts.push('Cash available: ' + fmt(avail, cur));
      var q = parseFloat(String($('kf-qty').value).replace(/'/g, '').replace(',', '.'));
      var p = parseFloat(String(priceInput.value).replace(/'/g, '').replace(',', '.'));
      if (q > 0 && p > 0) {
        var cost = q * p;
        parts.push('This buy: ' + fmt(cost, cur));
        short = cost > avail + 0.005;
        if (short) parts.push('Not enough ' + cur + ' cash. Log a deposit or currency exchange first.');
      }
    }
    hint.textContent = parts.join(' · ');
    hint.classList.toggle('short', short);
  }
  function loadLastPrice() {
    lastPrice = null;
    priceInput.placeholder = '';
    updateHint();
    var sym = fields.symbol.value.trim();
    if (!sym) return;
    var url = KF_ADMIN.ajaxUrl + '?action=kf_last_price&nonce=' + encodeURIComponent(KF_ADMIN.nonce) +
      '&symbol=' + encodeURIComponent(sym) + '&mic_code=' + encodeURIComponent(fields.mic.value || '');
    fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (res) {
      if (res && res.success && res.data && res.data.price > 0) {
        lastPrice = res.data;
        priceInput.placeholder = String(Math.round(res.data.price * 10000) / 10000);
      }
      updateHint();
    }).catch(function () { updateHint(); });
  }
  ['kf-side', 'kf-qty', 'kf-price'].forEach(function (id) { $(id).addEventListener('input', updateHint); $(id).addEventListener('change', updateHint); });
  var curLabel = $('kf-price-cur');
  var results = [];
  var active = -1;
  var timer = null;
  var seq = 0;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  function close() {
    list.hidden = true;
    q.setAttribute('aria-expanded', 'false');
    active = -1;
  }

  function highlight(i) {
    active = i;
    Array.prototype.forEach.call(list.children, function (li, j) {
      li.setAttribute('aria-selected', j === i ? 'true' : 'false');
      if (j === i) li.scrollIntoView({ block: 'nearest' });
    });
  }

  function show(items, message) {
    results = items;
    list.innerHTML = '';
    if (message) {
      list.innerHTML = '<li aria-disabled="true"><small>' + esc(message) + '</small></li>';
    } else {
      items.forEach(function (r, i) {
        var li = document.createElement('li');
        li.setAttribute('role', 'option');
        li.id = 'kf-opt-' + i;
        li.innerHTML =
          '<span><strong>' + esc(r.symbol) + '</strong> ' + esc(r.name) + '<br><small>' +
          esc([r.exchange, r.country, r.type, r.currency].filter(Boolean).join(' · ')) + '</small></span>' +
          '<span class="kf-chip ' + (r.auto ? 'auto' : 'manual') + '">' + (r.auto ? 'Auto' : 'Manual price') + '</span>';
        li.addEventListener('mousedown', function (e) { e.preventDefault(); pick(i); });
        list.appendChild(li);
      });
    }
    list.hidden = false;
    q.setAttribute('aria-expanded', 'true');
  }

  function pick(i) {
    var r = results[i];
    if (!r) return;
    fields.symbol.value = r.symbol;
    fields.exchange.value = r.exchange || '';
    fields.name.value = r.name || r.symbol;
    fields.currency.value = r.currency || 'USD';
    fields.mic.value = r.mic_code || '';
    fields.country.value = r.country || '';
    fields.type.value = r.type || '';
    fields.mode.value = r.auto ? 'auto' : 'manual';
    curLabel.textContent = fields.currency.value;
    picked.hidden = false;
    picked.innerHTML = esc(r.name) + ' · <code>' + esc(r.symbol) + '</code>' +
      (r.exchange ? ' · ' + esc(r.exchange) : '') + ' · ' + esc(r.currency) +
      (r.auto ? ' · prices update hourly' : ' · not on the free plan, so you enter its price by hand');
    q.value = '';
    close();
    loadLastPrice();
    $('kf-qty').focus();
  }

  function search() {
    var term = q.value.trim();
    if (term.length < 2) { close(); return; }
    var mine = ++seq;
    show([], 'Searching…');
    var url = KF_ADMIN.ajaxUrl + '?action=kf_symbol_search&nonce=' + encodeURIComponent(KF_ADMIN.nonce) + '&q=' + encodeURIComponent(term);
    fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (mine !== seq) return;
        if (!res || !res.success) { show([], 'Search failed. Check the API key in Settings, or type the symbol below.'); return; }
        if (!res.data.length) { show([], 'No matches. Try the company name or ticker, or fill in the fields below by hand.'); return; }
        show(res.data);
        highlight(0);
      })
      .catch(function () { if (mine === seq) show([], 'Search failed. Try again.'); });
  }

  q.addEventListener('input', function () {
    clearTimeout(timer);
    timer = setTimeout(search, 300);
  });
  q.addEventListener('keydown', function (e) {
    if (list.hidden || !results.length) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); highlight(Math.min(active + 1, results.length - 1)); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(Math.max(active - 1, 0)); }
    else if (e.key === 'Enter') { e.preventDefault(); pick(active < 0 ? 0 : active); }
    else if (e.key === 'Escape') { close(); }
  });
  q.addEventListener('blur', function () { setTimeout(close, 150); });

  // Typing a symbol by hand means it's no longer the listing picked from search.
  fields.symbol.addEventListener('input', function () {
    fields.mic.value = '';
    fields.exchange.value = '';
    fields.country.value = '';
    fields.type.value = '';
    picked.hidden = true;
  });
  fields.currency.addEventListener('input', function () {
    curLabel.textContent = fields.currency.value.toUpperCase() || 'USD';
    updateHint();
  });
  var symTimer = null;
  fields.symbol.addEventListener('input', function () { clearTimeout(symTimer); symTimer = setTimeout(loadLastPrice, 500); });
  if (fields.symbol.value) loadLastPrice(); else updateHint();
})();

/* Currency exchange in the cash form: "Received amount" fills itself from the exchange rate
   (live for today, the day's close for a past date). Typing your own amount takes over;
   "Use rate" puts the calculated amount back. */
(function () {
  var $ = function (id) { return document.getElementById(id); };
  var type = $('kf-c-type'), cur = $('kf-c-cur'), amt = $('kf-c-amt'), cur2 = $('kf-c-cur2'), amt2 = $('kf-c-amt2'), date = $('kf-c-date'), hint = $('kf-c-rate');
  if (!type || !amt2 || !hint || typeof KF_ADMIN === 'undefined') return;
  var rate = null, source = '', manual = false, seq = 0, timer = null;
  var num = function (v) { var n = parseFloat(String(v).replace(/['’\s]/g, '').replace(',', '.')); return isFinite(n) ? n : null; };
  var fmtRate = function (r) { return r >= 100 ? r.toFixed(2) : r >= 1 ? r.toFixed(4) : r.toPrecision(4); };

  function render() {
    if (type.value !== 'exchange') { hint.textContent = ''; return; }
    if (rate === null) { hint.textContent = cur.value === cur2.value ? '' : 'Looking up the rate…'; return; }
    var text = '1 ' + cur.value + ' = ' + fmtRate(rate) + ' ' + cur2.value + ' (' + source + ')';
    hint.innerHTML = '';
    hint.appendChild(document.createTextNode(text));
    var a = num(amt.value);
    if (manual && a !== null) {
      var want = Math.round(a * rate * 100) / 100, have = num(amt2.value);
      if (have !== null && Math.abs(have - want) > 0.005) {
        var eff = have / a;
        hint.appendChild(document.createTextNode(' · your rate ' + fmtRate(eff) + ' (' + ((eff / rate - 1) * 100).toFixed(2) + '%) '));
        var use = document.createElement('a');
        use.href = '#'; use.textContent = 'Use rate';
        use.addEventListener('click', function (e) { e.preventDefault(); manual = false; fill(); });
        hint.appendChild(use);
      }
    }
  }
  function fill() {
    var a = num(amt.value);
    if (!manual && rate !== null && a !== null) amt2.value = (Math.round(a * rate * 100) / 100).toFixed(2);
    render();
  }
  function load() {
    if (type.value !== 'exchange' || !cur.value || !cur2.value) { render(); return; }
    var mine = ++seq;
    rate = null; render();
    var url = KF_ADMIN.ajaxUrl + '?action=kf_fx_rate&nonce=' + encodeURIComponent(KF_ADMIN.nonce) +
      '&from=' + encodeURIComponent(cur.value) + '&to=' + encodeURIComponent(cur2.value) + '&day=' + encodeURIComponent(date.value || '');
    fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (res) {
      if (mine !== seq) return;
      if (res && res.success && res.data) { rate = +res.data.rate; source = res.data.source; fill(); }
      else { hint.textContent = 'No rate available. Enter the received amount yourself.'; }
    }).catch(function () { if (mine === seq) hint.textContent = 'Rate lookup failed. Enter the received amount yourself.'; });
  }
  var later = function () { clearTimeout(timer); timer = setTimeout(load, 250); };
  [type, cur, cur2, date].forEach(function (n) { n.addEventListener('change', later); });
  amt.addEventListener('input', fill);
  amt2.addEventListener('input', function () { manual = amt2.value.trim() !== ''; render(); });
  load();
})();
