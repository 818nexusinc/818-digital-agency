/* 818 Digital Agency — progressive enhancement. All text is inserted with textContent (no HTML injection). */
(function () {
  'use strict';
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  function api(url, data) {
    return fetch(url, {
      method: data ? 'POST' : 'GET', credentials: 'same-origin',
      headers: data ? { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf } : { 'Accept': 'application/json' },
      body: data ? JSON.stringify(data) : undefined
    }).then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Unexpected response (' + r.status + ')' }; }); })
      .catch(function () { return { ok: false, error: 'Network error — check your connection and try again.' }; });
  }

  /* Render text with safe links: site paths (/services/x) and https URLs. */
  function richText(el, text) {
    var re = /(https:\/\/[^\s<>()]+|(?:^|(?<=[\s(]))\/[a-z0-9_\-\/#?=&.]+[a-z0-9_\/])/gi, last = 0, m;
    var s = String(text || '');
    try { re.lastIndex = 0; } catch (e) {}
    while ((m = re.exec(s))) {
      if (m.index > last) el.appendChild(document.createTextNode(s.slice(last, m.index)));
      var a = document.createElement('a'); a.href = m[0]; a.textContent = m[0];
      if (m[0].indexOf('http') === 0) { a.target = '_blank'; a.rel = 'noopener noreferrer'; }
      el.appendChild(a); last = m.index + m[0].length;
    }
    if (last < s.length) el.appendChild(document.createTextNode(s.slice(last)));
  }

  /* Mobile nav + app sidebars */
  $$('[data-toggle]').forEach(function (b) {
    b.addEventListener('click', function () { var t = document.getElementById(b.getAttribute('data-toggle')); if (t) { t.classList.toggle('open'); b.setAttribute('aria-expanded', t.classList.contains('open')); } });
  });
  $$('[data-copy]').forEach(function (b) { b.addEventListener('click', function () { var t = $(b.getAttribute('data-copy')); if (t && navigator.clipboard) navigator.clipboard.writeText(t.textContent).then(function () { b.textContent = 'Copied'; }); }); });
  $$('form[data-confirm]').forEach(function (f) { f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault(); }); });

  /* ── Chat widget ─────────────────────────────────────────────── */
  var panel = $('#chat-panel'), log = $('#chat-log'), form = $('#chat-form'), input = $('#chat-input'), fab = $('#chat-fab'), actionsBar = $('#chat-actions');
  var KEY = 'nx818_chat_conv', busy = false;
  function convId() { try { return sessionStorage.getItem(KEY); } catch (e) { return null; } }
  function setConv(id) { try { sessionStorage.setItem(KEY, id); } catch (e) {} }
  function bubble(role, text) {
    var d = document.createElement('div'); d.className = 'msg ' + role;
    if (role === 'assistant') richText(d, text); else d.textContent = text;
    log.appendChild(d); log.scrollTop = log.scrollHeight; return d;
  }
  function chips(list) {
    if (!actionsBar) return; actionsBar.textContent = '';
    (list || []).slice(0, 3).forEach(function (q) { var c = document.createElement('button'); c.type = 'button'; c.className = 'chip'; c.textContent = q; c.onclick = function () { send(q); }; actionsBar.appendChild(c); });
  }
  function actionLinks(actions) {
    (actions || []).forEach(function (a) {
      var label = { cart: 'Review cart & checkout', proposal: 'Open proposal ' + (a.number || ''), ticket: 'Support request ' + (a.ticket || a.number || '') + ' created', lead: 'Details saved — confirmation emailed', handoff: 'A person will follow up (ticket ' + (a.ticket || '') + ')' }[a.type];
      if (!label) return;
      var d = document.createElement(a.url ? 'a' : 'div'); d.className = 'msg note'; d.textContent = '✓ ' + label; if (a.url) { d.href = a.url; }
      log.appendChild(d);
      if (a.type === 'cart') { var n = $('.cart-pill .n'); if (n) n.textContent = a.count; else { var p = $('.cart-pill'); if (p) { var s = document.createElement('span'); s.className = 'n'; s.textContent = a.count; p.appendChild(s); } } }
    });
    log.scrollTop = log.scrollHeight;
  }
  function open(prefill) {
    if (!panel) return;
    panel.classList.add('open'); if (fab) fab.style.display = 'none';
    if (!log.childElementCount) {
      var id = convId();
      if (id) api('/api/v1/chat/history?conversation=' + encodeURIComponent(id)).then(function (r) { (r.messages || []).forEach(function (m) { bubble(m.role === 'user' ? 'user' : 'assistant', m.content); }); if (!log.childElementCount) greet(); });
      else greet();
    }
    if (prefill) send(prefill); else setTimeout(function () { input && input.focus(); }, 50);
  }
  function greet() {
    bubble('assistant', panel.getAttribute('data-greeting') || 'Hi! Tell me what you need and I\'ll recommend the right services, show transparent pricing and set everything up.');
    try { chips(JSON.parse(panel.getAttribute('data-suggestions') || '[]')); } catch (e) {}
  }
  function close() { panel.classList.remove('open'); if (fab) fab.style.display = ''; }
  function send(text) {
    text = (text || '').trim(); if (!text || busy) return;
    busy = true; bubble('user', text); if (input) input.value = '';
    var t = document.createElement('div'); t.className = 'msg assistant'; t.setAttribute('aria-label', 'Assistant is typing');
    t.innerHTML = '<span class="typing"><i></i><i></i><i></i></span>'; log.appendChild(t); log.scrollTop = log.scrollHeight;
    api('/api/v1/chat', { message: text, conversation: convId(), path: location.pathname, service: panel.getAttribute('data-service') || '' }).then(function (r) {
      t.remove(); busy = false;
      if (!r.ok) { bubble('assistant', r.error || 'Sorry, something went wrong.'); return; }
      setConv(r.conversation); bubble('assistant', r.reply); actionLinks(r.actions); chips(r.suggestions);
    });
  }
  if (panel) {
    fab && fab.addEventListener('click', function () { open(); });
    $('#chat-close') && $('#chat-close').addEventListener('click', close);
    form && form.addEventListener('submit', function (e) { e.preventDefault(); send(input.value); });
    input && input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(input.value); } });
    $('#chat-new') && $('#chat-new').addEventListener('click', function () { try { sessionStorage.removeItem(KEY); } catch (e) {} log.textContent = ''; greet(); });
    $$('[data-chat]').forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); open(b.getAttribute('data-chat') || ''); }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && panel.classList.contains('open')) close(); });
    if (document.body.hasAttribute('data-chat-inline')) open();
  }
  var comp = $('#composer');
  if (comp) {
    comp.addEventListener('submit', function (e) { e.preventDefault(); var v = $('#composer-text').value.trim(); open(v || null); });
    $$('#composer .chip').forEach(function (c) { c.addEventListener('click', function () { open(c.textContent); }); });
  }

  /* Live certificate */
  var cf = $('.cert-controls');
  if (cf) {
    var prices = JSON.parse(cf.getAttribute('data-prices') || '{}'), pricesN = JSON.parse(cf.getAttribute('data-prices-numbered') || '{}'), acts = JSON.parse(cf.getAttribute('data-acts') || '{}');
    var set = function (k, v) { var el = $('[data-cert="' + k + '"]'); if (el) el.textContent = v; };
    var render = function () {
      var n = $('#cn').value.trim(), end = $('#ce').value, p = $('#cp').value, numbered = end === 'Numbered';
      set('name', numbered ? '1234567 ' + (p === 'FED' ? 'Canada' : p) + ' Inc.' : ((n || 'Your Company') + ' ' + end));
      if (acts[p]) { set('issuer', acts[p][0]); set('act', acts[p][1]); }
      set('price', (numbered ? pricesN[p] : prices[p]) || '');
      $('[data-cert-slug]').value = numbered ? 'corp_numbered' : 'corp_named';
      $('#cn').disabled = numbered;
    };
    ['input', 'change'].forEach(function (ev) { cf.addEventListener(ev, render); });
    render();
  }

  /* Domain checker */
  var dform = $('#domain-check');
  if (dform) dform.addEventListener('submit', function (e) {
    e.preventDefault(); var out = $('#domain-result'), v = $('#domain-input').value.trim(); if (!v) return;
    out.textContent = 'Checking…';
    api('/api/v1/domain-check', { domain: v }).then(function (r) {
      out.textContent = '';
      if (!r.ok) { out.textContent = r.error; return; }
      var b = document.createElement('span'); b.className = 'badge ' + (r.status === 'available' ? 'b-green' : r.status === 'registered' ? 'b-red' : 'b-amber');
      b.textContent = r.domain + ' — ' + (r.status === 'available' ? 'available' : r.status === 'registered' ? 'already registered' : 'could not verify');
      out.appendChild(b);
      if (r.price) out.appendChild(document.createTextNode('  ' + r.price + '  '));
      if (r.status !== 'registered') {
        var f = document.createElement('form'); f.method = 'post'; f.action = '/cart/add'; f.style.display = 'inline';
        [['_csrf', csrf], ['slug', 'domain_register'], ['domain', r.domain], ['tld', r.domain.split('.').pop()]].forEach(function (p) { var i = document.createElement('input'); i.type = 'hidden'; i.name = p[0]; i.value = p[1]; f.appendChild(i); });
        var btn = document.createElement('button'); btn.className = 'btn btn-primary btn-sm'; btn.textContent = 'Add to cart'; f.appendChild(btn); out.appendChild(f);
      }
      if (r.note) { var n = document.createElement('div'); n.className = 'hint'; n.textContent = r.note; out.appendChild(n); }
    });
  });

  /* Owner assistant (admin) */
  var oform = $('#owner-form');
  if (oform) {
    var olog = $('#owner-log');
    oform.addEventListener('submit', function (e) {
      e.preventDefault(); var q = $('#owner-q').value.trim(); if (!q) return;
      var u = document.createElement('div'); u.className = 'msg user'; u.textContent = q; olog.appendChild(u); $('#owner-q').value = '';
      var t = document.createElement('div'); t.className = 'msg assistant'; t.innerHTML = '<span class="typing"><i></i><i></i><i></i></span>'; olog.appendChild(t); olog.scrollTop = olog.scrollHeight;
      api('/admin/assistant', { message: q }).then(function (r) { t.textContent = ''; richText(t, r.ok ? r.reply : (r.error || 'Error')); olog.scrollTop = olog.scrollHeight; });
    });
    $$('#owner-chips .chip').forEach(function (c) { c.addEventListener('click', function () { $('#owner-q').value = c.textContent; oform.requestSubmit(); }); });
  }


  /* Media: background videos stop for reduced motion, can be paused, and below-the-fold clips load only when scrolled to. */
  var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var hv = $('[data-autoplay]'), tg = $('[data-vid-toggle]');
  function setTg(paused) { if (!tg) return; tg.classList.toggle('paused', paused); tg.setAttribute('aria-label', paused ? 'Play background video' : 'Pause background video'); }
  if (hv) { if (still) { hv.removeAttribute('autoplay'); hv.pause(); setTg(true); } else { var pr = hv.play(); if (pr && pr.catch) pr.catch(function () { setTg(true); }); } }
  if (tg && hv) tg.addEventListener('click', function () { if (hv.paused) { hv.play(); setTg(false); } else { hv.pause(); setTg(true); } });
  var lazy = $$('[data-lazy-video]');
  if (lazy.length && 'IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (es) { es.forEach(function (en) {
      var v = en.target;
      if (en.isIntersecting) {
        if (!v.dataset.loaded) { $$('source[data-src]', v).forEach(function (s) { s.src = s.getAttribute('data-src'); }); v.load(); v.dataset.loaded = '1'; }
        if (!still) { var p = v.play(); if (p && p.catch) p.catch(function () {}); }
      } else if (!v.paused) v.pause();
    }); }, { rootMargin: '200px' });
    lazy.forEach(function (v) { io.observe(v); });
  }

  /* Cinematic layer: header glass on scroll, reveal, certificate tilt, tile spotlight, scroll story. All off for reduced motion. */
  document.documentElement.classList.add('js');
  var hdr = $('.site-header');
  if (hdr) { var onS = function () { hdr.classList.toggle('scrolled', window.scrollY > 40); }; window.addEventListener('scroll', onS, { passive: true }); onS(); }
  var rv = $$('[data-reveal]');
  if (still || !('IntersectionObserver' in window)) rv.forEach(function (el) { el.classList.add('in'); });
  else { var rio = new IntersectionObserver(function (es) { es.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); rio.unobserve(en.target); } }); }, { threshold: .12, rootMargin: '0px 0px -40px 0px' });
    rv.forEach(function (el, i) { el.style.transitionDelay = (i % 4) * 70 + 'ms'; rio.observe(el); }); }
  var tw = $('[data-tilt]'), cert = $('.cert');
  if (tw && cert && !still && window.matchMedia('(pointer:fine)').matches) {
    var hero = tw.closest('section') || tw;
    hero.addEventListener('pointermove', function (e) { var r = cert.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
      cert.style.setProperty('--ry', (x * 14).toFixed(2) + 'deg'); cert.style.setProperty('--rx', (-y * 10).toFixed(2) + 'deg'); cert.style.setProperty('--sheen', (50 - x * 120).toFixed(0) + '%'); });
    hero.addEventListener('pointerleave', function () { cert.style.removeProperty('--ry'); cert.style.removeProperty('--rx'); cert.style.removeProperty('--sheen'); });
  }
  var seal = $('.seal'), sealT;
  if (seal && !still) $$('#cp,#ce').forEach(function (el) { el.addEventListener('change', function () { seal.classList.remove('stamped', 'restamp'); void seal.offsetWidth; seal.classList.add('restamp'); }); });
  $$('[data-spot]').forEach(function (t) { t.addEventListener('pointermove', function (e) { var r = t.getBoundingClientRect(); t.style.setProperty('--mx', (e.clientX - r.left) + 'px'); t.style.setProperty('--my', (e.clientY - r.top) + 'px'); }); });
  var story = $('[data-story]');
  if (story) {
    var chs = $$('.chapters li', story), nEl = $('[data-story-n]', story), media = $('.story-media', story);
    var upd = function () { var mid = window.innerHeight * .55, cur = 0;
      chs.forEach(function (li, i) { var r = li.getBoundingClientRect(); if (r.top < mid) cur = i; });
      chs.forEach(function (li, i) { li.classList.toggle('on', i === cur); });
      if (nEl) nEl.textContent = cur + 1; if (media) media.style.setProperty('--p', ((cur + 1) / chs.length).toFixed(3)); };
    window.addEventListener('scroll', upd, { passive: true }); upd();
  }

  /* Mega menu (same structure as the original site): hover or click to open, one at a time; Esc or outside click closes. */
  var megas = $$('[data-mega]'), wide = window.matchMedia('(min-width: 961px)'), mt;
  function closeAll(except) { megas.forEach(function (li) { if (li === except) return; var b = $('.mn-btn', li), p = $('.mega', li); b.setAttribute('aria-expanded', 'false'); p.hidden = true; }); }
  function openM(li) { closeAll(li); $('.mn-btn', li).setAttribute('aria-expanded', 'true'); $('.mega', li).hidden = false; }
  megas.forEach(function (li) {
    var b = $('.mn-btn', li);
    b.addEventListener('click', function () { if (b.getAttribute('aria-expanded') === 'true') closeAll(); else openM(li); });
    li.addEventListener('mouseenter', function () { if (!wide.matches) return; clearTimeout(mt); mt = setTimeout(function () { openM(li); }, 90); });
    li.addEventListener('mouseleave', function () { if (!wide.matches) return; clearTimeout(mt); mt = setTimeout(closeAll, 220); });
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { var open = megas.filter(function (li) { return !$('.mega', li).hidden; })[0]; closeAll(); if (open) $('.mn-btn', open).focus(); } });
  document.addEventListener('click', function (e) { if (!e.target.closest('[data-mega]')) closeAll(); });

  /* Cookie notice: the site sets only an essential session cookie. Analytics stays off until an owner enables it (see Integration Hub). */
  var ck = $('#cookie');
  function consent() { try { return localStorage.getItem('nx818_cookie_consent'); } catch (e) { return 'essential'; } }
  if (ck && !consent()) ck.hidden = false;
  $$('[data-consent]').forEach(function (b) { b.addEventListener('click', function () { try { localStorage.setItem('nx818_cookie_consent', b.getAttribute('data-consent')); } catch (e) {} ck.hidden = true; }); });
})();
