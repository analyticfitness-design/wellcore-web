/* ============================================================
   WellCore v8 — Notificaciones, Perfil, Referidos, Renovacion
   Namespace: V8  (complementa V6 y V7)
   ES5-compatible, sin frameworks.
   Patron de seguridad: innerHTML solo para templates estaticos,
   textContent para insertar datos de usuario.
   ============================================================ */

var V8 = {};

/* ─── Safe DOM helpers ───────────────────────────────────── */
V8._setText = function(id, text) {
  var el = document.getElementById(id);
  if (el) el.textContent = String(text || '');
};
V8._setAttr = function(id, attr, val) {
  var el = document.getElementById(id);
  if (el) el.setAttribute(attr, val);
};
V8._show = function(id) {
  var el = document.getElementById(id);
  if (el) el.style.display = '';
};
V8._hide = function(id) {
  var el = document.getElementById(id);
  if (el) el.style.display = 'none';
};

/* ─── Notifications ──────────────────────────────────────── */
V8.Notifications = {
  _unread: 0,
  _notifications: [],
  _panelOpen: false,

  init: function(bellId, panelId) {
    var self = this;
    self._bellId  = bellId  || 'clientNotifBell';
    self._panelId = panelId || 'clientNotifPanel';
    self.load();
    document.addEventListener('click', function(e) {
      var bell  = document.getElementById(self._bellId);
      var panel = document.getElementById(self._panelId);
      if (!bell || !panel) return;
      if (!bell.contains(e.target) && !panel.contains(e.target)) {
        self.closePanel();
      }
    });
  },

  _getToken: function() {
    return localStorage.getItem('wc_token') || sessionStorage.getItem('wc_preview_token');
  },

  load: function() {
    var self = this;
    var token = self._getToken();
    if (!token) return;
    fetch('/api/notifications/list.php', { headers: { 'Authorization': 'Bearer ' + token } })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      self._unread = data.unread_count || 0;
      self._notifications = data.notifications || [];
      self._updateBadge();
    })
    .catch(function() {});
  },

  _updateBadge: function() {
    var badge = document.getElementById('clientNotifBadge');
    if (!badge) return;
    if (this._unread > 0) {
      badge.textContent = this._unread > 99 ? '99+' : this._unread;
      badge.style.display = 'flex';
    } else {
      badge.style.display = 'none';
    }
  },

  togglePanel: function() {
    if (this._panelOpen) { this.closePanel(); } else { this.openPanel(); }
  },

  openPanel: function() {
    var panel = document.getElementById(this._panelId);
    if (!panel) return;
    panel.classList.add('open');
    this._panelOpen = true;
    this._renderList();
  },

  closePanel: function() {
    var panel = document.getElementById(this._panelId);
    if (!panel) return;
    panel.classList.remove('open');
    this._panelOpen = false;
  },

  _renderList: function() {
    var list = document.getElementById('clientNotifList');
    if (!list) return;
    var items = this._notifications || [];

    // Limpiar lista
    while (list.firstChild) { list.removeChild(list.firstChild); }

    if (items.length === 0) return; // CSS :empty muestra mensaje

    for (var i = 0; i < items.length; i++) {
      var n = items[i];
      var item = document.createElement('div');
      item.className = 'notif-item' + (n.read_at ? '' : ' unread');
      item.dataset.nid = n.id;

      var titleEl = document.createElement('div');
      titleEl.className = 'notif-title';
      titleEl.textContent = n.title;
      item.appendChild(titleEl);

      if (n.body) {
        var bodyEl = document.createElement('div');
        bodyEl.className = 'notif-body';
        bodyEl.textContent = n.body;
        item.appendChild(bodyEl);
      }

      var timeEl = document.createElement('div');
      timeEl.className = 'notif-time';
      timeEl.textContent = V8._timeAgo(n.created_at);
      item.appendChild(timeEl);

      (function(nid, link) {
        item.addEventListener('click', function() {
          V8.Notifications._clickItem(nid, link);
        });
      }(n.id, n.link || ''));

      list.appendChild(item);
    }
  },

  _clickItem: function(id, link) {
    var token = this._getToken();
    if (!token) return;
    fetch('/api/notifications/mark-read', {
      method: 'POST',
      headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id })
    }).catch(function() {});
    for (var i = 0; i < this._notifications.length; i++) {
      if (this._notifications[i].id === id && !this._notifications[i].read_at) {
        this._notifications[i].read_at = new Date().toISOString();
        this._unread = Math.max(0, this._unread - 1);
        this._updateBadge();
        break;
      }
    }
    this._renderList();
    if (link) window.location.href = link;
  },

  markAllRead: function() {
    var self = this;
    var token = this._getToken();
    if (!token) return;
    fetch('/api/notifications/mark-read', {
      method: 'POST',
      headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
      body: JSON.stringify({ all: true })
    })
    .then(function() {
      self._unread = 0;
      self._updateBadge();
      var now = new Date().toISOString();
      for (var i = 0; i < self._notifications.length; i++) {
        self._notifications[i].read_at = now;
      }
      self._renderList();
    })
    .catch(function() {});
  }
};

/* ─── Profile ────────────────────────────────────────────── */
V8.Profile = {
  _data: null,

  _getToken: function() {
    return localStorage.getItem('wc_token') || sessionStorage.getItem('wc_preview_token');
  },

  init: function(containerId) {
    this._containerId = containerId || 'profileTabContent';
    this.load();
  },

  load: function() {
    var self = this;
    var token = self._getToken();
    if (!token) return;
    fetch('/api/client/profile', { headers: { 'Authorization': 'Bearer ' + token } })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      self._data = data.client || {};
      self.render();
      V8.RenewalBanner.check(self._data.subscription_end || null);
      V8.CheckinAlert.check();
    })
    .catch(function() {});
  },

  render: function() {
    var c = document.getElementById(this._containerId);
    if (!c) return;
    var d = this._data || {};
    var plan = d.plan || 'esencial';
    var initial = (d.name || 'U').charAt(0).toUpperCase();

    // Static template — sin datos de usuario en markup
    c.innerHTML = [
      '<div class="profile-card">',
      '  <div style="display:flex;align-items:center;gap:18px;margin-bottom:24px;flex-wrap:wrap">',
      '    <div class="profile-avatar-ring" id="profileAvatar"></div>',
      '    <div>',
      '      <div id="profileNameDisplay" style="font-size:18px;font-weight:800;color:var(--text)"></div>',
      '      <span id="profilePlanBadge"></span>',
      '    </div>',
      '  </div>',
      '  <div class="profile-form-grid">',
      '    <div class="profile-field">',
      '      <label>Nombre</label>',
      '      <input type="text" id="pf_name" maxlength="120">',
      '    </div>',
      '    <div class="profile-field">',
      '      <label>Ciudad</label>',
      '      <input type="text" id="pf_city" maxlength="100">',
      '    </div>',
      '    <div class="profile-field">',
      '      <label>Email</label>',
      '      <input type="email" id="pf_email" readonly>',
      '    </div>',
      '    <div class="profile-field">',
      '      <label>Fecha de nacimiento</label>',
      '      <input type="date" id="pf_birth_date">',
      '    </div>',
      '    <div class="profile-field full">',
      '      <label>Bio <span class="profile-char-count" id="bioCount">0/500</span></label>',
      '      <textarea id="pf_bio" rows="3" maxlength="500" placeholder="Cuentanos sobre tu objetivo..."></textarea>',
      '    </div>',
      '  </div>',
      '  <div style="margin-top:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">',
      '    <button class="btn v6-btn-primary" id="profileSaveBtn">',
      '      <i class="fa-solid fa-check"></i> Guardar cambios',
      '    </button>',
      '    <span id="profileSaveMsg" style="font-size:13px;display:none"></span>',
      '  </div>',
      '  <div style="margin-top:28px;padding-top:20px;border-top:1px solid var(--border)">',
      '    <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px">Plan actual</div>',
      '    <div style="display:flex;align-items:center;gap:12px">',
      '      <span id="profilePlanBadge2"></span>',
      '      <span id="profileSinceDate" style="font-size:13px;color:var(--text-muted)"></span>',
      '    </div>',
      '  </div>',
      '</div>'
    ].join('');

    // Insertar datos via textContent (seguro)
    V8._setText('profileAvatar', initial);
    V8._setText('profileNameDisplay', d.name || '');
    V8._setAttr('pf_name', 'value', d.name || '');
    V8._setAttr('pf_city', 'value', d.city || '');
    V8._setAttr('pf_email', 'value', d.email || '');
    V8._setAttr('pf_birth_date', 'value', d.birth_date || '');

    var bioEl = document.getElementById('pf_bio');
    if (bioEl) {
      bioEl.textContent = d.bio || '';
      bioEl.addEventListener('input', function() {
        V8._setText('bioCount', bioEl.value.length + '/500');
      });
    }
    V8._setText('bioCount', (d.bio || '').length + '/500');

    // Plan badge
    var badgeClass = 'plan-badge-' + plan;
    ['profilePlanBadge', 'profilePlanBadge2'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) { el.className = badgeClass; el.textContent = plan; }
    });

    V8._setText('profileSinceDate', 'Miembro desde ' + V8._formatDate(d.fecha_inicio));

    // Botón guardar
    var saveBtn = document.getElementById('profileSaveBtn');
    if (saveBtn) {
      saveBtn.addEventListener('click', function() { V8.Profile.save(); });
    }
  },

  save: function() {
    var token = this._getToken();
    if (!token) return;

    var payload = {};
    var nameEl  = document.getElementById('pf_name');
    var cityEl  = document.getElementById('pf_city');
    var bdEl    = document.getElementById('pf_birth_date');
    var bioEl   = document.getElementById('pf_bio');

    if (nameEl) payload.name       = nameEl.value;
    if (cityEl) payload.city       = cityEl.value;
    if (bdEl)   payload.birth_date = bdEl.value;
    if (bioEl)  payload.bio        = bioEl.value;

    var btn = document.getElementById('profileSaveBtn');
    var msg = document.getElementById('profileSaveMsg');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando...'; }

    fetch('/api/client/profile', {
      method: 'POST',
      headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-check"></i> Guardar cambios'; }
      if (msg) {
        msg.style.display = 'inline';
        if (data.ok) {
          msg.style.color = '#22c55e';
          msg.textContent = 'Cambios guardados';
          setTimeout(function() { msg.style.display = 'none'; }, 3000);
        } else {
          msg.style.color = '#ef4444';
          msg.textContent = data.error || 'Error al guardar';
        }
      }
    })
    .catch(function() {
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-check"></i> Guardar cambios'; }
      if (msg) { msg.style.display = 'inline'; msg.style.color = '#ef4444'; msg.textContent = 'Error de conexion'; }
    });
  }
};

/* ─── Referrals ──────────────────────────────────────────── */
V8.Referrals = {
  _getToken: function() {
    return localStorage.getItem('wc_token') || sessionStorage.getItem('wc_preview_token');
  },

  init: function(containerId) {
    this._containerId = containerId || 'referralsTabContent';
    this.load();
  },

  load: function() {
    var self = this;
    var token = self._getToken();
    if (!token) return;
    fetch('/api/client/referrals', { headers: { 'Authorization': 'Bearer ' + token } })
    .then(function(r) { return r.json(); })
    .then(function(data) { self.render(data); })
    .catch(function() {});
  },

  _link: '',

  render: function(data) {
    var c = document.getElementById(this._containerId);
    if (!c) return;
    var refs = data.referrals || [];
    this._link = data.referral_link || '';

    // Template estatico
    c.innerHTML = [
      '<div style="margin-bottom:24px">',
      '  <p id="referralDesc" style="font-size:13px;color:var(--text-muted);line-height:1.6;margin-bottom:16px"></p>',
      '  <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">Tu enlace de referido</div>',
      '  <div class="referral-box" style="margin-bottom:12px">',
      '    <span class="referral-link-text" id="referralLinkText"></span>',
      '    <button class="referral-copy-btn" id="referralCopyBtn">',
      '      <i class="fa-solid fa-copy"></i> Copiar',
      '    </button>',
      '  </div>',
      '  <div class="referral-stats">',
      '    <div class="referral-stat"><div class="referral-stat-num" id="rStatConverted">0</div><div class="referral-stat-label">Convertidos</div></div>',
      '    <div class="referral-stat"><div class="referral-stat-num" id="rStatTotal">0</div><div class="referral-stat-label">Total referidos</div></div>',
      '    <div class="referral-stat"><div class="referral-stat-num" id="rStatPending">0</div><div class="referral-stat-label">Semanas pendientes</div></div>',
      '  </div>',
      '</div>',
      '<div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">Historial</div>',
      '<div style="overflow-x:auto"><table class="referral-table" id="referralTable">',
      '<thead><tr><th>Email</th><th>Estado</th><th>Convertido</th></tr></thead>',
      '<tbody id="referralTbody"></tbody>',
      '</table></div>'
    ].join('');

    // Insertar datos de forma segura
    var descEl = document.getElementById('referralDesc');
    if (descEl) descEl.textContent = 'Cada amigo que se convierte en cliente te da 1 semana gratis de suscripcion.';

    V8._setText('referralLinkText', this._link);
    V8._setText('rStatConverted', data.total_converted || 0);
    V8._setText('rStatTotal', refs.length);
    V8._setText('rStatPending', data.reward_pending || 0);

    // Botón copiar
    var copyBtn = document.getElementById('referralCopyBtn');
    if (copyBtn) {
      copyBtn.addEventListener('click', function() { V8.Referrals.copyLink(); });
    }

    // Tabla de referidos via DOM
    var tbody = document.getElementById('referralTbody');
    if (!tbody) return;
    if (refs.length === 0) {
      var emptyRow = document.createElement('tr');
      var emptyTd  = document.createElement('td');
      emptyTd.colSpan = 3;
      emptyTd.style.cssText = 'text-align:center;padding:20px;color:var(--text-muted)';
      emptyTd.textContent = 'Aun no has referido a nadie';
      emptyRow.appendChild(emptyTd);
      tbody.appendChild(emptyRow);
      return;
    }
    var statusLabels = { pending: 'Pendiente', registered: 'Registrado', converted: 'Convertido' };
    for (var i = 0; i < refs.length; i++) {
      var r = refs[i];
      var tr = document.createElement('tr');

      var tdEmail = document.createElement('td');
      tdEmail.textContent = r.referred_email;

      var tdStatus = document.createElement('td');
      var pill = document.createElement('span');
      pill.className = 'status-pill ' + r.status;
      pill.textContent = statusLabels[r.status] || r.status;
      tdStatus.appendChild(pill);

      var tdDate = document.createElement('td');
      tdDate.style.color = 'var(--text-muted)';
      tdDate.textContent = r.converted_at ? V8._formatDate(r.converted_at) : '\u2014';

      tr.appendChild(tdEmail);
      tr.appendChild(tdStatus);
      tr.appendChild(tdDate);
      tbody.appendChild(tr);
    }
  },

  copyLink: function() {
    var link = this._link;
    var btn  = document.getElementById('referralCopyBtn');
    var done = function() {
      if (btn) { btn.classList.add('copied'); btn.innerHTML = '<i class="fa-solid fa-check"></i> Copiado'; }
      setTimeout(function() {
        if (btn) { btn.classList.remove('copied'); btn.innerHTML = '<i class="fa-solid fa-copy"></i> Copiar'; }
      }, 2500);
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(link).then(done).catch(function() { V8.Referrals._fallback(link, done); });
    } else {
      V8.Referrals._fallback(link, done);
    }
  },

  _fallback: function(text, cb) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.cssText = 'position:fixed;left:-9999px;top:-9999px';
    document.body.appendChild(ta);
    ta.focus(); ta.select();
    try { document.execCommand('copy'); } catch(e) {}
    document.body.removeChild(ta);
    if (cb) cb();
  }
};

/* ─── Renewal Banner ─────────────────────────────────────── */
V8.RenewalBanner = {
  check: function(subscriptionEnd) {
    if (!subscriptionEnd) return;
    var end = new Date(subscriptionEnd);
    var daysLeft = Math.ceil((end - Date.now()) / 86400000);
    if (daysLeft > 7 || daysLeft < 0) return;
    var c = document.getElementById('renewalBannerContainer');
    if (!c) return;

    var banner = document.createElement('div');
    banner.className = 'renewal-banner';

    var text = document.createElement('span');
    var fechaStr = end.toLocaleDateString('es-MX', { day: 'numeric', month: 'short' });
    text.textContent = daysLeft <= 0
      ? 'Tu suscripcion vencio. Renueva para recuperar el acceso.'
      : 'Tu suscripcion vence el ' + fechaStr + ' (' + daysLeft + ' dias). No pierdas tu progreso.';

    var link = document.createElement('a');
    link.href = '/pago.html';
    link.textContent = 'Renovar \u2192';

    banner.appendChild(text);
    banner.appendChild(link);
    c.appendChild(banner);
  }
};

/* ─── Checkin Alert ──────────────────────────────────────── */
V8.CheckinAlert = {
  check: function() {
    var token = localStorage.getItem('wc_token') || sessionStorage.getItem('wc_preview_token');
    if (!token) return;
    fetch('/api/checkins?limit=1', { headers: { 'Authorization': 'Bearer ' + token } })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      var checkins = data.checkins || [];
      var needed = checkins.length === 0 ||
        Math.floor((Date.now() - new Date(checkins[0].checkin_date).getTime()) / 86400000) >= 6;
      if (needed) V8.CheckinAlert.show();
    })
    .catch(function() {});
  },

  show: function() {
    var c = document.getElementById('checkinAlertContainer');
    if (!c) return;

    var alert = document.createElement('div');
    alert.className = 'checkin-alert';

    var icon = document.createElement('span');
    icon.className = 'checkin-alert-icon';
    icon.textContent = '\uD83D\uDCCB'; // 📋

    var textDiv = document.createElement('div');
    textDiv.className = 'checkin-alert-text';
    var strong = document.createElement('strong');
    strong.textContent = 'Es hora de tu check-in semanal';
    var p = document.createElement('p');
    p.style.margin = '0';
    p.textContent = 'Cuentale a tu coach como vas esta semana.';
    textDiv.appendChild(strong);
    textDiv.appendChild(p);

    var btn = document.createElement('button');
    btn.className = 'btn v6-btn-primary';
    btn.style.flexShrink = '0';
    btn.textContent = 'Enviar check-in';
    btn.addEventListener('click', V8.CheckinAlert.scrollToCheckin);

    alert.appendChild(icon);
    alert.appendChild(textDiv);
    alert.appendChild(btn);
    c.appendChild(alert);
  },

  scrollToCheckin: function() {
    // Usar showSection si existe (cliente.html), fallback a click en nav-checkin
    if (typeof showSection === 'function') {
      showSection('checkin');
    } else {
      var el = document.getElementById('nav-checkin') || document.querySelector('[data-tab="checkins"]');
      if (el) el.click();
    }
  }
};

/* ─── Admin Notifications bell (misma logica, user_type=admin) ─ */
V8.AdminNotifications = {
  _unread: 0,
  _notifications: [],

  _getToken: function() {
    return localStorage.getItem('wc_admin_token') || localStorage.getItem('wc_token');
  },

  init: function() {
    this.load();
  },

  load: function() {
    var self = this;
    var token = self._getToken();
    if (!token) return;
    fetch('/api/notifications/list.php', { headers: { 'Authorization': 'Bearer ' + token } })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      self._unread = data.unread_count || 0;
      self._notifications = data.notifications || [];
      var badge = document.getElementById('adminNotifBadge');
      if (badge) {
        if (self._unread > 0) { badge.textContent = self._unread; badge.style.display = 'flex'; }
        else { badge.style.display = 'none'; }
      }
    })
    .catch(function() {});
  }
};

/* ─── Utilities ──────────────────────────────────────────── */
V8._formatDate = function(dateStr) {
  if (!dateStr) return '\u2014';
  try {
    return new Date(dateStr).toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' });
  } catch(e) { return dateStr; }
};

V8._timeAgo = function(dateStr) {
  if (!dateStr) return '';
  var diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
  if (diff < 60)    return 'Hace un momento';
  if (diff < 3600)  return 'Hace ' + Math.floor(diff / 60) + ' min';
  if (diff < 86400) return 'Hace ' + Math.floor(diff / 3600) + 'h';
  var d = Math.floor(diff / 86400);
  return 'Hace ' + d + (d === 1 ? ' dia' : ' dias');
};
