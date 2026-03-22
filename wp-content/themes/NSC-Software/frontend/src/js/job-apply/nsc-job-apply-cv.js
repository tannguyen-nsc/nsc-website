/**
 * Stage job-application CV via AJAX (uploads to wp-content/uploads/nsc-job-cv-tmp).
 * Clears the file input after success so CF7 does not re-upload; token is posted in hidden field.
 *
 * Status lines use CF7's .wpcf7-not-valid-tip; success state uses green + staged file row with remove.
 *
 * Built by Gulp → frontend/build/js/job-apply/ (synced to theme). Do not edit theme copy.
 */
(function () {
  'use strict';

  var cfg = window.nscJobApplyCv;
  if (!cfg || !cfg.ajaxUrl || !cfg.nonce) {
    return;
  }

  var msg = cfg.messages || {};
  var removeLabel = cfg.removeCvLabel || 'Remove CV';

  var allowedExt = /\.(pdf|docx|doc)$/i;
  /** Match server / CF7 filetypes:doc|docx|pdf (browsers may omit or vary MIME). */
  var allowedMime = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  ];

  var TIP_STATE = {
    clear: '',
    uploading: 'uploading',
    success: 'success',
  };

  function mimeLooksOk(file) {
    var t = (file.type || '').trim().toLowerCase();
    if (!t) {
      return true;
    }
    return allowedMime.indexOf(t) !== -1;
  }

  function setToken(form, token) {
    var hid = form.querySelector('input[name="nsc_cv_staging_token"]');
    if (hid) {
      hid.value = token || '';
    }
  }

  function formatWithPercentS(template, arg) {
    if (!template) {
      return '';
    }
    if (template.indexOf('%s') !== -1) {
      return template.replace(/%s/g, arg == null ? '' : String(arg));
    }
    return template + (arg ? ' ' + arg : '');
  }

  function getUploadRoot(input) {
    return input ? input.closest('.career-details__upload') : null;
  }

  function getCvWrap(input) {
    return input
      ? input.closest('.wpcf7-form-control-wrap[data-name="cv_file"]')
      : null;
  }

  function removeStagedRow(input) {
    var root = getUploadRoot(input);
    if (!root) {
      return;
    }
    root.classList.remove('career-details__upload--has-staged');
    var bar = root.querySelector('.career-details__cv-staged');
    if (bar) {
      bar.remove();
    }
  }

  function ensureStagedRowAfterLabel(input) {
    var root = getUploadRoot(input);
    var wrap = getCvWrap(input);
    var label = root && root.querySelector('.career-details__upload-label');
    if (!root || !wrap || !label) {
      return null;
    }
    var bar = root.querySelector('.career-details__cv-staged');
    if (!bar) {
      bar = document.createElement('div');
      bar.className = 'career-details__cv-staged';
      bar.setAttribute('role', 'status');
      var inner = document.createElement('div');
      inner.className = 'career-details__cv-staged-inner';
      var nameSpan = document.createElement('span');
      nameSpan.className = 'career-details__cv-staged-name';
      nameSpan.setAttribute('aria-live', 'polite');
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'career-details__cv-staged-remove';
      btn.setAttribute('aria-label', removeLabel);
      btn.innerHTML =
        '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">' +
        '<path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
        '</svg>';
      inner.appendChild(nameSpan);
      inner.appendChild(btn);
      bar.appendChild(inner);
      label.insertAdjacentElement('afterend', bar);
      btn.addEventListener('click', function () {
        setToken(input.closest('form'), '');
        removeStagedRow(input);
        showCvTip(input, '', 'info', TIP_STATE.clear);
        if (input && input.value !== undefined) {
          input.value = '';
        }
      });
    }
    return bar;
  }

  /**
   * Keep the tip as the last node in the wrap so it sits below the dashed box / staged row.
   */
  function moveTipToEndOfWrap(wrap, tip) {
    if (wrap && tip && tip.parentNode === wrap) {
      wrap.appendChild(tip);
    }
  }

  /**
   * @param {HTMLInputElement} input file control
   * @param {string} message
   * @param {'error'|'info'} mode
   * @param {string} tipState TIP_STATE.* for inline styling (position-lock.js)
   */
  function showCvTip(input, message, mode, tipState) {
    var wrap = getCvWrap(input);
    if (!wrap || !input) {
      return;
    }
    var tip = wrap.querySelector('.wpcf7-not-valid-tip');
    if (mode === 'clear' || !message) {
      if (tip) {
        tip.remove();
      }
      input.classList.remove('wpcf7-not-valid');
      input.removeAttribute('aria-invalid');
      if (typeof input.setCustomValidity === 'function') {
        input.setCustomValidity('');
      }
      return;
    }
    if (!tip) {
      tip = document.createElement('span');
      tip.className = 'wpcf7-not-valid-tip';
      tip.setAttribute('aria-hidden', 'true');
      wrap.appendChild(tip);
    }
    tip.textContent = message;
    tip.removeAttribute('data-nsc-cv-tip-state');
    if (tipState && tipState !== TIP_STATE.clear) {
      tip.setAttribute('data-nsc-cv-tip-state', tipState);
    }
    moveTipToEndOfWrap(wrap, tip);
    if (mode === 'error') {
      input.classList.add('wpcf7-not-valid');
      input.setAttribute('aria-invalid', 'true');
      if (typeof input.setCustomValidity === 'function') {
        input.setCustomValidity(message);
      }
    } else {
      input.classList.remove('wpcf7-not-valid');
      input.setAttribute('aria-invalid', 'false');
      if (typeof input.setCustomValidity === 'function') {
        input.setCustomValidity('');
      }
    }
  }

  function showStagedSuccess(input, fileName, successMessage) {
    var root = getUploadRoot(input);
    if (!root) {
      return;
    }
    root.classList.add('career-details__upload--has-staged');
    var bar = ensureStagedRowAfterLabel(input);
    if (bar) {
      var nameEl = bar.querySelector('.career-details__cv-staged-name');
      if (nameEl) {
        nameEl.textContent = fileName || '';
      }
    }
    showCvTip(input, successMessage, 'info', TIP_STATE.success);
  }

  document.addEventListener('change', function (e) {
    var input = e.target;
    if (!input || input.name !== 'cv_file' || input.type !== 'file') {
      return;
    }
    var form = input.closest('form');
    if (!form || !form.classList.contains('wpcf7-form')) {
      return;
    }

    removeStagedRow(input);
    showCvTip(input, '', 'info', TIP_STATE.clear);

    if (!input.files || !input.files.length) {
      setToken(form, '');
      return;
    }

    var file = input.files[0];
    if (!file || file.size <= 0) {
      showCvTip(input, msg.nsc_cv_empty_file || '', 'error', TIP_STATE.clear);
      input.value = '';
      setToken(form, '');
      return;
    }
    if (file.size > cfg.maxBytes) {
      showCvTip(input, msg.upload_file_too_large || '', 'error', TIP_STATE.clear);
      input.value = '';
      setToken(form, '');
      return;
    }
    if (!allowedExt.test(file.name)) {
      showCvTip(input, msg.upload_file_type_invalid || '', 'error', TIP_STATE.clear);
      input.value = '';
      setToken(form, '');
      return;
    }
    if (!mimeLooksOk(file)) {
      showCvTip(
        input,
        msg.nsc_cv_bad_mime || msg.upload_file_type_invalid || '',
        'error',
        TIP_STATE.clear
      );
      input.value = '';
      setToken(form, '');
      return;
    }

    showCvTip(input, msg.nsc_cv_uploading || '', 'info', TIP_STATE.uploading);

    var fd = new FormData();
    fd.append('action', cfg.action);
    fd.append('nonce', cfg.nonce);
    fd.append('job_id', String(cfg.jobId));
    fd.append('file', file);

    fetch(cfg.ajaxUrl, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
    })
      .then(function (r) {
        if (!r || !r.ok) {
          throw new Error('bad_response');
        }
        var ct = (r.headers.get('content-type') || '').toLowerCase();
        if (ct.indexOf('application/json') === -1) {
          throw new Error('not_json');
        }
        return r.json();
      })
      .then(function (data) {
        if (!data || !data.success || !data.data || !data.data.token) {
          var failMsg =
            (data && data.data && data.data.message) ||
            msg.upload_failed ||
            '';
          removeStagedRow(input);
          showCvTip(input, failMsg, 'error', TIP_STATE.clear);
          input.value = '';
          setToken(form, '');
          return;
        }
        setToken(form, data.data.token);
        var name = data.data.name || file.name;
        showStagedSuccess(
          input,
          name,
          formatWithPercentS(msg.nsc_cv_uploaded || '', name)
        );
        input.value = '';
      })
      .catch(function (err) {
        var failMsg =
          err && err.message === 'bad_response'
            ? msg.upload_failed || ''
            : err && err.message === 'not_json'
              ? msg.upload_failed || ''
              : msg.nsc_cv_network_error || msg.upload_failed || '';
        removeStagedRow(input);
        showCvTip(input, failMsg, 'error', TIP_STATE.clear);
        input.value = '';
        setToken(form, '');
      });
  });

  document.addEventListener(
    'wpcf7mailsent',
    function (ev) {
      var form = ev.target;
      if (!form || !form.classList.contains('wpcf7-form')) {
        return;
      }
      setToken(form, '');
      var fileInput = form.querySelector(
        'input[type="file"][name="cv_file"]'
      );
      if (fileInput) {
        removeStagedRow(fileInput);
        showCvTip(fileInput, '', 'error', TIP_STATE.clear);
      }
    },
    false
  );
})();
