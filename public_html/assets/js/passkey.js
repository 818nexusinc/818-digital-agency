/* Passkey sign-in and registration (WebAuthn). No library: the browser and the device do the cryptography. */
(function () {
  'use strict';
  if (!window.PublicKeyCredential || !navigator.credentials) return;
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';
  function b2u(buf) { var s = '', b = new Uint8Array(buf); for (var i = 0; i < b.length; i++) s += String.fromCharCode(b[i]); return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
  function u2b(str) { str = str.replace(/-/g, '+').replace(/_/g, '/'); while (str.length % 4) str += '='; var s = atob(str), b = new Uint8Array(s.length); for (var i = 0; i < s.length; i++) b[i] = s.charCodeAt(i); return b.buffer; }
  function post(url, body) {
    return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(body || {}) })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok && !j.error) j.error = 'Something went wrong. Try again.'; return j; }); });
  }
  function nice(err) {
    if (!err) return 'Something went wrong. Try again.';
    if (err.name === 'NotAllowedError') return 'Cancelled, or no passkey for this website on this device. Try again, or use your phone (choose "Use a phone or tablet").';
    if (err.name === 'InvalidStateError') return 'This device already has a passkey for your account.';
    if (err.name === 'SecurityError') return 'Passkeys only work on the real website address (https).';
    return err.message || String(err);
  }

  /* Sign in */
  var btn = document.querySelector('[data-passkey-login]');
  if (btn) {
    var msg = document.querySelector('[data-passkey-msg]');
    btn.hidden = false;
    var or = document.querySelector('[data-passkey-or]'); if (or) or.hidden = false;
    var pw = document.querySelector('[data-pw-btn]'); if (pw) pw.classList.add('btn-ghost');
    var say = function (t, bad) { msg.hidden = !t; msg.textContent = t || ''; msg.classList.toggle('bad', !!bad); };
    btn.addEventListener('click', function () {
      btn.disabled = true; say('Waiting for your passkey…');
      post('/login/passkey/options').then(function (o) {
        if (o.error) throw new Error(o.error);
        return navigator.credentials.get({ publicKey: { challenge: u2b(o.challenge), rpId: o.rpId, timeout: o.timeout, userVerification: o.userVerification, allowCredentials: [] } });
      }).then(function (c) {
        say('Checking…');
        return post('/login/passkey', { id: c.id, rawId: b2u(c.rawId), type: c.type, response: {
          clientDataJSON: b2u(c.response.clientDataJSON), authenticatorData: b2u(c.response.authenticatorData),
          signature: b2u(c.response.signature), userHandle: c.response.userHandle ? b2u(c.response.userHandle) : null } });
      }).then(function (r) {
        if (!r.ok) throw new Error(r.error);
        say('Signed in. Opening…'); location.href = r.to || '/admin';
      }).catch(function (e) { say(nice(e), true); btn.disabled = false; });
    });
  }

  /* Add a passkey (Control Tower → Sign-in & recovery) */
  var add = document.querySelector('[data-passkey-add]');
  if (add) {
    var amsg = document.querySelector('[data-passkey-add-msg]');
    var asay = function (t, bad) { amsg.hidden = !t; amsg.textContent = t || ''; amsg.classList.toggle('bad', !!bad); };
    add.addEventListener('click', function () {
      var name = (document.querySelector('[data-passkey-name]') || {}).value || '';
      add.disabled = true; asay('Follow the prompt on your device (Face ID, fingerprint, PIN or security key)…');
      post('/admin/sign-in/passkey/options').then(function (o) {
        if (o.error) throw new Error(o.error);
        o.challenge = u2b(o.challenge); o.user.id = u2b(o.user.id);
        o.excludeCredentials = (o.excludeCredentials || []).map(function (x) { return { type: x.type, id: u2b(x.id) }; });
        return navigator.credentials.create({ publicKey: o });
      }).then(function (c) {
        return post('/admin/sign-in/passkey', { name: name, id: c.id, rawId: b2u(c.rawId), type: c.type, response: {
          clientDataJSON: b2u(c.response.clientDataJSON), attestationObject: b2u(c.response.attestationObject),
          transports: c.response.getTransports ? c.response.getTransports() : [] } });
      }).then(function (r) {
        if (!r.ok) throw new Error(r.error);
        asay('Passkey added.'); location.reload();
      }).catch(function (e) { asay(nice(e), true); add.disabled = false; });
    });
  }
  var unsupported = document.querySelector('[data-passkey-unsupported]'); if (unsupported) unsupported.hidden = true;
})();
