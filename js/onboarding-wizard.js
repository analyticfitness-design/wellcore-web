/**
 * WellCore — Onboarding Wizard
 * Interactive step-by-step guide for new clients (first 3 days).
 * Shows feature highlights with pulsing dots and guided navigation.
 */
(function() {
  'use strict';

  var WIZARD_KEY = 'wc_onboarding_done';
  var STEP_KEY = 'wc_onboarding_step';

  window.addEventListener('DOMContentLoaded', function() {
    setTimeout(checkOnboarding, 2500);
  });

  function checkOnboarding() {
    if (localStorage.getItem(WIZARD_KEY) === '1') return;
    var token = localStorage.getItem('wc_token');
    if (!token) return;

    var user = null;
    try { user = JSON.parse(localStorage.getItem('wc_user') || '{}'); } catch(e) {}
    if (!user || !user.created_at) return;

    // Only show for clients in first 3 days
    var created = new Date(user.created_at);
    var now = new Date();
    var daysSince = Math.floor((now - created) / 86400000);
    if (daysSince > 3) {
      localStorage.setItem(WIZARD_KEY, '1');
      return;
    }

    var currentStep = parseInt(localStorage.getItem(STEP_KEY) || '0');
    showWizard(currentStep);
  }

  var steps = [
    {
      title: 'Bienvenido a WellCore!',
      text: 'Tu plataforma de coaching fitness personalizado. Te voy a mostrar las funciones principales en 30 segundos.',
      icon: 'fas fa-hand-sparkles',
      target: null,
      action: null
    },
    {
      title: 'Tus Habitos Diarios',
      text: 'Registra 4 habitos cada dia: agua, sueno, nutricion y estres. Completa los 4 para mantener tu racha.',
      icon: 'fas fa-fire',
      target: 'habitos',
      highlight: 'nav-habitos'
    },
    {
      title: 'Metricas Corporales',
      text: 'Registra tu peso, grasa corporal y medidas. Te recomendamos hacerlo cada 7-10 dias para ver tu progreso.',
      icon: 'fas fa-weight',
      target: 'metricas',
      highlight: 'nav-metricas'
    },
    {
      title: 'Chat con tu Coach',
      text: 'Habla directamente con tu coach. Puedes enviar mensajes, fotos y recibir feedback personalizado.',
      icon: 'fas fa-comments',
      target: 'chat',
      highlight: 'nav-chat'
    },
    {
      title: 'Misiones Diarias',
      text: 'Cada dia tienes 3 misiones para ganar XP. Completa todas para un bonus extra!',
      icon: 'fas fa-tasks',
      target: null,
      highlight: 'daily-mission-card'
    },
    {
      title: 'Fotos de Progreso',
      text: 'Sube fotos cada 7-10 dias. Te damos una guia visual para que sean consistentes y puedas ver tu transformacion.',
      icon: 'fas fa-camera',
      target: 'fotos',
      highlight: 'nav-fotos'
    },
    {
      title: 'Listo para empezar!',
      text: 'Tu primera mision: completa tus 4 habitos de hoy. Tu coach esta pendiente de ti!',
      icon: 'fas fa-rocket',
      target: null,
      action: 'finish'
    }
  ];

  function showWizard(stepIndex) {
    if (stepIndex >= steps.length) {
      finishWizard();
      return;
    }

    var step = steps[stepIndex];
    removeExistingOverlay();

    // Create overlay
    var overlay = document.createElement('div');
    overlay.id = 'onboarding-overlay';
    overlay.style.cssText = 'position:fixed;inset:0;z-index:99998;background:rgba(0,0,0,0.8);display:flex;align-items:center;justify-content:center;animation:wcFadeIn .3s ease;backdrop-filter:blur(4px);';

    var card = document.createElement('div');
    card.style.cssText = 'background:var(--surface,#161618);border:1px solid rgba(200,16,46,0.3);border-radius:16px;padding:32px 28px;text-align:center;max-width:340px;width:90%;animation:wcScaleIn .35s cubic-bezier(.34,1.56,.64,1);';

    // Progress dots
    var dots = document.createElement('div');
    dots.style.cssText = 'display:flex;justify-content:center;gap:6px;margin-bottom:20px;';
    for (var i = 0; i < steps.length; i++) {
      var dot = document.createElement('div');
      dot.style.cssText = 'width:8px;height:8px;border-radius:50%;' +
        (i === stepIndex ? 'background:var(--red,#E31E24);' :
         i < stepIndex ? 'background:#22c55e;' : 'background:rgba(255,255,255,.15);');
      dots.appendChild(dot);
    }
    card.appendChild(dots);

    // Icon
    var iconDiv = document.createElement('div');
    iconDiv.style.cssText = 'font-size:2.5rem;margin-bottom:14px;color:var(--red,#E31E24);';
    var iconEl = document.createElement('i');
    iconEl.className = step.icon;
    iconDiv.appendChild(iconEl);
    card.appendChild(iconDiv);

    // Title
    var title = document.createElement('div');
    title.style.cssText = 'font-size:1.1rem;font-weight:800;color:#fff;margin-bottom:8px;';
    title.textContent = step.title;
    card.appendChild(title);

    // Text
    var text = document.createElement('div');
    text.style.cssText = 'font-size:.84rem;color:var(--gray,#999);line-height:1.55;margin-bottom:22px;';
    text.textContent = step.text;
    card.appendChild(text);

    // Buttons
    var btns = document.createElement('div');
    btns.style.cssText = 'display:flex;gap:10px;justify-content:center;';

    if (stepIndex > 0) {
      var prevBtn = document.createElement('button');
      prevBtn.style.cssText = 'background:none;border:1px solid var(--border,#333);color:var(--gray);padding:10px 18px;border-radius:8px;font-size:.82rem;cursor:pointer;';
      prevBtn.textContent = 'Atras';
      prevBtn.addEventListener('click', function() {
        localStorage.setItem(STEP_KEY, String(stepIndex - 1));
        showWizard(stepIndex - 1);
      });
      btns.appendChild(prevBtn);
    }

    var nextBtn = document.createElement('button');
    nextBtn.style.cssText = 'background:var(--red,#E31E24);color:#fff;border:none;border-radius:8px;padding:10px 24px;font-size:.85rem;font-weight:700;cursor:pointer;';
    nextBtn.textContent = stepIndex === steps.length - 1 ? 'Comenzar!' : 'Siguiente';
    nextBtn.addEventListener('click', function() {
      if (window.WCSound) WCSound.play('toggleOn');
      if (step.target && typeof showSection === 'function') showSection(step.target);
      localStorage.setItem(STEP_KEY, String(stepIndex + 1));
      showWizard(stepIndex + 1);
    });
    btns.appendChild(nextBtn);

    card.appendChild(btns);

    // Skip link
    var skip = document.createElement('div');
    skip.style.cssText = 'margin-top:14px;font-size:.72rem;color:var(--gray);cursor:pointer;opacity:.6;';
    skip.textContent = 'Saltar tutorial';
    skip.addEventListener('click', finishWizard);
    card.appendChild(skip);

    // Step counter
    var counter = document.createElement('div');
    counter.style.cssText = 'margin-top:10px;font-size:.68rem;color:var(--gray);opacity:.4;';
    counter.textContent = (stepIndex + 1) + ' / ' + steps.length;
    card.appendChild(counter);

    overlay.appendChild(card);

    // Animations
    if (!document.getElementById('wc-onboarding-styles')) {
      var style = document.createElement('style');
      style.id = 'wc-onboarding-styles';
      style.textContent = '@keyframes wcFadeIn{from{opacity:0}to{opacity:1}}@keyframes wcScaleIn{from{transform:scale(.7);opacity:0}to{transform:scale(1);opacity:1}}@keyframes wcPulse{0%,100%{box-shadow:0 0 0 0 rgba(227,30,36,.4)}50%{box-shadow:0 0 0 8px rgba(227,30,36,0)}}';
      document.head.appendChild(style);
    }

    document.body.appendChild(overlay);

    // Highlight target element if specified
    if (step.highlight) {
      var el = document.getElementById(step.highlight) || document.querySelector('[data-section="' + step.highlight + '"]');
      if (el) {
        el.style.animation = 'wcPulse 1.5s infinite';
        el.dataset.onboardingHighlight = '1';
      }
    }
  }

  function removeExistingOverlay() {
    var existing = document.getElementById('onboarding-overlay');
    if (existing) existing.remove();
    // Clean highlights
    document.querySelectorAll('[data-onboarding-highlight]').forEach(function(el) {
      el.style.animation = '';
      delete el.dataset.onboardingHighlight;
    });
  }

  function finishWizard() {
    removeExistingOverlay();
    localStorage.setItem(WIZARD_KEY, '1');
    localStorage.removeItem(STEP_KEY);
    if (window.WCSound) WCSound.play('celebration');
    if (window.showToast) showToast('Tutorial completado! +20 XP', '#22c55e');

    // Award XP for completing onboarding
    var token = localStorage.getItem('wc_token');
    if (token) {
      fetch('/api/gamification/earn-xp.php', {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
        body: JSON.stringify({ event: 'onboarding_complete', xp: 20 })
      }).catch(function() {});
    }
  }

  // Allow manual re-trigger
  window.restartOnboarding = function() {
    localStorage.removeItem(WIZARD_KEY);
    localStorage.removeItem(STEP_KEY);
    showWizard(0);
  };

})();
