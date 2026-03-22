/**
 * Stage job-application CV via AJAX (uploads to wp-content/uploads/nsc-job-cv-tmp).
 * Clears the file input after success so CF7 does not re-upload; token is posted in hidden field.
 *
 * Built by Gulp → frontend/build/js/job-apply/ (synced to theme). Do not edit theme copy.
 */
(function () {
  'use strict';

  var cfg = window.nscJobApplyCv;
  if (!cfg || !cfg.ajaxUrl || !cfg.nonce) {
    return;
  }

  var allowedExt = /\.(pdf|docx|doc)$/i;
  /** Match server / CF7 filetypes:doc|docx|pdf (browsers may omit or vary MIME). */
  var allowedMime = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  ];

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

  function showHint(wrap, message, isError) {
    if (!wrap) {
      return;
    }
    var el = wrap.querySelector('.nsc-cv-stage-msg');
    if (!el) {
      el = document.createElement('p');
      el.className = 'nsc-cv-stage-msg';
      el.setAttribute('role', 'status');
      wrap.appendChild(el);
    }
    el.textContent = message || '';
    if (!message) {
      el.removeAttribute('style');
      return;
    }
    var base =
      'display:block;margin-top:0.5rem;font-size:0.875rem;line-height:1.25rem;font-weight:600;';
    if (isError) {
      el.style.cssText = base + 'color:#dc2626;';
    } else {
      el.style.cssText = base + 'color:#fecaca;';
    }
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

    var wrap = input.closest('.career-details__upload') || input.closest('.wpcf7-form-control-wrap');
    showHint(wrap, '', false);

    if (!input.files || !input.files.length) {
      setToken(form, '');
      return;
    }

    var file = input.files[0];
    if (!file || file.size <= 0) {
      showHint(wrap, cfg.i18n.emptyFile || 'Empty file.', true);
      input.value = '';
      setToken(form, '');
      return;
    }
    if (file.size > cfg.maxBytes) {
      showHint(wrap, cfg.i18n.tooLarge, true);
      input.value = '';
      setToken(form, '');
      return;
    }
    if (!allowedExt.test(file.name)) {
      showHint(wrap, cfg.i18n.badType, true);
      input.value = '';
      setToken(form, '');
      return;
    }
    if (!mimeLooksOk(file)) {
      showHint(wrap, cfg.i18n.badMime || cfg.i18n.badType, true);
      input.value = '';
      setToken(form, '');
      return;
    }

    showHint(wrap, cfg.i18n.uploading, false);

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
          var msg =
            (data && data.data && data.data.message) || cfg.i18n.failed;
          showHint(wrap, msg, true);
          input.value = '';
          setToken(form, '');
          return;
        }
        setToken(form, data.data.token);
        showHint(
          wrap,
          'CV uploaded: ' + (data.data.name || file.name),
          false
        );
        input.value = '';
      })
      .catch(function (err) {
        var msg =
          err && err.message === 'bad_response'
            ? cfg.i18n.failed
            : err && err.message === 'not_json'
              ? cfg.i18n.failed
              : cfg.i18n.networkError || cfg.i18n.failed;
        showHint(wrap, msg, true);
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
      var wrap = form.querySelector('.career-details__upload');
      if (wrap) {
        showHint(wrap, '', false);
      }
    },
    false
  );

})();
