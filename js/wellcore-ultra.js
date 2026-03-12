/* ============================================================
   WELLCORE FITNESS — ULTRA PREMIUM JS LAYER
   Canvas Particles · Mouse Tracking · Intersection Observers
   Animated Counters · Bienestar Ring · Reveal Animations
   Version: 1.0 | 2026-03-10
   ============================================================ */

(function WellCoreUltra() {
  'use strict';

  /* ----------------------------------------------------------
     1. Canvas Particle System
     100 nodes, mouse repulsion, auto-connect lines
     Colors: rgba(227,30,36,0.6) dots / rgba(227,30,36,0.08) lines
  ---------------------------------------------------------- */
  (function initParticles() {
    var canvas = document.getElementById('ultra-canvas');
    if (!canvas) return;

    var ctx = canvas.getContext('2d');
    var W, H, nodes = [];
    var mouse = { x: -9999, y: -9999 };
    var NODE_COUNT = 100;
    var CONNECTION_DIST = 120;
    var REPULSION_RADIUS = 80;
    var REPULSION_STRENGTH = 0.5;

    function Particle() {
      this.x = Math.random() * W;
      this.y = Math.random() * H;
      this.vx = (Math.random() - 0.5) * 0.4;
      this.vy = (Math.random() - 0.5) * 0.4;
      this.radius = 1.5 + Math.random() * 1.5;
    }

    Particle.prototype.update = function() {
      // Mouse repulsion
      var dx = this.x - mouse.x;
      var dy = this.y - mouse.y;
      var dist = Math.sqrt(dx * dx + dy * dy);
      if (dist < REPULSION_RADIUS && dist > 0) {
        var force = (REPULSION_RADIUS - dist) / REPULSION_RADIUS;
        this.vx += (dx / dist) * force * REPULSION_STRENGTH;
        this.vy += (dy / dist) * force * REPULSION_STRENGTH;
      }

      // Velocity damping
      this.vx *= 0.98;
      this.vy *= 0.98;

      // Clamp velocity
      var speed = Math.sqrt(this.vx * this.vx + this.vy * this.vy);
      if (speed > 1.5) {
        this.vx = (this.vx / speed) * 1.5;
        this.vy = (this.vy / speed) * 1.5;
      }

      this.x += this.vx;
      this.y += this.vy;

      // Wrap around edges
      if (this.x < -10) this.x = W + 10;
      if (this.x > W + 10) this.x = -10;
      if (this.y < -10) this.y = H + 10;
      if (this.y > H + 10) this.y = -10;
    };

    Particle.prototype.draw = function() {
      ctx.beginPath();
      ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(227,30,36,0.6)';
      ctx.fill();
    };

    function resize() {
      W = canvas.width = window.innerWidth;
      H = canvas.height = window.innerHeight;
    }

    function initNodes() {
      nodes = [];
      for (var i = 0; i < NODE_COUNT; i++) {
        nodes.push(new Particle());
      }
    }

    function drawConnections() {
      for (var i = 0; i < nodes.length; i++) {
        for (var j = i + 1; j < nodes.length; j++) {
          var dx = nodes[i].x - nodes[j].x;
          var dy = nodes[i].y - nodes[j].y;
          var dist = Math.sqrt(dx * dx + dy * dy);
          if (dist < CONNECTION_DIST) {
            var alpha = (1 - dist / CONNECTION_DIST) * 0.08;
            ctx.beginPath();
            ctx.moveTo(nodes[i].x, nodes[i].y);
            ctx.lineTo(nodes[j].x, nodes[j].y);
            ctx.strokeStyle = 'rgba(227,30,36,' + alpha + ')';
            ctx.lineWidth = 1;
            ctx.stroke();
          }
        }
      }
    }

    function animate() {
      ctx.clearRect(0, 0, W, H);
      drawConnections();
      for (var i = 0; i < nodes.length; i++) {
        nodes[i].update();
        nodes[i].draw();
      }
      requestAnimationFrame(animate);
    }

    window.addEventListener('resize', function() {
      resize();
    });

    window.addEventListener('mousemove', function(e) {
      mouse.x = e.clientX;
      mouse.y = e.clientY;
    });

    window.addEventListener('mouseleave', function() {
      mouse.x = -9999;
      mouse.y = -9999;
    });

    resize();
    initNodes();
    animate();
  })();


  /* ----------------------------------------------------------
     2. Mouse-tracking radial gradient on .card-interactive
  ---------------------------------------------------------- */
  function initMouseTracking() {
    var cards = document.querySelectorAll('.card-interactive, .section-card');
    cards.forEach(function(card) {
      card.addEventListener('mousemove', function(e) {
        var rect = card.getBoundingClientRect();
        var x = ((e.clientX - rect.left) / rect.width * 100).toFixed(1);
        var y = ((e.clientY - rect.top) / rect.height * 100).toFixed(1);
        card.style.setProperty('--mx', x + '%');
        card.style.setProperty('--my', y + '%');
      });
    });
  }


  /* ----------------------------------------------------------
     3. Intersection Observer for reveal animations
  ---------------------------------------------------------- */
  function initRevealObserver() {
    if (!('IntersectionObserver' in window)) return;

    var observer = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          // Stagger children
          var staggerChildren = entry.target.querySelectorAll('.reveal-stagger');
          staggerChildren.forEach(function(el, i) {
            setTimeout(function() {
              el.classList.add('visible');
            }, i * 80);
          });
        }
      });
    }, { threshold: 0.12 });

    document.querySelectorAll('.reveal, .reveal-stagger').forEach(function(el) {
      observer.observe(el);
    });
  }


  /* ----------------------------------------------------------
     4. Animated number counters (count-up on visibility)
  ---------------------------------------------------------- */
  function animateCounter(el) {
    var raw = el.dataset.target || el.textContent;
    var target = parseInt(raw, 10);
    if (isNaN(target)) return;

    var duration = 1200;
    var start = performance.now();
    var from = 0;
    var suffix = el.dataset.suffix || '';

    function tick(now) {
      var elapsed = now - start;
      var progress = Math.min(elapsed / duration, 1);
      // Cubic ease-out
      var ease = 1 - Math.pow(1 - progress, 3);
      el.textContent = Math.round(from + (target - from) * ease) + suffix;
      if (progress < 1) {
        requestAnimationFrame(tick);
      } else {
        el.textContent = target + suffix;
        el.classList.add('stat-animate');
      }
    }
    requestAnimationFrame(tick);
  }

  function initCounters() {
    if (!('IntersectionObserver' in window)) return;

    var counterObserver = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          counterObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.3 });

    document.querySelectorAll('.counter-animate').forEach(function(el) {
      counterObserver.observe(el);
    });
  }


  /* ----------------------------------------------------------
     5. Section title kinetic underline via IntersectionObserver
  ---------------------------------------------------------- */
  function initSectionTitleObserver() {
    if (!('IntersectionObserver' in window)) return;

    var sectionObserver = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        var title = entry.target.querySelector('.section-title, h2');
        if (title) {
          title.classList.toggle('visible', entry.isIntersecting);
        }
      });
    }, { threshold: 0.2 });

    document.querySelectorAll('.section').forEach(function(s) {
      sectionObserver.observe(s);
    });
  }


  /* ----------------------------------------------------------
     6. Bienestar/wellness ring updater
  ---------------------------------------------------------- */
  function updateBienestarRing(value, max) {
    max = max || 10;
    var rings = document.querySelectorAll('.bienestar-ring');
    rings.forEach(function(ring) {
      var pct = (value / max * 100).toFixed(0);
      ring.style.setProperty('--pct', pct);
      var color = value >= 7 ? '#22C55E' : value >= 5 ? '#F59E0B' : '#E31E24';
      ring.style.setProperty('--ring-color', color);
      var valueEl = ring.querySelector('.bienestar-ring-value');
      if (valueEl) valueEl.textContent = value;
    });
  }
  window.updateBienestarRing = updateBienestarRing;


  /* ----------------------------------------------------------
     7. Add 'card-interactive' class to all cards automatically
  ---------------------------------------------------------- */
  function initCardInteractive() {
    document.querySelectorAll(
      '.section-card, .kpi-card, .metric-card, .checkin-card, .plan-card, .glass-card'
    ).forEach(function(c) {
      c.classList.add('card-interactive');
    });
  }


  /* ----------------------------------------------------------
     8. Section reveal on nav click (smooth fade transition)
  ---------------------------------------------------------- */
  function initSectionTransitions() {
    var origShowSection = window.showSection;
    if (typeof origShowSection !== 'function') return;

    window.showSection = function(id) {
      var prevSection = document.querySelector('.section.active');
      var nextId = 'sec-' + id;

      if (prevSection && prevSection.id !== nextId) {
        prevSection.style.opacity = '0';
        setTimeout(function() {
          origShowSection(id);
          var newSection = document.getElementById(nextId);
          if (newSection) {
            newSection.style.opacity = '0';
            requestAnimationFrame(function() {
              requestAnimationFrame(function() {
                newSection.style.transition = 'opacity 0.3s ease';
                newSection.style.opacity = '1';
              });
            });
          }
        }, 150);
      } else {
        origShowSection(id);
      }
    };
  }


  /* ----------------------------------------------------------
     9. Add .reveal class to .section elements via JS
        (avoids HTML modifications)
  ---------------------------------------------------------- */
  function initSectionReveal() {
    // Skip in portal pages — sections use display:none/block toggling,
    // not scroll-reveal. Adding .reveal breaks them with opacity:0.
    if (document.querySelector('.sidebar-nav') || document.getElementById('sidebar')) return;
    document.querySelectorAll('.section').forEach(function(s) {
      s.classList.add('reveal');
    });
  }


  /* ----------------------------------------------------------
     10. Value update pulse — mark elements whose value changes
  ---------------------------------------------------------- */
  function pulseValue(el) {
    el.classList.remove('value-updated');
    // Force reflow
    void el.offsetWidth;
    el.classList.add('value-updated');
  }
  window.WCUltra = window.WCUltra || {};
  window.WCUltra.pulseValue = pulseValue;
  window.WCUltra.updateBienestarRing = updateBienestarRing;
  window.WCUltra.animateCounter = animateCounter;


  /* ----------------------------------------------------------
     11. Boot — wait for DOM ready
  ---------------------------------------------------------- */
  function boot() {
    initCardInteractive();
    initMouseTracking();
    initRevealObserver();
    initCounters();
    initSectionTitleObserver();
    initSectionTransitions();
    // Give original showSection time to be defined
    setTimeout(initSectionReveal, 100);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

}());
