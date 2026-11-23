/* 818 Digital Agency — progressive enhancement. All text is inserted with textContent (no HTML injection). */
(function () {
  'use strict';
  var I18N = (function () { try { return JSON.parse(document.documentElement.getAttribute('data-i18n') || '{}'); } catch (e) { return {}; } })();
  var T = function (s) { return I18N[s] || s; };
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
  $$('[data-copy]').forEach(function (b) { b.addEventListener('click', function () { var t = $(b.getAttribute('data-copy')); if (t && navigator.clipboard) navigator.clipboard.writeText(t.textContent).then(function () { b.textContent = T('Copied'); }); }); });
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
    out.textContent = T('Checking…');
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
        var btn = document.createElement('button'); btn.className = 'btn btn-primary btn-sm'; btn.textContent = T('Add to cart'); f.appendChild(btn); out.appendChild(f);
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

  /* v5 motion: every looping clip plays only while on screen; sections rise in once; the filing tracker steps through its stages. */
  var vids = $$('video[data-autoplay]');
  if (still) vids.forEach(function (v) { v.removeAttribute('autoplay'); v.pause(); });
  else if ('IntersectionObserver' in window) {
    var vio = new IntersectionObserver(function (es) { es.forEach(function (en) { var v = en.target; if (en.isIntersecting) { var p = v.play(); if (p && p.catch) p.catch(function () {}); } else if (!v.paused) v.pause(); }); }, { rootMargin: '120px' });
    vids.forEach(function (v) { vio.observe(v); });
  }
  var rvs = $$('[data-rv]');
  if (rvs.length && !still && 'IntersectionObserver' in window) {
    document.documentElement.classList.add('js-rv');
    rvs.forEach(function (el) { var sib = Array.prototype.filter.call(el.parentNode.children, function (c) { return c.hasAttribute('data-rv'); }); el.style.setProperty('--d', (Math.min(sib.indexOf(el), 6) * 0.08) + 's'); });
    var rvo = new IntersectionObserver(function (es) { es.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); rvo.unobserve(en.target); } }); }, { threshold: .1, rootMargin: '0px 0px -30px 0px' });
    rvs.forEach(function (el) { rvo.observe(el); });
    setTimeout(function () { rvs.forEach(function (el) { if (el.getBoundingClientRect().top < innerHeight) el.classList.add('in'); }); }, 60);
  }
  var trk = $('[data-track]');
  if (trk && !still) {
    var li = $$('li', trk), at = 2;
    setInterval(function () {
      at = (at + 1) % (li.length + 2);
      li.forEach(function (x, i) { x.className = i < at ? 'done' : (i === at ? 'now' : ''); });
    }, 1800);
  }

  /* Console: "/" focuses the page finder, which filters the sidebar; Enter opens the first match. */
  var nf = $('[data-navfind]');
  if (nf) {
    var navA = $$('.side nav a');
    nf.addEventListener('input', function () { var q = nf.value.trim().toLowerCase(); navA.forEach(function (a) { a.hidden = q && a.textContent.toLowerCase().indexOf(q) < 0; }); $$('.c-grp').forEach(function (g) { g.hidden = !$$('a:not([hidden])', g).length; }); });
    nf.addEventListener('keydown', function (e) { if (e.key === 'Enter') { var f = navA.filter(function (a) { return !a.hidden; })[0]; if (f) location.href = f.href; } if (e.key === 'Escape') { nf.value = ''; nf.dispatchEvent(new Event('input')); nf.blur(); } });
    document.addEventListener('keydown', function (e) { var tg = e.target.tagName; if ((e.key === '/' || ((e.metaKey || e.ctrlKey) && e.key === 'k')) && !/INPUT|TEXTAREA|SELECT/.test(tg)) { e.preventDefault(); var sd = $('#side'); if (sd && innerWidth <= 960) sd.classList.add('open'); nf.focus(); } });
  }

  /* Homepage story: header height for full-screen chapters, chapter rail, and the live name preview. */
  var hdr = $('.site-header');
  function setHH() { if (hdr) document.documentElement.style.setProperty('--hh', hdr.offsetHeight + 'px'); }
  setHH(); window.addEventListener('resize', setHH);
  var rail = $('[data-rail]');
  if (rail && 'IntersectionObserver' in window) {
    var rl = $$('a', rail), chs = $$('.story .ch');
    var cio = new IntersectionObserver(function (es) { es.forEach(function (en) {
      if (!en.isIntersecting) return;
      rl.forEach(function (a) { if (a.getAttribute('href') === '#' + en.target.id) a.setAttribute('aria-current', 'step'); else a.removeAttribute('aria-current'); });
      rail.classList.toggle('on-dark', en.target.classList.contains('ch-idea'));
    }); }, { threshold: .55 });
    chs.forEach(function (c) { cio.observe(c); });
    var tail = $('.story') && $('.story').nextElementSibling;
    var tio = new IntersectionObserver(function (es) { es.forEach(function (en) { rail.classList.toggle('off', en.boundingClientRect.top < innerHeight * .5 && en.isIntersecting); }); }, { threshold: [0, .3] });
    if (tail) tio.observe(tail);
  }
  var bn = $('[data-bizname]');
  if (bn) {
    var sheet = $('.sheet'), jur = $('[data-jur]'), def = bn.getAttribute('placeholder');
    var slug = function (v) { return v.toLowerCase().replace(/&/g, 'and').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '').slice(0, 40) || 'yourbusiness'; };
    var paint = function () {
      var v = bn.value.trim() || def, end = ($('[data-end]:checked') || {}).value || 'Inc.';
      $$('[data-name-out]').forEach(function (o) { o.textContent = v; });
      $$('[data-slug-out]').forEach(function (o) { o.textContent = slug(v); });
      $$('[data-end-out]').forEach(function (o) { o.textContent = end; });
      $$('[data-jur-out],[data-jur-out2]').forEach(function (jo) { if (jur) jo.textContent = jur.options[jur.selectedIndex].text; });
      $$('[data-name-link]').forEach(function (a) { a.href = a.getAttribute('data-name-link') + (bn.value.trim() ? '?name=' + encodeURIComponent(bn.value.trim() + ' ' + end) : ''); });
      if (sheet) sheet.classList.toggle('signed', bn.value.trim().length > 1);
      var dm = $('[data-dm]'); if (dm) dm.placeholder = slug(v) + '.ca';
    };
    bn.addEventListener('input', paint); if (jur) jur.addEventListener('change', paint);
    $$('[data-end]').forEach(function (r) { r.addEventListener('change', paint); });
    paint();
  }

  /* Colour flow: the page colour blends continuously from one chapter's colour to the next as you scroll,
     the header and rail take the tone of the chapter in view, and the rail fills with progress. */
  var root = document.documentElement, flowChs = $$('.story .ch[data-flow]');
  var hex = function (h) { h = h.replace('#', ''); return [parseInt(h.substr(0, 2), 16), parseInt(h.substr(2, 2), 16), parseInt(h.substr(4, 2), 16)]; };
  var mix = function (a, b, t) { return 'rgb(' + a.map(function (v, i) { return Math.round(v + (b[i] - v) * t); }).join(',') + ')'; };
  var onScroll = function () { document.body.classList.toggle('scrolled', scrollY > 10); };
  onScroll(); window.addEventListener('scroll', onScroll, { passive: true });
  if (!flowChs.length) root.setAttribute('data-tone', 'light');
  else {
    var cols = flowChs.map(function (c) { return hex(c.getAttribute('data-flow')); });
    var railFill = $('[data-rail-fill]'), ticking = false;
    var flow = function () {
      ticking = false;
      var mid = innerHeight * 0.5, cur = 0;
      flowChs.forEach(function (c, i) { if (c.getBoundingClientRect().top <= mid) cur = i; });
      var r = flowChs[cur].getBoundingClientRect(), col = cols[cur];
      var next = cur + 1 < flowChs.length ? cur + 1 : null;
      if (next !== null) {
        // blend across the last 40% of the chapter, eased
        var start = r.height * 0.6, into = mid - r.top - start, span = r.height * 0.4;
        var t = Math.max(0, Math.min(1, into / span)); t = t * t * (3 - 2 * t);
        root.style.setProperty('--flow', mix(cols[cur], cols[next], t));
      } else root.style.setProperty('--flow', 'rgb(' + col.join(',') + ')');
      if (flowChs[flowChs.length - 1].getBoundingClientRect().bottom <= mid) { root.style.setProperty('--flow', '#ffffff'); root.setAttribute('data-tone', 'light'); }
      else {
        var hdrCh = flowChs.filter(function (c) { var b = c.getBoundingClientRect(); return b.top <= 40 && b.bottom > 40; })[0] || flowChs[cur];
        root.setAttribute('data-tone', hdrCh.getAttribute('data-tone') || 'light');
      }
      if (railFill) { var st = $('.story'), sb = st.getBoundingClientRect(); railFill.parentNode.parentNode.style.setProperty('--rp', Math.max(0, Math.min(100, (-sb.top + mid) / sb.height * 100)) + '%'); }
    };
    window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(flow); } }, { passive: true });
    window.addEventListener('resize', flow); flow();
  }

  /* Chapters and sections get .in-view once, which starts their own animations (split headings, bars, calendar). */
  $$('[data-split]').forEach(function (h) {
    var words = h.textContent.trim().split(/\s+/); h.textContent = ''; h.setAttribute('aria-label', words.join(' '));
    words.forEach(function (w, i) { var o = document.createElement('span'); o.className = 'w'; o.setAttribute('aria-hidden', 'true'); var n = document.createElement('span'); n.textContent = w; n.style.setProperty('--wi', i); o.appendChild(n); h.appendChild(o); h.appendChild(document.createTextNode(' ')); });
  });
  var viewEls = $$('.story .ch, main section');
  if (still || !('IntersectionObserver' in window)) viewEls.forEach(function (el) { el.classList.add('in-view'); });
  else {
    var vio2 = new IntersectionObserver(function (es) { es.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in-view'); vio2.unobserve(en.target); } }); }, { threshold: 0.18 });
    viewEls.forEach(function (el) { vio2.observe(el); });
  }

  /* Start here: the path under the pointer or focus opens. */
  $$('.paths .path').forEach(function (p, i, all) {
    var open = function () { all.forEach(function (x) { x.classList.toggle('on', x === p); }); };
    p.addEventListener('mouseenter', open); p.addEventListener('focusin', open); p.addEventListener('click', open);
  });

  /* Mobile drawer: lock the page behind it and close it on Escape. */
  var mb = $('.menu-btn'), nb = $('#site-navbar');
  if (mb && nb) {
    mb.addEventListener('click', function () { setTimeout(function () { document.body.classList.toggle('nav-open', nb.classList.contains('open')); }, 0); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && nb.classList.contains('open')) { nb.classList.remove('open'); document.body.classList.remove('nav-open'); mb.setAttribute('aria-expanded', 'false'); } });
  }

  /* Site-wide reveal: tag common blocks on every page so they rise in once; hero images unmask. */
  if (!still && 'IntersectionObserver' in window && !$('.story')) {
    $$('main .sec-head, main .hub-card, main .feature-row > *, main .faq-l details, main .prog, main .wz, main .wz-sum, main .help-card, main .refund-note, main .cta-l, main .about-grid > *, main .dom-box, main table').forEach(function (el) { if (!el.hasAttribute('data-rv')) el.setAttribute('data-rv', ''); });
    document.documentElement.classList.add('js-rv');
    var later = $$('[data-rv]:not(.in)');
    later.forEach(function (el) { var sib = Array.prototype.filter.call(el.parentNode.children, function (c) { return c.hasAttribute('data-rv'); }); el.style.setProperty('--d', (Math.min(sib.indexOf(el), 6) * 0.07) + 's'); });
    var rvo2 = new IntersectionObserver(function (es) { es.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); rvo2.unobserve(en.target); } }); }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });
    later.forEach(function (el) { rvo2.observe(el); });
    $$('.sh-frame').forEach(function (f) { f.classList.add('rv-img'); rvo2.observe(f); });
  }

  /* Menu: a soft pill glides to the item under the pointer, in that business's colour. */
  var ml = $('.mn-list');
  if (ml && window.matchMedia('(min-width:1241px)').matches) {
    var glide = document.createElement('li'); glide.className = 'mn-glide'; glide.setAttribute('aria-hidden', 'true'); ml.appendChild(glide);
    var moveTo = function (it) { if (!it) { glide.style.opacity = 0; return; } var b = $('.mn-btn', it), r = b.getBoundingClientRect(), lr = $('.site-header').getBoundingClientRect();
      glide.style.cssText = 'opacity:1;width:' + r.width + 'px;height:' + r.height + 'px;transform:translate(' + (r.left - lr.left) + 'px,' + (r.top - lr.top) + 'px);background:' + getComputedStyle(it).getPropertyValue('--acc-soft'); };
    $$('.mn-item', ml).forEach(function (it) { it.addEventListener('mouseenter', function () { moveTo(it); }); it.addEventListener('focusin', function () { moveTo(it); }); });
    ml.addEventListener('mouseleave', function () { var open = $('.mn-btn[aria-expanded=true]', ml); moveTo(open ? open.closest('.mn-item') : $('.mn-btn.on', ml) ? $('.mn-btn.on', ml).closest('.mn-item') : null); });
    var on = $('.mn-btn.on', ml); if (on) setTimeout(function () { moveTo(on.closest('.mn-item')); }, 50);
  }

  /* Homepage: one wheel step or key press moves to the next chapter (desktop). Inside a chapter taller than the
     screen, scrolling stays native until its end; below the story the page scrolls normally. */
  var fchs = $$('.story .ch');
  if (fchs.length && !still && window.matchMedia('(min-width: 901px) and (pointer: fine)').matches) {
    var lock = false, storyEnd = function () { var l = fchs[fchs.length - 1]; return l.offsetTop + l.offsetHeight; };
    var curIdx = function () { var y = scrollY + innerHeight * 0.5, c = 0; fchs.forEach(function (ch, i) { if (ch.offsetTop <= y) c = i; }); return c; };
    var go = function (i) { lock = true; window.scrollTo({ top: fchs[i].offsetTop, behavior: 'smooth' }); setTimeout(function () { lock = false; }, 900); };
    var step = function (dir, e) {
      if (scrollY >= storyEnd() - 4 && dir > 0) return false;
      if (scrollY > storyEnd() - innerHeight + 4 && dir < 0 && scrollY > fchs[fchs.length - 1].offsetTop + 4 && fchs[fchs.length - 1].offsetHeight <= innerHeight + 4) { go(fchs.length - 1); return true; }
      if (scrollY >= storyEnd() - 4) return false;
      var i = curIdx(), ch = fchs[i], top = ch.offsetTop, bottom = top + ch.offsetHeight;
      if (dir > 0) {
        if (bottom > scrollY + innerHeight + 6) return false;
        if (i < fchs.length - 1) { go(i + 1); return true; }
        return false;
      }
      if (scrollY > top + 6 && ch.offsetHeight > innerHeight + 6) return false;
      if (scrollY > top + 6) { go(i); return true; }
      if (i > 0) { go(i - 1); return true; }
      return false;
    };
    window.addEventListener('wheel', function (e) {
      if (e.ctrlKey || Math.abs(e.deltaY) < 4) return;
      if (e.target.closest && e.target.closest('.mega, .chooser, textarea, select, .chat')) return;
      if (lock) { if (scrollY < storyEnd() - 4) e.preventDefault(); return; }
      if (step(e.deltaY > 0 ? 1 : -1, e)) e.preventDefault();
    }, { passive: false });
    document.addEventListener('keydown', function (e) {
      if (/INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.target.isContentEditable) return;
      var d = { ArrowDown: 1, PageDown: 1, ' ': 1, ArrowUp: -1, PageUp: -1 }[e.key];
      if (!d || e.shiftKey && e.key === ' ') return;
      if (lock) { e.preventDefault(); return; }
      if (step(d)) e.preventDefault();
    });
  }

  /* Header photos lean gently toward the pointer. */
  if (!still && window.matchMedia('(pointer: fine)').matches) $$('[data-tilt]').forEach(function (f) {
    var box = f.parentNode;
    box.addEventListener('mousemove', function (e) { var r = box.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
      f.style.transform = 'perspective(900px) rotateY(' + (x * 6).toFixed(2) + 'deg) rotateX(' + (-y * 6).toFixed(2) + 'deg)'; });
    box.addEventListener('mouseleave', function () { f.style.transform = ''; });
  });

  /* Trust pages: table of contents from the headings, with the current section highlighted. */
  var tsrc = $('[data-toc-src]'), toc = $('[data-toc]');
  if (tsrc && toc) {
    var hs = $$('h2', tsrc);
    if (!hs.length) toc.parentNode.querySelector('p').hidden = true;
    hs.forEach(function (h, i) { h.id = h.id || 'sec-' + (i + 1); var li = document.createElement('li'), a = document.createElement('a'); a.href = '#' + h.id; a.textContent = h.textContent; li.appendChild(a); toc.appendChild(li); });
    if ('IntersectionObserver' in window) {
      var tio = new IntersectionObserver(function (es) { es.forEach(function (en) { if (en.isIntersecting) $$('a', toc).forEach(function (a) { a.classList.toggle('on', a.getAttribute('href') === '#' + en.target.id); }); }); }, { rootMargin: '-30% 0px -60% 0px' });
      hs.forEach(function (h) { tio.observe(h); });
    }
  }
})();
