/* 818 Digital Agency — step-by-step service forms (same flows as the original site).
   Progressive enhancement: steps, inline validation, repeatable rows, live price summary, review, submit.
   All text is inserted with textContent. Prices shown here are previews; the server recalculates everything. */
(function () {
  'use strict';
  var I18N = (function () { try { return JSON.parse(document.documentElement.getAttribute('data-i18n') || '{}'); } catch (e) { return {}; } })();
  var T = function (s) { return I18N[s] || s; };
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';
  var FR = /^fr/.test(document.documentElement.lang);
  var money = function (c) { var n = (c / 100).toLocaleString(FR ? 'fr-CA' : 'en-CA', { minimumFractionDigits: c % 100 ? 2 : 0, maximumFractionDigits: 2 }); return FR ? n + '\u00a0$' : '$' + n; };
  var RULES = {
    postal: [/^[ABCEGHJ-NPRSTVXY]\d[ABCEGHJ-NPRSTV-Z][ -]?\d[ABCEGHJ-NPRSTV-Z]\d$/i, 'Enter a valid Canadian postal code (e.g. E1C 4X8).'],
    phone: [/^[+\d][\d\s().-]{6,19}$/, 'Enter a valid phone number.'],
    person: [/^[\p{L}][\p{L}' .-]{0,79}$/u, 'Use letters only.'],
    bizname: [/^.{2,100}$/, 'Use 2 to 100 characters.'],
    domain: [/^(?!-)[a-z0-9-]{1,63}(\.[a-z]{2,})+$/i, 'Enter a domain like yourbusiness.ca'],
    prefix: [/^[a-z0-9._-]{1,40}$/i, 'Letters, numbers, dots, dashes only.']
  };

  function fieldWrap(el) { return el.closest('.fld, fieldset, .rep, .ck') || el.parentElement; }
  function setErr(el, msg) {
    var w = fieldWrap(el), fe = w && (w.querySelector(':scope > .fe') || (w.parentElement && w.parentElement.querySelector(':scope > .fe')));
    if (w) w.classList.toggle('bad', !!msg);
    if (fe) fe.textContent = msg || '';
  }
  function check(el) {
    if (el.disabled || el.closest('[hidden]') && !el.closest('.wz-step:not([hidden])')) return true;
    var v = (el.value || '').trim(), msg = '';
    if (el.type === 'radio') {
      if (el.required && !$$('input[name="' + CSS.escape(el.name) + '"]', el.form).some(function (r) { return r.checked; })) msg = T('Please choose an option.');
    } else if (el.type === 'checkbox') {
      if (el.required && !el.checked) msg = el.name === 'agree' ? 'Please read and accept the Service Agreement to continue.' : T('Required.');
    } else if (el.required && !v) msg = T('Required.');
    else if (v && el.dataset.rule && RULES[el.dataset.rule] && !RULES[el.dataset.rule][0].test(v)) msg = T(RULES[el.dataset.rule][1]);
    else if (v && el.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) msg = T('Enter a valid email address.');
    else if (v && el.minLength > 0 && v.length < el.minLength) msg = T('Please write at least {n} characters.').replace('{n}', el.minLength);
    else if (v && el.type === 'number' && el.min !== '' && +v < +el.min) msg = T('Minimum is {n}.').replace('{n}', el.min);
    else if (v && el.type === 'date' && el.dataset.future && v < new Date().toISOString().slice(0, 10)) msg = T('Choose today or a later date.');
    setErr(el, msg);
    return !msg;
  }

  function Wizard(form) {
    var steps = $$('.wz-step', form), tabs = $$('.wz-tabs li', form), bar = $('.wz-bar i', form), cur = 0;
    var page = form.closest('.svc-body') || document;
    var totalEl = $('[data-sum-total]', page), linesEl = $('[data-sum-lines]', page), mTotal = $('[data-sum-total-m]'), mbar = $('[data-wz-mbar]');

    /* repeatable rows */
    $$('[data-repeat]', form).forEach(function (rep) {
      var list = $('.rep-list', rep), tpl = $('template', rep), min = +rep.dataset.min, max = +rep.dataset.max, add = $('[data-rep-add]', rep);
      function renum() {
        $$('.rep-item', list).forEach(function (it, i) {
          $('.rep-head b', it).textContent = rep.dataset.item + ' ' + (i + 1);
          $$('[name]', it).forEach(function (f) { f.name = f.name.replace(/\[\d+\]/, '[' + i + ']'); });
          $('[data-rep-remove]', it).hidden = $$('.rep-item', list).length <= min;
        });
        add.hidden = $$('.rep-item', list).length >= max;
        form.dispatchEvent(new Event('change'));
      }
      function addRow() {
        var n = $$('.rep-item', list).length, html = tpl.innerHTML.replace(/\{i\}/g, n).replace(/\{n\}/g, n + 1);
        var d = document.createElement('div'); d.innerHTML = html; var it = d.firstElementChild; list.appendChild(it);
        $('[data-rep-remove]', it).addEventListener('click', function () { it.remove(); renum(); });
        renum(); return it;
      }
      add.addEventListener('click', function () { var it = addRow(); var f = $('input,select,textarea', it); if (f) f.focus(); });
      for (var i = 0; i < Math.max(min, 1); i++) addRow();
    });

    /* conditional blocks: data-show="name=value" */
    function conditions() {
      $$('[data-show], [data-hide]', form).forEach(function (b) {
        var rule = b.dataset.show || b.dataset.hide, p = rule.split('='), hit = $$('[name="' + p[0] + '"]', form).some(function (f) { return (f.type === 'radio' || f.type === 'checkbox') ? f.checked && f.value === p[1] : f.value === p[1]; });
        var on = b.dataset.show ? hit : !hit;
        b.hidden = !on;
        $$('input,select,textarea', b).forEach(function (f) { f.disabled = !on; });
      });
    }

    /* live price */
    function price() {
      var base = totalEl ? +totalEl.dataset.base || 0 : 0, sum = base, lines = [];
      $$('[data-cents], [data-by]', form).forEach(function (f) {
        if (f.disabled || ((f.type === 'radio' || f.type === 'checkbox') && !f.checked)) return;
        var c = +f.dataset.cents;
        if (f.dataset.by) { var src = $$('[name="' + f.dataset.by + '"]', form).filter(function (x) { return x.type !== 'radio' || x.checked; })[0]; c = src ? +(f.getAttribute('data-c-' + src.value) || 0) : 0; }
        if (!c) return; sum += c; lines.push([f.dataset.line || f.dataset.text || T('Item'), c, f.dataset.per || '']);
      });
      var txt = sum ? money(sum) : '—';
      if (totalEl) totalEl.textContent = txt;
      if (mTotal) mTotal.textContent = txt;
      if (linesEl) { linesEl.textContent = ''; lines.forEach(function (l) { var li = document.createElement('li'); var a = document.createElement('span'); a.textContent = l[0]; var b = document.createElement('b'); b.textContent = money(l[1]) + (l[2] || ''); li.appendChild(a); li.appendChild(b); linesEl.appendChild(li); }); }
      $$('[data-sum-field]', page).forEach(function (o) { var f = $$('[name="' + o.dataset.sumField + '"]', form).filter(function (x) { return x.type !== 'radio' || x.checked; })[0]; o.textContent = f ? (f.dataset.text || f.value || '—') : '—'; });
    }

    function show(i) {
      cur = Math.max(0, Math.min(steps.length - 1, i));
      steps.forEach(function (s, k) { s.hidden = k !== cur; });
      tabs.forEach(function (t, k) { t.classList.toggle('on', k === cur); t.classList.toggle('done', k < cur); });
      if (bar) bar.style.width = (steps.length > 1 ? (cur / (steps.length - 1)) * 100 : 100) + '%';
      if ($('[data-wz-review]', steps[cur])) review($('[data-wz-review]', steps[cur]));
      if (mbar) { var mn = $('[data-wz-mnext]', mbar); mn.hidden = !!$('[data-wz-review]', steps[cur]); }
      if (show.ready) saveDraft();
      var top = form.getBoundingClientRect().top + window.scrollY - 140;
      if (show.ready && window.scrollY > top) window.scrollTo({ top: top, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
      if (show.ready) { var first = $('h3', steps[cur]); if (first) { first.tabIndex = -1; first.focus({ preventScroll: true }); } }
    }
    function validStep(s) {
      var ok = true, seen = {};
      $$('input,select,textarea', s).forEach(function (el) {
        if (el.type === 'radio') { if (seen[el.name]) return; seen[el.name] = 1; }
        if (el.name === 'website' || el.type === 'hidden') return;
        if (!check(el)) ok = false;
      });
      $$('[data-repeat]', s).forEach(function (rep) {
        var n = $$('.rep-item', rep).length, fe = $(':scope > .fe', rep);
        var msg = n < +rep.dataset.min ? T('Add at least {n}.').replace('{n}', rep.dataset.min) : '';
        if (!msg && rep.dataset.total) { var t = $$('[name$="[' + rep.dataset.total + ']"]', rep).reduce(function (a, f) { return a + (+f.value || 0); }, 0); if (Math.round(t) !== 100) msg = T('Shares must total 100% (now {n}%).').replace('{n}', t); }
        if (fe) fe.textContent = msg; if (msg) ok = false;
      });
      if (!ok) { var bad = $('.bad input, .bad select, .bad textarea', s); if (bad) bad.focus(); }
      return ok;
    }
    function review(dl) {
      dl.textContent = '';
      steps.slice(0, -1).forEach(function (s) {
        var h = $('h3', s), rows = [], seen = {};
        $$('input,select,textarea', s).forEach(function (f) {
          if (f.disabled || !f.name || f.name === 'website' || f.type === 'hidden') return;
          if ((f.type === 'radio' || f.type === 'checkbox') && !f.checked) return;
          var key = f.type === 'checkbox' ? f.name + f.value : f.name; if (seen[key]) return; seen[key] = 1;
          var v = f.dataset.text || (f.tagName === 'SELECT' ? (f.options[f.selectedIndex] || {}).text : f.value);
          if (!v || !String(v).trim()) return;
          var item = f.closest('.rep-item'), lab = (item ? $('.rep-head b', item).textContent + ' · ' : '') + (f.dataset.label || f.name);
          rows.push([lab, v]);
        });
        if (!rows.length) return;
        var dt = document.createElement('dt'); dt.className = 'grp'; dt.textContent = h ? h.textContent : ''; dl.appendChild(dt);
        rows.forEach(function (r) { var a = document.createElement('dt'); a.textContent = r[0]; var b = document.createElement('dd'); b.textContent = r[1]; dl.appendChild(a); dl.appendChild(b); });
      });
    }

    /* collect into nested object: a[b][0][c] → {a:{b:[{c}]}} */
    /* Unfinished forms are kept on our side so the team can help the visitor finish. */
    var draftKey = 'nx818_draft_' + location.pathname, draftToken = '';
    try { draftToken = sessionStorage.getItem(draftKey) || ''; } catch (e) {}
    function saveDraft() {
      var p = payload(), d = { token: draftToken, form: p.slug || form.dataset.form || '', path: location.pathname, step: cur, steps: steps.length, data: p.details || {} };
      fetch('/order/draft', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(d), keepalive: true })
        .then(function (r) { return r.json(); }).then(function (j) { if (j && j.token) { draftToken = j.token; try { sessionStorage.setItem(draftKey, j.token); } catch (e) {} var sv = $('.wz-saved', form); if (!sv) { sv = document.createElement('p'); sv.className = 'wz-saved'; sv.setAttribute('role', 'status'); form.appendChild(sv); } sv.textContent = T('Progress saved. You can come back and finish later.'); } }).catch(function () {});
    }
    var draftTimer = null;
    form.addEventListener('focusout', function () { clearTimeout(draftTimer); draftTimer = setTimeout(function () { if (show.ready) saveDraft(); }, 1500); });
    function payload() {
      var out = { details: {}, addons: [] }, CTRL = { slug: 1, province: 1, tld: 1, domain: 1, agree: 1, kind: 1, website: 1, addon_tld: 1, addon_domain: 1, _ft: 1, _hj: 1 };
      $$('input,select,textarea', form).forEach(function (f) {
        if (f.disabled || !f.name) return;
        if ((f.type === 'radio' || f.type === 'checkbox') && !f.checked) return;
        var v = f.type === 'checkbox' && f.value === 'on' ? true : f.value;
        if (f.name === 'addons[]') { out.addons.push(v); return; }
        if (CTRL[f.name]) { out[f.name] = f.name === 'agree' ? true : v; return; }
        if (v === '' ) return;
        var keys = f.name.replace(/\]/g, '').split('['), o = out.details;
        keys.forEach(function (k, i) {
          var last = i === keys.length - 1, nextIsNum = !last && /^\d+$/.test(keys[i + 1]);
          if (last) { o[k] = v; return; }
          if (o[k] === undefined) o[k] = nextIsNum ? [] : {};
          o = o[k];
        });
      });
      Object.keys(out.details).forEach(function (k) { if (Array.isArray(out.details[k])) out.details[k] = out.details[k].filter(Boolean); });
      if (form.dataset.kind) out.kind = form.dataset.kind;
      return out;
    }

    form.addEventListener('click', function (e) {
      if (e.target.closest('[data-wz-next]')) { if (validStep(steps[cur])) show(cur + 1); }
      if (e.target.closest('[data-wz-back]')) show(cur - 1);
    });
    tabs.forEach(function (t, k) { t.addEventListener('click', function () { if (k < cur) show(k); }); });
    if (mbar) $('[data-wz-mnext]', mbar).addEventListener('click', function () { if (validStep(steps[cur])) show(cur + 1); });
    form.addEventListener('input', function (e) { if (e.target.closest('.bad')) check(e.target); price(); });
    form.addEventListener('change', function (e) { if (e.target && e.target.name) { conditions(); if (e.target.closest && e.target.closest('.bad')) check(e.target); } price(); });
    form.addEventListener('focusout', function (e) { if (e.target.matches && e.target.matches('input:not([type=radio]):not([type=checkbox]),select,textarea') && e.target.value) check(e.target); });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var last = steps[steps.length - 1];
      if (!validStep(last)) return;
      for (var i = 0; i < steps.length - 1; i++) if (!validStep(steps[i])) { show(i); return; }
      var btn = $('[data-wz-submit]', form), label = btn.textContent; btn.disabled = true; btn.textContent = T('Working…');
      fetch(form.dataset.endpoint || '/order/add', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(Object.assign(payload(), { draft: draftToken })) })
        .then(function (r) { return r.json().then(function (j) { return [r.ok, j]; }); })
        .then(function (res) {
          var ok = res[0] && res[1].ok, j = res[1];
          if (!ok) { var fe = $('.agree + .fe', last) || $('.fe', last); if (fe) fe.textContent = (j && (j.error || j.message)) || T('Something went wrong. Please try again.'); btn.disabled = false; btn.textContent = label; return; }
          if (j.redirect) { var pill = $('.cart-pill'); if (pill) { var n = $('.n', pill) || pill.appendChild(document.createElement('span')); n.className = 'n'; n.textContent = j.count; } window.location.href = j.redirect; return; }
          var done = $('.wz-done', last); $$('.wz-nav, .wz-review, .agree, p.muted', last).forEach(function (x) { x.hidden = true; });
          done.hidden = false; done.textContent = '';
          var h = document.createElement('h4'); h.textContent = T('Request received'); var p = document.createElement('p');
          p.textContent = T('Thank you. We have emailed you a confirmation{ref} and will reply within one business day.').replace('{ref}', j.reference ? ' (' + T('reference') + ' ' + j.reference + ')' : '');
          done.appendChild(h); done.appendChild(p);
        })
        .catch(function () { btn.disabled = false; btn.textContent = label; var fe = $('.agree + .fe', last); if (fe) fe.textContent = T('Network error. Please try again.'); });
    });
    conditions(); price(); show(0); show.ready = true;
  }
  $$('[data-wz]').forEach(Wizard);


  function post(url, body) {
    return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(body) })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { j._ok = r.ok && j.ok !== false; return j; }); });
  }

  /* Domain search (live registry check through our API) */
  $$('[data-domain-tool]').forEach(function (box) {
    var tlds = JSON.parse(box.dataset.tlds || '{}'), out = $('[data-dom-results]', box), inp = $('#dom-q', box), agreeWrap = $('[data-dom-agree]', box), err = $('[data-dom-err]', box);
    function row(domain) {
      var r = document.createElement('div'); r.className = 'dom-res';
      var a = document.createElement('div'); var b = document.createElement('b'); b.textContent = domain; var st = document.createElement('div'); st.className = 'st unk'; st.textContent = T('Checking…'); a.appendChild(b); a.appendChild(st);
      var tld = domain.split('.').slice(1).join('.'), right = document.createElement('div'); right.style.textAlign = 'right';
      var pr = document.createElement('div'); pr.textContent = tlds[tld] ? money(tlds[tld]) + '/yr' : ''; pr.style.fontWeight = '650';
      var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'btn btn-primary btn-sm'; btn.textContent = T('Add to cart'); btn.hidden = true;
      right.appendChild(pr); right.appendChild(btn); r.appendChild(a); r.appendChild(right); out.appendChild(r);
      post('/api/v1/domain-check', { domain: domain }).then(function (j) {
        if (!j._ok) { st.textContent = j.error || T('Could not verify. Try again.'); return; }
        if (j.status === 'available') { st.className = 'st ok'; st.textContent = '✓ Available'; btn.hidden = !tlds[tld]; if (!tlds[tld]) st.textContent += ' (extension not offered yet)'; agreeWrap.hidden = false; }
        else if (j.status === 'registered') { st.className = 'st no'; st.textContent = T('Already registered'); }
        else { st.textContent = T('Availability confirmed before registration'); btn.hidden = !tlds[tld]; agreeWrap.hidden = false; }
      });
      btn.addEventListener('click', function () {
        err.textContent = '';
        if (!$('[data-agree]', box).checked) { err.textContent = T('Please read and accept the Service Agreement to continue.'); return; }
        btn.disabled = true; btn.textContent = T('Adding…');
        post('/order/add', { slug: 'domain_register', domain: domain, tld: tld, agree: true, details: { domain: domain } }).then(function (j) {
          if (j._ok && j.redirect) location.href = j.redirect; else { btn.disabled = false; btn.textContent = T('Add to cart'); err.textContent = j.error || T('Could not add this domain.'); }
        });
      });
    }
    function search(q) {
      q = (q || '').trim().toLowerCase().replace(/^https?:\/\//, '').replace(/^www\./, '').replace(/\/.*$/, '');
      if (!q) return; out.textContent = ''; err.textContent = '';
      if (!/^[a-z0-9-]+(\.[a-z.]{2,})?$/.test(q)) { err.textContent = T('Enter a name like mybusiness or mybusiness.ca'); return; }
      var base = q.split('.')[0], list = q.indexOf('.') > 0 ? [q] : [];
      Object.keys(tlds).slice(0, 4).forEach(function (t) { var d = base + '.' + t; if (list.indexOf(d) < 0) list.push(d); });
      list.slice(0, 5).forEach(row);
    }
    $('[data-dom-form]', box).addEventListener('submit', function (e) { e.preventDefault(); search(inp.value); });
    var q0 = new URLSearchParams(location.search).get('q'); if (q0) { inp.value = q0.slice(0, 80); search(inp.value); }
    $$('[data-tld]', box).forEach(function (b) { b.addEventListener('click', function () { var base = (inp.value.trim() || 'mybusiness').split('.')[0]; inp.value = base + '.' + b.dataset.tld; search(inp.value); }); });
  });

  /* Bundle builder: live preview; the server decides the real discount. */
  $$('[data-bundle]').forEach(function (form) {
    var D = JSON.parse(form.dataset.bundle), tiers = D.tiers;
    var regSlug = { sole_prop: 'sole_prop', partnership: 'partnership', named: 'corp_named', numbered: 'corp_numbered' };
    function val(n) { var f = $$('[name="' + n + '"]', form).filter(function (x) { return x.checked; })[0]; return f ? f.value : null; }
    function pct(n) { var p = 0; Object.keys(tiers).forEach(function (k) { if (n >= +k) p = tiers[k]; }); return p; }
    function calc() {
      var prov = val('province'), rs = regSlug[val('reg_type')], regC = (D.reg[rs] || {})[prov];
      var items = {
        registration: [regC, 'one', T('Business registration')], management: [D.bm, 'one', T('Annual return filing')], domain: [D.tlds[val('tld')], 'yr', T('Domain') + ' .' + val('tld')],
        email: [D.email[val('email_plan')], 'mo', T('Business email')], website: [D.website[val('website_plan')], 'mo', T('Website')], maintenance: [D.maint[val('maint_plan')], 'mo', T('Maintenance')]
      };
      $$('.pill input[name="reg_type"]', form).forEach(function (r) { var c = (D.reg[regSlug[r.value]] || {})[prov]; r.disabled = c === null || c === undefined; });
      var chosen = Object.keys(items).filter(function (k) { var cb = $('[name="services[' + k + ']"]', form); return cb.checked && items[k][0]; });
      var p = pct(chosen.length), t = { one: 0, yr: 0, mo: 0 }, saved = 0, lines = $('[data-b-lines]', form); lines.textContent = '';
      Object.keys(items).forEach(function (k) { var el = $('[data-price="' + k + '"]', form), it = items[k]; el.textContent = it[0] ? money(it[0]) + (it[1] === 'one' ? '' : '/' + it[1]) : T('Not available'); });
      chosen.forEach(function (k) {
        var it = items[k], d = Math.floor(it[0] * p / 100); t[it[1]] += it[0] - d; saved += d;
        var li = document.createElement('li'), a = document.createElement('span'), b = document.createElement('b');
        a.textContent = it[2]; b.textContent = money(it[0] - d) + (it[1] === 'one' ? '' : '/' + it[1]); li.appendChild(a); li.appendChild(b); lines.appendChild(li);
      });
      $('[data-b-empty]', form).hidden = chosen.length > 0;
      $('[data-b-save]', form).textContent = p ? T('{p}% bundle discount · you save {s}').replace('{p}', p).replace('{s}', money(saved)) : (chosen.length === 1 ? T('Add one more service to save 5%.') : '');
      var tot = $('[data-b-totals]', form); tot.textContent = '';
      [[T('One-time'), t.one], [T('Yearly'), t.yr], [T('Monthly'), t.mo]].forEach(function (r) { if (!r[1]) return; var a = document.createElement('dt'); a.textContent = r[0]; var b = document.createElement('dd'); b.textContent = money(r[1]); tot.appendChild(a); tot.appendChild(b); });
      var today = t.one + t.yr + t.mo, m = $('[data-b-mtotal]'); if (m) m.textContent = today ? money(today) : '—';
      $$('[data-tiers] li', form).forEach(function (li) { li.classList.toggle('on', +li.dataset.n === Object.keys(tiers).filter(function (k) { return chosen.length >= +k; }).pop() * 1); });
      return chosen;
    }
    form.addEventListener('change', calc);
    $('[data-all]', form).addEventListener('click', function () { $$('[name^="services["]', form).forEach(function (c) { c.checked = true; }); calc(); });
    $('[data-none]', form).addEventListener('click', function () { $$('[name^="services["]', form).forEach(function (c) { c.checked = false; }); calc(); });
    function submit(e) {
      if (e) e.preventDefault();
      var err = $('[data-b-err]', form), chosen = calc(); err.textContent = '';
      if (!chosen.length) { err.textContent = T('Please select at least one service.'); return; }
      if (!$('[name="agree"]', form).checked) { err.textContent = T('Please read and accept the Service Agreement to continue.'); $('[name="agree"]', form).focus(); return; }
      var svc = {}; chosen.forEach(function (k) { svc[k] = 1; });
      var btn = $('[data-b-submit]', form); btn.disabled = true; btn.textContent = T('Adding…');
      post('/order/bundle', { province: val('province'), reg_type: val('reg_type'), tld: val('tld'), email_plan: val('email_plan'), website_plan: val('website_plan'), maint_plan: val('maint_plan'), services: svc, agree: true })
        .then(function (j) { if (j._ok && j.redirect) location.href = j.redirect; else { btn.disabled = false; btn.textContent = T('Add bundle to cart'); err.textContent = j.error || T('Could not add the bundle.'); } });
    }
    form.addEventListener('submit', submit);
    var mgo = $('[data-b-mgo]'); if (mgo) mgo.addEventListener('click', function () { submit(); if ($('[data-b-err]', form).textContent) $('[data-b-err]', form).scrollIntoView({ block: 'center' }); });
    calc();
  });

  /* Home: how-it-works steps open on click */
  $$('[data-steps4]').forEach(function (w) { $$('.st4', w).forEach(function (b) { b.addEventListener('click', function () { $$('.st4', w).forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-expanded', x === b ? 'true' : 'false'); }); }); }); });

  /* "Start with a name" boxes: carry the typed name into the form below. */
  $$('form[data-prefill]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault(); var v = $('input', f).value.trim(), t = $('[name="' + f.dataset.prefill + '"]');
      if (t) { t.value = v; t.dispatchEvent(new Event('input', { bubbles: true })); }
      var o = document.getElementById('order'); if (o) o.scrollIntoView({ behavior: 'smooth' });
    });
  });

  /* Choose-a-card helpers used on hub pages: selecting a structure in the quiz highlights its card. */
  $$('[data-jump]').forEach(function (b) { b.addEventListener('click', function () { var t = document.getElementById(b.dataset.jump); if (t) { t.scrollIntoView({ behavior: 'smooth', block: 'center' }); t.classList.add('pulse'); setTimeout(function () { t.classList.remove('pulse'); }, 1600); } }); });

  /* Arriving from the homepage name preview: /register/nuans?name=… fills the first proposed name. */
  var qn = new URLSearchParams(location.search).get('name');
  if (qn) $$('[name="entity[proposed_name]"]').forEach(function (f) { f.value = qn.slice(0, 100); f.dispatchEvent(new Event('input', { bubbles: true })); });
})();
