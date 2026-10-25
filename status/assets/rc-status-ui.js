/**
 * RC Status UI — vanilla Modal / Offcanvas / lightbox (no Bootstrap JS)
 * Phase 3 extraction · RC 5.2.4
 */
(function (global) {
  'use strict';

  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  /* ── Backdrop ─────────────────────────────────────────── */
  function ensureBackdrop(id) {
    var el = document.getElementById(id);
    if (el) return el;
    el = document.createElement('div');
    el.id = id;
    el.className = 'rc-st-backdrop';
    el.hidden = true;
    document.body.appendChild(el);
    return el;
  }

  /* ── Modal ────────────────────────────────────────────── */
  var activeModal = null;

  function showModal(el, opts) {
    opts = opts || {};
    if (!el) return;
    var backdrop = ensureBackdrop('rc-st-modal-backdrop');
    backdrop.hidden = false;
    backdrop.classList.add('is-open');
    el.classList.add('rc-st-modal-open', 'show');
    el.style.display = 'block';
    el.setAttribute('aria-hidden', 'false');
    document.body.classList.add('rc-st-modal-open');
    activeModal = el;
    if (opts.staticBackdrop) {
      backdrop.onclick = null;
    } else {
      backdrop.onclick = function () { hideModal(el); };
    }
    var focusable = el.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (focusable) try { focusable.focus(); } catch (e) {}
  }

  function hideModal(el) {
    el = el || activeModal;
    if (!el) return;
    el.classList.remove('rc-st-modal-open', 'show');
    el.style.display = 'none';
    el.setAttribute('aria-hidden', 'true');
    var backdrop = document.getElementById('rc-st-modal-backdrop');
    if (backdrop) {
      backdrop.classList.remove('is-open');
      backdrop.hidden = true;
      backdrop.onclick = null;
    }
    document.body.classList.remove('rc-st-modal-open');
    if (activeModal === el) activeModal = null;
  }

  /* ── Offcanvas (mobile sidebar) ───────────────────────── */
  function showOffcanvas(el) {
    if (!el) return;
    var backdrop = ensureBackdrop('rc-st-oc-backdrop');
    backdrop.hidden = false;
    backdrop.classList.add('is-open');
    backdrop.onclick = function () { hideOffcanvas(el); };
    el.classList.add('rc-st-oc-open', 'show');
    el.setAttribute('aria-hidden', 'false');
    document.body.classList.add('rc-st-oc-open');
  }

  function hideOffcanvas(el) {
    if (!el) el = qs('.offcanvas.rc-st-oc-open, .offcanvas-lg.rc-st-oc-open');
    if (!el) return;
    el.classList.remove('rc-st-oc-open', 'show');
    el.setAttribute('aria-hidden', 'true');
    var backdrop = document.getElementById('rc-st-oc-backdrop');
    if (backdrop) {
      backdrop.classList.remove('is-open');
      backdrop.hidden = true;
      backdrop.onclick = null;
    }
    document.body.classList.remove('rc-st-oc-open');
  }

  /* ── Wire data attributes (Bootstrap-compatible subset) ─ */
  function bindDataApi() {
    document.addEventListener('click', function (e) {
      var t = e.target.closest('[data-bs-toggle="offcanvas"], [data-rc-toggle="offcanvas"]');
      if (t) {
        e.preventDefault();
        var sel = t.getAttribute('data-bs-target') || t.getAttribute('data-rc-target') || t.getAttribute('href');
        if (sel) showOffcanvas(qs(sel));
        return;
      }
      t = e.target.closest('[data-bs-dismiss="offcanvas"], [data-rc-dismiss="offcanvas"]');
      if (t) {
        e.preventDefault();
        var targetSel = t.getAttribute('data-bs-target') || t.getAttribute('data-rc-target');
        hideOffcanvas(targetSel ? qs(targetSel) : null);
        return;
      }
      t = e.target.closest('[data-bs-dismiss="modal"], [data-rc-dismiss="modal"]');
      if (t) {
        e.preventDefault();
        var modal = t.closest('.modal');
        hideModal(modal);
        return;
      }
      t = e.target.closest('[data-bs-dismiss="alert"]');
      if (t) {
        e.preventDefault();
        var alert = t.closest('.alert');
        if (alert) alert.remove();
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      if (activeModal) {
        // Respect data-bs-keyboard="false" (e.g. compliance must choose)
        if (activeModal.getAttribute('data-bs-keyboard') !== 'false') {
          hideModal(activeModal);
        }
      }
      hideOffcanvas(null);
    });
  }

  /* ── Public API (Bootstrap-like shim for residual calls) ─ */
  var Modal = function (el) {
    this._el = typeof el === 'string' ? qs(el) : el;
  };
  Modal.prototype.show = function () { showModal(this._el, { staticBackdrop: this._el && this._el.getAttribute('data-bs-backdrop') === 'static' }); };
  Modal.prototype.hide = function () { hideModal(this._el); };
  Modal.getInstance = function (el) { return el ? new Modal(el) : null; };

  var Offcanvas = function (el) {
    this._el = typeof el === 'string' ? qs(el) : el;
  };
  Offcanvas.prototype.show = function () { showOffcanvas(this._el); };
  Offcanvas.prototype.hide = function () { hideOffcanvas(this._el); };
  Offcanvas.getInstance = function (el) { return el ? new Offcanvas(el) : null; };

  global.bootstrap = global.bootstrap || {};
  global.bootstrap.Modal = Modal;
  global.bootstrap.Offcanvas = Offcanvas;

  global.rcStatusUI = {
    showModal: showModal,
    hideModal: hideModal,
    showOffcanvas: showOffcanvas,
    hideOffcanvas: hideOffcanvas,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindDataApi);
  } else {
    bindDataApi();
  }
})(window);
