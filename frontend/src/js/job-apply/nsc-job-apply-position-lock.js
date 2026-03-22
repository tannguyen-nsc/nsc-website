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
   * Spinner hidden so it cannot sit on top of the submit button and steal clicks.
   */
  var spinnerInline =
    'display:none!important;visibility:hidden!important;width:0!important;height:0!important;margin:0!important;padding:0!important;overflow:hidden!important;pointer-events:none!important;position:absolute!important;clip:rect(0,0,0,0)!important';
  var screenReaderInline = 'display:none!important';
  var notValidTipInline =
    'display:block;margin-top:0.25rem;font-size:0.75rem;line-height:1rem;font-weight:600;color:#ef4444';

  function applyNscCf7InlineStyles(wrap) {
    if (!wrap || !wrap.querySelectorAll) {
      return;
    }
    wrap.querySelectorAll('.screen-reader-response').forEach(function (el) {
      el.setAttribute('style', screenReaderInline);
    });
    wrap.querySelectorAll('.wpcf7-not-valid-tip').forEach(function (el) {
      el.setAttribute('style', notValidTipInline);
    });
    wrap.querySelectorAll('.wpcf7-spinner').forEach(function (el) {
      el.setAttribute('style', spinnerInline);
    });
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
