/*
 * WebAuthn (security keys / passkeys) without a build step. Binds by data attributes:
 * - [data-webauthn-register]: register a key from the profile (App\Controller\TwoFactorController).
 * - [data-webauthn-authenticate]: second login step with the key (WebauthnLoginController).
 * - [data-passkey-login]: passwordless login (PasskeyLoginController), with conditional UI
 *   (autofill) when the browser supports it.
 * Every request sends the CSRF token (cookie is httponly) as X-CSRF-Token. Binary fields go as base64url.
 */
(function () {
  'use strict';
  // htmx re-runs scripts inside swapped content: load once
  if (window.AppWebauthn) return;

  var b64urlToBytes = function (value) {
    var b64 = value.replace(/-/g, '+').replace(/_/g, '/');
    var pad = b64.length % 4 ? '='.repeat(4 - (b64.length % 4)) : '';
    var raw = atob(b64 + pad);
    var bytes = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
    return bytes.buffer;
  };
  var bytesToB64url = function (buffer) {
    if (!buffer) return null;
    var bytes = new Uint8Array(buffer);
    var raw = '';
    for (var i = 0; i < bytes.length; i++) raw += String.fromCharCode(bytes[i]);
    return btoa(raw).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  };
  var decodeIds = function (list) {
    return (list || []).map(function (item) { return Object.assign({}, item, { id: b64urlToBytes(item.id) }); });
  };

  var creationOptions = function (o) {
    o.challenge = b64urlToBytes(o.challenge);
    o.user = Object.assign({}, o.user, { id: b64urlToBytes(o.user.id) });
    o.excludeCredentials = decodeIds(o.excludeCredentials);
    return o;
  };
  var requestOptions = function (o) {
    o.challenge = b64urlToBytes(o.challenge);
    o.allowCredentials = decodeIds(o.allowCredentials);
    return o;
  };
  var serialize = function (credential) {
    var r = credential.response;
    var response = { clientDataJSON: bytesToB64url(r.clientDataJSON) };
    if (r.attestationObject) response.attestationObject = bytesToB64url(r.attestationObject);
    if (r.authenticatorData) response.authenticatorData = bytesToB64url(r.authenticatorData);
    if (r.signature) response.signature = bytesToB64url(r.signature);
    if (r.userHandle) response.userHandle = bytesToB64url(r.userHandle);
    if (typeof r.getTransports === 'function') response.transports = r.getTransports();
    return { id: credential.id, rawId: bytesToB64url(credential.rawId), type: credential.type, response: response };
  };

  var post = function (url, csrf, body) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrf,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(body || {})
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        if (!res.ok) throw Object.assign(new Error(data.message || 'request failed'), { server: true, data: data });
        return data;
      });
    });
  };

  var showError = function (root, text) {
    var box = root.querySelector('[data-webauthn-message]');
    if (box) { box.textContent = text; box.classList.remove('hidden'); }
  };
  var silent = function (err) { return err && (err.name === 'NotAllowedError' || err.name === 'AbortError'); };
  var supported = function () { return typeof window.PublicKeyCredential === 'function' && !!navigator.credentials; };

  var once = function (root) {
    if (root.dataset.webauthnBound) return false;
    root.dataset.webauthnBound = '1';
    return true;
  };

  var bind = function (scope) {
  // Profile: register a key (options -> navigator.credentials.create -> verify)
  scope.querySelectorAll('[data-webauthn-register]').forEach(function (root) {
    if (!once(root)) return;
    var button = root.querySelector('[data-webauthn-start]');
    if (!supported()) { showError(root, root.dataset.unsupported); if (button) button.disabled = true; return; }
    if (!button) return;
    button.addEventListener('click', function () {
      button.disabled = true;
      post(root.dataset.optionsUrl, root.dataset.csrf)
        .then(function (options) { return navigator.credentials.create({ publicKey: creationOptions(options) }); })
        .then(function (credential) { return post(root.dataset.registerUrl, root.dataset.csrf, serialize(credential)); })
        .then(function (data) { window.location.assign(data.redirectUrl); })
        .catch(function (err) {
          button.disabled = false;
          if (!silent(err)) showError(root, (err.server && err.data.message) || root.dataset.error);
        });
    });
  });

  // Login second step: use the registered key (options -> navigator.credentials.get -> verify)
  scope.querySelectorAll('[data-webauthn-authenticate]').forEach(function (root) {
    if (!once(root)) return;
    var button = root.querySelector('[data-webauthn-start]');
    if (!supported()) { showError(root, root.dataset.unsupported); if (button) button.disabled = true; return; }
    if (!button) return;
    button.addEventListener('click', function () {
      button.disabled = true;
      fetch(root.dataset.optionsUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (res) { if (!res.ok) throw new Error('options'); return res.json(); })
        .then(function (options) { return navigator.credentials.get({ publicKey: requestOptions(options) }); })
        .then(function (credential) { return post(root.dataset.actionUrl, root.dataset.csrf, serialize(credential)); })
        .then(function (data) { window.location.assign(data.redirectUrl); })
        .catch(function (err) {
          button.disabled = false;
          if (!silent(err)) showError(root, (err.server && err.data.message) || root.dataset.error);
        });
    });
  });

  // Login page: passwordless login with a passkey. With conditional UI the browser offers the
  // passkeys in the username field; the button runs a modal request (and aborts the pending one).
  scope.querySelectorAll('[data-passkey-login]').forEach(function (root) {
    if (!once(root)) return;
    if (!supported()) return;
    root.hidden = false;
    var button = root.querySelector('[data-passkey-start]');
    var form = root.closest('form');
    var remember = function () {
      var box = form && form.querySelector('input[type=checkbox][name=remember_me]');
      return box && box.checked ? '1' : '';
    };
    var pending = null;
    var signIn = function (mediation) {
      if (pending) pending.abort();
      pending = new AbortController();
      var signal = pending.signal;
      return post(root.dataset.optionsUrl, root.dataset.csrf)
        .then(function (options) {
          var request = { publicKey: requestOptions(options), signal: signal };
          if (mediation) request.mediation = mediation;
          return navigator.credentials.get(request);
        })
        .then(function (credential) {
          var body = serialize(credential);
          body.remember_me = remember();
          return post(root.dataset.actionUrl, root.dataset.csrf, body);
        })
        .then(function (data) { window.location.assign(data.redirectUrl); });
    };
    if (button) {
      button.addEventListener('click', function () {
        button.disabled = true;
        signIn(null).catch(function (err) {
          button.disabled = false;
          if (!silent(err)) showError(root, root.dataset.error);
        });
      });
    }
    if (typeof PublicKeyCredential.isConditionalMediationAvailable === 'function') {
      PublicKeyCredential.isConditionalMediationAvailable().then(function (available) {
        if (available) signIn('conditional').catch(function (err) { if (!silent(err)) showError(root, root.dataset.error); });
      });
    }
  });

  };

  bind(document);
  // Content swapped by htmx (e.g. the two-step verification card)
  document.addEventListener('htmx:load', function (event) { bind(event.target.parentElement || document); });

  window.AppWebauthn = { post: post, requestOptions: requestOptions, serialize: serialize, supported: supported, silent: silent, showError: showError };
})();
