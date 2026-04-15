/**
 * Live-sync repeater max rows when "repeaterItemLimit" changes (pageComponents flexible layouts).
 * Re-enables "Add row" after remove: ACF confirms removal via tooltip (not a direct remove-row click),
 * then fires change on the repeater hidden input — we listen for that.
 */
(function ($) {
  if (typeof acf === 'undefined') {
    return;
  }

  var LAYOUT_REPEATER = {
    nscBlockStats: 'stats',
    nscBlockOurServices: 'services',
    nscBlockWhyUs: 'items',
    nscBlockHowWeWork: 'items',
    nscBlockHowWeWorkPageEngagement: 'engagementModels',
    nscBlockOurLeaders: 'leaders',
    nscBlockGlobalPresence: 'locations',
    nscBlockAiImpact: 'items',
    nscBlockAiSecurity: 'items',
  };

  var LAYOUT_DEFAULT_LIMIT = {
    nscBlockStats: 4,
    nscBlockOurServices: 8,
    nscBlockWhyUs: 7,
    nscBlockHowWeWork: 4,
    nscBlockHowWeWorkPageEngagement: 4,
    nscBlockOurLeaders: 4,
    nscBlockGlobalPresence: 5,
    nscBlockAiImpact: 4,
    nscBlockAiSecurity: 3,
  };

  var ABS_MAX = 30;

  function clampMax(n, fallback) {
    var v = parseInt(String(n), 10);
    if (!v || v < 1) {
      v = fallback;
    }
    if (v > ABS_MAX) {
      v = ABS_MAX;
    }
    return v;
  }

  function getRepeaterFieldWrapFromRepeaterEl($repeater) {
    if (!$repeater || !$repeater.length) {
      return $();
    }
    var $w = $repeater.closest('.acf-field.acf-field-repeater');
    if ($w.length) {
      return $w;
    }
    return $repeater.closest('.acf-field[data-type="repeater"]');
  }

  function resolveRepeaterFieldWrap(field) {
    if (!field || !field.$el || !field.$el.closest) {
      return $();
    }
    var $el = field.$el;
    if ($el.hasClass('acf-field-repeater')) {
      return $el;
    }
    var $w = $el.closest('.acf-field.acf-field-repeater');
    if ($w.length) {
      return $w;
    }
    return $el.closest('.acf-field[data-type="repeater"]');
  }

  /**
   * Match ACF repeater $rows(): real rows only (excludes clone template and rows marked deleted).
   */
  function countRepeaterRows($repeater) {
    if (!$repeater || !$repeater.length) {
      return 0;
    }
    return $repeater.find('tbody:first > tr').not('.acf-clone, .acf-deleted').length;
  }

  function syncRepeaterAddRowUi($fieldWrap) {
    if (!$fieldWrap || !$fieldWrap.length) {
      return;
    }
    var $repeater = $fieldWrap.find('.acf-repeater').first();
    if (!$repeater.length) {
      return;
    }
    var max = parseInt($repeater.attr('data-max'), 10);
    if ((!max || max < 1) && typeof acf.getField === 'function') {
      var fld = acf.getField($fieldWrap);
      if (fld && typeof fld.get === 'function') {
        max = parseInt(fld.get('max'), 10) || 0;
      }
    }
    if (max > 0) {
      $repeater.attr('data-max', max);
    }
    var $add = $repeater.find('[data-event="add-row"]');

    if (!max || max < 1) {
      $repeater.removeClass('nsc-acf-repeater-at-max');
      $add.attr('aria-disabled', 'false');
      $add.off('click.nscBlockRepeaterMax');
      return;
    }

    var n = countRepeaterRows($repeater);
    var atMax = n >= max;
    $repeater.toggleClass('nsc-acf-repeater-at-max', atMax);
    $add.attr('aria-disabled', atMax ? 'true' : 'false');
    $add.off('click.nscBlockRepeaterMax');
    if (atMax) {
      $add.on('click.nscBlockRepeaterMax', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
      });
    }
  }

  /** After ACF updates the repeater DOM (remove/add), refresh its Add button state. */
  function nscRefreshRepeaterFieldUi($fieldWrap) {
    syncRepeaterAddRowUi($fieldWrap);
    if (typeof acf.getField !== 'function') {
      return;
    }
    var field = acf.getField($fieldWrap);
    if (field && typeof field.render === 'function') {
      try {
        field.render();
      } catch (err) {
        /* ignore */
      }
      syncRepeaterAddRowUi($fieldWrap);
    }
  }

  var debounceMap = new WeakMap();

  function scheduleDebouncedRepeaterSync($fieldWrap) {
    if (!$fieldWrap || !$fieldWrap.length) {
      return;
    }
    var prev = debounceMap.get($fieldWrap[0]);
    if (prev) {
      window.clearTimeout(prev);
    }
    var t = window.setTimeout(function () {
      debounceMap.delete($fieldWrap[0]);
      nscRefreshRepeaterFieldUi($fieldWrap);
    }, 60);
    debounceMap.set($fieldWrap[0], t);
  }

  function applyMaxForLayout($layout, max) {
    var layoutName = $layout.data('layout');
    if (!layoutName || !LAYOUT_REPEATER[layoutName]) {
      return;
    }
    var repName = LAYOUT_REPEATER[layoutName];
    var $fieldWrap = $layout
      .find('.acf-field.acf-field-repeater[data-name="' + repName + '"], .acf-field[data-type="repeater"][data-name="' + repName + '"]')
      .first();
    if (!$fieldWrap.length) {
      return;
    }
    var $repeater = $fieldWrap.find('.acf-repeater').first();
    if ($repeater.length) {
      $repeater.attr('data-max', max);
    }
    if (typeof acf.getField === 'function') {
      var field = acf.getField($fieldWrap);
      if (field && typeof field.set === 'function') {
        field.set('max', max);
      }
    }
    nscRefreshRepeaterFieldUi($fieldWrap);
  }

  function syncFromInput($input) {
    var $layout = $input.closest('.acf-flexible-content .layout');
    if (!$layout.length) {
      $layout = $input.closest('.layout');
    }
    if (!$layout.length) {
      return;
    }
    var layoutName = $layout.data('layout');
    if (!layoutName || !LAYOUT_REPEATER[layoutName]) {
      return;
    }
    var fallback = LAYOUT_DEFAULT_LIMIT[layoutName] || 4;
    var max = clampMax($input.val(), fallback);
    applyMaxForLayout($layout, max);
  }

  function syncAllLayoutRepeaters() {
    $('.acf-flexible-content .layout').each(function () {
      var $layout = $(this);
      var layoutName = $layout.data('layout');
      if (!layoutName || !LAYOUT_REPEATER[layoutName]) {
        return;
      }
      var repName = LAYOUT_REPEATER[layoutName];
      var $fieldWrap = $layout
        .find('.acf-field.acf-field-repeater[data-name="' + repName + '"], .acf-field[data-type="repeater"][data-name="' + repName + '"]')
        .first();
      nscRefreshRepeaterFieldUi($fieldWrap);
    });
  }

  acf.addAction('ready', function () {
    $(document).on('change input', '.acf-field[data-name="repeaterItemLimit"] input[type="number"]', function () {
      syncFromInput($(this));
    });
    $('.acf-field[data-name="repeaterItemLimit"] input[type="number"]').each(function () {
      syncFromInput($(this));
    });
    syncAllLayoutRepeaters();
  });

  acf.addAction('ready_field/type=repeater', function (field) {
    var $wrap = resolveRepeaterFieldWrap(field);
    if ($wrap.length) {
      nscRefreshRepeaterFieldUi($wrap);
    }
  });

  /**
   * ACF removes rows after tooltip confirm; it triggers change on this input, then render().
   * This is the reliable hook (remove-row click alone is not).
   */
  $(document).on('change', '.acf-repeater > input.acf-repeater-hidden-input', function () {
    var $rep = $(this).closest('.acf-repeater');
    scheduleDebouncedRepeaterSync(getRepeaterFieldWrapFromRepeaterEl($rep));
  });

  $(document).on(
    'click',
    '.acf-repeater a[data-event="add-row"], .acf-repeater a[data-event="remove-row"], .acf-repeater a[data-event="duplicate-row"]',
    function () {
      var $rep = $(this).closest('.acf-repeater');
      var $wrap = getRepeaterFieldWrapFromRepeaterEl($rep);
      window.setTimeout(function () {
        scheduleDebouncedRepeaterSync($wrap);
      }, 0);
      window.setTimeout(function () {
        scheduleDebouncedRepeaterSync($wrap);
      }, 400);
    }
  );
})(jQuery);
