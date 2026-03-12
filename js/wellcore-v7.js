/* ============================================================
   WellCore Fitness v7 — Premium Effects Engine 2026
   Toast, confetti, magnetic buttons, custom cursor, kinetic
   typography, canvas particles, countUp, scroll progress,
   accordion, navbar, smooth scroll, and more.
   Pure vanilla JS — no frameworks, no imports.
   ============================================================ */

(function () {
  'use strict';

  /* ── Shared State ── */
  var rafIds = [];
  var cursorDot = null;
  var cursorCircle = null;
  var lenisInstance = null;
  var particleSystems = [];
  var resizeTimer = null;
  var pointerFine = window.matchMedia && window.matchMedia('(pointer: fine)').matches;

  /* ── Utility: requestAnimationFrame polyfill ── */
  var rAF = window.requestAnimationFrame || window.webkitRequestAnimationFrame ||
            window.mozRequestAnimationFrame || function (cb) { return setTimeout(cb, 16); };
  var cAF = window.cancelAnimationFrame || window.webkitCancelAnimationFrame ||
            window.mozCancelAnimationFrame || function (id) { clearTimeout(id); };

  /* ── Utility: Linear interpolation ── */
  function lerp(a, b, n) {
    return a + (b - a) * n;
  }

  /* ── Utility: Debounce ── */
  function debounce(fn, delay) {
    var timer = null;
    return function () {
      var ctx = this;
      var args = arguments;
      if (timer) clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
    };
  }


  /* ================================================================
     1. TOAST NOTIFICATION SYSTEM
     ================================================================ */

  var toastContainer = null;
  var TOAST_SYMBOLS = {
    success: '\u2713',  // ✓
    error:   '\u2715',  // ✕
    info:    '\u2139',  // ℹ
    warning: '\u26A0'   // ⚠
  };

  function toastShow(message, type, duration) {
    type = type || 'info';
    duration = duration || 4000;

    /* Create container on first call */
    if (!toastContainer) {
      toastContainer = document.createElement('div');
      toastContainer.className = 'v7-toast-container';
      toastContainer.setAttribute('aria-live', 'polite');
      document.body.appendChild(toastContainer);
    }

    var toast = document.createElement('div');
    toast.className = 'v7-toast v7-toast--' + type;

    var icon = document.createElement('span');
    icon.className = 'v7-toast__icon';
    icon.textContent = TOAST_SYMBOLS[type] || TOAST_SYMBOLS.info;
    toast.appendChild(icon);

    var msg = document.createElement('span');
    msg.className = 'v7-toast__message';
    msg.textContent = message;
    toast.appendChild(msg);

    toastContainer.appendChild(toast);

    /* Trigger enter animation on next frame */
    rAF(function () {
      toast.classList.add('v7-toast--visible');
    });

    /* Auto-dismiss */
    setTimeout(function () {
      toast.classList.remove('v7-toast--visible');
      toast.classList.add('v7-toast--exit');
      setTimeout(function () {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
      }, 350);
    }, duration);

    return toast;
  }


  /* ================================================================
     2. CONFETTI SYSTEM
     ================================================================ */

  function confettiBurst() {
    var COLORS = ['#E31E24', '#FFFFFF', '#FFD700', '#00E5FF'];
    var PIECES = 80;
    var container = document.createElement('div');
    container.className = 'v7-confetti-container';
    container.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:99999;overflow:hidden;';
    document.body.appendChild(container);

    for (var i = 0; i < PIECES; i++) {
      var piece = document.createElement('div');
      var size = Math.random() * 8 + 4;
      var startX = Math.random() * 100;
      var drift = (Math.random() - 0.5) * 200;
      var rotEnd = Math.random() * 720 - 360;
      var fallDuration = Math.random() * 1500 + 2000;
      var delay = Math.random() * 400;
      var color = COLORS[Math.floor(Math.random() * COLORS.length)];

      piece.style.cssText =
        'position:absolute;' +
        'left:' + startX + '%;' +
        'top:-10px;' +
        'width:' + size + 'px;' +
        'height:' + (size * 0.6) + 'px;' +
        'background:' + color + ';' +
        'border-radius:2px;' +
        'opacity:1;' +
        'animation:v7ConfettiFall ' + fallDuration + 'ms ease-in ' + delay + 'ms forwards;' +
        '--v7-drift:' + drift + 'px;' +
        '--v7-rot:' + rotEnd + 'deg;';

      container.appendChild(piece);
    }

    /* Inject keyframes if not already present */
    if (!document.getElementById('v7-confetti-keyframes')) {
      var style = document.createElement('style');
      style.id = 'v7-confetti-keyframes';
      style.textContent =
        '@keyframes v7ConfettiFall {' +
          '0% { transform: translateY(0) translateX(0) rotate(0deg); opacity: 1; }' +
          '100% { transform: translateY(100vh) translateX(var(--v7-drift, 50px)) rotate(var(--v7-rot, 360deg)); opacity: 0; }' +
        '}';
      document.head.appendChild(style);
    }

    /* Cleanup after animation completes */
    setTimeout(function () {
      if (container.parentNode) container.parentNode.removeChild(container);
    }, 3500);
  }


  /* ================================================================
     3. COUNTUP ANIMATION
     ================================================================ */

  function countUp(el, target, duration) {
    duration = duration || 2000;
    var startTime = null;
    var decimals = (String(target).split('.')[1] || '').length;

    function easeOutExpo(t) {
      return t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
    }

    function step(timestamp) {
      if (!startTime) startTime = timestamp;
      var progress = Math.min((timestamp - startTime) / duration, 1);
      var eased = easeOutExpo(progress);
      var current = target * eased;

      /* Format with locale separator */
      var formatted;
      try {
        formatted = current.toLocaleString(undefined, {
          minimumFractionDigits: decimals,
          maximumFractionDigits: decimals
        });
      } catch (e) {
        formatted = current.toFixed(decimals);
      }

      /* Apply prefix / suffix from data attributes */
      var prefix = el.getAttribute('data-v7-count-prefix') || '';
      var suffix = el.getAttribute('data-v7-count-suffix') || '';
      el.textContent = prefix + formatted + suffix;

      if (progress < 1) {
        rAF(step);
      }
    }

    rAF(step);
  }

  function initAutoCountUp() {
    if (!('IntersectionObserver' in window)) return;

    var elements = document.querySelectorAll('[data-v7-count]');
    if (!elements.length) return;

    var observer = new IntersectionObserver(function (entries) {
      for (var i = 0; i < entries.length; i++) {
        if (entries[i].isIntersecting) {
          var el = entries[i].target;
          var target = parseFloat(el.getAttribute('data-v7-count')) || 0;
          var duration = parseInt(el.getAttribute('data-v7-count-duration'), 10) || 2000;
          countUp(el, target, duration);
          observer.unobserve(el);
        }
      }
    }, { threshold: 0.3 });

    for (var i = 0; i < elements.length; i++) {
      observer.observe(elements[i]);
    }
  }


  /* ================================================================
     4. MAGNETIC BUTTONS (desktop only, GSAP optional)
     ================================================================ */

  function initMagneticButtons() {
    if (!pointerFine) return;

    var selector = '.v7-btn-primary, .v7-btn-glow, .v7-nav-cta, [data-magnetic]';
    var hasGSAP = typeof window.gsap !== 'undefined';

    document.addEventListener('mousemove', function (e) {
      if (!e.target || !e.target.closest) return;
      var btn = e.target.closest(selector);
      if (!btn) return;

      var rect = btn.getBoundingClientRect();
      var offsetX = (e.clientX - rect.left - rect.width / 2) * 0.15;
      var offsetY = (e.clientY - rect.top - rect.height / 2) * 0.15;

      if (hasGSAP) {
        window.gsap.to(btn, { x: offsetX, y: offsetY, duration: 0.3, ease: 'power2.out' });
      } else {
        btn.style.transition = 'transform 0.15s ease-out';
        btn.style.transform = 'translate(' + offsetX + 'px, ' + offsetY + 'px)';
      }
    });

    document.addEventListener('mouseleave', function (e) {
      if (!e.target || !e.target.closest) return;
      var btn = e.target.closest(selector);
      if (!btn) return;

      if (hasGSAP) {
        window.gsap.to(btn, { x: 0, y: 0, duration: 0.5, ease: 'elastic.out(1, 0.4)' });
      } else {
        btn.style.transition = 'transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
        btn.style.transform = 'translate(0, 0)';
      }
    }, true);

    /* Fallback: also listen on mouseout for individual buttons */
    document.addEventListener('mouseout', function (e) {
      if (!e.target || !e.target.closest) return;
      var btn = e.target.closest(selector);
      if (!btn) return;
      /* Only reset if mouse actually left the button */
      if (!btn.contains(e.relatedTarget)) {
        if (hasGSAP) {
          window.gsap.to(btn, { x: 0, y: 0, duration: 0.5, ease: 'elastic.out(1, 0.4)' });
        } else {
          btn.style.transition = 'transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
          btn.style.transform = 'translate(0, 0)';
        }
      }
    });
  }


  /* ================================================================
     5. MOUSE-TRACKING GLOW
     ================================================================ */

  function initGlowTrack() {
    var elements = document.querySelectorAll('.v7-glow-track');
    if (!elements.length) return;

    var ticking = false;
    var lastX = 0;
    var lastY = 0;
    var activeEl = null;

    document.addEventListener('mousemove', function (e) {
      var el = e.target.closest('.v7-glow-track');
      if (!el) {
        activeEl = null;
        return;
      }
      activeEl = el;
      lastX = e.clientX;
      lastY = e.clientY;

      if (!ticking) {
        rAF(function () {
          if (activeEl) {
            var rect = activeEl.getBoundingClientRect();
            var mx = ((lastX - rect.left) / rect.width * 100).toFixed(1);
            var my = ((lastY - rect.top) / rect.height * 100).toFixed(1);
            activeEl.style.setProperty('--mx', mx + '%');
            activeEl.style.setProperty('--my', my + '%');
          }
          ticking = false;
        });
        ticking = true;
      }
    });
  }


  /* ================================================================
     6. CUSTOM CURSOR (desktop only)
     ================================================================ */

  var cursorX = 0, cursorY = 0;
  var circleX = 0, circleY = 0;
  var cursorVisible = false;
  var cursorRafId = null;

  function initCustomCursor() {
    if (!pointerFine) return;

    cursorDot = document.createElement('div');
    cursorDot.className = 'v7-cursor-dot';
    cursorCircle = document.createElement('div');
    cursorCircle.className = 'v7-cursor-circle';
    document.body.appendChild(cursorDot);
    document.body.appendChild(cursorCircle);

    var hoverSelector = 'a, button, [data-cursor-hover], .v7-btn-primary, .v7-btn-glow';

    document.addEventListener('mousemove', function (e) {
      cursorX = e.clientX;
      cursorY = e.clientY;
      /* Dot follows instantly */
      cursorDot.style.left = cursorX + 'px';
      cursorDot.style.top = cursorY + 'px';

      if (!cursorVisible) {
        cursorVisible = true;
        cursorDot.style.opacity = '1';
        cursorCircle.style.opacity = '1';
      }
    });

    /* Circle follows with lerp */
    function animateCursor() {
      circleX = lerp(circleX, cursorX, 0.12);
      circleY = lerp(circleY, cursorY, 0.12);
      cursorCircle.style.left = circleX + 'px';
      cursorCircle.style.top = circleY + 'px';
      cursorRafId = rAF(animateCursor);
    }
    cursorRafId = rAF(animateCursor);
    rafIds.push(cursorRafId);

    /* Hover state */
    document.addEventListener('mouseover', function (e) {
      if (e.target.closest(hoverSelector)) {
        document.body.classList.add('v7-cursor-hover');
      }
    });
    document.addEventListener('mouseout', function (e) {
      if (e.target.closest(hoverSelector)) {
        document.body.classList.remove('v7-cursor-hover');
      }
    });

    /* Hide when mouse leaves window */
    document.addEventListener('mouseleave', function () {
      cursorVisible = false;
      cursorDot.style.opacity = '0';
      cursorCircle.style.opacity = '0';
    });
    document.addEventListener('mouseenter', function () {
      cursorVisible = true;
      cursorDot.style.opacity = '1';
      cursorCircle.style.opacity = '1';
    });
  }


  /* ================================================================
     7. KINETIC TYPOGRAPHY (Split Text)
     ================================================================ */

  function initSplitText() {
    var elements = document.querySelectorAll('[data-v7-split]');
    if (!elements.length) return;

    var charIndex;

    for (var i = 0; i < elements.length; i++) {
      var el = elements[i];
      var text = el.textContent;
      var words = text.split(/\s+/);
      el.textContent = '';
      el.setAttribute('aria-label', text);
      charIndex = 0;

      for (var w = 0; w < words.length; w++) {
        if (w > 0) {
          /* Add space between words */
          var space = document.createTextNode(' ');
          el.appendChild(space);
        }

        var wordSpan = document.createElement('span');
        wordSpan.className = 'v7-split-word';
        wordSpan.style.display = 'inline-block';

        var chars = words[w].split('');
        for (var c = 0; c < chars.length; c++) {
          var charSpan = document.createElement('span');
          charSpan.className = 'v7-split-char';
          charSpan.style.display = 'inline-block';
          charSpan.style.setProperty('--i', charIndex);
          charSpan.textContent = chars[c];
          wordSpan.appendChild(charSpan);
          charIndex++;
        }

        el.appendChild(wordSpan);
      }
    }

    /* Observe for reveal */
    if (!('IntersectionObserver' in window)) {
      /* Fallback: reveal all immediately */
      for (var j = 0; j < elements.length; j++) {
        elements[j].classList.add('revealed');
      }
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      for (var k = 0; k < entries.length; k++) {
        if (entries[k].isIntersecting) {
          entries[k].target.classList.add('revealed');
          observer.unobserve(entries[k].target);
        }
      }
    }, { threshold: 0.2 });

    for (var m = 0; m < elements.length; m++) {
      observer.observe(elements[m]);
    }
  }


  /* ================================================================
     8. SCROLL REVEAL FALLBACK
     ================================================================ */

  function initScrollRevealFallback() {
    /* Only activate if browser lacks scroll-driven animations */
    if (typeof CSS !== 'undefined' && CSS.supports && CSS.supports('animation-timeline', 'view()')) {
      return;
    }
    if (!('IntersectionObserver' in window)) return;

    var elements = document.querySelectorAll('.v7-reveal, .v7-reveal-left, .v7-reveal-right');
    if (!elements.length) return;

    var observer = new IntersectionObserver(function (entries) {
      for (var i = 0; i < entries.length; i++) {
        if (entries[i].isIntersecting) {
          entries[i].target.classList.add('revealed');
          observer.unobserve(entries[i].target);
        }
      }
    }, { threshold: 0.15 });

    for (var i = 0; i < elements.length; i++) {
      observer.observe(elements[i]);
    }
  }


  /* ================================================================
     9. CANVAS 2D PARTICLES
     ================================================================ */

  function initParticles() {
    var canvases = document.querySelectorAll('[data-v7-particles]');
    if (!canvases.length) return;

    var isMobile = window.innerWidth < 768;
    var PARTICLE_COUNT = isMobile ? 25 : 50;

    for (var n = 0; n < canvases.length; n++) {
      (function (canvas) {
        var ctx = canvas.getContext('2d');
        if (!ctx) return;

        var particles = [];
        var animating = true;
        var particleRafId = null;

        function resize() {
          canvas.width = canvas.offsetWidth;
          canvas.height = canvas.offsetHeight;
        }
        resize();

        /* Create particles */
        for (var i = 0; i < PARTICLE_COUNT; i++) {
          particles.push({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height,
            radius: Math.random() * 2 + 1,
            opacity: Math.random() * 0.3 + 0.1,
            speedX: (Math.random() - 0.5) * 0.6,
            speedY: (Math.random() - 0.5) * 0.6
          });
        }

        function draw() {
          if (!animating) return;
          ctx.clearRect(0, 0, canvas.width, canvas.height);

          for (var i = 0; i < particles.length; i++) {
            var p = particles[i];

            /* Update position */
            p.x += p.speedX;
            p.y += p.speedY;

            /* Wrap around edges */
            if (p.x < 0) p.x = canvas.width;
            if (p.x > canvas.width) p.x = 0;
            if (p.y < 0) p.y = canvas.height;
            if (p.y > canvas.height) p.y = 0;

            /* Draw circle */
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(227, 30, 36, ' + p.opacity + ')';
            ctx.fill();
          }

          particleRafId = rAF(draw);
        }
        draw();

        /* Pause when not visible */
        if ('IntersectionObserver' in window) {
          var visObserver = new IntersectionObserver(function (entries) {
            animating = entries[0].isIntersecting;
            if (animating) draw();
          }, { threshold: 0 });
          visObserver.observe(canvas);
        }

        /* Debounced resize */
        var onResize = debounce(function () {
          resize();
          /* Reposition particles within new bounds */
          for (var i = 0; i < particles.length; i++) {
            if (particles[i].x > canvas.width) particles[i].x = Math.random() * canvas.width;
            if (particles[i].y > canvas.height) particles[i].y = Math.random() * canvas.height;
          }
        }, 250);
        window.addEventListener('resize', onResize);

        /* Track for cleanup */
        particleSystems.push({
          canvas: canvas,
          stop: function () {
            animating = false;
            if (particleRafId) cAF(particleRafId);
          },
          removeResize: function () {
            window.removeEventListener('resize', onResize);
          }
        });
      })(canvases[n]);
    }
  }


  /* ================================================================
     10. SCROLL PROGRESS BAR FALLBACK
     ================================================================ */

  function initScrollProgress() {
    /* Only apply fallback if CSS scroll-driven animations are not supported */
    if (typeof CSS !== 'undefined' && CSS.supports && CSS.supports('animation-timeline', 'scroll()')) {
      return;
    }

    var fill = document.querySelector('.v7-scroll-progress-fill');
    var bar = document.querySelector('.v7-scroll-progress');
    if (!fill && !bar) return;

    var target = fill || bar;
    var scrollTicking = false;

    window.addEventListener('scroll', function () {
      if (!scrollTicking) {
        rAF(function () {
          var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
          var docHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
          var progress = docHeight > 0 ? scrollTop / docHeight : 0;
          target.style.transform = 'scaleX(' + progress + ')';
          scrollTicking = false;
        });
        scrollTicking = true;
      }
    }, { passive: true });
  }


  /* ================================================================
     11. SMOOTH SCROLL (Lenis Integration)
     ================================================================ */

  function initLenis() {
    if (typeof window.Lenis === 'undefined') return;

    lenisInstance = new window.Lenis({
      duration: 1.2,
      easeFunction: function (t) {
        return Math.min(1, 1.001 - Math.pow(2, -10 * t));
      }
    });

    function raf(time) {
      lenisInstance.raf(time);
      rAF(raf);
    }
    rAF(raf);
  }


  /* ================================================================
     12. NAVBAR SCROLL HANDLER
     ================================================================ */

  function initNavbarScroll() {
    var nav = document.querySelector('.v7-nav');
    if (!nav) return;

    var navTicking = false;

    window.addEventListener('scroll', function () {
      if (!navTicking) {
        rAF(function () {
          if (window.pageYOffset > 50) {
            nav.classList.add('scrolled');
          } else {
            nav.classList.remove('scrolled');
          }
          navTicking = false;
        });
        navTicking = true;
      }
    }, { passive: true });

    /* Set initial state */
    if (window.pageYOffset > 50) {
      nav.classList.add('scrolled');
    }
  }


  /* ================================================================
     13. MOBILE MENU TOGGLE
     ================================================================ */

  function initMobileMenu() {
    var hamburger = document.querySelector('.v7-nav-hamburger');
    var mobileMenu = document.querySelector('.v7-mobile-menu');
    if (!hamburger || !mobileMenu) return;

    hamburger.addEventListener('click', function () {
      var isOpen = mobileMenu.classList.toggle('open');
      hamburger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    /* Close on link click */
    var menuLinks = mobileMenu.querySelectorAll('a');
    for (var i = 0; i < menuLinks.length; i++) {
      menuLinks[i].addEventListener('click', function () {
        mobileMenu.classList.remove('open');
        hamburger.setAttribute('aria-expanded', 'false');
      });
    }

    /* Close on Escape key */
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' || e.keyCode === 27) {
        if (mobileMenu.classList.contains('open')) {
          mobileMenu.classList.remove('open');
          hamburger.setAttribute('aria-expanded', 'false');
          hamburger.focus();
        }
      }
    });
  }


  /* ================================================================
     14. ACCORDION SYSTEM
     ================================================================ */

  function initAccordions() {
    var triggers = document.querySelectorAll('.v7-accordion-trigger');
    if (!triggers.length) return;

    for (var i = 0; i < triggers.length; i++) {
      triggers[i].addEventListener('click', function () {
        var item = this.closest('.v7-accordion-item');
        if (!item) return;

        var accordion = item.closest('.v7-accordion');
        var content = item.querySelector('.v7-accordion-content');
        var isOpen = item.classList.contains('open');

        /* Close other items in the same accordion (exclusive mode) */
        if (accordion) {
          var siblings = accordion.querySelectorAll('.v7-accordion-item.open');
          for (var j = 0; j < siblings.length; j++) {
            if (siblings[j] !== item) {
              siblings[j].classList.remove('open');
              var sibContent = siblings[j].querySelector('.v7-accordion-content');
              if (sibContent) {
                sibContent.style.maxHeight = '0';
              }
            }
          }
        }

        /* Toggle current item */
        if (isOpen) {
          item.classList.remove('open');
          if (content) content.style.maxHeight = '0';
        } else {
          item.classList.add('open');
          if (content) content.style.maxHeight = content.scrollHeight + 'px';
        }
      });
    }

    /* Set initial max-height for already-open items */
    var openItems = document.querySelectorAll('.v7-accordion-item.open .v7-accordion-content');
    for (var k = 0; k < openItems.length; k++) {
      openItems[k].style.maxHeight = openItems[k].scrollHeight + 'px';
    }
  }


  /* ================================================================
     15. CLEANUP / DESTROY
     ================================================================ */

  function destroy() {
    /* Cancel tracked animation frames */
    for (var i = 0; i < rafIds.length; i++) {
      cAF(rafIds[i]);
    }
    rafIds = [];

    /* Stop particle systems */
    for (var j = 0; j < particleSystems.length; j++) {
      particleSystems[j].stop();
      particleSystems[j].removeResize();
    }
    particleSystems = [];

    /* Remove cursor elements */
    if (cursorDot && cursorDot.parentNode) cursorDot.parentNode.removeChild(cursorDot);
    if (cursorCircle && cursorCircle.parentNode) cursorCircle.parentNode.removeChild(cursorCircle);
    cursorDot = null;
    cursorCircle = null;
    document.body.classList.remove('v7-cursor-hover');

    /* Cancel cursor animation */
    if (cursorRafId) cAF(cursorRafId);

    /* Destroy Lenis */
    if (lenisInstance && typeof lenisInstance.destroy === 'function') {
      lenisInstance.destroy();
      lenisInstance = null;
    }

    /* Remove toast container */
    if (toastContainer && toastContainer.parentNode) {
      toastContainer.parentNode.removeChild(toastContainer);
      toastContainer = null;
    }

    /* Remove confetti keyframes style */
    var confettiStyle = document.getElementById('v7-confetti-keyframes');
    if (confettiStyle && confettiStyle.parentNode) {
      confettiStyle.parentNode.removeChild(confettiStyle);
    }
  }


  /* ================================================================
     16. PUBLIC API (V7 Namespace)
     ================================================================ */

  window.V7 = {
    toast: toastShow,
    confetti: confettiBurst,
    countUp: countUp,
    lenis: null,
    destroy: destroy
  };


  /* ================================================================
     17. INITIALIZATION
     ================================================================ */

  function init() {
    initMagneticButtons();
    initGlowTrack();
    initCustomCursor();
    initSplitText();
    initScrollRevealFallback();
    initParticles();
    initAutoCountUp();
    initScrollProgress();
    initLenis();
    initNavbarScroll();
    initMobileMenu();
    initAccordions();

    /* Expose Lenis instance after init */
    window.V7.lenis = lenisInstance;
  }

  /* Handle both early and late script loading */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
