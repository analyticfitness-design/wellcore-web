/* ===== M26: RETOS GRUPALES - CHALLENGES MODULE ===== */
/* XSS-safe: all user text via textContent; DOM built with createElement */
var Challenges = (function () {
  'use strict';

  var TYPE_COLORS = {
    steps:       '#f59e0b',
    checkins:    '#E31E24',
    weight_loss: '#10b981',
    streak:      '#8b5cf4'
  };
  var TYPE_LABELS = {
    steps:       'Pasos',
    checkins:    'Check-ins',
    weight_loss: 'Perdida de peso',
    streak:      'Racha'
  };
  var TYPE_UNITS = {
    steps:       'pasos',
    checkins:    'check-ins',
    weight_loss: 'kg',
    streak:      'dias'
  };

  var _challenges  = [];
  var _currentLbId = null;

  /* ---- auth ---- */
  function getToken() {
    return (sessionStorage.getItem('wc_preview_token') ||
            localStorage.getItem('wc_token') || '');
  }

  function apiFetch(path, opts) {
    opts = opts || {};
    opts.headers = Object.assign(
      { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + getToken() },
      opts.headers || {}
    );
    return fetch('/api/challenges/' + path, opts);
  }

  /* ---- DOM helpers ---- */
  function el(tag, css) {
    var node = document.createElement(tag);
    if (css) node.style.cssText = css;
    return node;
  }

  function txt(tag, content, css) {
    var node = el(tag, css);
    node.textContent = content;
    return node;
  }

  function clearNode(node) {
    while (node.firstChild) node.removeChild(node.firstChild);
  }

  /* ---- build a single reto card using DOM API ---- */
  function buildCard(ch) {
    var c        = TYPE_COLORS[ch.challenge_type] || '#888';
    var days     = Math.max(0, Math.ceil((new Date(ch.end_date).getTime() - Date.now()) / 86400000));
    var progress = Number(ch.user_progress) || 0;
    var goal     = Number(ch.goal_value);
    var pct      = goal > 0 ? Math.min(100, Math.round((progress / goal) * 100)) : 0;
    var unit     = TYPE_UNITS[ch.challenge_type] || '';

    var card = el('div',
      'background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);' +
      'padding:18px;display:flex;flex-direction:column;gap:10px;position:relative;overflow:hidden;');
    card.id = 'reto-card-' + Number(ch.id);
    card.className = 'v7-glow-track';

    /* color accent top bar */
    card.appendChild(el('div',
      'position:absolute;top:0;left:0;right:0;height:3px;background:' + c + ';opacity:0.7;'));

    /* title + type tag row */
    var titleRow = el('div',
      'display:flex;align-items:flex-start;justify-content:space-between;gap:8px;padding-top:2px;');
    titleRow.appendChild(txt('div', ch.title,
      'font-weight:700;font-size:.95rem;line-height:1.3;'));
    titleRow.appendChild(txt('span', TYPE_LABELS[ch.challenge_type] || ch.challenge_type,
      'font-size:.65rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;white-space:nowrap;' +
      'color:' + c + ';background:' + c + '22;border:1px solid ' + c + '44;border-radius:4px;padding:2px 7px;'));
    card.appendChild(titleRow);

    /* description (user text via textContent) */
    if (ch.description) {
      card.appendChild(txt('div', ch.description,
        'font-size:.77rem;color:var(--gray);line-height:1.5;'));
    }

    /* meta info row */
    var meta = el('div', 'display:flex;gap:14px;font-size:.72rem;color:var(--gray);');
    meta.appendChild(txt('span', Number(ch.participant_count) + ' participantes'));
    meta.appendChild(txt('span', days + ' dias restantes'));
    card.appendChild(meta);

    if (ch.user_joined) {
      /* progress bar */
      var barWrap = el('div',
        'background:rgba(255,255,255,0.07);border-radius:99px;height:7px;overflow:hidden;margin:6px 0 3px;');
      var barFill = el('div',
        'height:100%;width:' + pct + '%;background:' + c + ';border-radius:99px;transition:width .4s ease;');
      barWrap.appendChild(barFill);
      card.appendChild(barWrap);
      card.appendChild(txt('div', pct + '% - ' + progress + ' / ' + goal + ' ' + unit,
        'font-size:.7rem;color:var(--gray);'));

      /* leaderboard button */
      var lbBtn = el('button',
        'width:100%;padding:8px;background:rgba(255,255,255,0.06);border:1px solid var(--border);' +
        'border-radius:8px;color:var(--gray);font-size:.78rem;cursor:pointer;margin-top:4px;');
      lbBtn.textContent = 'Ver Ranking';
      (function (id) {
        lbBtn.addEventListener('click', function () { showLeaderboard(id); });
      }(Number(ch.id)));
      card.appendChild(lbBtn);

      /* progress update form */
      var form = el('div',
        'margin-top:10px;padding-top:10px;border-top:1px solid var(--border);');
      form.id = 'update-form-' + Number(ch.id);
      form.appendChild(txt('div', 'Actualizar mi progreso',
        'font-size:.72rem;color:var(--gray);margin-bottom:6px;'));

      var inputRow = el('div', 'display:flex;gap:8px;');
      var inp = el('input',
        'flex:1;background:var(--surface-2);border:1px solid var(--border);border-radius:6px;' +
        'color:#fff;padding:6px 10px;font-size:.8rem;');
      inp.type  = 'number';
      inp.min   = '0';
      inp.step  = '0.5';
      inp.value = String(progress);
      inp.id    = 'prog-input-' + Number(ch.id);
      inputRow.appendChild(inp);

      var okBtn = el('button',
        'background:' + c + ';border:none;border-radius:6px;color:#fff;' +
        'padding:6px 12px;font-size:.8rem;font-weight:700;cursor:pointer;');
      okBtn.textContent = 'OK';
      (function (id) {
        okBtn.addEventListener('click', function () { updateProgress(id); });
      }(Number(ch.id)));
      inputRow.appendChild(okBtn);
      form.appendChild(inputRow);

      if (ch.user_rank != null) {
        form.appendChild(txt('div', 'Tu posicion: #' + Number(ch.user_rank),
          'font-size:.7rem;color:var(--gray);margin-top:5px;'));
      }
      card.appendChild(form);

    } else {
      /* join button */
      var joinBtn = el('button',
        'margin-top:4px;padding:9px 16px;background:' + c + ';border:none;border-radius:8px;' +
        'color:#fff;font-weight:700;font-size:.82rem;cursor:pointer;transition:opacity .2s;width:100%;');
      joinBtn.textContent = 'Unirme al Reto';
      joinBtn.addEventListener('mouseover', function () { joinBtn.style.opacity = '0.85'; });
      joinBtn.addEventListener('mouseout',  function () { joinBtn.style.opacity = '1'; });
      (function (id) {
        joinBtn.addEventListener('click', function () { join(id); });
      }(Number(ch.id)));
      card.appendChild(joinBtn);
    }

    return card;
  }

  /* ---- render all challenge cards ---- */
  function renderAll() {
    var container = document.getElementById('retosContainer');
    if (!container) return;
    clearNode(container);

    if (!_challenges.length) {
      var empty = el('div',
        'color:var(--gray);padding:40px;text-align:center;grid-column:1/-1;');
      var icon = el('i', 'font-size:2rem;opacity:0.3;display:block;margin-bottom:12px;');
      icon.className = 'fas fa-trophy';
      empty.appendChild(icon);
      empty.appendChild(document.createTextNode('No hay retos activos en este momento.'));
      container.appendChild(empty);
      return;
    }

    _challenges.forEach(function (ch) { container.appendChild(buildCard(ch)); });
  }

  /* ---- init: load challenges from API ---- */
  function init() {
    apiFetch('list.php')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        _challenges = data.challenges || [];
        renderAll();
      })
      .catch(function () {
        var c = document.getElementById('retosContainer');
        if (c) {
          clearNode(c);
          c.appendChild(txt('div', 'Error al cargar retos. Intenta de nuevo.',
            'color:var(--gray);padding:40px;text-align:center;grid-column:1/-1;'));
        }
      });
  }

  /* ---- join a challenge ---- */
  function join(challengeId) {
    challengeId = Number(challengeId);
    var card    = document.getElementById('reto-card-' + challengeId);
    var btn     = card ? card.querySelector('button') : null;
    if (btn) { btn.disabled = true; btn.textContent = 'Uniendose...'; }

    apiFetch('join.php', {
      method: 'POST',
      body:   JSON.stringify({ challenge_id: challengeId })
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.success) {
          var ch = _challenges.find(function (c) { return c.id === challengeId; });
          if (ch) { ch.user_joined = true; ch.user_progress = 0; ch.participant_count += 1; }
          renderAll();
        } else {
          if (btn) { btn.disabled = false; btn.textContent = 'Unirme al Reto'; }
          alert(data.error || 'No se pudo unir al reto.');
        }
      })
      .catch(function () {
        if (btn) { btn.disabled = false; btn.textContent = 'Unirme al Reto'; }
        alert('Error de red. Intenta de nuevo.');
      });
  }

  /* ---- update user progress ---- */
  function updateProgress(challengeId) {
    challengeId = Number(challengeId);
    var inp     = document.getElementById('prog-input-' + challengeId);
    if (!inp) return;
    var val = parseFloat(inp.value);
    if (isNaN(val) || val < 0) { alert('Ingresa un valor valido.'); return; }

    apiFetch('progress.php', {
      method: 'POST',
      body:   JSON.stringify({ challenge_id: challengeId, progress: val })
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.success !== false) {
          var ch = _challenges.find(function (c) { return c.id === challengeId; });
          if (ch) { ch.user_progress = data.progress; ch.user_rank = data.rank; }
          renderAll();
        } else {
          alert(data.error || 'Error al actualizar progreso.');
        }
      })
      .catch(function () { alert('Error de red.'); });
  }

  /* ---- show leaderboard modal ---- */
  function showLeaderboard(challengeId) {
    challengeId  = Number(challengeId);
    _currentLbId = challengeId;
    var modal    = document.getElementById('retosLeaderboardModal');
    var content  = document.getElementById('lbContent');
    if (!modal || !content) return;
    modal.style.display = 'flex';
    clearNode(content);

    /* loading spinner */
    var spinner  = el('div', 'color:var(--gray);text-align:center;padding:30px;');
    var spinIcon = el('i'); spinIcon.className = 'fas fa-spinner fa-spin';
    spinner.appendChild(spinIcon);
    spinner.appendChild(document.createTextNode(' Cargando...'));
    content.appendChild(spinner);

    var ch = _challenges.find(function (c) { return c.id === challengeId; });
    var titleEl    = document.getElementById('lbTitle');
    var subtitleEl = document.getElementById('lbSubtitle');
    if (titleEl)    titleEl.textContent = ch ? ch.title : 'Leaderboard';
    if (subtitleEl) subtitleEl.textContent = ch
      ? (TYPE_LABELS[ch.challenge_type] || '') + ' - Meta: ' + ch.goal_value + ' ' + (TYPE_UNITS[ch.challenge_type] || '')
      : '';

    apiFetch('leaderboard.php?challenge_id=' + challengeId)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        clearNode(content);
        var parts = data.participants || [];

        if (!parts.length) {
          content.appendChild(txt('p', 'Aun no hay participantes.',
            'color:var(--gray);text-align:center;padding:24px;'));
          return;
        }

        var accentColor = ch ? (TYPE_COLORS[ch.challenge_type] || '#E31E24') : '#E31E24';
        var goal        = ch ? Number(ch.goal_value) : null;
        var medals      = ['1st', '2nd', '3rd'];

        var table = document.createElement('table');
        table.style.cssText = 'width:100%;border-collapse:collapse;';

        var thead   = document.createElement('thead');
        var headRow = document.createElement('tr');
        headRow.style.cssText = 'font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--gray);';
        ['#', 'Nombre', 'Progreso', 'Estado'].forEach(function (label, i) {
          headRow.appendChild(txt('th', label,
            'padding:6px 8px;text-align:' + (i > 1 ? 'right' : 'left') + ';'));
        });
        thead.appendChild(headRow);
        table.appendChild(thead);

        var tbody = document.createElement('tbody');
        parts.forEach(function (p, i) {
          var rankDisplay = (p.rank >= 1 && p.rank <= 3) ? medals[p.rank - 1] : '#' + Number(p.rank);
          var row = document.createElement('tr');
          row.style.cssText =
            (p.is_current_user ? 'background:' + accentColor + '11;' :
             (i % 2 === 0 ? 'background:rgba(255,255,255,0.02);' : '')) +
            'border-bottom:1px solid var(--border);';

          /* rank */
          row.appendChild(txt('td', rankDisplay, 'padding:9px 8px;font-size:.8rem;'));

          /* name — user text via textContent */
          var nameCell = el('td', 'padding:9px 8px;font-size:.82rem;' +
            (p.is_current_user ? 'font-weight:700;color:#fff;' : 'color:var(--gray);'));
          nameCell.appendChild(document.createTextNode(p.first_name));
          if (p.is_current_user) {
            nameCell.appendChild(txt('span', ' TU',
              'font-size:.65rem;color:' + accentColor + ';margin-left:4px;'));
          }
          row.appendChild(nameCell);

          /* progress */
          var pctNum = (goal && goal > 0)
            ? ' (' + Math.min(100, Math.round((Number(p.progress) / goal) * 100)) + '%)'
            : '';
          var progCell = el('td',
            'padding:9px 8px;font-size:.82rem;text-align:right;font-family:var(--font-data);');
          progCell.appendChild(document.createTextNode(String(p.progress)));
          if (pctNum) {
            progCell.appendChild(txt('span', pctNum, 'color:var(--gray);font-size:.7rem;'));
          }
          row.appendChild(progCell);

          /* completed */
          var doneCell = el('td',
            'padding:9px 8px;font-size:.72rem;text-align:right;color:' +
            (p.completed_at ? '#10b981' : 'var(--gray)') + ';');
          doneCell.textContent = p.completed_at ? 'Completo' : '-';
          row.appendChild(doneCell);

          tbody.appendChild(row);
        });

        table.appendChild(tbody);
        content.appendChild(table);
      })
      .catch(function () {
        clearNode(content);
        content.appendChild(txt('p', 'Error al cargar ranking.',
          'color:var(--gray);text-align:center;padding:24px;'));
      });
  }

  /* ---- close leaderboard modal ---- */
  function closeLeaderboard() {
    var modal = document.getElementById('retosLeaderboardModal');
    if (modal) modal.style.display = 'none';
    _currentLbId = null;
  }

  /* close on backdrop click */
  document.addEventListener('click', function (e) {
    var modal = document.getElementById('retosLeaderboardModal');
    if (modal && e.target === modal) closeLeaderboard();
  });

  return {
    _inited:          false,
    init:             init,
    join:             join,
    updateProgress:   updateProgress,
    showLeaderboard:  showLeaderboard,
    closeLeaderboard: closeLeaderboard
  };
}());
