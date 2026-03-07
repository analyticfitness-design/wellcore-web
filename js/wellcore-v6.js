/* ============================================================
   WellCore Fitness v6 — Effects Engine 2026
   Toast notifications, magnetic buttons, IO-based reveals,
   scroll progress, and utilities for future modules.
   Purely additive — does not touch v5-effects.js functionality.
   ============================================================ */

(function () {
  'use strict';

  /* ── Toast Notification System ── */
  var TOAST_ICONS = {
    success: 'fa-check-circle',
    error: 'fa-times-circle',
    info: 'fa-info-circle',
    warning: 'fa-exclamation-triangle'
  };

  var V6Toast = {
    container: null,

    init: function () {
      if (this.container) return;
      this.container = document.createElement('div');
      this.container.className = 'v6-toast-container';
      document.body.appendChild(this.container);
    },

    show: function (message, type, duration) {
      type = type || 'success';
      duration = duration || 3500;
      this.init();

      var toast = document.createElement('div');
      toast.className = 'v6-toast v6-toast-' + type;

      var iconClass = TOAST_ICONS[type];
      if (iconClass) {
        var icon = document.createElement('i');
        icon.className = 'fas ' + iconClass;
        toast.appendChild(icon);
      }

      var span = document.createElement('span');
      span.textContent = message;
      toast.appendChild(span);

      this.container.appendChild(toast);

      requestAnimationFrame(function () {
        toast.classList.add('show');
      });

      setTimeout(function () {
        toast.classList.remove('show');
        setTimeout(function () {
          if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 300);
      }, duration);

      return toast;
    },

    success: function (msg, duration) { return this.show(msg, 'success', duration); },
    error: function (msg, duration) { return this.show(msg, 'error', duration); },
    info: function (msg, duration) { return this.show(msg, 'info', duration); },
    warning: function (msg, duration) { return this.show(msg, 'warning', duration); }
  };


  /* ── Magnetic Buttons (desktop only) ── */
  function initMagneticButtons() {
    if (!window.matchMedia('(pointer: fine)').matches) return;

    document.addEventListener('mousemove', function (e) {
      var btn = e.target.closest('.v6-magnetic');
      if (!btn) return;
      var rect = btn.getBoundingClientRect();
      var x = (e.clientX - rect.left - rect.width / 2) * 0.3;
      var y = (e.clientY - rect.top - rect.height / 2) * 0.3;
      btn.style.transform = 'translate(' + x + 'px, ' + y + 'px)';
    });

    document.addEventListener('mouseout', function (e) {
      var btn = e.target.closest('.v6-magnetic');
      if (btn) btn.style.transform = '';
    });
  }


  /* ── Scroll Progress Bar Fallback ── */
  function initScrollProgressFallback() {
    if (CSS.supports && CSS.supports('animation-timeline', 'scroll()')) return;

    var bar = document.querySelector('.v6-scroll-progress');
    if (!bar) return;

    var ticking = false;
    window.addEventListener('scroll', function () {
      if (!ticking) {
        requestAnimationFrame(function () {
          var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
          var docHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
          var progress = docHeight > 0 ? scrollTop / docHeight : 0;
          bar.style.transform = 'scaleX(' + progress + ')';
          ticking = false;
        });
        ticking = true;
      }
    });
  }


  /* ── IntersectionObserver Reveal (fallback for scroll-driven) ── */
  function initRevealFallback() {
    if (CSS.supports && CSS.supports('animation-timeline', 'view()')) return;

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('v6-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });

    var revealEls = document.querySelectorAll(
      '.v6-reveal, .v6-reveal-fade, .v6-reveal-left, .v6-reveal-right, .v6-reveal-scale'
    );
    revealEls.forEach(function (el) { observer.observe(el); });
  }


  /* ── CountUp Animation ──
     Animates a number from 0 to target. Lightweight, no library.
     Usage: V6.countUp(element, targetNumber, duration)
  */
  function countUp(el, target, duration) {
    duration = duration || 1500;
    var start = 0;
    var startTime = null;
    var decimals = (String(target).split('.')[1] || '').length;

    function step(timestamp) {
      if (!startTime) startTime = timestamp;
      var progress = Math.min((timestamp - startTime) / duration, 1);
      var eased = 1 - Math.pow(1 - progress, 3);
      var current = start + (target - start) * eased;
      el.textContent = current.toFixed(decimals);
      if (progress < 1) requestAnimationFrame(step);
    }

    requestAnimationFrame(step);
  }


  /* ── Progress Ring ──
     Animates an SVG circle's stroke-dashoffset.
     Usage: V6.animateRing(svgCircleElement, percent)
  */
  function animateRing(circle, percent) {
    var radius = circle.r.baseVal.value;
    var circumference = 2 * Math.PI * radius;
    circle.style.strokeDasharray = circumference;
    circle.style.strokeDashoffset = circumference;

    requestAnimationFrame(function () {
      circle.style.strokeDashoffset = circumference * (1 - percent / 100);
    });
  }


  /* ── Before/After Comparison Slider ──
     Initializes all .v6-compare elements for drag interaction.
     Usage: Add class="v6-compare" to container with 2 imgs.
  */
  function initCompareSliders() {
    document.querySelectorAll('.v6-compare').forEach(function (el) {
      function updatePos(clientX) {
        var rect = el.getBoundingClientRect();
        var x = Math.max(0, Math.min(clientX - rect.left, rect.width));
        var pct = (x / rect.width * 100).toFixed(1) + '%';
        el.style.setProperty('--v6-compare-pos', pct);
      }

      var dragging = false;

      el.addEventListener('mousedown', function (e) {
        dragging = true;
        updatePos(e.clientX);
        e.preventDefault();
      });
      document.addEventListener('mousemove', function (e) {
        if (dragging) updatePos(e.clientX);
      });
      document.addEventListener('mouseup', function () { dragging = false; });

      el.addEventListener('touchstart', function (e) {
        dragging = true;
        updatePos(e.touches[0].clientX);
      }, { passive: true });
      el.addEventListener('touchmove', function (e) {
        if (dragging) updatePos(e.touches[0].clientX);
      }, { passive: true });
      el.addEventListener('touchend', function () { dragging = false; });
    });
  }


  /* ── Confetti Trigger ──
     Fires confetti using tsParticles if available.
     Gracefully does nothing if library not loaded.
  */
  function triggerConfetti() {
    if (typeof confetti === 'function') {
      confetti({
        particleCount: 120,
        spread: 70,
        origin: { y: 0.6 },
        colors: ['#E31E24', '#FFD700', '#FFFFFF']
      });
    }
  }


  /* ── Lenis Smooth Scroll (desktop only) ── */
  function initLenis() {
    if (!window.matchMedia('(pointer: fine)').matches) return;
    if (typeof Lenis === 'undefined') return;

    var lenis = new Lenis({ lerp: 0.1, smoothWheel: true });

    function raf(time) {
      lenis.raf(time);
      requestAnimationFrame(raf);
    }
    requestAnimationFrame(raf);
  }


  /* ── Custom Cursor (desktop only) ── */
  function initCustomCursor() {
    if (!window.matchMedia('(pointer: fine)').matches) return;

    var cursor = document.createElement('div');
    cursor.className = 'v6-cursor';
    var dot = document.createElement('div');
    dot.className = 'v6-cursor-dot';
    document.body.appendChild(cursor);
    document.body.appendChild(dot);

    var mx = 0, my = 0, cx = 0, cy = 0;

    document.addEventListener('mousemove', function (e) {
      mx = e.clientX;
      my = e.clientY;
      dot.style.left = mx + 'px';
      dot.style.top = my + 'px';
    });

    function lerp(a, b, n) { return a + (b - a) * n; }

    function animate() {
      cx = lerp(cx, mx, 0.15);
      cy = lerp(cy, my, 0.15);
      cursor.style.left = cx + 'px';
      cursor.style.top = cy + 'px';
      requestAnimationFrame(animate);
    }
    requestAnimationFrame(animate);

    document.addEventListener('mousedown', function () { cursor.classList.add('v6-cursor-click'); });
    document.addEventListener('mouseup', function () { cursor.classList.remove('v6-cursor-click'); });

    var hoverEls = 'a, button, [role="button"], input[type="submit"], .v6-magnetic';
    document.addEventListener('mouseover', function (e) {
      if (e.target.closest(hoverEls)) cursor.classList.add('v6-cursor-hover');
    });
    document.addEventListener('mouseout', function (e) {
      if (e.target.closest(hoverEls)) cursor.classList.remove('v6-cursor-hover');
    });
  }


  /* ── Initialize on DOM Ready ── */
  function init() {
    initMagneticButtons();
    initRevealFallback();
    initScrollProgressFallback();
    initCompareSliders();
    initLenis();
    initCustomCursor();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }


  /* ── Public API ── */
  window.V6 = {
    toast: V6Toast,
    countUp: countUp,
    animateRing: animateRing,
    triggerConfetti: triggerConfetti
  };

})();
