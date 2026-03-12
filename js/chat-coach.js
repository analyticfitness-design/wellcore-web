/**
 * WellCore — Chat Coach (client-side)
 * Handles direct messaging between client and coach.
 */
(function() {
  'use strict';

  var _chatLoaded = false;
  var _pollInterval = null;
  var _lastMsgId = 0;

  // Init when chat section is shown
  var _origShow = window.showSection;
  window.showSection = function(id) {
    _origShow(id);
    if (id === 'chat' && !_chatLoaded) {
      _chatLoaded = true;
      initChat();
    }
    if (id === 'chat') {
      markChatRead();
      startChatPoll();
    } else {
      stopChatPoll();
    }
  };

  // Load unread count on page load
  window.addEventListener('DOMContentLoaded', function() {
    setTimeout(pollUnread, 2000);
    setInterval(pollUnread, 30000);
  });

  function getToken() {
    return localStorage.getItem('wc_token') || '';
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

  function pollUnread() {
    if (!getToken()) return;
    apiCall('chat/unread.php').then(function(data) {
      if (!data.ok) return;
      var badge = document.getElementById('chat-unread-badge');
      if (badge) {
        if (data.unread > 0) {
          badge.textContent = data.unread > 99 ? '99+' : data.unread;
          badge.style.display = 'inline-block';
        } else {
          badge.style.display = 'none';
        }
      }
    }).catch(function() {});
  }

  function initChat() {
    loadCoachPresence();
    loadHistory();
  }

  function loadCoachPresence() {
    apiCall('coach/presence.php').then(function(data) {
      if (!data.ok || !data.coach) return;
      var c = data.coach;
      var nameEl = document.getElementById('chat-coach-name');
      var dotEl  = document.getElementById('chat-coach-dot');
      var statEl = document.getElementById('chat-coach-status');
      var avatarEl = document.getElementById('chat-coach-avatar');

      if (nameEl) nameEl.textContent = c.name || 'Tu Coach';
      if (avatarEl) avatarEl.textContent = (c.name || 'C').charAt(0).toUpperCase();
      if (dotEl) {
        var colors = { online: '#22c55e', away: '#f59e0b', offline: '#666' };
        dotEl.style.background = colors[c.status] || '#666';
      }
      if (statEl) statEl.textContent = c.label || '';
    }).catch(function() {});

    var user = null;
    try { user = JSON.parse(localStorage.getItem('wc_user') || '{}'); } catch(e) {}
    var plan = (user && user.plan) || 'esencial';
    var limits = { esencial: 5, metodo: 15, elite: null };
    var lim = limits[plan];
    var limEl = document.getElementById('chat-msg-limit');
    if (limEl) {
      limEl.textContent = lim ? plan.toUpperCase() + ' \u2014 ' + lim + ' msg/semana' : 'ELITE \u2014 Ilimitado';
    }
  }

  function loadHistory() {
    var area = document.getElementById('chat-messages-list');
    if (!area) return;
    area.textContent = '';
    var loader = document.createElement('div');
    loader.style.cssText = 'text-align:center;color:var(--gray);padding:20px;';
    var icon = document.createElement('i');
    icon.className = 'fas fa-spinner fa-spin';
    loader.appendChild(icon);
    area.appendChild(loader);

    apiCall('chat/history.php?limit=50').then(function(data) {
      if (!data.ok) {
        area.textContent = '';
        var err = document.createElement('div');
        err.style.cssText = 'text-align:center;color:var(--gray);padding:20px;';
        err.textContent = 'Error cargando mensajes';
        area.appendChild(err);
        return;
      }
      if (!data.messages || data.messages.length === 0) {
        showEmptyChat(area);
        return;
      }
      renderMessages(data.messages);
    }).catch(function() {
      area.textContent = '';
      var err = document.createElement('div');
      err.style.cssText = 'text-align:center;color:#ef4444;padding:20px;';
      err.textContent = 'Error de conexion';
      area.appendChild(err);
    });
  }

  function showEmptyChat(area) {
    area.textContent = '';
    var wrap = document.createElement('div');
    wrap.style.cssText = 'text-align:center;color:var(--gray);font-size:.8rem;padding:40px 0;';
    var icon = document.createElement('i');
    icon.className = 'fas fa-comments';
    icon.style.cssText = 'font-size:2rem;opacity:.3;display:block;margin-bottom:8px;';
    wrap.appendChild(icon);
    wrap.appendChild(document.createTextNode('Inicia una conversacion con tu coach'));
    area.appendChild(wrap);
  }

  function renderMessages(messages) {
    var area = document.getElementById('chat-messages-list');
    if (!area) return;
    area.textContent = '';

    messages.forEach(function(msg) {
      var isClient = msg.sender_type === 'client';
      var div = document.createElement('div');
      div.style.cssText = 'display:flex;flex-direction:column;' + (isClient ? 'align-items:flex-end;' : 'align-items:flex-start;');

      var bubble = document.createElement('div');
      bubble.style.cssText = 'max-width:80%;padding:10px 14px;border-radius:12px;font-size:.85rem;line-height:1.5;word-wrap:break-word;' +
        (isClient
          ? 'background:var(--red);color:#fff;border-bottom-right-radius:4px;'
          : 'background:rgba(255,255,255,0.08);color:var(--white);border-bottom-left-radius:4px;');
      bubble.textContent = msg.content;

      var meta = document.createElement('div');
      meta.style.cssText = 'font-size:.65rem;color:var(--gray);margin-top:2px;display:flex;align-items:center;gap:4px;';
      meta.appendChild(document.createTextNode(msg.created_at ? formatTime(msg.created_at) : ''));

      if (isClient) {
        var checkIcon = document.createElement('i');
        if (msg.read_at) {
          checkIcon.className = 'fas fa-check-double';
          checkIcon.style.cssText = 'color:#22c55e;font-size:.6rem;';
        } else {
          checkIcon.className = 'fas fa-check';
          checkIcon.style.cssText = 'opacity:.5;font-size:.6rem;';
        }
        meta.appendChild(checkIcon);
      }

      div.appendChild(bubble);
      div.appendChild(meta);
      area.appendChild(div);

      if (msg.id) _lastMsgId = Math.max(_lastMsgId, parseInt(msg.id));
    });

    scrollChatBottom();
  }

  function formatTime(dateStr) {
    try {
      var d = new Date(dateStr.replace(' ', 'T'));
      var now = new Date();
      var isToday = d.toDateString() === now.toDateString();
      var h = d.getHours().toString().padStart(2, '0');
      var m = d.getMinutes().toString().padStart(2, '0');
      if (isToday) return h + ':' + m;
      return d.getDate() + '/' + (d.getMonth()+1) + ' ' + h + ':' + m;
    } catch(e) { return ''; }
  }

  function scrollChatBottom() {
    var area = document.getElementById('chat-messages-area');
    if (area) area.scrollTop = area.scrollHeight;
  }

  window.sendChatMessage = function() {
    var input = document.getElementById('chat-input');
    if (!input) return;
    var msg = input.value.trim();
    if (!msg) return;
    input.value = '';

    var area = document.getElementById('chat-messages-list');
    // Remove empty state
    var emptyEl = area.querySelector('div[style*="text-align:center"]');
    if (emptyEl && emptyEl.parentNode === area) area.removeChild(emptyEl);

    // Optimistic render
    var div = document.createElement('div');
    div.style.cssText = 'display:flex;flex-direction:column;align-items:flex-end;';
    var bubble = document.createElement('div');
    bubble.style.cssText = 'max-width:80%;padding:10px 14px;border-radius:12px;font-size:.85rem;line-height:1.5;word-wrap:break-word;background:var(--red);color:#fff;border-bottom-right-radius:4px;opacity:.7;';
    bubble.textContent = msg;
    var meta = document.createElement('div');
    meta.style.cssText = 'font-size:.65rem;color:var(--gray);margin-top:2px;';
    meta.textContent = 'Enviando...';
    div.appendChild(bubble);
    div.appendChild(meta);
    area.appendChild(div);
    scrollChatBottom();

    apiCall('chat/send.php', { method: 'POST', body: { message: msg } }).then(function(data) {
      if (data.ok) {
        bubble.style.opacity = '1';
        meta.textContent = formatTime(data.sent_at);
        var check = document.createElement('i');
        check.className = 'fas fa-check';
        check.style.cssText = 'opacity:.5;font-size:.6rem;margin-left:4px;';
        meta.appendChild(check);
      } else {
        bubble.style.background = '#333';
        meta.textContent = data.error || 'Error';
        meta.style.color = '#ef4444';
      }
    }).catch(function() {
      bubble.style.background = '#333';
      meta.textContent = 'Error de conexion';
      meta.style.color = '#ef4444';
    });
  };

  function markChatRead() {
    apiCall('chat/mark-read.php', { method: 'POST', body: {} }).then(function() {
      pollUnread();
    }).catch(function() {});
  }

  function startChatPoll() {
    stopChatPoll();
    _pollInterval = setInterval(function() {
      apiCall('chat/history.php?limit=50').then(function(data) {
        if (!data.ok || !data.messages) return;
        var newMax = 0;
        data.messages.forEach(function(m) {
          if (m.id) newMax = Math.max(newMax, parseInt(m.id));
        });
        if (newMax > _lastMsgId) {
          renderMessages(data.messages);
          markChatRead();
        }
      }).catch(function() {});
    }, 5000);
  }

  function stopChatPoll() {
    if (_pollInterval) { clearInterval(_pollInterval); _pollInterval = null; }
  }

})();
