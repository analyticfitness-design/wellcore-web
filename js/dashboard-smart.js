/**
 * WellCore — Smart Dashboard (Bloque B)
 * Contextual greeting + Daily Mission widget
 */
(function() {
  'use strict';

  window.addEventListener('DOMContentLoaded', function() {
    setTimeout(initSmartDashboard, 800);
  });

  function getToken() { return localStorage.getItem('wc_token') || ''; }
  function getUser() {
    try { return JSON.parse(localStorage.getItem('wc_user') || '{}'); } catch(e) { return {}; }
  }

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

  function initSmartDashboard() {
    if (!getToken()) return;
    updateContextualGreeting();
    loadDailyMissions();
  }

  // ── Contextual Greeting ───────────────────────────────────────
  function updateContextualGreeting() {
    var user = getUser();
    var name = (user.name || '').split(' ')[0] || '';
    if (!name) return;

    var h = new Date().getHours();
    var greeting = '';
    var sub = '';

    // Check for streak in XP widget
    var streakEl = document.getElementById('xp-streak-count');
    var streak = streakEl ? parseInt(streakEl.textContent) || 0 : 0;

    // Check birthday
    var isBirthday = false;
    if (user.birth_date) {
      var bd = new Date(user.birth_date);
      var now = new Date();
      if (bd.getMonth() === now.getMonth() && bd.getDate() === now.getDate()) {
        isBirthday = true;
      }
    }

    // Check days since join
    var daysSinceJoin = 999;
    if (user.created_at) {
      var joined = new Date(user.created_at);
      daysSinceJoin = Math.floor((Date.now() - joined.getTime()) / 86400000);
    }

    if (isBirthday) {
      greeting = 'Feliz cumple, ' + name + '!';
      sub = 'Hoy entrena el cumpleanero';
    } else if (streak >= 7) {
      greeting = name + ', ' + streak + ' dias seguidos';
      sub = 'No rompas la racha';
    } else if (daysSinceJoin <= 7) {
      greeting = 'Semana ' + Math.ceil(daysSinceJoin / 7) + ', ' + name;
      sub = 'Cada dia cuenta en tu transformacion';
    } else if (h >= 6 && h < 12) {
      greeting = 'Buenos dias, ' + name;
      sub = 'Nuevo dia, nuevas oportunidades';
    } else if (h >= 12 && h < 18) {
      greeting = 'Buenas tardes, ' + name;
      sub = 'Ya entrenaste hoy?';
    } else {
      greeting = 'Buenas noches, ' + name;
      sub = 'Registra tus habitos antes de dormir';
    }

    var greetEl = document.getElementById('welcome-greeting');
    if (greetEl) greetEl.textContent = greeting;

    // Update date with contextual sub
    var dateEl = document.getElementById('welcome-date');
    if (dateEl && sub) {
      var now = new Date();
      var days = ['Domingo','Lunes','Martes','Miercoles','Jueves','Viernes','Sabado'];
      var months = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
      dateEl.textContent = days[now.getDay()] + ' ' + now.getDate() + ' ' + months[now.getMonth()] + ' \u2014 ' + sub;
    }
  }

  // ── Daily Missions ────────────────────────────────────────────
  function loadDailyMissions() {
    apiCall('client/daily-mission.php').then(function(data) {
      if (!data.ok) return;
      renderMissions(data);
    }).catch(function() {});
  }

  function renderMissions(data) {
    var card = document.getElementById('daily-mission-card');
    var items = document.getElementById('mission-items');
    var progressLabel = document.getElementById('mission-progress-label');
    var barFill = document.getElementById('mission-bar-fill');
    var xpMsg = document.getElementById('mission-xp-msg');
    if (!card || !items) return;

    card.style.display = 'block';
    items.textContent = '';

    var missions = data.missions || [];
    var completed = data.completed || 0;
    var total = data.total || 3;

    missions.forEach(function(m, idx) {
      var row = document.createElement('div');
      row.style.cssText = 'display:flex;align-items:center;gap:12px;padding:10px 12px;background:rgba(255,255,255,0.03);border-radius:8px;cursor:pointer;transition:all .15s;';
      if (m.done) row.style.opacity = '0.6';

      var check = document.createElement('div');
      check.style.cssText = 'width:24px;height:24px;border-radius:50%;border:2px solid ' + (m.done ? '#22c55e' : 'var(--border)') + ';display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s;';
      if (m.done) {
        check.style.background = '#22c55e';
        var checkIcon = document.createElement('i');
        checkIcon.className = 'fas fa-check';
        checkIcon.style.cssText = 'color:#fff;font-size:.6rem;';
        check.appendChild(checkIcon);
      }

      var icon = document.createElement('i');
      icon.className = 'fas ' + (m.icon || 'fa-circle');
      icon.style.cssText = 'color:var(--red);font-size:.85rem;width:20px;text-align:center;';

      var textWrap = document.createElement('div');
      textWrap.style.cssText = 'flex:1;';
      var title = document.createElement('div');
      title.style.cssText = 'font-size:.84rem;font-weight:600;color:var(--white);' + (m.done ? 'text-decoration:line-through;' : '');
      title.textContent = m.title;
      var desc = document.createElement('div');
      desc.style.cssText = 'font-size:.72rem;color:var(--gray);margin-top:1px;';
      desc.textContent = m.description || '';
      textWrap.appendChild(title);
      textWrap.appendChild(desc);

      row.appendChild(check);
      row.appendChild(icon);
      row.appendChild(textWrap);

      if (!m.done) {
        row.addEventListener('click', function() { completeMission(idx); });
        row.addEventListener('mouseenter', function() { row.style.background = 'rgba(255,255,255,0.06)'; });
        row.addEventListener('mouseleave', function() { row.style.background = 'rgba(255,255,255,0.03)'; });
      }

      items.appendChild(row);
    });

    if (progressLabel) progressLabel.textContent = completed + '/' + total;
    if (barFill) barFill.style.width = (total > 0 ? Math.round((completed / total) * 100) : 0) + '%';
    if (xpMsg) xpMsg.style.display = (completed >= total && !data.xp_awarded) ? 'none' : (completed >= total ? 'block' : 'none');
  }

  function completeMission(index) {
    apiCall('client/daily-mission.php', { method: 'POST', body: { index: index } }).then(function(data) {
      if (!data.ok) return;
      renderMissions(data);
      if (data.xp_gained && data.xp_gained > 0) {
        var xpMsg = document.getElementById('mission-xp-msg');
        if (xpMsg) {
          xpMsg.style.display = 'block';
          xpMsg.textContent = '+' + data.xp_gained + ' XP ganados!';
        }
      }
    }).catch(function() {});
  }

})();
