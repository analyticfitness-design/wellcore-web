/* ============================================================
   WellCore Fitness v5 — Effects Engine
   Cursor, Preloader, Scroll Reveal, Nav Scroll
   ============================================================ */

(function () {
  'use strict';

  /* ── Preloader ── */
  const preloader = document.getElementById('v5-preloader');
  if (preloader) {
    window.addEventListener('load', function () {
      setTimeout(function () {
        preloader.classList.add('done');
        setTimeout(function () { preloader.remove(); }, 700);
      }, 400);
    });
  }

  /* ── Custom Cursor (solo desktop) ── */
  if (window.matchMedia('(pointer: fine)').matches) {
    const cursor = document.createElement('div');
    cursor.id = 'v5-cursor';
    cursor.style.cssText = 'position:fixed;top:0;left:0;pointer-events:none;z-index:10000;';

    const dot = document.createElement('div');
    dot.className = 'v5-cursor-dot';
    const ring = document.createElement('div');
    ring.className = 'v5-cursor-ring';

    cursor.appendChild(dot);
    cursor.appendChild(ring);
    document.body.appendChild(cursor);

    let mx = 0, my = 0, rx = 0, ry = 0;

    document.addEventListener('mousemove', function (e) {
      mx = e.clientX;
      my = e.clientY;
      dot.style.transform  = 'translate(' + (mx - 4) + 'px,' + (my - 4) + 'px)';
    });

    function animateRing() {
      rx += (mx - rx) * 0.12;
      ry += (my - ry) * 0.12;
      ring.style.transform = 'translate(' + (rx - 18) + 'px,' + (ry - 18) + 'px)';
      requestAnimationFrame(animateRing);
    }
    animateRing();

    const interactables = 'a, button, .btn-v5, .pricing-card, .dash-nav-item, .stat-card, input, textarea';
    document.addEventListener('mouseover', function (e) {
      if (e.target.closest(interactables)) {
        ring.style.width  = '52px';
        ring.style.height = '52px';
        ring.style.borderColor = 'rgba(227,30,36,0.8)';
        dot.style.opacity = '0.4';
      }
    });
    document.addEventListener('mouseout', function (e) {
      if (e.target.closest(interactables)) {
        ring.style.width  = '36px';
        ring.style.height = '36px';
        ring.style.borderColor = 'rgba(227,30,36,0.5)';
        dot.style.opacity = '1';
      }
    });

    const s = document.createElement('style');
    s.textContent = [
      '#v5-cursor .v5-cursor-dot{',
        'width:8px;height:8px;border-radius:50%;background:#E31E24;',
        'position:absolute;transition:width .2s,height .2s,opacity .2s;}',
      '#v5-cursor .v5-cursor-ring{',
        'width:36px;height:36px;border-radius:50%;',
        'border:1px solid rgba(227,30,36,0.5);',
        'position:absolute;',
        'transition:width .3s cubic-bezier(.2,1,.2,1),',
        'height .3s cubic-bezier(.2,1,.2,1),border-color .3s;}'
    ].join('');
    document.head.appendChild(s);
  }

  /* ── Navbar Scroll ── */
  const navbar = document.getElementById('navbar');
  if (navbar) {
    function handleScroll() {
      navbar.classList.toggle('scrolled', window.scrollY > 10);
    }
    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
  }

  /* ── Mobile Nav Toggle ── */
  const navToggle  = document.getElementById('navToggle');
  const mobileMenu = document.getElementById('navMobileMenu');
  if (navToggle && mobileMenu) {
    navToggle.addEventListener('click', function () {
      mobileMenu.classList.toggle('open');
    });
  }

  /* ── Scroll Reveal [data-v5-reveal] ── */
  function initReveal() {
    const els = document.querySelectorAll('[data-v5-reveal]');
    if (!els.length) return;

    const obs = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('revealed');
          obs.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    els.forEach(function (el) { obs.observe(el); });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReveal);
  } else {
    initReveal();
  }

  /* ── Counter Animation ── */
  function animateCounter(el) {
    const target   = parseInt(el.getAttribute('data-target'), 10);
    const duration = 1400;
    const start    = performance.now();
    const prefix   = el.getAttribute('data-prefix') || '';
    const suffix   = el.getAttribute('data-suffix') || '';

    function update(now) {
      const elapsed  = now - start;
      const progress = Math.min(elapsed / duration, 1);
      const ease     = 1 - Math.pow(1 - progress, 3);
      const val      = Math.round(target * ease);
      el.textContent = prefix + val.toLocaleString('es-CO') + suffix;
      if (progress < 1) requestAnimationFrame(update);
    }
    requestAnimationFrame(update);
  }

  const counterObs = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        animateCounter(entry.target);
        counterObs.unobserve(entry.target);
      }
    });
  }, { threshold: 0.5 });

  document.querySelectorAll('[data-counter]').forEach(function (el) {
    counterObs.observe(el);
  });

  /* ── Progress Bar Animation ── */
  const progObs = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        const fill = entry.target.querySelector('.progress-v5-fill');
        const pct  = entry.target.getAttribute('data-progress');
        if (fill && pct) fill.style.width = pct + '%';
        progObs.unobserve(entry.target);
      }
    });
  }, { threshold: 0.3 });

  document.querySelectorAll('.progress-v5[data-progress]').forEach(function (el) {
    const fill = el.querySelector('.progress-v5-fill');
    if (fill) fill.style.width = '0%';
    progObs.observe(el);
  });

  /* ── Dashboard Sidebar Toggle (mobile) ── */
  const sidebarToggle = document.getElementById('dashSidebarToggle');
  const sidebar = document.querySelector('.dash-sidebar');
  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
    });
    document.addEventListener('click', function (e) {
      if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
        sidebar.classList.remove('open');
      }
    });
  }

  /* ── Active nav item highlight (dashboard) ── */
  document.querySelectorAll('.dash-nav-item[href]').forEach(function (item) {
    if (item.href === window.location.href) {
      item.classList.add('active');
    }
  });

  /* ── Toast notification ── */
  window.v5Toast = function (msg, type) {
    type = type || 'success';
    var colors = { success: '#22C55E', error: '#E31E24', info: '#00D9FF', warn: '#F59E0B' };
    var toast = document.createElement('div');
    toast.style.cssText = [
      'position:fixed;bottom:28px;left:50%;transform:translateX(-50%);',
      'background:#111113;border:1px solid ' + (colors[type] || colors.success) + ';',
      'color:#fff;font-family:\'JetBrains Mono\',monospace;font-size:11px;',
      'letter-spacing:1px;padding:12px 24px;z-index:99999;',
      'animation:fadeUp .3s ease forwards;white-space:nowrap;'
    ].join('');
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(function () {
      toast.style.opacity = '0';
      toast.style.transition = 'opacity .3s';
      setTimeout(function () { toast.remove(); }, 300);
    }, 3200);
  };

  /* ── Invoice print ── */
  const printBtn = document.getElementById('invoicePrintBtn');
  if (printBtn) {
    printBtn.addEventListener('click', function () { window.print(); });
  }

  /* ── Mobile: prevent accidental clicks during scroll ── */
  if ('ontouchstart' in window) {
    var touchStartY = 0;
    var touchMoved  = false;

    document.addEventListener('touchstart', function (e) {
      touchStartY = e.touches[0].clientY;
      touchMoved  = false;
    }, { passive: true });

    document.addEventListener('touchmove', function (e) {
      if (Math.abs(e.touches[0].clientY - touchStartY) > 10) {
        touchMoved = true;
      }
    }, { passive: true });

    document.addEventListener('click', function (e) {
      if (touchMoved) {
        e.preventDefault();
        e.stopPropagation();
        touchMoved = false;
      }
    }, { capture: true });
  }

})();
