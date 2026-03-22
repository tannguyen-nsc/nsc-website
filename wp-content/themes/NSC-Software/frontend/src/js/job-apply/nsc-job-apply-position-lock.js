/**
 * Lock job Position field without HTML readonly/disabled (value still posts with CF7).
 * Relies on data-nsc-position-lock from server + key/paste guards.
 *
 * Built by Gulp → frontend/build/js/job-apply/ (synced to theme). Do not edit theme copy.
 */
(function () {
  'use strict';

  var bound = new WeakSet();

  function getLockedValue(input) {
    var fromAttr = input.getAttribute('data-nsc-position-lock');
    if (fromAttr != null && String(fromAttr).length) {
      return String(fromAttr);
    }

    return input.value;
  }

  function bindPositionLock(input) {
    if (!input || input.name !== 'job_position' || bound.has(input)) {
      return;
    }

    bound.add(input);

    var locked = getLockedValue(input);
    input.setAttribute('data-nsc-position-lock', locked);
    if (input.value !== locked) {
      input.value = locked;
    }

    input.addEventListener('keydown', function (e) {
      var k = e.key;
      /* Allow Enter to submit the form (otherwise default submit from this field is blocked). */
      if (k === 'Enter') {
        return;
      }

      if (k === 'Tab' || k === 'Escape') {
        return;
      }

      if (e.ctrlKey || e.metaKey) {
        if (k === 'a' || k === 'c') {
          return;
        }

        if (k === 'v' || k === 'x') {
          e.preventDefault();
          return;
        }
      }

      if (
        k === 'ArrowLeft' ||
        k === 'ArrowRight' ||
        k === 'ArrowUp' ||
        k === 'ArrowDown' ||
        k === 'Home' ||
        k === 'End'
      ) {
        return;
      }

      e.preventDefault();
    });

    input.addEventListener('beforeinput', function (e) {
      if (e.isComposing) {
        return;
      }

      e.preventDefault();
    });

    input.addEventListener('paste', function (e) {
      e.preventDefault();
    });
    input.addEventListener('cut', function (e) {
      e.preventDefault();
    });
    input.addEventListener('drop', function (e) {
      e.preventDefault();
    });

    input.addEventListener('input', function () {
      if (input.value !== locked) {
        input.value = locked;
      }
    });
  }

  function scanForm(form) {
    if (!form || !form.querySelector) {
      return;
    }

    var el = form.querySelector('input[name="job_position"]');
    if (el) {
      bindPositionLock(el);
    }
  }

  function scanDocument() {
    document.querySelectorAll('form.wpcf7-form').forEach(scanForm);
  }

  document.addEventListener('DOMContentLoaded', scanDocument);

  ['wpcf7invalid', 'wpcf7spam', 'wpcf7mailfailed', 'wpcf7mailsent'].forEach(function (evt) {
    document.addEventListener(evt, function (e) {
      var form = e.target;
      if (form && form.classList && form.classList.contains('wpcf7-form')) {
        window.setTimeout(function () {
          scanForm(form);
        }, 0);
      }
    });
  });

  document.addEventListener(
    'wpcf7submit',
    function (e) {
      var form = e.target;
      if (form && form.classList && form.classList.contains('wpcf7-form')) {
        window.setTimeout(function () {
          scanForm(form);
        }, 80);
      }
    },
    false
  );

  /**
   * Inline styles on CF7 nodes (theme disables default CF7 CSS on job singles).
   * Custom .nsc-job-apply-loading below footer replaces CF7 .wpcf7-spinner.
   */
  var screenReaderInline = 'display:none!important';
  var notValidTipInline =
    'display:block;margin-top:0.25rem;font-size:1rem;line-height:1.25rem;font-weight:600;color:#ef4444';
  /** Shared layout; color appended per CF7 form state (theme disables CF7 CSS on job singles). */
  var responseOutputBaseInline =
    'display:block;margin:0;padding:0;font-size:1rem;line-height:1.25rem;font-weight:600;border:0;box-shadow:none;width:100%;box-sizing:border-box;background:transparent';
  var responseOutputColorSent = '#6ee7b9';
  var responseOutputColorError = '#ef4444';
  var responseOutputColorSpam = '#fb923c';

  /**
   * CF7 form status classes (see contact-form-7/includes/js — status map + custom-*).
   * Mirrored onto .wpcf7-response-output for styling like form.wpcf7-form.<state>.
   */
  var WPCF7_FORM_STATUS_CLASSES = [
    'init',
    'invalid',
    'unaccepted',
    'spam',
    'aborted',
    'sent',
    'failed',
    'submitting',
    'resetting',
    'validating',
    'payment-required',
  ];

  function nscRemoveCustomStatusClasses(el) {
    if (!el || !el.classList) {
      return;
    }

    Array.prototype.slice.call(el.classList).forEach(function (c) {
      if (c.indexOf('custom-') === 0) {
        el.classList.remove(c);
      }
    });
  }

  /**
   * Keep .wpcf7-response-output state classes in sync with form.wpcf7-form (incl. custom-*).
   * @param {HTMLElement} form
   * @param {HTMLElement} out
   */
  function nscSyncResponseOutputStateClassesFromForm(form, out) {
    if (!form || !out || !form.classList || !out.classList) {
      return;
    }

    WPCF7_FORM_STATUS_CLASSES.forEach(function (c) {
      out.classList.remove(c);
    });
    nscRemoveCustomStatusClasses(out);

    WPCF7_FORM_STATUS_CLASSES.forEach(function (c) {
      if (form.classList.contains(c)) {
        out.classList.add(c);
      }
    });

    Array.prototype.slice.call(form.classList).forEach(function (c) {
      if (c.indexOf('custom-') === 0) {
        out.classList.add(c);
      }
    });
  }

  /**
   * Capture-phase: form class may lag one frame; force a single status on the output.
   * @param {HTMLElement} out
   * @param {string} singleState one of WPCF7_FORM_STATUS_CLASSES
   */
  function nscForceResponseOutputStateClass(out, singleState) {
    if (!out || !out.classList || !singleState) {
      return;
    }

    WPCF7_FORM_STATUS_CLASSES.forEach(function (c) {
      out.classList.remove(c);
    });
    nscRemoveCustomStatusClasses(out);
    out.classList.add(singleState);
  }

  /**
   * Whether .wpcf7-response-output should allow hiding the custom loading row.
   * Idle = init or resetting only; any other CF7 status (or custom-*) means show result → hide loading.
   * @param {HTMLElement} out
   * @returns {boolean}
   */
  function nscResponseOutputShowsNonIdleState(out) {
    if (!out || !out.classList) {
      return false;
    }

    var i;
    var c;
    for (i = 0; i < WPCF7_FORM_STATUS_CLASSES.length; i++) {
      c = WPCF7_FORM_STATUS_CLASSES[i];
      if (c === 'init' || c === 'resetting') {
        continue;
      }

      if (out.classList.contains(c)) {
        return true;
      }
    }

    var cls = Array.prototype.slice.call(out.classList);
    for (i = 0; i < cls.length; i++) {
      if (cls[i].indexOf('custom-') === 0) {
        return true;
      }
    }

    return false;
  }

  /**
   * @param {HTMLElement} form
   * @param {string=} forceColor optional hex (e.g. capture-phase before .sent class is on form)
   */
  function nscJobApplyResponseOutputFullInline(form, forceColor) {
    if (forceColor) {
      return responseOutputBaseInline + ';color:' + forceColor;
    }

    if (!form || !form.classList) {
      return responseOutputBaseInline;
    }

    if (form.classList.contains('sent')) {
      return responseOutputBaseInline + ';color:' + responseOutputColorSent;
    }

    if (form.classList.contains('spam')) {
      return responseOutputBaseInline + ';color:' + responseOutputColorSpam;
    }

    if (
      form.classList.contains('invalid') ||
      form.classList.contains('unaccepted') ||
      form.classList.contains('payment-required') ||
      form.classList.contains('failed') ||
      form.classList.contains('aborted')
    ) {
      return responseOutputBaseInline + ';color:' + responseOutputColorError;
    }

    var ds = form.getAttribute('data-status') || '';
    if (ds.indexOf('custom-') === 0) {
      return responseOutputBaseInline + ';color:' + responseOutputColorError;
    }

    return responseOutputBaseInline;
  }

  /** CV staging: uploading (pale), success (green); errors use notValidTipInline */
  var cvTipInfoInline =
    'display:block;margin-top:0.25rem;font-size:1rem;line-height:1.25rem;font-weight:600;color:#fecaca';
  var cvTipSuccessInline =
    'display:block;margin-top:0.25rem;font-size:1rem;line-height:1.25rem;font-weight:600;color:#86efac';

  var NSC_CONSENT_TIP_ATTR = 'data-nsc-consent-tip';

  /**
   * Apply layout-only inline styles after CF7 has updated `data-status` / classes (same frame is too early).
   */
  function nscDeferredSetJobApplyResponseOutputLayout(wrap) {
    if (!wrap || !wrap.querySelectorAll) {
      return;
    }

    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(function () {
        wrap.querySelectorAll('.wpcf7-response-output').forEach(function (el) {
          var form = el.closest('form.wpcf7-form');
          if (!form) {
            return;
          }

          var status = form.getAttribute('data-status') || '';
          if (status === 'submitting') {
            return;
          }

          nscSyncResponseOutputStateClassesFromForm(form, el);
          el.setAttribute('style', nscJobApplyResponseOutputFullInline(form));
        });
      });
    });
  }

  /**
   * Footer → .nsc-job-apply-loading (custom spinner) → .wpcf7-response-output.
   * Removes CF7 .wpcf7-spinner (theme uses small border spinner + no has-spinner on submit).
   */
  function ensureNscJobApplyLoadingAfterFooter(footer) {
    var next = footer.nextElementSibling;
    if (
      next &&
      next.classList &&
      next.classList.contains('nsc-job-apply-loading')
    ) {
      return next;
    }

    var el = document.createElement('span');
    el.className = 'nsc-job-apply-loading';
    el.setAttribute('aria-hidden', 'true');
    var icon = document.createElement('span');
    icon.className = 'nsc-job-apply-loading__icon';
    el.appendChild(icon);
    footer.insertAdjacentElement('afterend', el);
    return el;
  }

  function moveCf7FeedbackBelowFooter(form) {
    if (!form || !form.querySelector) {
      return;
    }

    var footer = form.querySelector('.career-details__apply-footer');
    if (!footer) {
      return;
    }

    form.querySelectorAll('.wpcf7-spinner').forEach(function (sp) {
      sp.remove();
    });
    var loading = ensureNscJobApplyLoadingAfterFooter(footer);
    var response = form.querySelector('.wpcf7-response-output');
    if (response) {
      loading.insertAdjacentElement('afterend', response);
    }
  }

  /**
   * Drop loading only when .wpcf7-response-output mirrors a non-idle state (not init / resetting).
   * Syncs output classes from the form first so this matches form state before deferred RAF.
   */
  function removeNscJobApplyLoadingIfResponseVisible(form) {
    if (!form || !form.querySelectorAll || !form.classList) {
      return;
    }

    if (form.classList.contains('submitting')) {
      return;
    }

    var out = form.querySelector('.wpcf7-response-output');
    if (!out) {
      return;
    }

    nscSyncResponseOutputStateClassesFromForm(form, out);

    if (!nscResponseOutputShowsNonIdleState(out)) {
      return;
    }

    form.querySelectorAll('.nsc-job-apply-loading').forEach(function (el) {
      el.remove();
    });
  }

  /** Keep CV file validation tip under the dashed upload / staged row (CF7 may insert mid-wrap). */
  function syncCvFileTipOrder(wrap) {
    if (!wrap || !wrap.querySelector) {
      return;
    }

    var fwrap = wrap.querySelector(
      '.wpcf7-form-control-wrap[data-name="cv_file"]'
    );
    if (!fwrap) {
      return;
    }

    var tip = fwrap.querySelector('.wpcf7-not-valid-tip');
    if (tip) {
      fwrap.appendChild(tip);
    }
  }

  /**
   * CF7 appends .wpcf7-not-valid-tip inside the privacy wrap (inside the label), which breaks the
   * consent row layout. Move it after label.career-details__consent so it sits on the next line
   * below checkbox + policy text. Remove when field is valid (CF7 only removes tips inside wrap).
   */
  function syncConsentNotValidTipPlacement(wrap) {
    if (!wrap || !wrap.querySelector) {
      return;
    }

    var label = wrap.querySelector('label.career-details__consent');
    if (!label) {
      return;
    }

    var fwrap = label.querySelector(
      '.wpcf7-form-control-wrap[data-name="privacy_accept"]'
    );
    var invalid =
      fwrap &&
      fwrap.querySelector('.wpcf7-not-valid, .wpcf7-acceptance.wpcf7-not-valid');

    var after = label.nextElementSibling;
    var afterIsOurs =
      after &&
      after.classList &&
      after.classList.contains('wpcf7-not-valid-tip') &&
      after.getAttribute(NSC_CONSENT_TIP_ATTR) === '1';

    if (!invalid) {
      if (afterIsOurs) {
        after.remove();
      }

      return;
    }

    var footer = label.closest('.career-details__apply-footer');
    var tipInWrap = fwrap && fwrap.querySelector('.wpcf7-not-valid-tip');
    if (tipInWrap && footer) {
      Array.prototype.slice.call(footer.children).forEach(function (n) {
        if (
          n !== tipInWrap &&
          n.classList &&
          n.classList.contains('wpcf7-not-valid-tip') &&
          n.getAttribute(NSC_CONSENT_TIP_ATTR) === '1'
        ) {
          n.remove();
        }
      });
      tipInWrap.setAttribute(NSC_CONSENT_TIP_ATTR, '1');
      label.insertAdjacentElement('afterend', tipInWrap);
    }
  }

  function applyNscCf7InlineStyles(wrap) {
    if (!wrap || !wrap.querySelectorAll) {
      return;
    }

    wrap.querySelectorAll('form.wpcf7-form').forEach(function (form) {
      moveCf7FeedbackBelowFooter(form);
      removeNscJobApplyLoadingIfResponseVisible(form);
    });
    syncConsentNotValidTipPlacement(wrap);
    wrap.querySelectorAll('.screen-reader-response').forEach(function (el) {
      el.setAttribute('style', screenReaderInline);
    });
    wrap.querySelectorAll('.wpcf7-not-valid-tip').forEach(function (el) {
      var fwrap = el.closest('.wpcf7-form-control-wrap[data-name="cv_file"]');
      if (fwrap) {
        var isError = fwrap.querySelector(
          'input[type="file"].wpcf7-not-valid'
        );
        var cvState = el.getAttribute('data-nsc-cv-tip-state');
        var inline;
        if (cvState === 'success') {
          inline = cvTipSuccessInline;
        } else if (cvState === 'uploading') {
          inline = cvTipInfoInline;
        } else {
          inline = isError ? notValidTipInline : cvTipInfoInline;
        }

        el.setAttribute('style', inline);
      } else {
        el.setAttribute('style', notValidTipInline);
      }
    });
    nscDeferredSetJobApplyResponseOutputLayout(wrap);
  }

  function getJobApplyWrap() {
    return document.querySelector('.career-details__apply-form--cf7');
  }

  /** Defer DOM writes so we never run in the same synchronous turn as CF7 submit (fixes broken submit when debug alert is off). */
  var inlineStyleTimer = null;
  function scheduleApplyNscCf7InlineStyles(wrap) {
    if (!wrap) {
      return;
    }

    if (inlineStyleTimer !== null) {
      window.clearTimeout(inlineStyleTimer);
    }

    inlineStyleTimer = window.setTimeout(function () {
      inlineStyleTimer = null;
      applyNscCf7InlineStyles(wrap);
    }, 0);
  }

  function watchCf7Form(form) {
    if (!form || !window.MutationObserver) {
      return;
    }

    var wrap = getJobApplyWrap();
    var obs = new MutationObserver(function () {
      if (wrap) {
        scheduleApplyNscCf7InlineStyles(wrap);
      }
    });
    obs.observe(form, { childList: true, subtree: true });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var wrap = getJobApplyWrap();
    if (!wrap) {
      return;
    }

    applyNscCf7InlineStyles(wrap);
    wrap.querySelectorAll('form.wpcf7-form').forEach(function (f) {
      watchCf7Form(f);
    });
  });

  /** Capture: success green immediately (class may lag one tick). */
  document.addEventListener(
    'wpcf7mailsent',
    function (e) {
      var form = e.target;
      if (
        !form ||
        !form.classList ||
        !form.classList.contains('wpcf7-form') ||
        !form.closest('.career-details__apply-form--cf7')
      ) {
        return;
      }

      var out = form.querySelector('.wpcf7-response-output');
      if (out) {
        nscForceResponseOutputStateClass(out, 'sent');
        out.setAttribute(
          'style',
          nscJobApplyResponseOutputFullInline(form, responseOutputColorSent)
        );
      }

      form.querySelectorAll('.nsc-job-apply-loading').forEach(function (el) {
        el.remove();
      });
    },
    true
  );

  var nscCf7EventToStatusClass = {
    wpcf7invalid: 'invalid',
    wpcf7mailfailed: 'failed',
    wpcf7spam: 'spam',
  };

  ['wpcf7invalid', 'wpcf7mailfailed', 'wpcf7spam'].forEach(function (evt) {
    document.addEventListener(
      evt,
      function (e) {
        var form = e.target;
        if (
          !form ||
          !form.classList ||
          !form.classList.contains('wpcf7-form') ||
          !form.closest('.career-details__apply-form--cf7')
        ) {
          return;
        }

        var out = form.querySelector('.wpcf7-response-output');
        var col =
          evt === 'wpcf7spam'
            ? responseOutputColorSpam
            : responseOutputColorError;
        if (out) {
          var forced = nscCf7EventToStatusClass[evt];
          if (forced) {
            nscForceResponseOutputStateClass(out, forced);
          }

          window.requestAnimationFrame(function () {
            nscSyncResponseOutputStateClassesFromForm(form, out);
            out.setAttribute('style', nscJobApplyResponseOutputFullInline(form, col));
          });
        }

        form.querySelectorAll('.nsc-job-apply-loading').forEach(function (el) {
          el.remove();
        });
      },
      true
    );
  });

  ['wpcf7invalid', 'wpcf7spam', 'wpcf7mailfailed', 'wpcf7mailsent', 'wpcf7submit'].forEach(function (evt) {
    document.addEventListener(evt, function (e) {
      var t = e.target;
      if (t && t.classList && t.classList.contains('wpcf7-form')) {
        var w = getJobApplyWrap();
        if (w) {
          scheduleApplyNscCf7InlineStyles(w);
        }
      }
    });
  });

  /** Debug: logs only (bubble phase — do not use alert/capture; that delayed DOM and masked the MutationObserver bug). */
  var ui = typeof window.nscJobApplyUi !== 'undefined' ? window.nscJobApplyUi : null;
  if (ui && ui.debugSubmitClick && typeof console !== 'undefined' && console.info) {
    document.addEventListener(
      'click',
      function (e) {
        var el = e.target && e.target.closest && e.target.closest('input.wpcf7-submit, button.wpcf7-submit');
        if (!el || !el.closest('.career-details__apply-form--cf7')) {
          return;
        }

        console.info(
          '[NSC job apply] submit click',
          typeof ui.debugSubmitMessage === 'string' && ui.debugSubmitMessage ? ui.debugSubmitMessage : ''
        );
      },
      false
    );
  }
})();
