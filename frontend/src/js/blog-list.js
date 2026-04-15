(function () {
  'use strict';

  /** Featured sidebar: 3-line excerpt if title is 1 line, 2-line excerpt if title wraps (2+ lines). */
  function measureTitleLineCount(titleEl) {
    var st = window.getComputedStyle(titleEl);
    var lh = st.lineHeight;
    var linePx;
    if (!lh || lh === 'normal') {
      linePx = (parseFloat(st.fontSize) || 16) * 1.25;
    } else {
      linePx = parseFloat(lh) || 20;
    }
    var h = titleEl.scrollHeight;
    var n = Math.round(h / linePx);
    if (n < 1) {
      n = 1;
    }
    return n;
  }

  function applyFeaturedSidebarExcerptClamp() {
    var items = document.querySelectorAll(
      '.blog-list-details .featured-sidebar .sidebar-item'
    );
    if (!items.length) {
      return;
    }

    var isLg = window.matchMedia('(min-width: 992px)').matches;

    for (var i = 0; i < items.length; i++) {
      var item = items[i];
      var h3 = item.querySelector('.item-content h3');
      var excerpt = item.querySelector('.item-excerpt');
      if (!h3 || !excerpt) {
        continue;
      }

      if (!isLg) {
        excerpt.removeAttribute('data-line-clamp');
        continue;
      }

      var lines = measureTitleLineCount(h3);
      var clamp = lines <= 1 ? 3 : 2;
      excerpt.setAttribute('data-line-clamp', String(clamp));
    }
  }

  var sidebarClampTimer = null;
  function scheduleFeaturedSidebarExcerptClamp() {
    clearTimeout(sidebarClampTimer);
    sidebarClampTimer = setTimeout(function () {
      applyFeaturedSidebarExcerptClamp();
    }, 100);
  }

  applyFeaturedSidebarExcerptClamp();
  window.addEventListener('resize', scheduleFeaturedSidebarExcerptClamp, {
    passive: true
  });
  window.addEventListener('load', scheduleFeaturedSidebarExcerptClamp);
  requestAnimationFrame(function () {
    requestAnimationFrame(applyFeaturedSidebarExcerptClamp);
  });

  document
    .querySelectorAll('.blog-list-details .featured-sidebar .sidebar-item .item-thumbnail img')
    .forEach(function (img) {
      if (!img.complete) {
        img.addEventListener('load', scheduleFeaturedSidebarExcerptClamp);
      }
    });

  var BLOG_LIST_SELECTOR = '#blog-list-app';
  var root =
    typeof window.blogVueData === 'object' && window.blogVueData !== null
      ? window.blogVueData
      : {};
  var PER_PAGE = parseInt(String(root.perPage || '6'), 10) || 6;

  var el = document.querySelector(BLOG_LIST_SELECTOR);
  if (!el || typeof Vue === 'undefined') return;

  var defaultFilters = [
    { id: 'all', label: 'All Categories' },
    { id: 'Technology', label: 'Technology' },
    { id: 'Cultures', label: 'Cultures' }
  ];
  var filters =
    Array.isArray(root.filters) && root.filters.length
      ? root.filters.slice()
      : defaultFilters.slice();

  var defaultBlogs = [];

  /** Plain object; never null/undefined so template property access never throws. */
  function normalizeUiLabels(raw) {
    var base = {
      searchPlaceholder: 'Search',
      searchResultSingular: 'result',
      searchResultPlural: 'results',
      readMore: 'Read More',
      previous: 'Prev',
      next: 'Next',
      empty: 'No blog found.'
    };
    if (raw == null || typeof raw !== 'object' || Array.isArray(raw)) {
      return base;
    }
    var out = {};
    for (var k in base) {
      if (Object.prototype.hasOwnProperty.call(base, k)) {
        out[k] =
          typeof raw[k] === 'string' && raw[k] !== '' ? raw[k] : base[k];
      }
    }
    return out;
  }

  function getBlogsFromWindow() {
    var data = window.blogVueData;
    if (Array.isArray(data)) return data.slice();
    if (data && Array.isArray(data.blogs)) return data.blogs.slice();
    if (data && Array.isArray(data.posts)) return data.posts.slice();
    return defaultBlogs.slice();
  }

  var app = Vue.createApp({
    data: function () {
      return {
        blogs: [],
        activeFilter: 'all',
        searchQuery: '',
        currentPage: 1,
        perPage: PER_PAGE,
        filters: filters,
        uiLabels: normalizeUiLabels(root.labels),
        viewportWidth: window.innerWidth,
        loading: false,
        loadError: null
      };
    },
    computed: {
      filteredBlogs: function () {
        var list = this.blogs;
        var query = (this.searchQuery || '').trim().toLowerCase();
        var active = (this.activeFilter || 'all').toLowerCase();

        if (query) {
          list = list.filter(function (blog) {
            var title = (blog.title || '').toLowerCase();
            var excerpt = (blog.excerpt || '').toLowerCase();
            var category = (blog.category || '').toLowerCase();
            return (
              title.indexOf(query) !== -1 ||
              excerpt.indexOf(query) !== -1 ||
              category.indexOf(query) !== -1
            );
          });
        }

        if (active !== 'all') {
          list = list.filter(function (blog) {
            var c = (blog.category || '').toLowerCase();
            var a = (active || '').toLowerCase();
            return c === a;
          });
        }

        return list;
      },
      filteredResultCount: function () {
        return this.filteredBlogs.length;
      },
      searchResultsSummary: function () {
        var q = (this.searchQuery || '').trim();
        if (!q.length) {
          return '';
        }
        var n = this.filteredResultCount;
        var labels = normalizeUiLabels(this.uiLabels);
        var sing = String(labels.searchResultSingular || 'result').trim();
        var plur = String(labels.searchResultPlural || 'results').trim();
        var word = n === 1 ? sing : plur;
        return n + ' ' + word;
      },
      totalPages: function () {
        var n = this.filteredResultCount;
        if (n <= 0) return 0;
        return Math.ceil(n / this.perPage);
      },
      paginatedPosts: function () {
        var list = this.filteredBlogs;
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
      searchQuery: function () {
        this.currentPage = 1;
      }
    },
    methods: {
      setFilter: function (filterId) {
        this.activeFilter = filterId;
      },
      setPage: function (page) {
        if (page >= 1 && page <= this.totalPages) {
          this.currentPage = page;
        }
      },
      loadBlogs: function () {
        this.loadError = null;
        var fallback = 'blog-details.html';
        this.blogs = getBlogsFromWindow().map(function (post) {
          var p = Object.assign({}, post);
          var lk = (p.link || '').trim();
          if (!lk || lk === '#' || lk === 'javascript:void(0)') {
            p.link = fallback;
          }
          return p;
        });
      }
    },
    mounted: function () {
      this.loadBlogs();

      var updateViewport = function () {
        this.viewportWidth = window.innerWidth;
      }.bind(this);

      updateViewport();
      window.addEventListener('resize', updateViewport);
    }
  });

  app.mount(BLOG_LIST_SELECTOR);
})();
