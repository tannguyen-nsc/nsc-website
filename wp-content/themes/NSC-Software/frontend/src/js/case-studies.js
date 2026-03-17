(function () {
  'use strict';

  var CASE_STUDIES_SELECTOR = '#case-studies-app';
  var PER_PAGE = 6;
  var DESKTOP_DESCRIPTION_LINES = 2;
  var DEFAULT_DESCRIPTION_LINES = 3;

  var el = document.querySelector(CASE_STUDIES_SELECTOR);
  if (!el || typeof Vue === 'undefined') return;

  var filters = [
    { id: 'all', label: 'All Categories' },
    { id: 'Technology', label: 'Technology' },
    { id: 'Fintech', label: 'Fintech' },
    { id: 'Blockchain', label: 'Blockchain' },
    { id: 'Web 3', label: 'Web 3' },
    { id: 'Saas', label: 'Saas' },
    { id: 'Education', label: 'Education' },
    { id: 'Lifestyle', label: 'Lifestyle' }
  ];

  var defaultStudies = [];

  function getStudiesFromWindow() {
    var data = window.caseStudiesVueData;
    if (Array.isArray(data)) return data.slice();
    if (data && Array.isArray(data.studies)) return data.studies.slice();
    if (data && Array.isArray(data.caseStudies)) return data.caseStudies.slice();
    return defaultStudies.slice();
  }

  var app = Vue.createApp({
    data: function () {
      return {
        studies: [],
        activeFilter: 'all',
        currentPage: 1,
        perPage: PER_PAGE,
        filters: filters,
        viewportWidth: window.innerWidth,
        loading: false,
        loadError: null
      };
    },
    computed: {
      filteredStudies: function () {
        var list = this.studies;
        if (this.activeFilter !== 'all') {
          list = list.filter(function (study) {
            return study.category === this.activeFilter;
          }.bind(this));
        }
        return list;
      },
      totalPages: function () {
        var n = this.filteredStudies.length;
        if (n <= 0) return 0;
        return Math.ceil(n / this.perPage);
      },
      paginatedStudies: function () {
        var list = this.filteredStudies;
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
      activeFilter: function () {
        this.currentPage = 1;
      },
      currentPage: function () {
        this.scheduleEqualizeCardHeights();
      },
      studies: function () {
        this.scheduleEqualizeCardHeights();
      }
    },
    methods: {
      getDescriptionLineLimit: function () {
        return window.innerWidth >= 1024 ? DESKTOP_DESCRIPTION_LINES : DEFAULT_DESCRIPTION_LINES;
      },
      trimDescriptionToFit: function (descriptionEl) {
        if (!descriptionEl) return;

        var fullText = descriptionEl.dataset.fullText;
        if (!fullText) {
          fullText = (descriptionEl.textContent || '').replace(/\s+/g, ' ').trim();
          descriptionEl.dataset.fullText = fullText;
        }

        descriptionEl.textContent = fullText;
        descriptionEl.style.overflow = 'hidden';

        var computed = window.getComputedStyle(descriptionEl);
        var lineHeight = parseFloat(computed.lineHeight);
        if (!Number.isFinite(lineHeight)) {
          var fontSize = parseFloat(computed.fontSize) || 16;
          lineHeight = fontSize * 1.5;
        }

        var maxLines = this.getDescriptionLineLimit();
        var maxHeight = Math.ceil(lineHeight * maxLines);
        descriptionEl.style.maxHeight = maxHeight + 'px';

        if (descriptionEl.scrollHeight <= maxHeight) {
          return;
        }

        var words = fullText.split(' ');
        if (!words.length) {
          descriptionEl.textContent = '';
          return;
        }

        var low = 0;
        var high = words.length;
        var best = '';

        while (low <= high) {
          var mid = Math.floor((low + high) / 2);
          var candidate = words.slice(0, mid).join(' ').trim();
          if (candidate.length > 0) {
            candidate += '...';
          }

          descriptionEl.textContent = candidate;
          if (descriptionEl.scrollHeight <= maxHeight) {
            best = candidate;
            low = mid + 1;
          } else {
            high = mid - 1;
          }
        }

        descriptionEl.textContent = best || words[0] + '...';
      },
      applyDescriptionExcerpts: function () {
        var descriptions = this.$el ? this.$el.querySelectorAll('.case-study-card .card-description') : [];
        if (!descriptions.length) return;

        Array.prototype.forEach.call(descriptions, function (descriptionEl) {
          this.trimDescriptionToFit(descriptionEl);
        }.bind(this));
      },
      setFilter: function (filterId) {
        this.activeFilter = filterId;
      },
      setPage: function (page) {
        if (page >= 1 && page <= this.totalPages) {
          this.currentPage = page;
        }
      },
      loadStudies: function () {
        this.loadError = null;
        this.studies = getStudiesFromWindow();
      },
      equalizeCardContentHeights: function () {
        var isMultiColumn = window.innerWidth >= 768;
        var cardContents = this.$el ? this.$el.querySelectorAll('.case-study-card .card-content') : [];

        if (!cardContents.length) return false;

        // Reset first so we always measure natural content height.
        Array.prototype.forEach.call(cardContents, function (content) {
          content.style.height = '';
        });

        if (!isMultiColumn) {
          return true;
        }

        if (this.$el) {
          void this.$el.offsetHeight;
        }

        var maxHeight = 0;
        Array.prototype.forEach.call(cardContents, function (content) {
          var height = content.offsetHeight;
          if (height > maxHeight) {
            maxHeight = height;
          }
        });

        if (!Number.isFinite(maxHeight) || maxHeight <= 0) {
          return false;
        }

        Array.prototype.forEach.call(cardContents, function (content) {
          content.style.height = maxHeight + 'px';
        });

        return true;
      },
      scheduleEqualizeCardHeights: function () {
        this.$nextTick(function () {
          requestAnimationFrame(function () {
            requestAnimationFrame(function () {
              this.applyDescriptionExcerpts();
              var measured = this.equalizeCardContentHeights();
              if (!measured) {
                setTimeout(function () {
                  this.applyDescriptionExcerpts();
                  this.equalizeCardContentHeights();
                }.bind(this), 200);
              }
            }.bind(this));
          }.bind(this));
        }.bind(this));
      }
    },
    mounted: function () {
      this.loadStudies();

      var updateViewport = function () {
        this.viewportWidth = window.innerWidth;
      }.bind(this);

      updateViewport();
      window.addEventListener('resize', updateViewport);
      this._boundScheduleEqualize = this.scheduleEqualizeCardHeights.bind(this);
      this._boundWindowLoadEqualize = this.scheduleEqualizeCardHeights.bind(this);
      window.addEventListener('resize', this._boundScheduleEqualize);

      this.scheduleEqualizeCardHeights();
      setTimeout(this._boundScheduleEqualize, 300);

      window.addEventListener('load', this._boundWindowLoadEqualize, { once: true });
      if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(this._boundScheduleEqualize).catch(function () {
          // no-op
        });
      }
    },
    updated: function () {
      this.scheduleEqualizeCardHeights();
    },
    beforeUnmount: function () {
      if (this._boundScheduleEqualize) {
        window.removeEventListener('resize', this._boundScheduleEqualize);
      }
      if (this._boundWindowLoadEqualize) {
        window.removeEventListener('load', this._boundWindowLoadEqualize);
      }
    }
  });

  app.mount(CASE_STUDIES_SELECTOR);
})();
