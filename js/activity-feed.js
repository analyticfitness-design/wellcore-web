/**
 * Activity Feed — Real-time admin notifications
 * WellCore v7 Integration
 */

class ActivityFeed {
  constructor() {
    this.events = [];
    this.pollingInterval = null;
    this.isCollapsed = false;
    this.container = null;
    this.filterClientId = null;
    this.filterType = null;
    this.days = 7; // default range
  }

  init() {
    this.render();
    this.attachListeners();
    this.startPolling();
  }

  render() {
    this.container = document.createElement('div');
    this.container.className = 'activity-feed';
    this.container.id = 'activity-feed-panel';

    // Header
    const header = document.createElement('div');
    header.className = 'activity-feed__header';

    const title = document.createElement('span');
    title.className = 'activity-feed__title';
    title.textContent = 'Activity Feed';

    const controls = document.createElement('div');
    controls.className = 'activity-feed__controls';

    const collapseBtn = document.createElement('button');
    collapseBtn.className = 'activity-feed__btn';
    collapseBtn.id = 'af-collapse';
    collapseBtn.textContent = '_';
    collapseBtn.title = 'Collapse';

    const closeBtn = document.createElement('button');
    closeBtn.className = 'activity-feed__btn';
    closeBtn.id = 'af-close';
    closeBtn.textContent = 'X';
    closeBtn.title = 'Close';

    controls.appendChild(collapseBtn);
    controls.appendChild(closeBtn);
    header.appendChild(title);
    header.appendChild(controls);

    // Stats section
    const stats = document.createElement('div');
    stats.className = 'activity-feed__stats';

    const countRow = document.createElement('div');
    countRow.className = 'activity-feed__count-row';

    const countDiv = document.createElement('div');
    countDiv.className = 'activity-feed__count';
    countDiv.id = 'af-count-label';
    const countNum = document.createElement('span');
    countNum.className = 'activity-feed__count-number';
    countNum.id = 'af-count';
    countNum.textContent = '0';
    countDiv.appendChild(countNum);
    countDiv.appendChild(document.createTextNode(' EVENTS'));

    // Days range selector
    const rangeDiv = document.createElement('div');
    rangeDiv.className = 'activity-feed__range';
    const rangeSelect = document.createElement('select');
    rangeSelect.id = 'af-range';
    [
      { v: '1', t: 'Hoy' },
      { v: '3', t: '3 dias' },
      { v: '7', t: '7 dias' },
      { v: '14', t: '14 dias' },
      { v: '30', t: '30 dias' },
    ].forEach(o => {
      const opt = document.createElement('option');
      opt.value = o.v;
      opt.textContent = o.t;
      if (o.v === '7') opt.selected = true;
      rangeSelect.appendChild(opt);
    });
    rangeDiv.appendChild(rangeSelect);

    countRow.appendChild(countDiv);
    countRow.appendChild(rangeDiv);

    const breakdown = document.createElement('div');
    breakdown.className = 'activity-feed__breakdown';
    breakdown.id = 'af-breakdown';

    stats.appendChild(countRow);
    stats.appendChild(breakdown);

    // Filter section
    const filter = document.createElement('div');
    filter.className = 'activity-feed__filter';
    const select = document.createElement('select');
    select.id = 'af-client-filter';
    const defaultOpt = document.createElement('option');
    defaultOpt.value = '';
    defaultOpt.textContent = 'Todos los clientes';
    select.appendChild(defaultOpt);
    filter.appendChild(select);

    // Events list
    const list = document.createElement('div');
    list.className = 'activity-feed__list';
    list.id = 'af-events-list';
    const empty = document.createElement('div');
    empty.className = 'activity-feed__empty';
    empty.textContent = 'Cargando...';
    list.appendChild(empty);

    // Assemble
    this.container.appendChild(header);
    this.container.appendChild(stats);
    this.container.appendChild(filter);
    this.container.appendChild(list);
    document.body.appendChild(this.container);
  }

  attachListeners() {
    document.getElementById('af-collapse').addEventListener('click', (e) => { e.stopPropagation(); this.toggle(); });
    document.getElementById('af-close').addEventListener('click', (e) => { e.stopPropagation(); this.close(); });

    this.container.addEventListener('click', () => {
      if (this.isCollapsed) this.toggle();
    });

    document.getElementById('af-client-filter').addEventListener('change', (e) => {
      this.filterClientId = e.target.value || null;
      this.updateFeed(true);
    });

    document.getElementById('af-range').addEventListener('change', (e) => {
      this.days = parseInt(e.target.value) || 7;
      this.updateFeed(true);
    });

    // Click on event to impersonate
    document.addEventListener('click', (e) => {
      const eventEl = e.target.closest('.activity-feed__event');
      if (eventEl) {
        const clientId = eventEl.dataset.clientId;
        if (clientId) this.impersonateClient(clientId);
      }
    });
  }

  startPolling() {
    this.updateFeed();
    this.pollingInterval = setInterval(() => this.updateFeed(), 8000);
  }

  async updateFeed(force = false) {
    try {
      const params = new URLSearchParams();
      if (this.filterClientId) params.append('client_id', this.filterClientId);
      params.append('days', this.days);
      params.append('limit', '50');

      const token = localStorage.getItem('wc_token');
      const headers = {};
      if (token) headers['Authorization'] = 'Bearer ' + token;

      const response = await fetch(`/api/admin/activity-feed.php?${params.toString()}`, { headers });
      if (!response.ok) throw new Error(`API error: ${response.status}`);

      const data = await response.json();

      // Detect new events
      const oldEventIds = this.events.map(e => `${e.client_id}_${e.timestamp}`);
      const newEventIds = data.events.map(e => `${e.client_id}_${e.timestamp}`);
      const hasNewEvents = newEventIds.some(id => !oldEventIds.includes(id));

      this.events = data.events;
      this.renderStats(data.today_count, data.range_count, data.days, data.breakdown);
      this.renderEvents(data.events, hasNewEvents);

      if (!this.filterDropdownPopulated) {
        this.populateFilterDropdown();
      }
    } catch (error) {
      console.error('Activity Feed error:', error);
      this.renderEmpty();
    }
  }

  renderStats(todayCount, rangeCount, days, breakdown) {
    const labelEl = document.getElementById('af-count-label');
    if (labelEl) {
      // Clear and rebuild safely using DOM methods
      while (labelEl.firstChild) labelEl.removeChild(labelEl.firstChild);
      const num = document.createElement('span');
      num.className = 'activity-feed__count-number';
      num.id = 'af-count';
      num.textContent = rangeCount;
      labelEl.appendChild(num);
      labelEl.appendChild(document.createTextNode(' EVENTS '));
      if (todayCount > 0) {
        const todayBadge = document.createElement('span');
        todayBadge.className = 'activity-feed__today-badge';
        todayBadge.textContent = todayCount + ' hoy';
        labelEl.appendChild(todayBadge);
      }
    }

    const breakdownEl = document.getElementById('af-breakdown');
    if (!breakdownEl) return;

    const chips = [
      { label: 'Check-in', emoji: '✅', count: breakdown.checkin || 0, type: 'checkin' },
      { label: 'Training', emoji: '🏋️', count: breakdown.training || 0, type: 'training' },
      { label: 'Metrics', emoji: '📈', count: breakdown.metric || 0, type: 'metric' },
      { label: 'Weight', emoji: '⚖️', count: breakdown.weight || 0, type: 'weight' },
      { label: 'Retos', emoji: '🏆', count: breakdown.challenge || 0, type: 'challenge' },
      { label: 'Msgs', emoji: '💬', count: breakdown.message || 0, type: 'message' },
      { label: 'XP', emoji: '⭐', count: breakdown.xp || 0, type: 'xp' },
      { label: 'Habits', emoji: '🎯', count: breakdown.habit || 0, type: 'habit' },
      { label: 'Fotos', emoji: '📸', count: breakdown.photo || 0, type: 'photo' },
      { label: 'Social', emoji: '👥', count: breakdown.community || 0, type: 'community' },
    ];

    while (breakdownEl.firstChild) breakdownEl.removeChild(breakdownEl.firstChild);
    chips.forEach(chip => {
      const chipEl = document.createElement('div');
      chipEl.className = 'activity-feed__chip';
      if (chip.count > 0) chipEl.classList.add('activity-feed__chip--active');
      chipEl.dataset.type = chip.type;
      chipEl.textContent = chip.emoji + ' ' + chip.count;
      chipEl.title = chip.label;
      breakdownEl.appendChild(chipEl);
    });
  }

  renderEvents(events, hasNew = false) {
    const list = document.getElementById('af-events-list');
    if (!list) return;

    if (events.length === 0) {
      this.renderEmpty();
      return;
    }

    // Group by day
    const grouped = {};
    events.forEach(event => {
      const day = event.timestamp.substring(0, 10); // YYYY-MM-DD
      if (!grouped[day]) grouped[day] = [];
      grouped[day].push(event);
    });

    while (list.firstChild) list.removeChild(list.firstChild);
    const today = new Date().toISOString().substring(0, 10);
    const yesterday = new Date(Date.now() - 86400000).toISOString().substring(0, 10);

    Object.keys(grouped).sort().reverse().forEach(day => {
      // Day header
      const dayHeader = document.createElement('div');
      dayHeader.className = 'activity-feed__day-header';
      if (day === today) dayHeader.textContent = 'Hoy — ' + grouped[day].length + ' eventos';
      else if (day === yesterday) dayHeader.textContent = 'Ayer — ' + grouped[day].length + ' eventos';
      else dayHeader.textContent = this.formatDate(day) + ' — ' + grouped[day].length + ' eventos';
      list.appendChild(dayHeader);

      // Events for this day
      grouped[day].forEach((event, idx) => {
        const eventEl = this.createEventElement(event, idx, hasNew && day === today);
        list.appendChild(eventEl);
      });
    });
  }

  formatDate(dateStr) {
    const d = new Date(dateStr + 'T12:00:00');
    const months = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    const dayNames = ['Dom','Lun','Mar','Mie','Jue','Vie','Sab'];
    return dayNames[d.getDay()] + ' ' + d.getDate() + ' ' + months[d.getMonth()];
  }

  createEventElement(event, idx, hasNew) {
    const timeAgo = this.getTimeAgo(new Date(event.timestamp));
    const actionEmoji = this.getActionEmoji(event.action);
    const avatarClass = 'activity-feed__avatar--' + event.action;

    const eventDiv = document.createElement('div');
    eventDiv.className = 'activity-feed__event';
    if (hasNew && idx === 0) eventDiv.classList.add('activity-feed__event--new');
    eventDiv.dataset.clientId = event.client_id;

    // Header
    const header = document.createElement('div');
    header.className = 'activity-feed__event-header';

    const clientInfo = document.createElement('div');
    clientInfo.className = 'activity-feed__event-client';

    const avatar = document.createElement('div');
    avatar.className = 'activity-feed__avatar ' + avatarClass;
    avatar.textContent = event.client_avatar;

    const info = document.createElement('div');
    info.className = 'activity-feed__client-info';

    const name = document.createElement('p');
    name.className = 'activity-feed__client-name';
    name.textContent = event.client_name;

    info.appendChild(name);

    if (event.client_plan) {
      const plan = document.createElement('span');
      plan.className = 'activity-feed__plan-badge activity-feed__plan-badge--' + event.client_plan;
      plan.textContent = event.client_plan;
      info.appendChild(plan);
    }

    const timestamp = document.createElement('span');
    timestamp.className = 'activity-feed__timestamp';
    timestamp.textContent = timeAgo;

    clientInfo.appendChild(avatar);
    clientInfo.appendChild(info);
    clientInfo.appendChild(timestamp);
    header.appendChild(clientInfo);

    // Action
    const action = document.createElement('div');
    action.className = 'activity-feed__event-action';

    const icon = document.createElement('span');
    icon.className = 'activity-feed__action-icon';
    icon.textContent = actionEmoji;

    action.appendChild(icon);
    action.appendChild(document.createTextNode(event.description));

    eventDiv.appendChild(header);
    eventDiv.appendChild(action);

    return eventDiv;
  }

  renderEmpty() {
    const list = document.getElementById('af-events-list');
    if (list) {
      while (list.firstChild) list.removeChild(list.firstChild);
      const empty = document.createElement('div');
      empty.className = 'activity-feed__empty';
      empty.textContent = 'Sin eventos en este periodo';
      list.appendChild(empty);
    }
  }

  getActionEmoji(action) {
    const emojis = {
      checkin: '✅',
      metric: '📈',
      challenge: '🏆',
      message: '💬',
      training: '🏋️',
      weight: '⚖️',
      xp: '⭐',
      habit: '🎯',
      photo: '📸',
      community: '👥'
    };
    return emojis[action] || '📍';
  }

  getTimeAgo(date) {
    const now = new Date();
    const diff = (now - date) / 1000;

    if (diff < 60) return 'ahora';
    if (diff < 3600) return Math.floor(diff / 60) + 'm';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h';
    if (diff < 172800) return 'ayer';
    return Math.floor(diff / 86400) + 'd';
  }

  async populateFilterDropdown() {
    const select = document.getElementById('af-client-filter');
    if (!select) return;

    try {
      const tk = localStorage.getItem('wc_token');
      const h = {};
      if (tk) h['Authorization'] = 'Bearer ' + tk;
      const resp = await fetch('/api/admin/clients.php', { headers: h });
      if (resp.ok) {
        const data = await resp.json();
        const clients = data.clients || data || [];
        if (Array.isArray(clients)) {
          clients.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.name || ((c.first_name || '') + ' ' + (c.last_name || '')).trim();
            select.appendChild(opt);
          });
          this.filterDropdownPopulated = true;
          return;
        }
      }
    } catch (e) { /* fallback */ }

    const clientNames = [...new Map(
      this.events.map(e => [e.client_id, e.client_name])
    ).entries()];
    clientNames.forEach(([id, name]) => {
      const opt = document.createElement('option');
      opt.value = id;
      opt.textContent = name;
      select.appendChild(opt);
    });
    this.filterDropdownPopulated = true;
  }

  async impersonateClient(clientId) {
    try {
      const token = localStorage.getItem('wc_token');
      const hdrs = { 'Content-Type': 'application/json' };
      if (token) hdrs['Authorization'] = 'Bearer ' + token;

      const response = await fetch('/api/admin/impersonate.php', {
        method: 'POST',
        headers: hdrs,
        body: JSON.stringify({ client_id: clientId })
      });

      if (response.ok) {
        const data = await response.json();
        if (data.token) {
          localStorage.setItem('client_token', data.token);
          window.location.href = '/cliente.html';
        }
      }
    } catch (error) {
      console.error('Impersonate error:', error);
    }
  }

  toggle() {
    this.isCollapsed = !this.isCollapsed;
    this.container.classList.toggle('collapsed');

    if (this.isCollapsed) {
      clearInterval(this.pollingInterval);
    } else {
      this.startPolling();
    }
  }

  close() {
    clearInterval(this.pollingInterval);
    this.container.remove();
  }
}

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    const feed = new ActivityFeed();
    feed.init();
  });
} else {
  const feed = new ActivityFeed();
  feed.init();
}
