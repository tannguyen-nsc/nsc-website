(function () {
  'use strict';

  var OPEN_POSITIONS_SELECTOR = '#open-positions-app';
  var PER_PAGE = 5;

  var LEGACY_TABS = [
    { id: 'all', label: 'All Positions', fullLabel: 'All Positions', mobileLabel: 'All' },
    { id: 'management', label: 'Management', fullLabel: 'Management', mobileLabel: 'Management' },
    { id: 'engineering', label: 'Engineering', fullLabel: 'Engineering', mobileLabel: 'Engineering' },
    { id: 'business', label: 'Business', fullLabel: 'Business', mobileLabel: 'Business' }
  ];

  var DEFAULT_LABELS = {
    loading: 'Loading positions...',
    error: 'Could not load positions.',
    empty: 'No positions found.',
    previous: 'Previous',
    next: 'Next',
    applyNow: 'Apply Now'
  };

  function cloneTabs(tabs) {
    return tabs.map(function (t) {
      return {
        id: t.id,
        label: t.label,
        fullLabel: t.fullLabel || t.label,
        mobileLabel: t.mobileLabel || t.label
      };
    });
  }

  /**
   * @returns {{ jobs: array, tabs: array|null, perPage: number, defaultTab: string, labels: object }}
   */
  function getPayload() {
    var d = window.jobVueData;
    if (Array.isArray(d)) {
      return {
        jobs: d.slice(),
        tabs: null,
        perPage: PER_PAGE,
        defaultTab: 'engineering',
        labels: {}
      };
    }
    if (d && typeof d === 'object') {
      var jobs = Array.isArray(d.jobs) ? d.jobs.slice() : [];
      var tabs = Array.isArray(d.tabs) && d.tabs.length ? d.tabs : null;
      var perPage = typeof d.perPage === 'number' && d.perPage > 0 ? d.perPage : PER_PAGE;
      var defaultTab = typeof d.defaultTab === 'string' && d.defaultTab ? d.defaultTab : 'all';
      var labels = d.labels && typeof d.labels === 'object' ? d.labels : {};
      return {
        jobs: jobs,
        tabs: tabs,
        perPage: perPage,
        defaultTab: defaultTab,
        labels: labels
      };
    }
    return {
      jobs: [],
      tabs: null,
      perPage: PER_PAGE,
      defaultTab: 'all',
      labels: {}
    };
  }

  /**
   * Plain object with every key; never null so {{ labels.applyNow }} never throws.
   */
  function mergeUiLabels(fromPayload) {
    var out = {};
    if (fromPayload == null || typeof fromPayload !== 'object' || Array.isArray(fromPayload)) {
      fromPayload = {};
    }
    Object.keys(DEFAULT_LABELS).forEach(function (k) {
      var v = fromPayload[k];
      out[k] = typeof v === 'string' && v !== '' ? v : DEFAULT_LABELS[k];
    });
    return out;
  }

  var el = document.querySelector(OPEN_POSITIONS_SELECTOR);
  if (!el || typeof Vue === 'undefined') return;

  var app = Vue.createApp({
    data: function () {
      var payload = getPayload();
      var tabSource = payload.tabs && payload.tabs.length ? cloneTabs(payload.tabs) : cloneTabs(LEGACY_TABS);
      return {
        jobs: [],
        activeTab: payload.defaultTab,
        currentPage: 1,
        perPage: payload.perPage,
        tabs: tabSource,
        viewportWidth: window.innerWidth,
        loading: false,
        loadError: null,
        uiLabels: mergeUiLabels(payload.labels)
      };
    },
    computed: {
      /** Always defined — use in template instead of uiLabels (avoids undefined during compile/hydrate). */
      labels: function () {
        return mergeUiLabels(this.uiLabels);
      },
      filteredJobs: function () {
        var list = this.jobs;
        if (this.activeTab !== 'all') {
          list = list.filter(function (job) {
            return job.category === this.activeTab;
          }.bind(this));
        }

        return list;
      },
      totalPages: function () {
        var n = this.filteredJobs.length;
        if (n <= 0) return 0;
        return Math.ceil(n / this.perPage);
      },
      paginatedJobs: function () {
        var list = this.filteredJobs;
        var start = (this.currentPage - 1) * this.perPage;
        return list.slice(start, start + this.perPage);
      },
      displayPages: function () {
        var total = this.totalPages;
        var current = this.currentPage;
        var result = [];
        var isMobilePagination = this.viewportWidth < 992;

        if (total <= 0) {
          return result;
        }

        if (isMobilePagination) {
          var start = Math.max(1, Math.min(current - 1, total - 2));
          var end = Math.min(total, start + 2);
          start = Math.max(1, end - 2);

          for (var i = start; i <= end; i++) {
            result.push({ num: i, isEllipsis: false });
          }

          return result;
        }

        if (total <= 5) {
          for (var j = 1; j <= total; j++) {
            result.push({ num: j, isEllipsis: false });
          }

          return result;
        }

        if (current <= 3) {
          for (var k = 1; k <= 5; k++) {
            result.push({ num: k, isEllipsis: false });
          }

          result.push({ num: null, isEllipsis: true });
          result.push({ num: total, isEllipsis: false });
        } else if (current >= total - 2) {
          result.push({ num: 1, isEllipsis: false });
          result.push({ num: null, isEllipsis: true });
          for (var m = total - 4; m <= total; m++) {
            result.push({ num: m, isEllipsis: false });
          }
        } else {
          result.push({ num: 1, isEllipsis: false });
          result.push({ num: null, isEllipsis: true });
          for (var p = current - 1; p <= current + 1; p++) {
            result.push({ num: p, isEllipsis: false });
          }

          result.push({ num: null, isEllipsis: true });
          result.push({ num: total, isEllipsis: false });
        }

        return result;
      }
    },
    watch: {
      activeTab: function () {
        this.currentPage = 1;
      }
    },
    methods: {
      setTab: function (tabId) {
        this.activeTab = tabId;
      },
      setPage: function (page) {
        if (page >= 1 && page <= this.totalPages) {
          this.currentPage = page;
        }
      },
      loadJobs: function () {
        this.loadError = null;
        var payload = getPayload();
        this.jobs = payload.jobs;
        this.perPage = payload.perPage;
        this.uiLabels = mergeUiLabels(payload.labels);
        var tabSource = payload.tabs && payload.tabs.length ? cloneTabs(payload.tabs) : cloneTabs(LEGACY_TABS);
        this.tabs = tabSource;
        if (payload.defaultTab) {
          var ids = this.tabs.map(function (t) { return t.id; });
          if (ids.indexOf(payload.defaultTab) !== -1) {
            this.activeTab = payload.defaultTab;
          }
        }
      }
    },
    mounted: function () {
      this.loadJobs();

      var self = this;
      var updateTabLabels = function () {
        var isMobile = window.innerWidth < 768;
        self.tabs.forEach(function (tab) {
          tab.label = isMobile ? tab.mobileLabel : tab.fullLabel;
        });
      };

      updateTabLabels();
      window.addEventListener('resize', updateTabLabels);

      var updateViewport = function () {
        this.viewportWidth = window.innerWidth;
      }.bind(this);

      updateViewport();
      window.addEventListener('resize', updateViewport);
    }
  });

  app.mount(OPEN_POSITIONS_SELECTOR);
})();
