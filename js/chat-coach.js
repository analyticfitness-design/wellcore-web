/**
 * WellCore — Chat Coach (client-side)
 * Handles direct messaging between client and coach.
 * Features: typing indicator, read receipts, sound alerts, photo sharing.
 */
(function() {
  'use strict';

  var _chatLoaded = false;
  var _pollInterval = null;
  var _typingInterval = null;
  var _typingPollInterval = null;
  var _lastMsgId = 0;
  var _lastMsgCount = 0;
  var _isTyping = false;
  var _typingTimeout = null;

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
      startTypingPoll();
    } else {
      stopChatPoll();
      stopTypingPoll();
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
          // Play sound if new messages arrived and chat not active
          var activeSection = document.querySelector('.section.active');
          var chatActive = activeSection && activeSection.id === 'sec-chat';
          if (!chatActive && window.WCSound) WCSound.play('messageReceived');
        } else {
          badge.style.display = 'none';
        }
      }
    }).catch(function() {});
  }

  function initChat() {
    loadCoachPresence();
    loadHistory();
    setupTypingDetection();
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
      _lastMsgCount = (data.messages || []).length;
      if (!data.messages || data.messages.length === 0) {
        showEmptyChat(area);
        return;
      }
      renderMessages(data.messages, false);
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

  function renderMessages(messages, playSound) {
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
        // Read receipts
        if (msg.read_at) {
          var checkIcon = document.createElement('i');
          checkIcon.className = 'fas fa-check-double';
          checkIcon.style.cssText = 'color:#22c55e;font-size:.6rem;';
          checkIcon.title = 'Visto ' + formatTime(msg.read_at);
          meta.appendChild(checkIcon);
        } else {
          var singleCheck = document.createElement('i');
          singleCheck.className = 'fas fa-check';
          singleCheck.style.cssText = 'opacity:.5;font-size:.6rem;';
          singleCheck.title = 'Enviado';
          meta.appendChild(singleCheck);
        }
      }

      div.appendChild(bubble);
      div.appendChild(meta);
      area.appendChild(div);

      if (msg.id) _lastMsgId = Math.max(_lastMsgId, parseInt(msg.id));
    });

    scrollChatBottom();

    // Play sound for new coach messages
    if (playSound && messages.length > _lastMsgCount) {
      var lastMsg = messages[messages.length - 1];
      if (lastMsg && lastMsg.sender_type === 'coach' && window.WCSound) {
        WCSound.play('messageReceived');
      }
    }
    _lastMsgCount = messages.length;
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

  // ── Typing Indicator ─────────────────────────────

  function setupTypingDetection() {
    var input = document.getElementById('chat-input');
    if (!input) return;
    input.addEventListener('input', function() {
      if (!_isTyping) {
        _isTyping = true;
        sendTypingSignal();
      }
      clearTimeout(_typingTimeout);
      _typingTimeout = setTimeout(function() { _isTyping = false; }, 3000);
    });
  }

  function sendTypingSignal() {
    apiCall('chat/typing.php', { method: 'POST', body: {} }).catch(function() {});
  }

  function startTypingPoll() {
    stopTypingPoll();
    _typingPollInterval = setInterval(function() {
      apiCall('chat/typing.php').then(function(data) {
        var indicator = document.getElementById('chat-typing-indicator');
        if (!indicator) return;
        if (data.ok && data.typing) {
          indicator.style.display = 'flex';
        } else {
          indicator.style.display = 'none';
        }
      }).catch(function() {});
    }, 2000);
  }

  function stopTypingPoll() {
    if (_typingPollInterval) { clearInterval(_typingPollInterval); _typingPollInterval = null; }
  }

  // ── Send Message ──────────────────────────────────

  window.sendChatMessage = function() {
    var input = document.getElementById('chat-input');
    if (!input) return;
    var msg = input.value.trim();
    if (!msg) return;
    input.value = '';
    _isTyping = false;

    var area = document.getElementById('chat-messages-list');
    var emptyEl = area.querySelector('div[style*="text-align:center"]');
    if (emptyEl && emptyEl.parentNode === area) area.removeChild(emptyEl);

    // Optimistic render
    var div = document.createElement('div');
    div.style.cssText = 'display:flex;flex-direction:column;align-items:flex-end;';
    var bubble = document.createElement('div');
    bubble.style.cssText = 'max-width:80%;padding:10px 14px;border-radius:12px;font-size:.85rem;line-height:1.5;word-wrap:break-word;background:var(--red);color:#fff;border-bottom-right-radius:4px;opacity:.7;';
    bubble.textContent = msg;
    var meta = document.createElement('div');
    meta.style.cssText = 'font-size:.65rem;color:var(--gray);margin-top:2px;display:flex;align-items:center;gap:4px;';
    meta.textContent = 'Enviando...';
    div.appendChild(bubble);
    div.appendChild(meta);
    area.appendChild(div);
    scrollChatBottom();

    if (window.WCSound) WCSound.play('toggleOn');

    apiCall('chat/send.php', { method: 'POST', body: { message: msg } }).then(function(data) {
      if (data.ok) {
        bubble.style.opacity = '1';
        meta.textContent = '';
        meta.appendChild(document.createTextNode(formatTime(data.sent_at)));
        var check = document.createElement('i');
        check.className = 'fas fa-check';
        check.style.cssText = 'opacity:.5;font-size:.6rem;';
        meta.appendChild(check);
      } else {
        bubble.style.background = '#333';
        meta.textContent = data.error || 'Error';
        meta.style.color = '#ef4444';
        if (window.WCSound) WCSound.play('error');
      }
    }).catch(function() {
      bubble.style.background = '#333';
      meta.textContent = 'Error de conexion';
      meta.style.color = '#ef4444';
      if (window.WCSound) WCSound.play('error');
    });
  };

  // ── Photo Sharing ─────────────────────────────────

  window.chatSendPhoto = function() {
    var fileInput = document.getElementById('chat-photo-input');
    if (!fileInput) {
      fileInput = document.createElement('input');
      fileInput.type = 'file';
      fileInput.id = 'chat-photo-input';
      fileInput.accept = 'image/*';
      fileInput.style.display = 'none';
      document.body.appendChild(fileInput);
      fileInput.addEventListener('change', function() {
        if (!fileInput.files || !fileInput.files[0]) return;
        uploadChatPhoto(fileInput.files[0]);
        fileInput.value = '';
      });
    }
    fileInput.click();
  };

  function uploadChatPhoto(file) {
    if (file.size > 5 * 1024 * 1024) {
      if (window.showToast) showToast('Imagen demasiado grande (max 5MB)', '#ef4444');
      return;
    }

    var area = document.getElementById('chat-messages-list');
    // Show uploading indicator
    var div = document.createElement('div');
    div.style.cssText = 'display:flex;flex-direction:column;align-items:flex-end;';
    var bubble = document.createElement('div');
    bubble.style.cssText = 'max-width:80%;padding:10px 14px;border-radius:12px;font-size:.82rem;background:var(--red);color:#fff;border-bottom-right-radius:4px;opacity:.6;';
    bubble.textContent = 'Enviando foto...';
    var progress = document.createElement('div');
    progress.style.cssText = 'width:100%;height:3px;background:rgba(255,255,255,.2);border-radius:2px;margin-top:6px;overflow:hidden;';
    var bar = document.createElement('div');
    bar.style.cssText = 'width:0%;height:100%;background:#fff;border-radius:2px;transition:width .3s;';
    progress.appendChild(bar);
    bubble.appendChild(progress);
    div.appendChild(bubble);
    area.appendChild(div);
    scrollChatBottom();

    var formData = new FormData();
    formData.append('photo', file);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/api/chat/send-photo.php');
    xhr.setRequestHeader('Authorization', 'Bearer ' + getToken());

    xhr.upload.addEventListener('progress', function(e) {
      if (e.lengthComputable) {
        bar.style.width = Math.round((e.loaded / e.total) * 100) + '%';
      }
    });

    xhr.addEventListener('load', function() {
      try {
        var data = JSON.parse(xhr.responseText);
        if (data.ok) {
          bubble.textContent = '';
          bubble.style.opacity = '1';
          bubble.style.padding = '4px';
          var img = document.createElement('img');
          img.src = data.photo_url;
          img.style.cssText = 'max-width:200px;border-radius:8px;display:block;';
          img.alt = 'Foto enviada';
          bubble.appendChild(img);
          if (window.WCSound) WCSound.play('photoUploaded');
        } else {
          bubble.textContent = 'Error: ' + (data.error || 'No se pudo enviar');
          bubble.style.background = '#333';
          if (window.WCSound) WCSound.play('error');
        }
      } catch(e) {
        bubble.textContent = 'Error al enviar foto';
        bubble.style.background = '#333';
      }
    });

    xhr.addEventListener('error', function() {
      bubble.textContent = 'Error de conexion';
      bubble.style.background = '#333';
    });

    xhr.send(formData);
  }

  // ── Read + Poll ───────────────────────────────────

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
          renderMessages(data.messages, true);
          markChatRead();
        }
      }).catch(function() {});
    }, 5000);
  }

  function stopChatPoll() {
    if (_pollInterval) { clearInterval(_pollInterval); _pollInterval = null; }
  }

})();
