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
  }

  init() {
    this.render();
    this.attachListeners();
    this.startPolling();
  }

  render() {
    // Create container
    this.container = document.createElement('div');
    this.container.className = 'activity-feed';
    this.container.id = 'activity-feed-panel';

    // Build structure using createElement for safety
    const header = document.createElement('div');
    header.className = 'activity-feed__header';

    const title = document.createElement('span');
    title.className = 'activity-feed__title';
    title.textContent = '≡ Activity Feed';

    const controls = document.createElement('div');
    controls.className = 'activity-feed__controls';

    const collapseBtn = document.createElement('button');
    collapseBtn.className = 'activity-feed__btn';
    collapseBtn.id = 'af-collapse';
    collapseBtn.textContent = '⊟';
    collapseBtn.title = 'Collapse';

    const closeBtn = document.createElement('button');
    closeBtn.className = 'activity-feed__btn';
    closeBtn.id = 'af-close';
    closeBtn.textContent = '✕';
    closeBtn.title = 'Close';

    controls.appendChild(collapseBtn);
    controls.appendChild(closeBtn);
    header.appendChild(title);
    header.appendChild(controls);

    // Stats section
    const stats = document.createElement('div');
    stats.className = 'activity-feed__stats';

    const countDiv = document.createElement('div');
    countDiv.className = 'activity-feed__count';
    countDiv.textContent = 'TODAY: ';
    const countNum = document.createElement('span');
    countNum.className = 'activity-feed__count-number';
    countNum.id = 'af-count';
    countNum.textContent = '0';
    countDiv.appendChild(countNum);
    countDiv.appendChild(document.createTextNode(' EVENTS'));

    const breakdown = document.createElement('div');
    breakdown.className = 'activity-feed__breakdown';
    breakdown.id = 'af-breakdown';

    stats.appendChild(countDiv);
    stats.appendChild(breakdown);

    // Filter section
    const filter = document.createElement('div');
    filter.className = 'activity-feed__filter';
    const select = document.createElement('select');
    select.id = 'af-client-filter';
    const defaultOpt = document.createElement('option');
    defaultOpt.value = '';
    defaultOpt.textContent = 'All Clients';
    select.appendChild(defaultOpt);
    filter.appendChild(select);

    // Events list
    const list = document.createElement('div');
    list.className = 'activity-feed__list';
    list.id = 'af-events-list';
    const empty = document.createElement('div');
    empty.className = 'activity-feed__empty';
    empty.textContent = 'Loading...';
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

    // Click en el panel colapsado para expandir
    this.container.addEventListener('click', () => {
      if (this.isCollapsed) this.toggle();
    });
    document.getElementById('af-client-filter').addEventListener('change', (e) => {
      this.filterClientId = e.target.value || null;
      this.updateFeed(true);
    });

    // Click on event to impersonate
    document.addEventListener('click', (e) => {
      const eventEl = e.target.closest('.activity-feed__event');
      if (eventEl) {
        const clientId = eventEl.dataset.clientId;
        if (clientId) {
          this.impersonateClient(clientId);
        }
      }
    });
  }

  startPolling() {
    this.updateFeed();
    this.pollingInterval = setInterval(() => this.updateFeed(), 4000);
  }

  async updateFeed(force = false) {
    try {
      const params = new URLSearchParams();
      if (this.filterClientId) params.append('client_id', this.filterClientId);
      params.append('limit', '15');

      const response = await fetch(`/api/admin/activity-feed.php?${params.toString()}`);
      if (!response.ok) throw new Error(`API error: ${response.status}`);

      const data = await response.json();

      // Detect new events
      const oldEventIds = this.events.map(e => `${e.client_id}_${e.timestamp}`);
      const newEventIds = data.events.map(e => `${e.client_id}_${e.timestamp}`);
      const hasNewEvents = newEventIds.some(id => !oldEventIds.includes(id));

      this.events = data.events;
      this.renderStats(data.today_count, data.breakdown);
      this.renderEvents(data.events, hasNewEvents);

      // Populate filter dropdown if first load
      if (!this.filterDropdownPopulated) {
        this.populateFilterDropdown();
      }
    } catch (error) {
      console.error('Activity Feed error:', error);
      this.renderEmpty();
    }
  }

  renderStats(count, breakdown) {
    const countEl = document.getElementById('af-count');
    if (countEl) countEl.textContent = count;

    const breakdownEl = document.getElementById('af-breakdown');
    if (!breakdownEl) return;

    const chips = [
      { label: '✅', count: breakdown.checkin || 0, type: 'checkin', color: 'green' },
      { label: '📈', count: breakdown.metric || 0, type: 'metric', color: 'blue' },
      { label: '🏆', count: breakdown.challenge || 0, type: 'challenge', color: 'gold' },
      { label: '📚', count: breakdown.academy || 0, type: 'academy', color: 'cyan' },
      { label: '💬', count: breakdown.message || 0, type: 'message', color: 'red' },
    ];

    breakdownEl.innerHTML = '';
    chips.forEach(chip => {
      const chipEl = document.createElement('div');
      chipEl.className = 'activity-feed__chip';
      chipEl.dataset.type = chip.type;
      chipEl.textContent = `${chip.label} ${chip.count}`;
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

    list.innerHTML = '';
    events.forEach((event, idx) => {
      const eventEl = this.createEventElement(event, idx, hasNew);
      list.appendChild(eventEl);
    });
  }

  createEventElement(event, idx, hasNew) {
    const timeAgo = this.getTimeAgo(new Date(event.timestamp));
    const actionEmoji = this.getActionEmoji(event.action);
    const avatarClass = `activity-feed__avatar--${event.action}`;

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
    avatar.className = `activity-feed__avatar ${avatarClass}`;
    avatar.textContent = event.client_avatar;

    const info = document.createElement('div');
    info.className = 'activity-feed__client-info';

    const name = document.createElement('p');
    name.className = 'activity-feed__client-name';
    name.textContent = event.client_name;

    info.appendChild(name);

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
      list.innerHTML = '';
      const empty = document.createElement('div');
      empty.className = 'activity-feed__empty';
      empty.textContent = 'Sin eventos';
      list.appendChild(empty);
    }
  }

  getActionEmoji(action) {
    const emojis = {
      checkin: '✅',
      metric: '📈',
      challenge: '🏆',
      academy: '📚',
      message: '💬'
    };
    return emojis[action] || '📍';
  }

  getTimeAgo(date) {
    const now = new Date();
    const diff = (now - date) / 1000; // seconds

    if (diff < 60) return 'now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h`;
    return `${Math.floor(diff / 86400)}d`;
  }

  populateFilterDropdown() {
    const select = document.getElementById('af-client-filter');
    if (!select) return;

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
      const response = await fetch('/api/admin/impersonate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ client_id: clientId })
      });

      if (response.ok) {
        const data = await response.json();
        if (data.token) {
          // Store temp client token and redirect
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
