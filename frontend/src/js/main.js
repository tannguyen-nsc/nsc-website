/**
 * Main JavaScript File
 * Refactored for better organization, maintainability, and code reuse
 */

(function() {
  'use strict';

  // ============================================================================
  // CONFIGURATION & CONSTANTS
  // ============================================================================
  
  const CONFIG = {
    SCROLL_END_DELAY: 150,
    OBSERVER_THRESHOLD: [0, 0.1],
    DEBUG: false,
    BREAKPOINTS: {
      MOBILE: 425,
      TABLET: 768,
      DESKTOP: 1024,
      LARGE: 1280
    },
    INIT_DELAY: 100,
    RESIZE_DEBOUNCE: 250
  };

  // ============================================================================
  // UTILITIES
  // ============================================================================

  /**
   * Debug logging helper
   */
  function debugLog(emoji, category, message, data = {}) {
    if (CONFIG.DEBUG) {
      console.log(`${emoji} [${category}] ${message}`, data);
    }
  }

  /**
   * Get current scroll position
   */
  function getScrollPosition() {
    return window.pageYOffset || 
           window.scrollY || 
           document.documentElement.scrollTop || 
           document.body.scrollTop || 
           0;
  }

  /**
   * Escape HTML to prevent XSS
   */
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Safe DOM ready initialization
   */
  function onDOMReady(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => {
        setTimeout(callback, CONFIG.INIT_DELAY);
      });
    } else {
      setTimeout(callback, CONFIG.INIT_DELAY);
    }
  }

  /**
   * Debounce function
   */
  function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  // ============================================================================
  // SCROLL LOCK MIXIN
  // ============================================================================

  /**
   * ScrollLockMixin - Provides scroll locking functionality to modal/overlay classes
   */
  const ScrollLockMixin = {
    lockBodyScroll() {
      const scrollY = getScrollPosition();
      
      // Store scroll position for restoration
      document.body.setAttribute('data-scroll-position', scrollY);
      
      // Prevent touch scrolling on iOS
      document.body.style.touchAction = 'none';
      document.body.style.webkitOverflowScrolling = 'none';
      
      // Lock html element
      document.documentElement.style.overflow = 'hidden';
      document.documentElement.style.height = '100%';
      
      // Prevent scroll events
      const preventScroll = (e) => {
        e.preventDefault();
        e.stopPropagation();
        return false;
      };
      
      // Store handler for cleanup
      this._preventScrollHandler = preventScroll;
      
      // Add scroll prevention listeners
      window.addEventListener('scroll', preventScroll, { passive: false, capture: true });
      document.addEventListener('scroll', preventScroll, { passive: false, capture: true });
      document.addEventListener('wheel', preventScroll, { passive: false, capture: true });
      document.addEventListener('touchmove', preventScroll, { passive: false, capture: true });
    },

    unlockBodyScroll() {
      // Remove scroll prevention listeners
      if (this._preventScrollHandler) {
        window.removeEventListener('scroll', this._preventScrollHandler, { capture: true });
        document.removeEventListener('scroll', this._preventScrollHandler, { capture: true });
        document.removeEventListener('wheel', this._preventScrollHandler, { capture: true });
        document.removeEventListener('touchmove', this._preventScrollHandler, { capture: true });
        this._preventScrollHandler = null;
      }
      
      // Get stored scroll position
      const scrollY = parseInt(document.body.getAttribute('data-scroll-position') || '0', 10);
      
      // Unlock body scroll
      document.body.style.position = '';
      document.body.style.top = '';
      document.body.style.left = '';
      document.body.style.right = '';
      document.body.style.width = '';
      document.body.style.height = '';
      document.body.style.overflow = '';
      document.body.style.touchAction = '';
      document.body.style.webkitOverflowScrolling = '';
      
      // Unlock html element
      document.documentElement.style.overflow = '';
      document.documentElement.style.height = '';
      
      // Remove stored scroll position
      document.body.removeAttribute('data-scroll-position');
    },

    handleBodyScroll() {
      const observer = new MutationObserver(() => {
        if (this.isOpen) {
          this.lockBodyScroll();
        }
      });

      observer.observe(document.body, {
        attributes: true,
        attributeFilter: ['style']
      });
    }
  };

  // ============================================================================
  // BASE MODAL CLASS
  // ============================================================================

  /**
   * BaseModal - Base class for modal/overlay components
   */
  class BaseModal {
    constructor(config) {
      this.overlay = document.getElementById(config.overlayId);
      this.modal = document.getElementById(config.modalId);
      this.closeBtn = document.getElementById(config.closeBtnId);
      this.isOpen = false;
      this.config = config;
      
      // Apply scroll lock mixin
      Object.assign(this, ScrollLockMixin);
    }

    init() {
      if (!this.modal) {
        console.warn(`${this.constructor.name}: Modal element not found`);
        return;
      }

      this.setupEventListeners();
      this.handleBodyScroll();
    }

    setupEventListeners() {
      // Close button
      if (this.closeBtn) {
        this.closeBtn.addEventListener('click', () => this.close());
      }

      // Overlay click
      if (this.overlay) {
        this.overlay.addEventListener('click', () => this.close());
      }

      // Escape key
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && this.isOpen) {
          this.close();
        }
      });
    }

    open() {
      if (this.isOpen) return;
      
      this.lockBodyScroll();
      
      setTimeout(() => {
        if (this.overlay) {
          this.overlay.classList.add('active');
        }

        if (this.modal) {
          this.modal.classList.add('active');
        }

        this.isOpen = true;
        this.onOpen();
      }, 10);
    }

    close() {
      if (!this.isOpen) return;

      if (this.overlay) {
        this.overlay.classList.remove('active');
      }

      if (this.modal) {
        this.modal.classList.remove('active');
      }

      this.isOpen = false;
      this.unlockBodyScroll();
      this.onClose();
    }

    // Override in subclasses
    onOpen() {}
    onClose() {}
  }

  // ============================================================================
  // SCROLL ANIMATION MANAGER
  // ============================================================================

  class ScrollAnimationManager {
    constructor() {
      this.elementStates = new WeakMap();
      this.resetQueue = new Set();
      this.scrollTimeout = null;
      this.observer = null;
    }

    init() {
      const animatedElements = document.querySelectorAll('.animate__animated');
      
      if (animatedElements.length === 0) {
        return;
      }

      this.setupElements(animatedElements);
      this.setupObserver();
      this.setupScrollListener();
      debugLog('🔵', 'INIT', 'Scroll animations initialized', {
        elementsCount: animatedElements.length
      });
    }

    setupElements(elements) {
      elements.forEach(element => {
        const animationClass = Array.from(element.classList).find(cls => 
          cls.startsWith('animate__') && cls !== 'animate__animated'
        );
        
        if (animationClass) {
          this.elementStates.set(element, {
            animationClass: animationClass,
            isAnimated: false,
            isProcessing: false
          });
          
          element.classList.remove(animationClass);
        }
      });
    }

    setupObserver() {
      const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: CONFIG.OBSERVER_THRESHOLD
      };

      this.observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          const element = entry.target;
          const state = this.elementStates.get(element);
          
          if (!state) return;

          if (entry.isIntersecting) {
            this.handleElementInView(element, state);
          } else {
            this.handleElementOutOfView(element, state);
          }
        });
      }, observerOptions);

      document.querySelectorAll('.animate__animated').forEach(element => {
        this.observer.observe(element);
      });
    }

    handleElementInView(element, state) {
      if (this.resetQueue.has(element)) {
        this.resetQueue.delete(element);
        debugLog('❌', 'OBSERVER', 'Removed from reset queue', {
          animationClass: state.animationClass
        });
      }
      
      if (!state.isAnimated && !state.isProcessing) {
        this.triggerAnimation(element, state);
      }
    }

    handleElementOutOfView(element, state) {
      if (state.isAnimated && !state.isProcessing) {
        this.resetQueue.add(element);
        debugLog('📋', 'OBSERVER', 'Added to reset queue', {
          animationClass: state.animationClass,
          queueSize: this.resetQueue.size
        });
      }
    }

    triggerAnimation(element, state) {
      if (!state || state.isAnimated || state.isProcessing) {
        return;
      }

      state.isProcessing = true;
      debugLog('✅', 'TRIGGER', 'Starting animation', {
        animationClass: state.animationClass
      });
      
      element.classList.add('in-view');
      
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          if (state && !state.isAnimated) {
            element.classList.add(state.animationClass);
            state.isAnimated = true;
            state.isProcessing = false;
            debugLog('✨', 'TRIGGER', 'Animation applied', {
              animationClass: state.animationClass
            });
          }
        });
      });
    }

    resetAnimation(element, state) {
      if (!state || !state.isAnimated || state.isProcessing) {
        return;
      }

      state.isProcessing = true;
      debugLog('🔄', 'RESET', 'Resetting animation', {
        animationClass: state.animationClass
      });
      
      element.classList.remove('in-view');
      element.classList.remove(state.animationClass);
      
      state.isAnimated = false;
      state.isProcessing = false;
      
      debugLog('✅', 'RESET', 'Animation reset complete', {
        animationClass: state.animationClass
      });
    }

    processResetQueue() {
      if (this.resetQueue.size === 0) {
        return;
      }
      
      debugLog('🔄', 'SCROLL-END', 'Processing reset queue', {
        queueSize: this.resetQueue.size
      });
      
      this.resetQueue.forEach(element => {
        const state = this.elementStates.get(element);
        if (state && state.isAnimated && !state.isProcessing) {
          this.resetAnimation(element, state);
        }
      });
      
      this.resetQueue.clear();
    }

    setupScrollListener() {
      window.addEventListener('scroll', () => {
        clearTimeout(this.scrollTimeout);
        this.scrollTimeout = setTimeout(() => {
          debugLog('⏹️', 'SCROLL-END', 'Scroll ended, processing resets');
          this.processResetQueue();
        }, CONFIG.SCROLL_END_DELAY);
      }, { passive: true });
    }
  }

  // ============================================================================
  // NUMBER COUNTER MANAGER
  // ============================================================================

  class NumberCounterManager {
    constructor() {
      this.elementStates = new WeakMap();
      this.animationInstances = new WeakMap();
      this.hasRun = false;
    }

    init() {
      if (typeof $ === 'undefined') {
        console.warn('jQuery is required for number counter animations');
        return;
      }

      const counterElements = $('.count');
      
      if (counterElements.length === 0) {
        return;
      }

      if (this.hasRun) {
        return;
      }

      this.hasRun = true;

      counterElements.each((index, element) => {
        const $element = $(element);
        const originalText = $element.text();
        const targetValue = originalText.replace(/[^0-9]/g, '');
        
        this.elementStates.set(element, {
          originalText: originalText,
          targetValue: targetValue,
          isAnimating: false
        });
      });
      counterElements.each((index, element) => {
        const state = this.elementStates.get(element);
        if (state) {
          this.animateCounter($(element), state);
        }
      });
    }

    animateCounter($element, state) {
      if (state.isAnimating) {
        return;
      }

      state.isAnimating = true;
      debugLog('✅', 'COUNTER', 'Starting counter animation', {
        targetValue: state.targetValue
      });

      $element.text('0');

      const animation = $element
        .prop('Counter', 0)
        .animate(
          {
            Counter: parseFloat(state.targetValue) || 0
          },
          {
            duration: 4000,
            easing: 'swing',
            step: (now) => {
              const formatted = Number(Math.ceil(now)).toLocaleString('en');
              $element.text(formatted);
            },
            complete: () => {
              $element.text(state.originalText);
              state.isAnimating = false;
              this.animationInstances.delete($element[0]);
              debugLog('✨', 'COUNTER', 'Counter animation complete', {
                finalValue: state.originalText
              });
            }
          }
        );

      this.animationInstances.set($element[0], animation);
    }
  }

  // ============================================================================
  // SLIDER MANAGERS
  // ============================================================================

  /**
   * Set equal heights for testimonial items and footers
   */
  function setEqualHeights($container) {
    if (!$container || $container.length === 0) {
      return;
    }

    requestAnimationFrame(() => {
      const $items = $container.find('.item');
      const $footers = $items.find('.footer');
      const $contents = $items.find('.content');

      if ($items.length === 0) {
        return;
      }

      let maxItemHeight = 0;
      let maxFooterHeight = 0;
      let maxContentHeight = 0;

      $items.css('height', 'auto');
      $footers.css('height', 'auto');
      $contents.css('height', 'auto');

      // Force reflow before measuring
      $container[0] && $container[0].offsetHeight;

      for (let i = 0; i < $items.length; i += 1) {
        const height = $($items[i]).outerHeight(true);
        if (height > maxItemHeight && height > 0 && !isNaN(height)) {
          maxItemHeight = height;
        }
      }

      for (let i = 0; i < $footers.length; i += 1) {
        const height = $($footers[i]).outerHeight(true);
        if (height > maxFooterHeight && height > 0 && !isNaN(height)) {
          maxFooterHeight = height;
        }
      }

      for (let i = 0; i < $contents.length; i += 1) {
        const height = $($contents[i]).outerHeight(true);
        if (height > maxContentHeight && height > 0 && !isNaN(height)) {
          maxContentHeight = height;
        }
      }

      if (maxItemHeight > 0 && !isNaN(maxItemHeight)) {
        $items.css('height', maxItemHeight + 'px');
      }

      if (maxFooterHeight > 0 && !isNaN(maxFooterHeight)) {
        $footers.css('height', maxFooterHeight + 'px');
      }

      if (maxContentHeight > 0 && !isNaN(maxContentHeight)) {
        $contents.css('height', maxContentHeight + 'px');
      }
    });
  }

  class TestimonialLogosSlider {
    constructor() {
      this.slider = null;
    }

    init() {
      const checkDependencies = () => {
        if (typeof $ === 'undefined' || typeof $.fn.slick === 'undefined') {
          setTimeout(checkDependencies, 100);
          return;
        }

        const $logosContainer = $('.testimonials .logos');
        
        if ($logosContainer.length === 0 || $logosContainer.hasClass('slick-initialized')) {
          return;
        }
        
        let slidesToShow = 3;
        if (screen.width > CONFIG.BREAKPOINTS.MOBILE) {
          slidesToShow = 4;
        }

        if (screen.width > CONFIG.BREAKPOINTS.TABLET) {
          slidesToShow = 5;
        }

        if (screen.width > CONFIG.BREAKPOINTS.DESKTOP) {
          slidesToShow = 6;
        }

        const totalLogos = $logosContainer.find('.logo').length;
        if (totalLogos > 1 && slidesToShow >= totalLogos) {
          slidesToShow = totalLogos - 1;
        }

        $logosContainer.slick({
          slidesToShow: slidesToShow,
          slidesToScroll: 1,
          autoplay: true,
          autoplaySpeed: 5000,
          speed: 800,
          arrows: false,
          dots: false,
          infinite: true,
          adaptiveHeight: false,
          pauseOnHover: true,
          touchMove: true,
          swipe: true,
          swipeToSlide: true,
          touchThreshold: 5,
          draggable: true,
          accessibility: true,
          centerMode: false,
          fade: false,
        });

        this.slider = $logosContainer;
        debugLog('✨', 'SLIDER', 'Testimonial logos slider initialized');
      };

      checkDependencies();
    }
  }

  class TestimonialsSlider {
    constructor() {
      this.slider = null;
    }

    init() {
      const checkDependencies = () => {
        if (typeof $ === 'undefined' || typeof $.fn.slick === 'undefined') {
          setTimeout(checkDependencies, 100);
          return;
        }

        const $testimonialsContainer = $('.testimonials-slider');
        
        if ($testimonialsContainer.length === 0 || $testimonialsContainer.hasClass('slick-initialized')) {
          return;
        }
        
        let slidesToShow = 1;
        if (screen.width >= CONFIG.BREAKPOINTS.DESKTOP) {
          slidesToShow = 2;
        }

        if (screen.width >= CONFIG.BREAKPOINTS.LARGE) {
          slidesToShow = 3;
        }

        $testimonialsContainer.on('init', function() {
          if (screen.width > CONFIG.BREAKPOINTS.MOBILE) {
            setTimeout(() => setEqualHeights($testimonialsContainer), 300);
          }

          $testimonialsContainer.find('img').on('load', function() {
            if (screen.width > CONFIG.BREAKPOINTS.MOBILE) {
              setEqualHeights($testimonialsContainer);
            }
          });
        });

        $testimonialsContainer.slick({
          slidesToShow: slidesToShow,
          slidesToScroll: 1,
          autoplay: true,
          autoplaySpeed: 5000,
          speed: 800,
          arrows: false,
          dots: false,
          infinite: true,
          adaptiveHeight: false,
          pauseOnHover: true,
          touchMove: true,
          swipe: true,
          swipeToSlide: true,
          touchThreshold: 5,
          draggable: true,
          accessibility: true,
          centerMode: false,
          fade: false,
          cssEase: 'ease-in-out',
          prevArrow: '<button type="button" class="slick-prev" aria-label="Previous"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M15.707 4.29289C16.0975 4.68342 16.0975 5.31643 15.707 5.70696L10.4141 10.9999H22C22.5522 10.9999 23 11.4476 23 11.9999C23 12.5522 22.5522 12.9999 22 12.9999H10.4141L15.707 18.2929C16.0975 18.6834 16.0975 19.3164 15.707 19.707C15.3165 20.0975 14.6834 20.0975 14.2929 19.707L7.29289 12.707C6.90237 12.3164 6.90237 11.6834 7.29289 11.293L14.2929 4.29289C14.6834 3.90237 15.3165 3.90237 15.707 4.29289Z" fill="currentColor"/></svg></button>',
          nextArrow: '<button type="button" class="slick-next" aria-label="Next"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M8.293 4.29289C7.90237 4.68342 7.90237 5.31643 8.293 5.70696L13.5859 10.9999H2C1.44772 10.9999 1 11.4476 1 11.9999C1 12.5522 1.44772 12.9999 2 12.9999H13.5859L8.293 18.2929C7.90237 18.6834 7.90237 19.3164 8.293 19.707C8.68342 20.0975 9.31643 20.0975 9.70696 19.707L16.707 12.707C17.0976 12.3164 17.0976 11.6834 16.707 11.293L9.70696 4.29289C9.31643 3.90237 8.68342 3.90237 8.293 4.29289Z" fill="currentColor"/></svg></button>'
        });

        this.slider = $testimonialsContainer;
        debugLog('✨', 'SLIDER', 'Testimonials slider initialized');
        
        let resizeTimeout;
        $(window).on('resize', () => {
          clearTimeout(resizeTimeout);
          resizeTimeout = setTimeout(() => {
            if (screen.width > CONFIG.BREAKPOINTS.MOBILE) {
              setEqualHeights($testimonialsContainer);
            }
          }, CONFIG.RESIZE_DEBOUNCE);
        });
      };

      checkDependencies();
    }
  }

  // ============================================================================
  // WHY US SLIDER
  // ============================================================================

  class WhyUsSlider {
    constructor() {
      this.slider = null;
      this.isMobile = window.innerWidth < CONFIG.BREAKPOINTS.LARGE;
      this.mobileGlobeStepX = 30;
      this.mobileGlobeCurrentX = -150;
    }

    resetMobileGlobePosition() {
      const globeImgs = document.querySelectorAll('.why-us .why-us-globe__img');
      this.mobileGlobeCurrentX = -150;
      globeImgs.forEach((img) => {
        img.style.objectPosition = '-150px 0px';
      });
    }

    unbindMobileGlobeSwipe($whyUsContent) {
      if ($whyUsContent && $whyUsContent.length > 0) {
        $whyUsContent.off('.whyUsGlobeSwipe');
      }

      $(window).off('resize.whyUsGlobeSwipe');
    }

    bindMobileGlobeSwipe($whyUsContent) {
      this.unbindMobileGlobeSwipe($whyUsContent);

      const globe = document.querySelector('.why-us .why-us-globe');
      const globeImgs = globe ? Array.from(globe.querySelectorAll('.why-us-globe__img')) : [];

      if (!$whyUsContent || $whyUsContent.length === 0 || !globe || globeImgs.length === 0) {
        return;
      }

      const applyPosition = (x) => {
        globeImgs.forEach((img) => {
          img.style.objectPosition = `${x}px 0px`;
        });
      };

      const isGlobeSwipeViewport = () => window.innerWidth < CONFIG.BREAKPOINTS.LARGE;

      const getMaxX = () => {
        if (!isGlobeSwipeViewport()) {
          this.resetMobileGlobePosition();
          return 0;
        }

        // Keep limits aligned with _why.scss:
        // <768px: globe 300, map 610 -> 310
        // 768px-1279px: globe 500, map 1017 -> 517
        return window.innerWidth >= CONFIG.BREAKPOINTS.TABLET ? 517 : 310;
      };

      const moveGlobeOneStep = (direction) => {
        const maxX = getMaxX();
        if (maxX <= 0) {
          this.mobileGlobeCurrentX = -150;
          applyPosition(-150);
          return;
        }

        // Match globe motion with swipe direction.
        if (direction === 'prev') {
          this.mobileGlobeCurrentX += this.mobileGlobeStepX;
        } else {
          this.mobileGlobeCurrentX -= this.mobileGlobeStepX;
        }

        // Wrap inside valid range so reverse swipes animate opposite way.
        if (this.mobileGlobeCurrentX <= -maxX) {
          this.mobileGlobeCurrentX = -150;
        } else if (this.mobileGlobeCurrentX > 0) {
          this.mobileGlobeCurrentX = -maxX;
        }

        applyPosition(this.mobileGlobeCurrentX);
      };

      $whyUsContent.on('beforeChange.whyUsGlobeSwipe', (event, slick, currentSlide, nextSlide) => {
        let direction = 'next';
        if (typeof currentSlide === 'number' && typeof nextSlide === 'number' && slick && typeof slick.slideCount === 'number') {
          const slideCount = slick.slideCount;
          if (nextSlide === (currentSlide - 1 + slideCount) % slideCount) {
            direction = 'prev';
          }
        }

        moveGlobeOneStep(direction);
      });

      this.mobileGlobeCurrentX = -150;
      applyPosition(-150);

      $(window).on('resize.whyUsGlobeSwipe', debounce(() => {
        const maxX = getMaxX();
        if (maxX <= 0) {
          this.resetMobileGlobePosition();
          return;
        }

        if (this.mobileGlobeCurrentX <= -maxX) {
          this.mobileGlobeCurrentX = -150;
        }

        applyPosition(this.mobileGlobeCurrentX);
      }, CONFIG.RESIZE_DEBOUNCE));
    }

    init() {
      const checkDependencies = () => {
        if (typeof $ === 'undefined' || typeof $.fn.slick === 'undefined') {
          setTimeout(checkDependencies, 100);
          return;
        }

        // Ensure DOM is ready
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', () => {
            setTimeout(checkDependencies, 100);
          });
          return;
        }

        const $whyUsContent = $('.why-us-content');
        
        if ($whyUsContent.length === 0) {
          return;
        }

        // Update isMobile based on current window width
        const currentWidth = window.innerWidth || 
                            document.documentElement.clientWidth || 
                            document.body.clientWidth || 
                            screen.width;
        this.isMobile = currentWidth < CONFIG.BREAKPOINTS.LARGE;

        // Only initialize on screens below 1280px
        if (!this.isMobile) {
          // If already initialized, destroy it
          if ($whyUsContent.hasClass('slick-initialized')) {
            $whyUsContent.slick('unslick');
          }

          return;
        }

        // Check if already initialized
        if ($whyUsContent.hasClass('slick-initialized')) {
          return;
        }

        // Calculate slidesToShow based on current window width
        let slidesToShow = 1;
        if (currentWidth > CONFIG.BREAKPOINTS.MOBILE) {
          slidesToShow = 2;
        }

        if (currentWidth > CONFIG.BREAKPOINTS.TABLET) {
          slidesToShow = 3;
        }

        // For screens between 1024px and 1280px, show 3 slides
        if (currentWidth >= CONFIG.BREAKPOINTS.DESKTOP && currentWidth < CONFIG.BREAKPOINTS.LARGE) {
          slidesToShow = 3;
        }

        debugLog('📐', 'SLIDER', 'Why Us slider slidesToShow calculated', {
          windowWidth: currentWidth,
          slidesToShow: slidesToShow,
          isMobile: this.isMobile,
          breakpoints: {
            mobile: CONFIG.BREAKPOINTS.MOBILE,
            tablet: CONFIG.BREAKPOINTS.TABLET,
            desktop: CONFIG.BREAKPOINTS.DESKTOP,
            large: CONFIG.BREAKPOINTS.LARGE
          }
        });
        
        // Initialize slick slider for mobile
        $whyUsContent.slick({
          slidesToShow: slidesToShow,
          slidesToScroll: 1,
          autoplay: true,
          autoplaySpeed: 3000,
          speed: 500,
          arrows:false,
          dots: false,
          infinite: true,
          adaptiveHeight: true,
          pauseOnHover: true,
          pauseOnFocus: true,
          touchMove: true,
          swipe: true,
          swipeToSlide: true,
          touchThreshold: 5,
          draggable: true,
          accessibility: true,
          centerMode: false,
          fade: false,
          cssEase: 'ease-in-out',
          prevArrow: '<button type="button" class="slick-prev" aria-label="Previous"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M15.707 4.29289C16.0975 4.68342 16.0975 5.31643 15.707 5.70696L10.4141 10.9999H22C22.5522 10.9999 23 11.4476 23 11.9999C23 12.5522 22.5522 12.9999 22 12.9999H10.4141L15.707 18.2929C16.0975 18.6834 16.0975 19.3164 15.707 19.707C15.3165 20.0975 14.6834 20.0975 14.2929 19.707L7.29289 12.707C6.90237 12.3164 6.90237 11.6834 7.29289 11.293L14.2929 4.29289C14.6834 3.90237 15.3165 3.90237 15.707 4.29289Z" fill="currentColor"/></svg></button>',
          nextArrow: '<button type="button" class="slick-next" aria-label="Next"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M8.293 4.29289C7.90237 4.68342 7.90237 5.31643 8.293 5.70696L13.5859 10.9999H2C1.44772 10.9999 1 11.4476 1 11.9999C1 12.5522 1.44772 12.9999 2 12.9999H13.5859L8.293 18.2929C7.90237 18.6834 7.90237 19.3164 8.293 19.707C8.68342 20.0975 9.31643 20.0975 9.70696 19.707L16.707 12.707C17.0976 12.3164 17.0976 11.6834 16.707 11.293L9.70696 4.29289C9.31643 3.90237 8.68342 3.90237 8.293 4.29289Z" fill="currentColor"/></svg></button>'
        });

        this.slider = $whyUsContent;
        this.bindMobileGlobeSwipe($whyUsContent);
        debugLog('✨', 'SLIDER', 'Why Us slider initialized');
      };

      checkDependencies();
    }

    destroy() {
      const $whyUsContent = $('.why-us-content');
      this.unbindMobileGlobeSwipe($whyUsContent);
      this.resetMobileGlobePosition();
      if ($whyUsContent.length > 0 && $whyUsContent.hasClass('slick-initialized')) {
        $whyUsContent.slick('unslick');
        this.slider = null;
        debugLog('🔄', 'SLIDER', 'Why Us slider destroyed');
      }
    }

    handleResize() {
      const wasMobile = this.isMobile;
      this.isMobile = window.innerWidth < CONFIG.BREAKPOINTS.LARGE;

      // If switching from mobile to desktop, destroy slider
      if (wasMobile && !this.isMobile) {
        this.destroy();
      }

      // If switching from desktop to mobile, initialize slider
      else if (!wasMobile && this.isMobile) {
        this.init();
      }

      // If still mobile but width changed, update slidesToShow
      else if (this.isMobile && this.slider && this.slider.hasClass('slick-initialized')) {
        const currentWidth = window.innerWidth || 
                            document.documentElement.clientWidth || 
                            document.body.clientWidth || 
                            screen.width;
        const $whyUsContent = $('.why-us-content');
        
        if ($whyUsContent.length > 0 && $whyUsContent.hasClass('slick-initialized')) {
          let newSlidesToShow = 1;
          if (currentWidth > CONFIG.BREAKPOINTS.MOBILE) {
            newSlidesToShow = 2;
          }

          if (currentWidth > CONFIG.BREAKPOINTS.TABLET) {
            newSlidesToShow = 3;
          }

          if (currentWidth >= CONFIG.BREAKPOINTS.DESKTOP && currentWidth < CONFIG.BREAKPOINTS.LARGE) {
            newSlidesToShow = 3;
          }
          
          // Update slidesToShow without destroying
          $whyUsContent.slick('slickSetOption', 'slidesToShow', newSlidesToShow, true);
          debugLog('🔄', 'SLIDER', 'Why Us slider slidesToShow updated', {
            windowWidth: currentWidth,
            slidesToShow: newSlidesToShow
          });
        }
      }
    }
  }

  // ============================================================================
  // MOBILE MENU
  // ============================================================================

  function initMobileMenu() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mobileNav = document.getElementById('mobileNav');
    const mobileNavClose = document.getElementById('mobileNavClose');
    const mobileHeader = document.querySelector('.mobile-header');
    
    if (!mobileMenuToggle || !mobileNav || !mobileHeader) {
      return;
    }
    
    const openMenu = () => {
      mobileNav.classList.add('active');
      document.body.style.overflow = 'hidden';
    };
    
    const closeMenu = () => {
      mobileNav.classList.remove('active');
      document.body.style.overflow = '';
    };
    
    mobileMenuToggle.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (mobileNav.classList.contains('active')) {
        closeMenu();
      } else {
        openMenu();
      }
    });
    
    if (mobileNavClose) {
      mobileNavClose.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        closeMenu();
      });
    }
    
    document.addEventListener('click', (e) => {
      if (!mobileHeader.contains(e.target) && mobileNav.classList.contains('active')) {
        closeMenu();
      }
    });
    
    const mobileDropdownToggles = document.querySelectorAll('.mobile-dropdown-toggle');
    mobileDropdownToggles.forEach(toggle => {
      toggle.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const parent = toggle.closest('.mobile-dropdown-parent');
        if (parent) {
          document.querySelectorAll('.mobile-dropdown-parent').forEach(otherParent => {
            if (otherParent !== parent) {
              otherParent.classList.remove('open');
            }
          });
          parent.classList.toggle('open');
        }
      });
    });
    
    const mobileNavLinks = mobileNav.querySelectorAll('a:not(.mobile-dropdown-toggle)');
    mobileNavLinks.forEach(link => {
      link.addEventListener('click', () => {
        closeMenu();
      });
    });
    
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && mobileNav.classList.contains('active')) {
        closeMenu();
      }
    });
  }

  // ============================================================================
  // HEADER SCROLL
  // ============================================================================

  function initHeaderScroll() {
    const desktopHeader = document.querySelector('header.desktop-header');
    const mobileHeader = document.querySelector('header.mobile-header');
    const headers = [desktopHeader, mobileHeader].filter(Boolean);
    
    if (headers.length === 0) return;

    const firstSection = document.querySelector('section');
    const isHomeHero = !!(firstSection && firstSection.classList.contains('hero') && firstSection.classList.contains('home'));
    if (isHomeHero && desktopHeader) {
      desktopHeader.classList.add('home');
    }

    function handleScroll() {
      const scrollPosition = getScrollPosition();
      const heroSection = document.querySelector('.hero');
      const heroHeight = heroSection ? heroSection.offsetHeight : 0;
      
      headers.forEach(header => {
        let initHeaderClass = '';
        const headerKey = header.classList.contains('desktop-header') ? 'desktopHeader' : 'mobileHeader';
        
        if(typeof window[`init${headerKey}`] === 'undefined') {
          if(header.classList.length > 0) {
            window[`${headerKey}Class`] = Array.from(header.classList).find(cls => 
              cls !== 'desktop-header' && cls !== 'mobile-header'
            ) || '';
          }
        } 

        window[`init${headerKey}`] = true;
        initHeaderClass = window[`${headerKey}Class`] || '';

        header.classList.remove('floating', 'transparent-floating');
        if (scrollPosition > heroHeight * 0.01) {
          header.classList.add('floating');
        } else if (initHeaderClass) {
          header.classList.add(initHeaderClass);
        }
      });
    }

    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
  }

  function initDropdownMenu() {
    const dropdownParents = document.querySelectorAll('header.desktop-header nav ul li:has(.dropdown)');
    
    dropdownParents.forEach(parent => {
      const dropdown = parent.querySelector('.dropdown');
      const link = parent.querySelector('a');
      
      if (!dropdown || !link) return;
    });
  }

  // ============================================================================
  // OUR SERVICES EQUAL HEIGHT (DESKTOP)
  // ============================================================================

  function initOurServicesEqualHeight() {
    const sections = document.querySelectorAll('.our-services');
    if (!sections.length) return;

    const updateHeights = () => {
      const isDesktop = window.innerWidth >= CONFIG.BREAKPOINTS.DESKTOP;

      sections.forEach(section => {
        const services = Array.from(section.querySelectorAll('.service'));
        if (!services.length) return;

        // Always reset before measuring to avoid stale values.
        services.forEach(service => {
          service.style.removeProperty('--service-height');
        });

        if (!isDesktop) {
          return;
        }

        // Force reflow after reset so measurements use natural heights.
        void section.offsetHeight;

        const maxHeight = services.reduce((max, service) => {
          const height = service.offsetHeight;
          return height > max ? height : max;
        }, 0);
        if (!maxHeight) return;

        services.forEach(service => {
          service.style.setProperty('--service-height', `${maxHeight}px`);
        });
      });
    };

    const scheduleUpdate = () => {
      requestAnimationFrame(() => {
        requestAnimationFrame(updateHeights);
      });
    };

    // Initial pass + delayed safety pass for first paint stability.
    scheduleUpdate();
    setTimeout(scheduleUpdate, 300);

    // Re-run after all assets are loaded.
    window.addEventListener('load', scheduleUpdate, { once: true });

    // Re-run when web fonts are ready (common source of first-load layout shift).
    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(scheduleUpdate).catch(() => {
        // no-op
      });
    }

    window.addEventListener('resize', debounce(updateHeights, CONFIG.RESIZE_DEBOUNCE));
  }

  // ============================================================================
  // OUR SERVICES HEADER TEXT SYNC (DESKTOP)
  // ============================================================================

  function initOurServicesHeaderTextSync() {
    const sections = document.querySelectorAll('.our-services');
    if (!sections.length) return;

    const bindHandlers = () => {
      const isDesktop = window.innerWidth >= CONFIG.BREAKPOINTS.DESKTOP;

      sections.forEach(section => {
        const items = Array.from(section.querySelectorAll('.service:not(:first-child)'));
        if (!items.length) return;

        items.forEach(item => {
          const headerSpan = item.querySelector('.header span');
          const h2 = item.querySelector('h2');
          if (!headerSpan || !h2) return;

          if (!headerSpan.dataset.originalText) {
            headerSpan.dataset.originalText = headerSpan.textContent || '';
          }

          const spanPart = (h2.querySelector('span')?.textContent || '').trim();
          const titleText = (h2.cloneNode(true));
          const titleSpan = titleText.querySelector('span');
          if (titleSpan) {
            titleSpan.remove();
          }

          const titlePart = (titleText.textContent || '').trim();
          const combined = `${spanPart} ${titlePart}`.trim();

          const activate = () => {
            if (!isDesktop) return;
            headerSpan.textContent = combined || headerSpan.dataset.originalText;
          };

          const deactivate = () => {
            headerSpan.textContent = headerSpan.dataset.originalText || '';
          };

          item.addEventListener('mouseenter', activate);
          item.addEventListener('mouseleave', deactivate);
          item.addEventListener('focusin', activate);
          item.addEventListener('focusout', deactivate);
        });
      });
    };

    bindHandlers();
    window.addEventListener('resize', debounce(bindHandlers, CONFIG.RESIZE_DEBOUNCE));
  }

  // ============================================================================
  // COMPANY STATS
  // ============================================================================

  function setCompanyStatsContentHeights() {
    const companyStatsSection = document.querySelector('.company-stats-full .company-stats');
    
    if (!companyStatsSection) {
      return;
    }
    
    const contentElements = companyStatsSection.querySelectorAll('.stat .content');
    
    if (contentElements.length === 0) {
      return;
    }
    
    contentElements.forEach(function(content) {
      content.style.height = 'auto';
    });
    
    if (contentElements[0]) {
      contentElements[0].offsetHeight;
    }
    
    let maxHeight = 0;
    contentElements.forEach(function(content) {
      const height = content.offsetHeight;
      if (height > maxHeight && height > 0 && !isNaN(height)) {
        maxHeight = height;
      }
    });
    
    if (maxHeight > 0 && !isNaN(maxHeight)) {
      contentElements.forEach(function(content) {
        content.style.height = maxHeight + 'px';
      });
    }
  }

  function swapCompanyStatsOnMobile() {
    const companyStatsSection = document.querySelector('.company-stats-full .company-stats');
    
    if (!companyStatsSection) {
      return;
    }
    
    const statItems = Array.from(companyStatsSection.querySelectorAll('.stat'));
    const totalStats = statItems.length;
    
    if (totalStats < 3) {
      return;
    }
    
    const isMobile = screen.width < CONFIG.BREAKPOINTS.DESKTOP;
    const hasSwapped = companyStatsSection.hasAttribute('data-swapped');
    
    statItems.forEach(function(item, index) {
      if (!item.hasAttribute('data-original-index')) {
        item.setAttribute('data-original-index', index.toString());
      }
    });
    
    if (isMobile && !hasSwapped) {
      for (let i = 1; i < statItems.length - 1; i += 2) {
        const currentStats = Array.from(companyStatsSection.querySelectorAll('.stat'));
        
        if (i < currentStats.length - 1 && i + 1 < currentStats.length - 1) {
          const current = currentStats[i];
          const next = currentStats[i + 1];
          
          if (current && next) {
            companyStatsSection.insertBefore(next, current);
          }
        }
      }

      companyStatsSection.setAttribute('data-swapped', 'true');
    } else if (!isMobile && hasSwapped) {
      const currentStats = Array.from(companyStatsSection.querySelectorAll('.stat'));
      
      currentStats.sort(function(a, b) {
        const indexA = parseInt(a.getAttribute('data-original-index') || '0', 10);
        const indexB = parseInt(b.getAttribute('data-original-index') || '0', 10);
        return indexA - indexB;
      });
      
      currentStats.forEach(function(item) {
        companyStatsSection.appendChild(item);
      });
      
      companyStatsSection.removeAttribute('data-swapped');
    }
  }

  function initCompanyStatsHeights() {
    const runCalculations = () => {
      swapCompanyStatsOnMobile();
      setCompanyStatsContentHeights();
    };
    
    onDOMReady(() => {
      setTimeout(runCalculations, CONFIG.INIT_DELAY);
    });
    
    window.addEventListener('resize', debounce(runCalculations, CONFIG.RESIZE_DEBOUNCE));
  }

  // ============================================================================
  // BOTTOM SHEET MANAGERS
  // ============================================================================

  class BottomSheetManager extends BaseModal {
    constructor() {
      super({
        overlayId: 'bottomSheetOverlay',
        modalId: 'bottomSheet',
        closeBtnId: 'bottomSheetClose'
      });
      
      // Alias modal as bottomSheet for clarity
      this.bottomSheet = this.modal;
      
      this.titleElement = document.getElementById('bottomSheetTitle');
      this.contentElement = document.getElementById('bottomSheetContent');
      this.isMobile = screen.width < CONFIG.BREAKPOINTS.DESKTOP;
      
      this.init();
    }

    init() {
      super.init();
      
      if (!this.bottomSheet || !this.contentElement) {
        console.warn('Bottom sheet elements not found');
        return;
      }

      if (!this.isMobile) {
        console.log('Not mobile device, bottom sheet disabled');
        return;
      }

      const serviceItems = document.querySelectorAll('.our-services .service:not(:first-child)');
      console.log('Found service items:', serviceItems.length);
      
      if (serviceItems.length === 0) {
        console.warn('No service items found');
        return;
      }

      serviceItems.forEach((item, index) => {
        item.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          console.log('Service item clicked:', index);
          this.openBottomSheet(item);
        });
      });

      window.addEventListener('resize', () => {
        const wasMobile = this.isMobile;
        this.isMobile = screen.width < CONFIG.BREAKPOINTS.DESKTOP;
        
        if (wasMobile && !this.isMobile && this.isOpen) {
          this.close();
        }
      });
    }

    openBottomSheet(serviceItem) {
      if (!this.isMobile || !this.bottomSheet || !this.contentElement) {
        console.warn('Cannot open bottom sheet - missing requirements');
        return;
      }

      const title = serviceItem.querySelector('h2')?.textContent || 'Service Details';
      const description = serviceItem.querySelector('.description') || serviceItem.querySelector('p');
      
      if (this.titleElement) {
        this.titleElement.textContent = title;
      }

      this.contentElement.innerHTML = '';
      
      const contentWrapper = document.createElement('div');
      contentWrapper.className = 'service-content-wrapper';
      
      if (description) {
        const descClone = description.cloneNode(true);
        contentWrapper.appendChild(descClone);
      } else {
        const paragraphs = serviceItem.querySelectorAll('p');
        paragraphs.forEach(p => {
          const pClone = p.cloneNode(true);
          contentWrapper.appendChild(pClone);
        });
      }
      
      this.contentElement.appendChild(contentWrapper);
      this.open();
    }

    onOpen() {
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          if (this.bottomSheet) {
            this.bottomSheet.scrollTop = 0;
            const contentArea = this.bottomSheet.querySelector('.bottom-sheet-content');
            if (contentArea) {
              contentArea.scrollTop = 0;
            }
          }
        });
      });
    }
  }

  class ServiceDetailsBottomSheetManager extends BaseModal {
    constructor() {
      super({
        overlayId: 'serviceDetailsBottomSheetOverlay',
        modalId: 'serviceDetailsBottomSheet',
        closeBtnId: 'serviceDetailsBottomSheetClose'
      });
      
      // Alias modal as bottomSheet for clarity
      this.bottomSheet = this.modal;
      
      this.titleElement = document.getElementById('serviceDetailsBottomSheetTitle');
      this.contentElement = document.getElementById('serviceDetailsBottomSheetContent');
      this.isMobile = screen.width < CONFIG.BREAKPOINTS.DESKTOP;
      
      this.init();
    }

    init() {
      super.init();
      
      if (!this.bottomSheet || !this.contentElement) {
        console.warn('Service Details Bottom sheet elements not found');
        return;
      }

      if (!this.isMobile) {
        console.log('Not mobile device, service details bottom sheet disabled');
        return;
      }

      const serviceItems = document.querySelectorAll('.our-services-details .service-item');
      console.log('Found service details items:', serviceItems.length);
      
      if (serviceItems.length === 0) {
        console.warn('No service details items found');
        return;
      }

      serviceItems.forEach((item, index) => {
        item.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          e.stopImmediatePropagation();
          console.log('Service details item clicked:', index);
          this.openBottomSheet(item);
        });
      });

      window.addEventListener('resize', () => {
        const wasMobile = this.isMobile;
        this.isMobile = screen.width < CONFIG.BREAKPOINTS.DESKTOP;
        
        if (wasMobile && !this.isMobile && this.isOpen) {
          this.close();
        }
      });
    }

    openBottomSheet(serviceItem) {
      console.log('Opening service details bottom sheet', { isMobile: this.isMobile, bottomSheet: this.bottomSheet, contentElement: this.contentElement });
      
      if (!this.isMobile || !this.bottomSheet || !this.contentElement) {
        console.warn('Cannot open service details bottom sheet - missing requirements');
        return;
      }

      const titleElement = serviceItem.querySelector('h2');
      let title = 'Service Details';
      if (titleElement) {
        const titleClone = titleElement.cloneNode(true);
        titleClone.querySelectorAll('span').forEach((span) => span.remove());
        title = titleClone.textContent.trim() || 'Service Details';
      }

      const descriptionContainer = serviceItem.querySelector('.description');
      
      console.log('Service details content found:', { title, hasDescription: !!descriptionContainer });
      
      if (this.titleElement) {
        this.titleElement.textContent = title;
      }

      this.contentElement.innerHTML = '';
      
      const contentWrapper = document.createElement('div');
      contentWrapper.className = 'service-content-wrapper';
      
      if (descriptionContainer) {
        // Copy full description HTML into a neutral wrapper so hidden source styles do not suppress content.
        const descContent = document.createElement('div');
        descContent.className = 'service-description';
        descContent.innerHTML = descriptionContainer.innerHTML.trim();

        // Convert any description heading to paragraph for consistent popup content flow.
        descContent.querySelectorAll('h2').forEach((h2) => {
          const p = document.createElement('p');
          p.className = 'highlight';
          p.innerHTML = h2.innerHTML;
          h2.replaceWith(p);
        });

        // Add service title to content (without number span) as the section heading.
        const serviceTitle = document.createElement('h2');
        serviceTitle.textContent = title;
        descContent.prepend(serviceTitle);

        contentWrapper.appendChild(descContent);
      }
      
      this.contentElement.appendChild(contentWrapper);
      this.open();
    }

    onOpen() {
      console.log('Service details bottom sheet opened');
      
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          if (this.bottomSheet) {   
            this.bottomSheet.scrollTop = 0;
            const contentArea = this.bottomSheet.querySelector('.bottom-sheet-content');
            if (contentArea) {
              contentArea.scrollTop = 0;
            }
          }
        });
      });
    }
  }

  // ============================================================================
  // TESTIMONIAL MODAL
  // ============================================================================

  class TestimonialModal extends BaseModal {
    constructor() {
      super({
        overlayId: 'testimonialModalOverlay',
        modalId: 'testimonialModal',
        closeBtnId: 'testimonialModalClose'
      });
      
      this.textElement = document.getElementById('testimonialModalText');
      this.thumbnailElement = document.getElementById('testimonialModalThumbnail');
      this.nameElement = document.getElementById('testimonialModalName');
      
      this.init();
    }

    init() {
      super.init();
      
      if (!this.modal || !this.textElement) {
        console.warn('Testimonial modal elements not found');
        return;
      }

      const readMoreLinks = document.querySelectorAll('.testimonials-slider .item .read-more');
      
      readMoreLinks.forEach((link) => {
        link.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          const item = link.closest('.item');
          if (item) {
            this.openModal(item);
          }
        });
      });
    }

    openModal(item) {
      if (!this.modal || !this.textElement) {
        return;
      }

      const popupContent = item.querySelector('.popup-content');
      const thumbnail = item.querySelector('.footer .thumbnail img');
      const name = item.querySelector('.footer .name h3');
      const title = item.querySelector('.footer .name p');
      
      if (this.textElement) {
        this.textElement.innerHTML = '';
        
        if (popupContent) {
          const paragraphs = popupContent.querySelectorAll('p');
          paragraphs.forEach((p) => {
            const clonedP = p.cloneNode(true);
            this.textElement.appendChild(clonedP);
          });
        } else {
          const content = item.querySelector('.content p');
          if (content) {
            const p = document.createElement('p');
            p.textContent = content.textContent;
            this.textElement.appendChild(p);
          }
        }
      }

      if (this.thumbnailElement && thumbnail) {
        this.thumbnailElement.innerHTML = '';
        const img = thumbnail.cloneNode(true);
        this.thumbnailElement.appendChild(img);
      }

      if (this.nameElement) {
        this.nameElement.innerHTML = '';
        if (name) {
          const h3 = document.createElement('h3');
          h3.textContent = name.textContent;
          this.nameElement.appendChild(h3);
        }

        if (title) {
          const p = document.createElement('p');
          p.textContent = title.textContent;
          this.nameElement.appendChild(p);
        }
      }

      this.open();
    }

    onOpen() {
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          if (this.modal) {
            const bodyArea = this.modal.querySelector('.testimonial-modal-body');
            if (bodyArea) {
              bodyArea.scrollTop = 0;
              console.log('Modal body scrolled to top, scrollTop:', bodyArea.scrollTop);
            } else {
              console.warn('Modal body element not found');
            }
          }
        });
      });
    }
  }

  // ============================================================================
  // SERVICE DETAILS MANAGER
  // ============================================================================

  class ServiceDetailsManager {
    constructor() {
      this.serviceItems = document.querySelectorAll('.our-services-details .service-item');
      this.rightColumn = document.querySelector('.our-services-details .right-column');
      this.activeService = null;
      this.isMobile = window.innerWidth < CONFIG.BREAKPOINTS.DESKTOP;
      this.autoplayInterval = null;
      this.currentIndex = 0;
      this.autoplaySpeed = 3000; // 3 seconds per item
      this.isPaused = false;
      this.userHeldAutoplay = false;
      
      this.init();
    }

    init() {
      if (this.serviceItems.length === 0) {
        return;
      }

      // Check if we're on desktop (lg breakpoint)
      this.checkAndSetActive();

      this.serviceItems.forEach((item) => {
        item.addEventListener('click', (e) => {
          e.preventDefault();
          this.setActiveService(item);

          // Desktop click means user selected an item intentionally;
          // keep autoplay held so the selected item stays active.
          if (!this.isMobile) {
            this.userHeldAutoplay = true;
            this.stopAutoplay();
            this.isPaused = true;
          }
        });

        // Pause on hover
        item.addEventListener('mouseenter', () => {
          this.pauseAutoplay();
        });

        // Resume on mouse leave
        item.addEventListener('mouseleave', () => {
          this.resumeAutoplay();
        });
      });

      // Start autoplay on desktop
      if (!this.isMobile) {
        this.startAutoplay();
      }

      window.addEventListener('resize', () => {
        const wasMobile = this.isMobile;
        this.isMobile = window.innerWidth < CONFIG.BREAKPOINTS.DESKTOP;
        
        if (!wasMobile && this.isMobile) {
          // Remove active class on mobile and stop autoplay
          this.stopAutoplay();
          this.userHeldAutoplay = false;
          this.serviceItems.forEach((item) => {
            item.classList.remove('active');
          });
        } else if (wasMobile && !this.isMobile) {
          // Add active class to first item when switching to desktop and start autoplay
          this.userHeldAutoplay = false;
          this.isPaused = false;
          this.checkAndSetActive();
          this.startAutoplay();
        }
      });
    }

    startAutoplay() {
      if (this.isMobile || this.serviceItems.length === 0) {
        return;
      }

      this.stopAutoplay(); // Clear any existing interval
      this.isPaused = false;

      // Find current active index
      const activeItem = Array.from(this.serviceItems).find(item => item.classList.contains('active'));
      this.currentIndex = activeItem ? Array.from(this.serviceItems).indexOf(activeItem) : 0;

      this.autoplayInterval = setInterval(() => {
        if (!this.isPaused && !this.isMobile) {
          this.currentIndex = (this.currentIndex + 1) % this.serviceItems.length;
          this.setActiveService(this.serviceItems[this.currentIndex]);
        }
      }, this.autoplaySpeed);

      debugLog('▶️', 'AUTOPLAY', 'Service items autoplay started');
    }

    pauseAutoplay() {
      this.isPaused = true;
    }

    resumeAutoplay() {
      if (!this.isMobile && this.autoplayInterval && !this.userHeldAutoplay) {
        this.isPaused = false;
      }
    }

    stopAutoplay() {
      if (this.autoplayInterval) {
        clearInterval(this.autoplayInterval);
        this.autoplayInterval = null;
        this.isPaused = false;
        debugLog('⏹️', 'AUTOPLAY', 'Service items autoplay stopped');
      }
    }

    checkAndSetActive() {
      // Only set active on desktop (lg breakpoint and above)
      if (window.innerWidth >= CONFIG.BREAKPOINTS.DESKTOP && this.serviceItems.length > 0) {
        // Check if no item is currently active
        const hasActive = Array.from(this.serviceItems).some(item => item.classList.contains('active'));
        if (!hasActive && this.rightColumn) {
          // Set first item as active
          this.setActiveService(this.serviceItems[0]);
        } else if (!hasActive) {
          // If right column doesn't exist yet, just add active class to first item
          this.serviceItems[0].classList.add('active');
        }
      }
    }

    setActiveService(serviceItem) {
      this.serviceItems.forEach((item) => {
        item.classList.remove('active');
      });

      serviceItem.classList.add('active');
      this.activeService = serviceItem;
      
      // Update current index for autoplay
      this.currentIndex = Array.from(this.serviceItems).indexOf(serviceItem);

      if (!this.isMobile) {
        this.populateServiceDetails(serviceItem);
      }
    }

    populateServiceDetails(serviceItem) {
      if (!this.rightColumn) {
        return;
      }

      const { titleText, descriptionHTML } = this.extractServiceData(serviceItem);

      const serviceDetailsHTML = `
        <div class="service-details">
          ${descriptionHTML}
        </div>
      `;

      this.rightColumn.innerHTML = serviceDetailsHTML;
    }

    extractServiceData(serviceItem) {
      const titleElement = serviceItem.querySelector('h2');
      const titleText = titleElement ? titleElement.textContent.replace(/^\d+\s*/, '').trim() : '';
      
      // Get description HTML content (includes all content: h2, p, features list, etc.)
      const descriptionContainer = serviceItem.querySelector('.description');
      let descriptionHTML = '';
      if (descriptionContainer) {
        // Get the innerHTML directly - includes all HTML content
        descriptionHTML = descriptionContainer.innerHTML.trim();
      }

      return { titleText, descriptionHTML };
    }
  }

  // ============================================================================
  // BLOG HEIGHTS
  // ============================================================================

  function setBlogItemHeights() {
    const itemsContainers = document.querySelectorAll('.blogs .items');
    
    itemsContainers.forEach(function(container) {
      const items = container.querySelectorAll('.item');
      
      if (items.length === 0) {
        return;
      }
      
      items.forEach(function(item) {
        item.style.height = 'auto';
      });
      
      container.offsetHeight;
      
      let maxHeight = 0;
      items.forEach(function(item) {
        const height = item.offsetHeight;
        if (height > maxHeight) {
          maxHeight = height;
        }
      });
      
      if (maxHeight > 0) {
        items.forEach(function(item) {
          item.style.height = maxHeight + 'px';
        });
      }
    });
  }

  function setBlogHeaderHeights() {
    const headers = document.querySelectorAll('.blogs .main .header');
    
    if (headers.length === 0) {
      return;
    }
    
    headers.forEach(function(header) {
      header.style.height = 'auto';
    });
    
    if (headers[0]) {
      headers[0].offsetParent && headers[0].offsetParent.offsetHeight;
    }
    
    let maxHeight = 0;
    headers.forEach(function(header) {
      const height = header.offsetHeight;
      if (height > maxHeight) {
        maxHeight = height;
      }
    });
    
    if (maxHeight > 0) {
      headers.forEach(function(header) {
        header.style.height = maxHeight + 'px';
      });
    }
  }

  function setBlogMainHeights() {
    const mainElements = document.querySelectorAll('.blogs .col .main');
    
    if (mainElements.length === 0) {
      return;
    }
    
    mainElements.forEach(function(main) {
      main.style.height = 'auto';
    });
    
    const windowWidth = screen.width;
    if (windowWidth < CONFIG.BREAKPOINTS.DESKTOP) {
      return;
    }
    
    if (mainElements[0]) {
      mainElements[0].offsetParent && mainElements[0].offsetParent.offsetHeight;
    }
    
    let maxHeight = 0;
    mainElements.forEach(function(main) {
      const height = main.offsetHeight;
      if (height > maxHeight) {
        maxHeight = height;
      }
    });
    
    if (maxHeight > 0) {
      mainElements.forEach(function(main) {
        main.style.height = maxHeight + 'px';
      });
    }
  }

  function setBlogContentH2Heights() {
    const h2Elements = document.querySelectorAll('.blogs .col .content h2');
    
    if (h2Elements.length === 0) {
      return;
    }
    
    h2Elements.forEach(function(h2) {
      h2.style.height = 'auto';
      h2.style.minHeight = 'auto';
    });
    
    if (h2Elements[0]) {
      h2Elements[0].offsetParent && h2Elements[0].offsetParent.offsetHeight;
    }
    
    let maxHeight = 0;
    h2Elements.forEach(function(h2) {
      const height = h2.offsetHeight;
      if (height > maxHeight) {
        maxHeight = height;
      }
    });
    
    if (maxHeight > 0) {
      h2Elements.forEach(function(h2) {
        h2.style.height = maxHeight + 'px';
      });
    }
  }

  function initBlogHeights() {
    const itemImages = document.querySelectorAll('.blogs .item img');
    const mainImages = document.querySelectorAll('.blogs .main .content img');
    const allImages = Array.from(itemImages).concat(Array.from(mainImages));
    let imagesLoaded = 0;
    
    function runHeightCalculations() {
      setBlogMainHeights();
      setBlogHeaderHeights();
      setBlogContentH2Heights();
      setBlogItemHeights();
    }
    
    if (allImages.length === 0) {
      setTimeout(runHeightCalculations, CONFIG.INIT_DELAY);
      return;
    }
    
    allImages.forEach(function(img) {
      if (img.complete) {
        imagesLoaded++;
        if (imagesLoaded === allImages.length) {
          setTimeout(runHeightCalculations, CONFIG.INIT_DELAY);
        }
      } else {
        img.addEventListener('load', function() {
          imagesLoaded++;
          if (imagesLoaded === allImages.length) {
            setTimeout(runHeightCalculations, CONFIG.INIT_DELAY);
          }
        });
      }
    });
    
    setTimeout(runHeightCalculations, 500);
  }

  // ============================================================================
  // TIMELINE ITEM HEIGHTS (equalize within each .timeline-items)
  // ============================================================================

  function equalizeTimelineHeights() {
    const containers = document.querySelectorAll('.timeline-items');
    containers.forEach(function(container) {
      const items = container.querySelectorAll('.timeline-item');
      if (!items.length) return;

      // Reset to auto to measure natural heights
      items.forEach(function(el) {
        el.style.height = '';
      });
      void container.offsetHeight; // force reflow
      const maxHeight = Math.max.apply(null, Array.from(items).map(function(el) {
        return el.offsetHeight;
      }));
      items.forEach(function(el) {
        el.style.height = maxHeight + 'px';
      });
    });
  }

  // ============================================================================
  // FOOTER OFFICE HEIGHTS (equalize across columns on lg)
  // ============================================================================

  function equalizeOfficeHeights() {
    const wrappers = document.querySelectorAll('footer .offices-wrapper');
    if (!wrappers.length) return;

    const isLg = window.matchMedia('(min-width: 1024px)').matches;

    wrappers.forEach(function(wrapper) {
      const officeCols = wrapper.querySelectorAll('.offices');
      const offices = wrapper.querySelectorAll('.office');
      if (!offices.length) return;

      // Always reset first for both breakpoints.
      officeCols.forEach(function(col) {
        col.style.height = '';
      });
      offices.forEach(function(el) {
        el.style.height = '';
      });

      if (!isLg) return;
      if (wrapper.offsetParent === null) return; // skip hidden wrapper

      // Reset to auto to measure natural heights on visible desktop wrapper.
      offices.forEach(function(el) {
        el.style.height = 'auto';
      });

      void wrapper.offsetHeight; // force reflow
      const maxHeight = Math.max.apply(null, Array.from(offices).map(function(el) {
        return el.offsetHeight;
      }));

      offices.forEach(function(el) {
        el.style.height = maxHeight + 'px';
      });

      // Keep the 3 footer office columns aligned to the same column height.
      let maxColHeight = 0;
      officeCols.forEach(function(col) {
        const h = col.offsetHeight;
        if (h > maxColHeight) maxColHeight = h;
      });
      if (maxColHeight > 0) {
        officeCols.forEach(function(col) {
          col.style.height = maxColHeight + 'px';
        });
      }
    });
  }

  // ============================================================================
  // AI IMPACT ACCORDION (2x2 grid, one item expanded at a time)
  // ============================================================================

  let aiImpactAutoplayInterval = null;
  let aiImpactUserHeld = false;
  const AI_IMPACT_AUTOPLAY_MS = 5000;

  function startAiImpactAutoplay() {
    const grid = document.querySelector('.ai-impact .ai-impact-grid');
    if (!grid) return;
    const items = grid.querySelectorAll('.ai-impact-item');
    const count = items.length;
    if (!count || aiImpactAutoplayInterval) return;
    if (!window.matchMedia('(max-width: 1023px)').matches) return;

    aiImpactAutoplayInterval = setInterval(function() {
      const g = document.querySelector('.ai-impact .ai-impact-grid');
      if (!g) return;
      const list = g.querySelectorAll('.ai-impact-item');
      let current = -1;
      list.forEach(function(it, i) {
        if (it.classList.contains('active')) current = i;
      });
      const next = (current + 1) % list.length;
      list.forEach(function(other, i) {
        other.classList.remove('active');
        const btn = other.querySelector('.ai-impact-item-toggle');
        if (btn) btn.setAttribute('aria-expanded', i === next ? 'true' : 'false');
      });
      list[next].classList.add('active');
    }, AI_IMPACT_AUTOPLAY_MS);
  }

  function stopAiImpactAutoplay() {
    if (aiImpactAutoplayInterval) {
      clearInterval(aiImpactAutoplayInterval);
      aiImpactAutoplayInterval = null;
    }
  }

  function handleAiImpactResize() {
    const isMobile = window.matchMedia('(max-width: 1023px)').matches;
    if (isMobile) {
      if (!aiImpactUserHeld) startAiImpactAutoplay();
    } else {
      stopAiImpactAutoplay();
      aiImpactUserHeld = false;
    }
  }

  function equalizeAiImpactItemHeights() {
    const grid = document.querySelector('.ai-impact .ai-impact-grid');
    if (!grid) return false;

    const items = grid.querySelectorAll('.ai-impact-item');
    if (!items.length) return false;

    // Remember which item was active
    let activeIndex = -1;
    items.forEach(function(item, i) {
      if (item.classList.contains('active')) activeIndex = i;
      item.style.height = '';
    });
    void grid.offsetHeight;

    // Expand all items to measure full height of each
    items.forEach(function(item) {
      item.classList.add('active');
    });
    void grid.offsetHeight;

    const maxHeight = Math.max.apply(null, Array.from(items).map(function(el) {
      return el.offsetHeight;
    }));

    // If layout is not ready yet (e.g. first load before fonts/styles settle),
    // avoid locking cards to 0px and let a later pass recompute.
    if (!Number.isFinite(maxHeight) || maxHeight <= 0) {
      items.forEach(function(item) {
        item.style.height = '';
      });
      return false;
    }

    // Restore active state (only the one that was active, or default to index 1)
    items.forEach(function(item, i) {
      item.classList.remove('active');
      if (i === (activeIndex >= 0 ? activeIndex : 1)) {
        item.classList.add('active');
        const btn = item.querySelector('.ai-impact-item-toggle');
        if (btn) btn.setAttribute('aria-expanded', 'true');
      } else {
        const btn = item.querySelector('.ai-impact-item-toggle');
        if (btn) btn.setAttribute('aria-expanded', 'false');
      }

      item.style.height = maxHeight + 'px';
    });

    return true;
  }

  function initAiImpactAccordion() {
    const grid = document.querySelector('.ai-impact .ai-impact-grid');
    if (!grid) return;

    const items = grid.querySelectorAll('.ai-impact-item');
    const itemCount = items.length;
    if (!itemCount) return;

    function activateItemByIndex(index) {
      const idx = Math.max(0, Math.min(index, itemCount - 1));
      items.forEach(function(other, i) {
        other.classList.remove('active');
        const btn = other.querySelector('.ai-impact-item-toggle');
        if (btn) btn.setAttribute('aria-expanded', i === idx ? 'true' : 'false');
      });
      items[idx].classList.add('active');
    }

    items.forEach(function(item, i) {
      const toggle = item.querySelector('.ai-impact-item-toggle');

      function openItem() {
        activateItemByIndex(i);
      }

      function closeItem() {
        item.classList.remove('active');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
      }

      function toggleItem() {
        const isActive = item.classList.contains('active');
        if (isActive) {
          closeItem();
        } else {
          openItem();
        }
      }

      // Whole box (ai-impact-item): click to activate / toggle
      item.addEventListener('click', function(e) {
        if (e.target.closest('.ai-impact-item-toggle')) return;
        if (window.matchMedia('(max-width: 1023px)').matches) {
          aiImpactUserHeld = true;
          stopAiImpactAutoplay();
          openItem();
        } else {
          toggleItem();
        }
      });

      if (toggle) {
        toggle.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          if (window.matchMedia('(max-width: 1023px)').matches) {
            aiImpactUserHeld = true;
            stopAiImpactAutoplay();
            openItem();
          } else {
            toggleItem();
          }
        });
      }

      // Desktop: hover to activate (same as click)
      item.addEventListener('mouseenter', function() {
        if (window.matchMedia('(min-width: 1024px)').matches) {
          openItem();
        }
      });
    });

    const scheduleEqualize = function() {
      requestAnimationFrame(function() {
        requestAnimationFrame(function() {
          const measured = equalizeAiImpactItemHeights();
          // Retry once quickly if first measurement still isn't stable.
          if (!measured) {
            setTimeout(function() {
              equalizeAiImpactItemHeights();
            }, 200);
          }
        });
      });
    };

    scheduleEqualize();
    setTimeout(scheduleEqualize, 300);
    window.addEventListener('load', scheduleEqualize, { once: true });
    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(scheduleEqualize).catch(function() {
        // no-op
      });
    }

    // Mobile (< lg): start autoplay (10s per item, wrap to first)
    if (window.matchMedia('(max-width: 1023px)').matches && !aiImpactUserHeld) {
      startAiImpactAutoplay();
    }
  }

  // ============================================================================
  // FOOTER ACCORDION
  // ============================================================================

  function initFooterAccordions() {
    const accordionToggles = document.querySelectorAll('footer .accordion-toggle');
    
    accordionToggles.forEach(function(toggle) {
      toggle.addEventListener('click', function() {
        const isExpanded = this.getAttribute('aria-expanded') === 'true';
        const contentId = this.getAttribute('aria-controls');
        const content = document.getElementById(contentId);
        
        this.setAttribute('aria-expanded', !isExpanded);
        
        if (content) {
          if (isExpanded) {
            content.classList.remove('expanded');
          } else {
            content.classList.add('expanded');
          }
        }
      });
    });
  }

  // ============================================================================
  // INITIALIZATION
  // ============================================================================

  // Store WhyUsSlider instance globally for resize handling
  let whyUsSliderInstance = null;

  function init() {
    initMobileMenu();
    initHeaderScroll();
    initDropdownMenu();
    initOurServicesEqualHeight();
    initOurServicesHeaderTextSync();

    const scrollAnimations = new ScrollAnimationManager();
    scrollAnimations.init();

    const numberCounters = new NumberCounterManager();
    numberCounters.init();

    const testimonialLogosSlider = new TestimonialLogosSlider();
    testimonialLogosSlider.init();

    const testimonialsSlider = new TestimonialsSlider();
    testimonialsSlider.init();

    whyUsSliderInstance = new WhyUsSlider();
    whyUsSliderInstance.init();
  }

  function initializeWhenReady() {
    onDOMReady(init);
    
    window.addEventListener('load', () => {
      if (typeof $ !== 'undefined' && typeof $.fn.slick !== 'undefined') {
        const $logosContainer = $('.testimonials .logos');
        if ($logosContainer.length > 0 && !$logosContainer.hasClass('slick-initialized')) {
          const testimonialLogosSlider = new TestimonialLogosSlider();
          testimonialLogosSlider.init();
        }
        
        const $testimonialsContainer = $('.testimonials-slider');
        if ($testimonialsContainer.length > 0 && !$testimonialsContainer.hasClass('slick-initialized')) {
          const testimonialsSlider = new TestimonialsSlider();
          testimonialsSlider.init();
        }

        // Initialize Why Us slider if on mobile
        if (!whyUsSliderInstance) {
          whyUsSliderInstance = new WhyUsSlider();
        }

        whyUsSliderInstance.init();
      }
      
      setTimeout(() => {
        swapCompanyStatsOnMobile();
        setCompanyStatsContentHeights();
      }, CONFIG.INIT_DELAY);
    });
  }

  // ============================================================================
  // WHY US GLOBE 3D ROTATION (object-position on img hover)
  // ============================================================================

  function initWhyUsGlobeRotation() {
    const globe = document.querySelector('.why-us .why-us-globe');
    const globeImgs = globe ? Array.from(globe.querySelectorAll('.why-us-globe__img')) : [];
    const whyUsItems = document.querySelectorAll('.why-us-item');
    if (!globe || globeImgs.length === 0 || !whyUsItems.length) return;

    const leftItemIndices = [0, 2, 4, 5]; // items 1, 3, 5, 6
    const rightItemIndices = [1, 3, 6];   // items 2, 4, 7

    function getGlobePositions() {
      const w = globe.offsetWidth;
      // Derive img width from container (globe can be hidden so getComputedStyle may be 0)
      var imgW = 0;
      for (var i = 0; i < globeImgs.length; i += 1) {
        var computedWidth = parseFloat(getComputedStyle(globeImgs[i]).width) || 0;
        if (computedWidth > 0) {
          imgW = computedWidth;
          break;
        }
      }

      if (!imgW && w) {
        if (w >= 600) imgW = 1200;
        else if (w >= 500) imgW = 1000;
        else imgW = 610;
      }

      if (!imgW) imgW = 500;
      const maxPos = Math.max(0, imgW - w);
      // Default position matches CSS: -910px -115px (base/xl), -900px -100px (2xl)
      var defaultX = -475;
      var defaultY = 0;
      if (w > 500) {
        defaultX = -560;
        defaultY = 0;
      }

      // Left/Right: 30px offset from default (e.g. Left = -910 + 30 = -880, Right = -910 - 30 = -940)
      var offsetX = 30;

      return {
        default: { x: defaultX, y: defaultY },
        left: { x: defaultX - offsetX, y: defaultY },
        right: { x: defaultX + offsetX, y: defaultY }
      };
    }

    function setGlobePosition(pos) {
      globeImgs.forEach(function(img) {
        img.style.objectPosition = pos.x + 'px ' + pos.y + 'px';
      });
    }

    whyUsItems.forEach(function(item, index) {
      item.addEventListener('mouseenter', function() {
        if (window.innerWidth < CONFIG.BREAKPOINTS.LARGE) return;
        var pos = getGlobePositions();
        if (leftItemIndices.indexOf(index) !== -1) {
          setGlobePosition(pos.left);
        } else if (rightItemIndices.indexOf(index) !== -1) {
          setGlobePosition(pos.right);
        }
      });
      item.addEventListener('mouseleave', function() {
        if (window.innerWidth < CONFIG.BREAKPOINTS.LARGE) return;
        var pos = getGlobePositions();
        setGlobePosition(pos.default);
      });
    });

    window.addEventListener('resize', debounce(function() {
      if (window.innerWidth < CONFIG.BREAKPOINTS.LARGE) return;
      var pos = getGlobePositions();
      setGlobePosition(pos.default);
    }, CONFIG.RESIZE_DEBOUNCE));
  }

  // Initialize all components
  initializeWhenReady();
  initCompanyStatsHeights();
  initBlogHeights();

  onDOMReady(function() {
    initWhyUsGlobeRotation();
  });

  // Global Presence card carousel
  function initGlobalPresenceCard() {
    const section = document.querySelector('.global-presence');
    if (!section) return;
    const cardBody = section.querySelector('.presence-card-body');
    const presenceCard = section.querySelector('.presence-card');
    const cardImage = presenceCard ? presenceCard.querySelector('.presence-card-image img') : null;
    const cardTitle = section.querySelector('.presence-card-title');
    const cardAddress = section.querySelector('.presence-card-address');
    const places = section.querySelectorAll('.places-data .place');
    const markers = section.querySelectorAll('.location-marker');
    const prevBtn = section.querySelector('.presence-card-prev');
    const nextBtn = section.querySelector('.presence-card-next');
    const markerLinks = section.querySelectorAll('.location-marker .label a');
    if (!cardTitle || !cardAddress || !places.length) return;

    // Measure max height across all places and apply to presence-card-body so card height never jumps
    function setPresenceCardHeightFromPlaces() {
      const clone = cardBody.cloneNode(true);
      clone.setAttribute('aria-hidden', 'true');
      clone.style.position = 'absolute';
      clone.style.left = '-9999px';
      clone.style.top = '0';
      clone.style.width = cardBody.offsetWidth + 'px';
      clone.style.visibility = 'hidden';
      clone.style.pointerEvents = 'none';
      presenceCard.appendChild(clone);

      const cloneTitle = clone.querySelector('.presence-card-title');
      const cloneAddress = clone.querySelector('.presence-card-address');
      let maxHeight = 0;

      places.forEach((place) => {
        const h3 = place.querySelector('h3');
        const addressP = place.querySelector('p:not(.place-description)');
        if (cloneTitle) cloneTitle.textContent = h3 ? h3.textContent : '';
        if (cloneAddress) cloneAddress.innerHTML = addressP ? addressP.innerHTML : '';
        maxHeight = Math.max(maxHeight, clone.scrollHeight);
      });

      clone.remove();
      if (maxHeight > 0) {
        cardBody.style.height = maxHeight + 'px';
        cardBody.style.minHeight = maxHeight + 'px';
      }
    }

    setPresenceCardHeightFromPlaces();
    let resizeTimeout;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(setPresenceCardHeightFromPlaces, 150);
    });

    let currentIndex = 2;
    const SLIDE_DURATION = 300;
    const AUTOPLAY_INTERVAL = 4000;
    const AUTOPLAY_RESUME_DELAY = 5000;
    let autoplayTimer = null;
    let autoplayResumeTimer = null;

    function startAutoplay() {
      stopAutoplay();
      autoplayTimer = setInterval(() => {
        showPlace(currentIndex + 1, 'next');
      }, AUTOPLAY_INTERVAL);
    }

    function stopAutoplay() {
      if (autoplayTimer) {
        clearInterval(autoplayTimer);
        autoplayTimer = null;
      }

      if (autoplayResumeTimer) {
        clearTimeout(autoplayResumeTimer);
        autoplayResumeTimer = null;
      }
    }

    function pauseAutoplayThenResume() {
      stopAutoplay();
      autoplayResumeTimer = setTimeout(startAutoplay, AUTOPLAY_RESUME_DELAY);
    }

    function showPlace(index, direction) {
      const nextIndex = (index + places.length) % places.length;
      const place = places[nextIndex];
      if (!place) return;

      const isChanging = nextIndex !== currentIndex;
      direction = direction || (nextIndex > currentIndex ? 'next' : 'prev');

      if (isChanging && cardBody) {
        cardBody.classList.remove('slide-out-left', 'slide-out-right');
        cardBody.classList.add(direction === 'next' ? 'slide-out-left' : 'slide-out-right');
      }

      function updateContent() {
        currentIndex = nextIndex;
        const placeImg = place.querySelector('.global-presence-place-image');
        const h3 = place.querySelector('h3');
        const addressP = place.querySelector('p:not(.place-description)');
        if (cardImage && placeImg) {
          var newSrc = placeImg.getAttribute('src');
          if (newSrc) {
            cardImage.setAttribute('src', newSrc);
          }

          cardImage.setAttribute('alt', placeImg.getAttribute('alt') || '');
        }

        cardTitle.textContent = h3 ? h3.textContent : '';
        cardAddress.innerHTML = addressP ? addressP.innerHTML : '';

        if (cardBody) {
          cardBody.classList.remove('slide-out-left', 'slide-out-right');
        }

        markers.forEach((m, i) => {
          m.classList.toggle('active', i === currentIndex);
        });
      }

      if (isChanging && cardBody) {
        setTimeout(updateContent, SLIDE_DURATION);
      } else {
        updateContent();
      }
    }

    prevBtn && prevBtn.addEventListener('click', () => {
      showPlace(currentIndex - 1, 'prev');
      pauseAutoplayThenResume();
    });
    nextBtn && nextBtn.addEventListener('click', () => {
      showPlace(currentIndex + 1, 'next');
      pauseAutoplayThenResume();
    });

    function goToPlaceByIndex(index) {
      if (Number.isNaN(index) || index < 0 || index >= places.length) return;
      showPlace(index, index > currentIndex ? 'next' : 'prev');
      pauseAutoplayThenResume();
    }

    markerLinks.forEach((link) => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const index = parseInt(link.getAttribute('data-index'), 10);
        goToPlaceByIndex(index);
      });
      link.addEventListener('mouseenter', () => {
        const index = parseInt(link.getAttribute('data-index'), 10);
        goToPlaceByIndex(index);
      });
    });

    markers.forEach((marker, index) => {
      marker.addEventListener('click', (e) => {
        if (e.target.closest('.label a')) return;
        goToPlaceByIndex(index);
      });
      marker.addEventListener('mouseenter', (e) => {
        if (e.target.closest('.label a')) return;
        goToPlaceByIndex(index);
      });
    });

    showPlace(currentIndex);
    startAutoplay();
  }

  onDOMReady(() => {
    initGlobalPresenceCard();
  });
  
  onDOMReady(() => {
    initFooterAccordions();
    equalizeOfficeHeights();
    equalizeTimelineHeights();
    initAiImpactAccordion();
  });

  // Initialize modals and bottom sheets
  onDOMReady(() => {
    try {
      // Check if elements exist before initializing
      if (document.getElementById('bottomSheet') && document.getElementById('bottomSheetContent')) {
        new BottomSheetManager();
      } else {
        console.warn('Bottom sheet elements not found in DOM, skipping initialization');
      }
      
      if (document.getElementById('serviceDetailsBottomSheet') && document.getElementById('serviceDetailsBottomSheetContent')) {
        new ServiceDetailsBottomSheetManager();
      } else {
        console.warn('Service Details Bottom sheet elements not found in DOM, skipping initialization');
      }
      
      if (document.getElementById('testimonialModal') && document.getElementById('testimonialModalText')) {
        new TestimonialModal();
      } else {
        console.warn('Testimonial modal elements not found in DOM, skipping initialization');
      }
      
      // Initialize ServiceDetailsManager
      new ServiceDetailsManager();
    } catch (error) {
      console.error('Error initializing components:', error);
    }
  });

  // Recalculate blog heights on resize
  window.addEventListener('resize', debounce(() => {
    setBlogMainHeights();
    setBlogHeaderHeights();
    setBlogContentH2Heights();
    setBlogItemHeights();
    equalizeOfficeHeights();
    equalizeTimelineHeights();
    equalizeAiImpactItemHeights();
    handleAiImpactResize();

    // Handle Why Us slider resize
    if (whyUsSliderInstance) {
      whyUsSliderInstance.handleResize();
    }
  }, CONFIG.RESIZE_DEBOUNCE));
})();
