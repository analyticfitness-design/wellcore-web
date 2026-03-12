/**
 * WellCore — Celebrations + Weekly Summary (Bloque D)
 * Shows animated overlays for achievements and weekly summary card.
 */
(function() {
  'use strict';

  window.addEventListener('DOMContentLoaded', function() {
    setTimeout(checkCelebrations, 1500);
  });

  function getToken() { return localStorage.getItem('wc_token') || ''; }

  function apiCall(endpoint, opts) {
    opts = opts || {};
    var headers = { 'Authorization': 'Bearer ' + getToken() };
    if (opts.body) headers['Content-Type'] = 'application/json';
    return fetch('/api/' + endpoint, {
      method: opts.method || 'GET',
      headers: headers,
      body: opts.body ? JSON.stringify(opts.body) : undefined
    }).then(function(r) { return r.json(); });
  }

  function checkCelebrations() {
    if (!getToken()) return;
    apiCall('client/celebrations.php').then(function(data) {
      if (!data.ok) return;

      // Show weekly summary card if available
      if (data.weekly_summary) renderWeeklySummary(data.weekly_summary);

      // Show first pending celebration
      if (data.celebrations && data.celebrations.length > 0) {
        showCelebration(data.celebrations[0]);
      }
    }).catch(function() {});
  }

  // ── Celebration overlay ───────────────────────────────────────
  function showCelebration(celeb) {
    var overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.85);display:flex;align-items:center;justify-content:center;animation:fadeIn .3s ease;backdrop-filter:blur(6px);';

    var card = document.createElement('div');
    card.style.cssText = 'background:var(--surface,#161618);border:1px solid rgba(200,16,46,0.3);border-radius:20px;padding:40px 36px;text-align:center;max-width:360px;width:90%;animation:scaleIn .4s cubic-bezier(.34,1.56,.64,1);';

    // Icon/animation area
    var iconArea = document.createElement('div');
    iconArea.style.cssText = 'font-size:4rem;margin-bottom:16px;line-height:1;';

    var icons = {
      confetti: '\ud83c\udf89',
      fire: '\ud83d\udd25',
      trophy: '\ud83c\udfc6',
      star: '\u2b50',
      gold_star: '\ud83c\udf1f',
      muscle: '\ud83d\udcaa',
      medal: '\ud83c\udfc5'
    };
    iconArea.textContent = icons[celeb.icon] || '\ud83c\udf89';

    var title = document.createElement('div');
    title.style.cssText = 'font-size:1.3rem;font-weight:900;color:#fff;margin-bottom:8px;';
    title.textContent = celeb.title;

    var xpBadge = document.createElement('div');
    xpBadge.style.cssText = 'display:inline-flex;align-items:center;gap:6px;background:rgba(245,158,11,0.15);border:1px solid rgba(245,158,11,0.3);border-radius:20px;padding:6px 16px;font-size:.85rem;font-weight:700;color:#f59e0b;margin-bottom:20px;';
    xpBadge.textContent = '+' + (celeb.xp || 0) + ' XP';

    var btn = document.createElement('button');
    btn.style.cssText = 'background:var(--red,#E31E24);color:#fff;border:none;border-radius:10px;padding:12px 30px;font-size:.9rem;font-weight:700;cursor:pointer;letter-spacing:.05em;';
    btn.textContent = 'Continuar';
    btn.addEventListener('click', function() {
      overlay.style.animation = 'fadeOut .2s ease forwards';
      setTimeout(function() {
        if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
      }, 200);
      // Mark as shown
      apiCall('client/celebrations.php', { method: 'POST', body: { event_type: celeb.type } }).catch(function() {});
    });

    card.appendChild(iconArea);
    card.appendChild(title);
    card.appendChild(xpBadge);
    card.appendChild(btn);
    overlay.appendChild(card);

    // Add CSS animations if not present
    if (!document.getElementById('celebration-styles')) {
      var style = document.createElement('style');
      style.id = 'celebration-styles';
      style.textContent = '@keyframes scaleIn{from{transform:scale(.6);opacity:0}to{transform:scale(1);opacity:1}}@keyframes fadeIn{from{opacity:0}to{opacity:1}}@keyframes fadeOut{from{opacity:1}to{opacity:0}}';
      document.head.appendChild(style);
    }

    document.body.appendChild(overlay);

    // Confetti particles
    if (celeb.icon === 'confetti' || celeb.icon === 'trophy' || celeb.icon === 'gold_star') {
      spawnConfetti(overlay);
    }
  }

  function spawnConfetti(container) {
    var colors = ['#E31E24', '#f59e0b', '#22c55e', '#3b82f6', '#a855f7', '#fff'];
    for (var i = 0; i < 50; i++) {
      var particle = document.createElement('div');
      var size = 4 + Math.random() * 8;
      var x = Math.random() * 100;
      var delay = Math.random() * 0.5;
      var duration = 1.5 + Math.random() * 2;
      var color = colors[Math.floor(Math.random() * colors.length)];
      particle.style.cssText = 'position:absolute;width:' + size + 'px;height:' + size + 'px;background:' + color +
        ';border-radius:' + (Math.random() > 0.5 ? '50%' : '1px') +
        ';left:' + x + '%;top:-10px;animation:confettiFall ' + duration + 's ease-in ' + delay + 's forwards;pointer-events:none;';
      container.appendChild(particle);
    }

    if (!document.getElementById('confetti-keyframes')) {
      var style = document.createElement('style');
      style.id = 'confetti-keyframes';
      style.textContent = '@keyframes confettiFall{0%{transform:translateY(0) rotate(0deg);opacity:1}100%{transform:translateY(100vh) rotate(720deg);opacity:0}}';
      document.head.appendChild(style);
    }
  }

  // ── Weekly Summary Card ───────────────────────────────────────
  function renderWeeklySummary(summary) {
    var d = summary.data || {};
    var container = document.getElementById('checkinAlertContainer');
    if (!container) return;

    var card = document.createElement('div');
    card.style.cssText = 'background:var(--surface);border:1px solid var(--border);border-radius:13px;padding:18px 20px;margin-bottom:16px;';

    var header = document.createElement('div');
    header.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:12px;';
    var icon = document.createElement('i');
    icon.className = 'fas fa-chart-bar';
    icon.style.cssText = 'color:var(--red);font-size:.9rem;';
    var label = document.createElement('span');
    label.style.cssText = 'font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--gray);';
    label.textContent = 'Tu Semana en WellCore';
    header.appendChild(icon);
    header.appendChild(label);
    card.appendChild(header);

    var grid = document.createElement('div');
    grid.style.cssText = 'display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;';

    function addStat(labelText, value, color) {
      var stat = document.createElement('div');
      stat.style.cssText = 'background:rgba(255,255,255,0.03);border-radius:8px;padding:10px;';
      var l = document.createElement('div');
      l.style.cssText = 'font-size:.7rem;color:var(--gray);margin-bottom:2px;';
      l.textContent = labelText;
      var v = document.createElement('div');
      v.style.cssText = 'font-size:1rem;font-weight:700;color:' + (color || 'var(--white)') + ';';
      v.textContent = value;
      stat.appendChild(l);
      stat.appendChild(v);
      grid.appendChild(stat);
    }

    if (d.bienestar !== null && d.bienestar !== undefined) {
      var bColor = d.bienestar >= 7 ? '#22c55e' : d.bienestar >= 5 ? '#f59e0b' : '#ef4444';
      var bArrow = '';
      if (d.prev_bienestar !== null && d.prev_bienestar !== undefined) {
        bArrow = d.bienestar > d.prev_bienestar ? ' \u2191' : (d.bienestar < d.prev_bienestar ? ' \u2193' : ' =');
      }
      addStat('Bienestar', d.bienestar + '/10' + bArrow, bColor);
    }
    if (d.dias_entrenados !== null && d.dias_entrenados !== undefined) {
      addStat('Dias Entrenados', d.dias_entrenados + '/5');
    }
    if (d.streak) addStat('Racha', d.streak + ' dias', '#f59e0b');
    if (d.xp_week) addStat('XP Ganados', '+' + d.xp_week, '#f59e0b');

    card.appendChild(grid);

    // Coach note
    if (summary.coach_note) {
      var note = document.createElement('div');
      note.style.cssText = 'background:rgba(200,16,46,0.06);border:1px solid rgba(200,16,46,0.15);border-radius:8px;padding:12px;font-size:.82rem;color:var(--gray);line-height:1.5;';
      var noteLabel = document.createElement('div');
      noteLabel.style.cssText = 'font-weight:700;color:var(--red);font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;';
      noteLabel.textContent = 'Tu coach dice:';
      note.appendChild(noteLabel);
      note.appendChild(document.createTextNode(summary.coach_note));
      card.appendChild(note);
    }

    container.appendChild(card);
  }

})();
