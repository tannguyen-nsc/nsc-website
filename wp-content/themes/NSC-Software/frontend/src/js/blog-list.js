(function () {
  'use strict';

  function initFeaturedSidebarItems() {
    var sidebarItems = Array.from(
      document.querySelectorAll('.blog-list-details .featured-sidebar .sidebar-item')
    );

    if (!sidebarItems.length) {
      return;
    }

    sidebarItems.forEach(function (item) {
      var excerpt = item.querySelector('.item-excerpt');
      if (!excerpt) {
        return;
      }

      if (!excerpt.dataset.fullText) {
        excerpt.dataset.fullText = (excerpt.textContent || '')
          .replace(/\s+/g, ' ')
          .trim();
      }
    });

    function trimExcerptToFit(content, excerpt) {
      var fullText = (excerpt.dataset.fullText || '').trim();
      excerpt.textContent = fullText;

      if (!fullText || content.scrollHeight <= content.clientHeight) {
        return;
      }

      function buildCutText(length) {
        var sliced = fullText.slice(0, length).trimEnd();
        var withoutPartialWord = sliced.replace(/\s+\S*$/, '').trimEnd();
        var finalText = withoutPartialWord || sliced;
        return finalText.replace(/(\.{3})+$/, '').trimEnd() + '...';
      }

      var low = 0;
      var high = Math.max(0, fullText.length - 1);
      var best = '...';

      while (low <= high) {
        var mid = Math.floor((low + high) / 2);
        var candidate = buildCutText(mid);

        excerpt.textContent = candidate;

        if (content.scrollHeight <= content.clientHeight) {
          best = candidate;
          low = mid + 1;
        } else {
          high = mid - 1;
        }
      }

      excerpt.textContent = best;
    }

    function applySidebarItemLayout() {
      var isDesktop = window.matchMedia('(min-width: 1024px)').matches;

      sidebarItems.forEach(function (item) {
        var thumbnail = item.querySelector('.item-thumbnail');
        var content = item.querySelector('.item-content');
        var excerpt = item.querySelector('.item-excerpt');

        if (!thumbnail || !content) {
          return;
        }

        content.style.height = '';

        if (excerpt && excerpt.dataset.fullText) {
          excerpt.textContent = excerpt.dataset.fullText;
        }

        if (excerpt) {
          if (isDesktop) {
            // Disable CSS line-clamp so JS truncation controls ending with "..."
            excerpt.style.display = 'block';
            excerpt.style.overflow = 'visible';
            excerpt.style.webkitLineClamp = 'unset';
            excerpt.style.webkitBoxOrient = 'initial';
          } else {
            excerpt.style.removeProperty('display');
            excerpt.style.removeProperty('overflow');
            excerpt.style.removeProperty('-webkit-line-clamp');
            excerpt.style.removeProperty('-webkit-box-orient');
          }
        }

        if (!isDesktop) {
          return;
        }

        var thumbnailHeight = thumbnail.offsetHeight;
        if (thumbnailHeight > 0) {
          content.style.height = thumbnailHeight + 'px';
        }

        if (excerpt && excerpt.dataset.fullText) {
          trimExcerptToFit(content, excerpt);
        }
      });
    }

    var resizeTimer = null;
    function handleResize() {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        applySidebarItemLayout();
      }, 150);
    }

    sidebarItems.forEach(function (item) {
      var image = item.querySelector('.item-thumbnail img');
      if (!image) {
        return;
      }

      if (!image.complete) {
        image.addEventListener('load', applySidebarItemLayout);
      }
    });

    window.addEventListener('resize', handleResize, { passive: true });
    window.addEventListener('load', applySidebarItemLayout);
    requestAnimationFrame(applySidebarItemLayout);
  }

  initFeaturedSidebarItems();

  var BLOG_LIST_SELECTOR = '#blog-list-app';
  var PER_PAGE = 6;

  var el = document.querySelector(BLOG_LIST_SELECTOR);
  if (!el || typeof Vue === 'undefined') return;

  var filters = [
    { id: 'all', label: 'All Categories' },
    { id: 'Technology', label: 'Technology' },
    { id: 'Cultures', label: 'Cultures' }
  ];

  var defaultBlogs = [];

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
            return (blog.category || '').toLowerCase() === active;
          });
        }

        return list;
      },
      filteredResultCount: function () {
        return this.filteredBlogs.length;
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
        this.blogs = getBlogsFromWindow();
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
