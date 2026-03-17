(function () {
  'use strict';

  var OPEN_POSITIONS_SELECTOR = '#open-positions-app';
  var PER_PAGE = 5;

  var el = document.querySelector(OPEN_POSITIONS_SELECTOR);
  if (!el || typeof Vue === 'undefined') return;

  var tabs = [
    { id: 'all', label: 'All Positions', fullLabel: 'All Positions', mobileLabel: 'All' },
    { id: 'management', label: 'Management', fullLabel: 'Management', mobileLabel: 'Management' },
    { id: 'engineering', label: 'Engineering', fullLabel: 'Engineering', mobileLabel: 'Engineering' },
    { id: 'business', label: 'Business', fullLabel: 'Business', mobileLabel: 'Business' }
  ];

  var defaultJobs = [];

  function getJobsFromWindow() {
    var data = window.jobVueData;
    if (Array.isArray(data)) return data.slice();
    if (data && Array.isArray(data.jobs)) return data.jobs.slice();
    return defaultJobs.slice();
  }

  var app = Vue.createApp({
    data: function () {
      return {
        jobs: [],
        activeTab: 'engineering',
        currentPage: 1,
        perPage: PER_PAGE,
        tabs: tabs,
        viewportWidth: window.innerWidth,
        loading: false,
        loadError: null
      };
    },
    computed: {
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
        var isMobilePagination = this.viewportWidth < 1024;

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
          for (var i = 1; i <= total; i++) {
            result.push({ num: i, isEllipsis: false });
          }

          return result;
        }

        if (current <= 3) {
          for (var i = 1; i <= 5; i++) {
            result.push({ num: i, isEllipsis: false });
          }

          result.push({ num: null, isEllipsis: true });
          result.push({ num: total, isEllipsis: false });
        } else if (current >= total - 2) {
          result.push({ num: 1, isEllipsis: false });
          result.push({ num: null, isEllipsis: true });
          for (var i = total - 4; i <= total; i++) {
            result.push({ num: i, isEllipsis: false });
          }
        } else {
          result.push({ num: 1, isEllipsis: false });
          result.push({ num: null, isEllipsis: true });
          for (var i = current - 1; i <= current + 1; i++) {
            result.push({ num: i, isEllipsis: false });
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
        this.jobs = getJobsFromWindow();
      }
    },
    mounted: function () {
      this.loadJobs();

      var updateTabLabels = function () {
        var isMobile = window.innerWidth < 768;
        tabs.forEach(function (tab) {
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
