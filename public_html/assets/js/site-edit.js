/* 818 in-page site editor — loaded only for the signed-in owner. Text and pictures are edited on the live page. */
(function () {
  'use strict';
  var cfgEl = document.getElementById('nx-edit-cfg'); if (!cfgEl) return;
  var cfg; try { cfg = JSON.parse(cfgEl.textContent); } catch (e) { return; }
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';
  var norm = function (s) { return String(s || '').replace(/\s+/g, ' ').trim(); };
  var el = function (tag, attrs, html) { var n = document.createElement(tag); for (var k in (attrs || {})) n.setAttribute(k, attrs[k]); if (html != null) n.innerHTML = html; return n; };
  var esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var url = new URL(location.href);

  /* ── Not editing: one button to start ── */
  if (!cfg.editing) {
    var b = el('a', { class: 'nxe-start', href: '#' }, '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/></svg><span>Edit this page</span>');
    b.addEventListener('click', function (e) { e.preventDefault(); url.searchParams.set('nx_edit', '1'); location.href = url.toString(); });
    document.body.appendChild(b);
    return;
  }

  document.documentElement.classList.add('nx-editing');
  var fields = cfg.fields || {};
  var changes = {};            // key -> {key, scope, en, fr, reset}
  var selected = null;         // {type:'text', key} | {type:'img', node}

  /* ── Toolbar and side panel ── */
  var bar = el('div', { class: 'nxe-bar', role: 'region', 'aria-label': 'Page editor' },
    '<div class="nxe-l"><b>Editing this page</b><span>Click text to change it. Click a picture to replace it. Ctrl/⌘-click a link to edit that page.</span></div>' +
    '<span class="nxe-count" aria-live="polite"></span>' +
    '<button type="button" class="nxe-btn" data-x="discard" hidden>Discard</button>' +
    '<button type="button" class="nxe-btn nxe-pri" data-x="save" disabled>Save and publish</button>' +
    '<button type="button" class="nxe-btn" data-x="exit">Done</button>');
  var panel = el('aside', { class: 'nxe-panel', 'aria-label': 'Selected item', hidden: '' });
  var toast = el('div', { class: 'nxe-toast', role: 'status', hidden: '' });
  document.body.appendChild(bar); document.body.appendChild(panel); document.body.appendChild(toast);
  var file = el('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif', hidden: '' }); document.body.appendChild(file);

  function say(msg, bad) { toast.textContent = msg; toast.className = 'nxe-toast' + (bad ? ' bad' : ''); toast.hidden = false; clearTimeout(say.t); say.t = setTimeout(function () { toast.hidden = true; }, 4200); }
  function count() {
    var n = Object.keys(changes).length;
    bar.querySelector('.nxe-count').textContent = n ? n + (n === 1 ? ' unsaved change' : ' unsaved changes') : '';
    bar.querySelector('[data-x=save]').disabled = !n; bar.querySelector('[data-x=discard]').hidden = !n;
  }
  function nodes(key) { return document.querySelectorAll('.nx-t[data-k="' + key + '"]'); }
  function change(key) {
    var f = fields[key] || {}, n = nodes(key)[0];
    return changes[key] || (changes[key] = { key: key, scope: n ? n.getAttribute('data-s') : 'one', en: n ? norm(n.innerText) : (f.en || ''), fr: f.fr || '' });
  }

  /* ── Text ── */
  function selectText(n) {
    var key = n.getAttribute('data-k'), f = fields[key] || { src: norm(n.innerText), en: norm(n.innerText), fr: '', frDefault: '' };
    clearSel(); selected = { type: 'text', key: key };
    nodes(key).forEach(function (x) { x.classList.add('nxe-on'); });
    if (!n.isContentEditable) {
      try { n.contentEditable = 'plaintext-only'; } catch (e) { n.contentEditable = 'true'; }
      if (n.contentEditable !== 'plaintext-only') n.contentEditable = 'true';
    }
    n.focus();
    var c = changes[key], scope = c ? c.scope : n.getAttribute('data-s'), fr = c ? c.fr : (f.fr || '');
    panel.innerHTML = '<div class="nxe-ph"><b>Text</b><button type="button" class="nxe-x" aria-label="Close">×</button></div>' +
      '<p class="nxe-hint">Type straight on the page. The French version is below.</p>' +
      '<label>French<textarea rows="3" data-f="fr" lang="fr" placeholder="' + esc(f.frDefault || 'Same as English') + '">' + esc(fr) + '</textarea></label>' +
      '<label>Where this change applies<select data-f="scope"><option value="one"' + (scope === 'one' ? ' selected' : '') + '>This page only</option><option value="all"' + (scope === 'all' ? ' selected' : '') + '>Every page</option></select></label>' +
      ((f.edited || (c && norm(c.en) !== norm(f.src))) ? '<p class="nxe-was">Originally: ' + esc(f.src) + '</p><button type="button" class="nxe-link" data-f="undo">Undo change (back to the original)</button>' : '');
    panel.hidden = false;
  }
  document.addEventListener('input', function (e) {
    var n = e.target.closest && e.target.closest('.nx-t'); if (!n) return;
    var key = n.getAttribute('data-k'), c = change(key); c.en = norm(n.innerText); delete c.reset;
    nodes(key).forEach(function (x) { if (x !== n) x.textContent = n.innerText; });
    count();
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.closest && e.target.closest('.nx-t')) { e.preventDefault(); e.target.blur(); } });
  document.addEventListener('paste', function (e) {
    var n = e.target.closest && e.target.closest('.nx-t'); if (!n || n.contentEditable === 'plaintext-only') return;
    e.preventDefault(); document.execCommand('insertText', false, norm((e.clipboardData || window.clipboardData).getData('text')));
  });
  panel.addEventListener('input', function (e) {
    if (!selected || selected.type !== 'text') return;
    var c = change(selected.key);
    if (e.target.getAttribute('data-f') === 'fr') { c.fr = e.target.value; delete c.reset; count(); }
  });
  panel.addEventListener('change', function (e) {
    if (selected && selected.type === 'text' && e.target.getAttribute('data-f') === 'scope') { change(selected.key).scope = e.target.value; count(); }
  });
  panel.addEventListener('click', function (e) {
    if (e.target.closest('.nxe-x')) { clearSel(); return; }
    if (selected && selected.type === 'text' && e.target.getAttribute('data-f') === 'undo') {
      var f = fields[selected.key] || {}, c = change(selected.key);
      c.reset = true; c.en = f.src; c.fr = f.frDefault || '';
      nodes(selected.key).forEach(function (x) { x.textContent = f.src; });
      panel.querySelector('[data-f=fr]').value = c.fr; count(); say('Back to the original wording. Save to publish.');
    }
  });

  /* ── Pictures (go live as soon as they upload) ── */
  function selectImg(img) {
    clearSel(); selected = { type: 'img', node: img }; img.classList.add('nxe-on');
    var changed = img.getAttribute('data-nx-changed') === '1';
    panel.innerHTML = '<div class="nxe-ph"><b>Picture</b><button type="button" class="nxe-x" aria-label="Close">×</button></div>' +
      '<img class="nxe-thumb" alt="" src="' + esc(img.tagName === 'VIDEO' ? (img.getAttribute('poster') || '') : (img.currentSrc || img.src)) + '">' +
      (img.tagName === 'VIDEO' ? '<p class="nxe-hint">This is a background video. You can replace it with a picture.</p>' : '') +
      '<button type="button" class="nxe-btn nxe-pri nxe-wide" data-f="pick">' + (img.tagName === 'VIDEO' ? 'Replace video with a picture' : 'Replace picture') + '</button>' +
      '<p class="nxe-hint">JPG, PNG or WebP, up to 8 MB. Large photos are resized automatically. The new picture goes live straight away, everywhere this picture is used.</p>' +
      (changed ? '<label>Description for screen readers and search<input type="text" data-f="alt" value="' + esc(img.getAttribute('alt') || '') + '" maxlength="300"></label><button type="button" class="nxe-link" data-f="restore">Restore the original picture</button>' : '');
    panel.hidden = false;
    panel.querySelector('[data-f=pick]').addEventListener('click', function () { file.value = ''; file.click(); });
    var alt = panel.querySelector('[data-f=alt]');
    if (alt) alt.addEventListener('change', function () { post({ action: 'alt', src: img.getAttribute('data-nx-src'), alt: alt.value }).then(function (r) { if (r.ok) { setAll(img, null, alt.value); say('Description saved.'); } }); });
    var rs = panel.querySelector('[data-f=restore]');
    if (rs) rs.addEventListener('click', function () {
      if (!confirm('Put the original picture back?')) return;
      post({ action: 'reset', src: img.getAttribute('data-nx-src') }).then(function (r) { if (r.ok) { if (img.classList.contains('nx-was-video')) { location.reload(); return; } setAll(img, img.getAttribute('data-nx-src'), null, '0'); selectImg(img); say('Original picture restored.'); } else say(r.error || 'Could not restore.', true); });
    });
  }
  function setAll(img, src, alt, changed) {
    document.querySelectorAll('img[data-nx-img="' + img.getAttribute('data-nx-img') + '"]').forEach(function (x) {
      if (src) x.src = src; if (alt != null) x.setAttribute('alt', alt); if (changed != null) x.setAttribute('data-nx-changed', changed);
    });
  }
  function post(fields, fileObj) {
    var fd = new FormData(); for (var k in fields) fd.append(k, fields[k]); if (fileObj) fd.append('picture', fileObj);
    return fetch('/site-edit/image', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Unexpected reply (' + r.status + ').' }; }); })
      .catch(function () { return { ok: false, error: 'Could not reach the server.' }; });
  }
  file.addEventListener('change', function () {
    if (!selected || selected.type !== 'img' || !file.files[0]) return;
    var img = selected.node; say('Uploading…');
    post({ src: img.getAttribute('data-nx-src'), alt: img.getAttribute('alt') || '' }, file.files[0]).then(function (r) {
      if (!r.ok) { say(r.error || 'Upload failed.', true); return; }
      if (img.tagName === 'VIDEO') { try { sessionStorage.setItem('nxe-msg', 'Video replaced with your picture. It is live now.'); } catch (e) {} location.reload(); return; }
      setAll(img, r.url, null, '1'); selectImg(img); say('Picture replaced. It is live now.');
    });
  });

  /* ── Selection, links and forms ── */
  function clearSel() {
    document.querySelectorAll('.nxe-on').forEach(function (x) { x.classList.remove('nxe-on'); });
    selected = null; panel.hidden = true;
  }
  document.addEventListener('click', function (e) {
    if (e.target === file || e.target.closest('.nxe-bar, .nxe-panel, .nxe-toast')) return;
    var a = e.target.closest('a[href]');
    if (a && (e.ctrlKey || e.metaKey)) {   // follow a link inside the editor
      e.preventDefault();
      var u = new URL(a.href, location.href);
      if (u.origin === location.origin) { u.searchParams.set('nx_edit', '1'); go(u.toString()); }
      return;
    }
    var t = e.target.closest('.nx-t'), img = e.target.closest('[data-nx-img]');
    if (!t && !img && !a && !e.target.closest('button, input, select, textarea, label, summary')) img = under(e.clientX, e.clientY);
    if (t || img || a || e.target.closest('button, label, summary')) { e.preventDefault(); }
    if (t) { selectText(t); return; }
    if (img) { selectImg(img); return; }
    if (e.target.closest('.brand')) { e.preventDefault(); panel.innerHTML = '<div class="nxe-ph"><b>Logo</b><button type="button" class="nxe-x" aria-label="Close">×</button></div><p class="nxe-hint">Upload the dark and white versions of your logo in the Control Tower.</p><a class="nxe-btn nxe-pri nxe-wide" href="/admin/site?tab=brand">Change the logo</a>'; panel.hidden = false; return; }
    if (!e.target.closest('.nx-t')) clearSel();
  }, true);
  document.addEventListener('submit', function (e) { if (!e.target.closest('.nxe-panel')) { e.preventDefault(); say('Forms are paused while you edit.'); } }, true);

  /* A picture or background video under the click point, even when text or an overlay sits on top of it. */
  function under(x, y) {
    var hit = null;
    document.querySelectorAll('[data-nx-img]').forEach(function (n) {
      var r = n.getBoundingClientRect();
      if (r.width > 40 && r.height > 40 && x >= r.left && x <= r.right && y >= r.top && y <= r.bottom && getComputedStyle(n).visibility !== 'hidden') hit = n;
    });
    return hit;
  }

  /* ── Save / discard / exit ── */
  function go(to) { if (Object.keys(changes).length && !confirm('Leave without saving your changes?')) return; changes = {}; location.href = to; }
  bar.addEventListener('click', function (e) {
    var x = e.target.closest('[data-x]'); if (!x) return;
    var act = x.getAttribute('data-x');
    if (act === 'exit') { url.searchParams.delete('nx_edit'); go(url.toString()); }
    if (act === 'discard') { changes = {}; location.reload(); }
    if (act === 'save') {
      x.disabled = true; x.textContent = 'Saving…';
      var list = Object.keys(changes).map(function (k) { return changes[k]; });
      fetch('/site-edit/save', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify({ page: cfg.page, changes: list }) })
        .then(function (r) { return r.json(); })
        .then(function (r) {
          if (r.problems && r.problems.length) { say('Not saved: ' + r.problems.join('; '), true); x.disabled = false; x.textContent = 'Save and publish'; return; }
          changes = {}; try { sessionStorage.setItem('nxe-saved', String(r.saved || 0)); } catch (e2) {}
          location.reload();
        })
        .catch(function () { say('Could not save. Check your connection and try again.', true); x.disabled = false; x.textContent = 'Save and publish'; });
    }
  });
  window.addEventListener('beforeunload', function (e) { if (Object.keys(changes).length) { e.preventDefault(); e.returnValue = ''; } });
  try { var m = sessionStorage.getItem('nxe-msg'); if (m) { sessionStorage.removeItem('nxe-msg'); setTimeout(function () { say(m); }, 300); } } catch (e) {}
  try { var s = sessionStorage.getItem('nxe-saved'); if (s !== null) { sessionStorage.removeItem('nxe-saved'); say(s === '1' ? 'Saved. The change is live.' : 'Saved ' + s + ' changes. They are live.'); } } catch (e) {}
  count();
})();
