/*
 * App behaviours without inline JS (Content-Security-Policy without 'unsafe-inline'):
 * - [data-confirm] on a form or on its submit button: asks before submitting.
 * - [data-submit-form="id"] on a link/button: submits that form (e.g. "Delete" in a dropdown menu).
 * - htmx: every request carries the CSRF token (<meta name="csrf-token">; the cookie is httponly).
 */
(function () {
  'use strict';

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    var submitter = event.submitter;
    var message = (submitter && submitter.dataset.confirm) || form.dataset.confirm;
    if (message && !window.confirm(message)) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);

  document.addEventListener('click', function (event) {
    var trigger = event.target instanceof Element ? event.target.closest('[data-submit-form]') : null;
    if (!trigger) return;
    event.preventDefault();
    var form = document.getElementById(trigger.dataset.submitForm);
    if (form instanceof HTMLFormElement) form.requestSubmit();
  });

  // [data-reveal="id"] shows that element (and hides itself); [data-reveal-close="id"] folds it back.
  document.addEventListener('click', function (event) {
    if (!(event.target instanceof Element)) return;
    var open = event.target.closest('[data-reveal]');
    var close = event.target.closest('[data-reveal-close]');
    var id = open ? open.dataset.reveal : close ? close.dataset.revealClose : null;
    var target = id ? document.getElementById(id) : null;
    if (!target) return;
    var trigger = open || document.querySelector('[data-reveal="' + CSS.escape(id) + '"]');
    target.hidden = !open;
    if (trigger) {
      trigger.hidden = !!open;
      trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    if (open) {
      var field = target.querySelector('input:not([type="hidden"]):not([hidden]), select, textarea');
      if (field) field.focus();
    } else if (trigger) {
      trigger.focus();
    }
  });

  document.addEventListener('htmx:configRequest', function (event) {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) event.detail.headers['X-CSRF-Token'] = meta.content;
  });
})();
