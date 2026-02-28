/**
 * WellCore Fitness — API Client
 * Connects frontend to PHP backend.
 * Falls back to localStorage if API unavailable (offline mode).
 *
 * Usage:
 *   WC_API.auth.login('client', 'email@example.com', 'password')
 *   WC_API.metrics.save({ peso: 75, grasa: 18 })
 *   WC_API.admin.clients.list({ search: 'john', plan: 'elite' })
 */

const WC_API = (() => {
  'use strict';

  // Change to full URL for cross-origin: 'https://wellcorefitness.com/api'
  const BASE = '/api';

  // ── Token management ──────────────────────────────────────────────
  const getToken  = ()  => localStorage.getItem('wc_token');
  const setToken  = (t) => localStorage.setItem('wc_token', t);
  const clearToken = () => localStorage.removeItem('wc_token');

  // ── Core HTTP helper ──────────────────────────────────────────────
  async function request(method, path, body = null, isForm = false) {
    const headers = {};
    const token = getToken();
    if (token) headers['Authorization'] = 'Bearer ' + token;
    if (!isForm) headers['Content-Type'] = 'application/json';

    const opts = { method, headers };
    if (body) opts.body = isForm ? body : JSON.stringify(body);

    try {
      const res  = await fetch(BASE + path, opts);
      const data = await res.json().catch(() => ({}));

      if (res.status === 401 && !path.startsWith('/auth/login')) {
        // Token expired — clear credentials and redirect to login
        clearToken();
        window.location.href = 'login.html';
        return null;
      }

      if (!res.ok) {
        throw new Error(data.error || `HTTP ${res.status}`);
      }
      return data;
    } catch (err) {
      console.warn('[WC_API] Request failed:', err.message);
      throw err;
    }
  }

  const get  = (path)       => request('GET',    path);
  const post = (path, body) => request('POST',   path, body);
  const put  = (path, body) => request('PUT',    path, body);
  const del  = (path)       => request('DELETE', path);

  // ── Auth ──────────────────────────────────────────────────────────
  const auth = {
    /**
     * Login for client or admin.
     * @param {'client'|'admin'} type
     * @param {string} identity  email (client) or username (admin)
     * @param {string} password
     */
    async login(type, identity, password) {
      const key  = type === 'admin' ? 'username' : 'email';
      const data = await post('/auth/login', { type, [key]: identity, password });

      if (data?.token) {
        setToken(data.token);
        localStorage.setItem('wc_user_type', type);

        // Cache UI display data (NOT used for security decisions)
        if (data.client) {
          localStorage.setItem('wc_client_name',  data.client.name);
          localStorage.setItem('wc_client_email', data.client.email);
          localStorage.setItem('wc_client_plan',  data.client.plan);
          localStorage.setItem('wc_client_id',    data.client.client_code);
        }
        if (data.coach_theme) {
          localStorage.setItem('wc_coach_theme', JSON.stringify(data.coach_theme));
        } else {
          localStorage.removeItem('wc_coach_theme');
        }
        if (data.user) {
          localStorage.setItem('wc_admin_user', data.user.username);
          localStorage.setItem('wc_admin_role', data.user.role || '');
        }
      }
      return data;
    },

    async logout() {
      try { await post('/auth/logout'); } catch (e) { /* ignore */ }
      clearToken();
      [
        'wc_user_type', 'wc_client_name', 'wc_client_email',
        'wc_client_plan', 'wc_client_id', 'wc_coach_theme',
        'wc_admin_user', 'wc_admin_role',
        // Clean up legacy keys
        'wc_client_auth', 'wc_admin_auth',
        'wc_admin_jefe_auth', 'wc_admin_coaches_auth', 'wc_admin_clientes_auth',
      ].forEach(k => localStorage.removeItem(k));
      window.location.href = 'login.html';
    },

    /** Returns current user data from the token. */
    me: () => get('/auth/me'),
  };

  // ── Client Profile ────────────────────────────────────────────────
  const profile = {
    /** GET /api/clients/profile */
    get: () => get('/clients/profile'),
    /** PUT /api/clients/profile */
    update: (data) => put('/clients/profile', data),
  };

  // ── Metrics ───────────────────────────────────────────────────────
  const metrics = {
    /** GET last N metric entries */
    history: (limit = 8) => get(`/metrics/index.php?limit=${limit}`),
    /** POST new metric entry { peso, grasa, musculo, ... } */
    save: (data) => post('/metrics/index.php', data),
  };

  // ── Training ──────────────────────────────────────────────────────
  const training = {
    /** GET training schedule for a specific ISO week */
    getWeek: (year, week) => get(`/training/index.php?year=${year}&week=${week}`),
    /** GET training logs for the last N weeks */
    getRange: (weeks = 8) => get(`/training/index.php?range=${weeks}`),
    /** POST toggle workout completion { date: 'YYYY-MM-DD', completed: true } */
    toggle: (date, completed) => post('/training/index.php', { date, completed }),
  };

  // ── Check-ins ─────────────────────────────────────────────────────
  const checkins = {
    /** GET last N check-ins for the authenticated client */
    list: (limit = 8) => get(`/checkins/index.php?limit=${limit}`),
    /** POST new weekly check-in */
    submit: (data) => post('/checkins/index.php', data),
  };

  // ── Progress Photos ───────────────────────────────────────────────
  const photos = {
    /** GET list of progress photos */
    list: (limit = 20) => get(`/photos/list.php?limit=${limit}`),
    /**
     * POST upload a new progress photo.
     * @param {File}   file   Image file from <input type="file">
     * @param {string} tipo   'frente' | 'lado' | 'espalda'
     * @param {string} [date] YYYY-MM-DD (defaults to today)
     */
    upload: (file, tipo, date) => {
      const form = new FormData();
      form.append('photo', file);
      form.append('tipo',  tipo);
      form.append('date',  date || new Date().toISOString().split('T')[0]);
      return request('POST', '/photos/upload.php', form, true);
    },
  };

  // ── Assigned Plans ────────────────────────────────────────────────
  const plans = {
    /** GET the assigned plan of a given type for the authenticated client */
    get: (type) => get(`/plans/get.php?type=${type}`),
  };

  // ── Admin ─────────────────────────────────────────────────────────
  const admin = {
    clients: {
      /**
       * GET paginated/filtered client list.
       * @param {{ search?: string, plan?: string, status?: string }} params
       */
      list: (params = {}) => {
        const q = new URLSearchParams(params).toString();
        return get(`/admin/clients.php${q ? '?' + q : ''}`);
      },
      /** GET single client with full data */
      get: (id) => get(`/admin/clients.php?id=${id}`),
      /** POST create a new client */
      create: (data) => post('/admin/clients.php', data),
      /** PUT update client fields (name, plan, status) */
      update: (id, data) => request('PUT', `/admin/clients.php?id=${id}`, data),
    },

    checkins: {
      /** GET all check-ins, optionally filtered to pending (no reply) */
      list: (pending = false) => get(`/admin/checkins.php${pending ? '?pending=1' : ''}`),
      /** PUT send coach reply to a check-in */
      reply: (id, reply) => request('PUT', `/admin/checkins.php?id=${id}`, { reply }),
    },

    plans: {
      /**
       * POST assign a plan to a client.
       * @param {{ client_id, plan_type, content, valid_from? }} data
       */
      assign: (data) => post('/admin/plans.php', data),
      /** GET active plans assigned to a client */
      getForClient: (clientId) => get(`/admin/plans.php?client_id=${clientId}`),
    },

    photos: {
      /** GET progress photos for a client (admin view) */
      list: (clientId, limit = 50) => get(`/admin/photos.php?client_id=${clientId}&limit=${limit}`),
    },
  };

  // ── Shop ──────────────────────────────────────────────────────────
  const shop = {
    products: {
      list: (params = {}) => {
        const q = new URLSearchParams(params).toString();
        return get(`/shop/products${q ? '?' + q : ''}`);
      },
      get: (slug) => get(`/shop/products?slug=${encodeURIComponent(slug)}`),
    },
    orders: {
      create: (data) => post('/shop/orders', data),
      status: (code) => get(`/shop/orders?code=${encodeURIComponent(code)}`),
    },
    analytics: {
      log: (data) => post('/shop/analytics', data),
    },
    categories: {
      list: () => get('/shop/categories'),
    },
    admin: {
      dashboard: () => get('/admin/shop'),
      orders: (params = {}) => {
        const q = new URLSearchParams({ orders: 1, ...params }).toString();
        return get(`/admin/shop?${q}`);
      },
      getOrder: (id) => get(`/admin/shop?order_id=${id}`),
      updateOrder: (id, data) => put(`/admin/shop?order_id=${id}`, data),
      upsertProduct: (data) => post('/admin/shop', data),
      deleteProduct: (id) => del(`/admin/shop?product_id=${id}`),
    },
  };

  // ── Offline fallback helper ───────────────────────────────────────
  /**
   * Try an API call; if it fails (offline/server error), run fallback instead.
   * @param {Function} apiCall   () => Promise
   * @param {Function} fallback  () => any  (returns localStorage data)
   */
  async function withFallback(apiCall, fallback) {
    try {
      return await apiCall();
    } catch (e) {
      console.warn('[WC_API] Offline — using local fallback');
      return fallback();
    }
  }

  // Public API
  return {
    auth,
    profile,
    metrics,
    training,
    checkins,
    photos,
    plans,
    admin,
    shop,
    withFallback,
    getToken,
  };
})();

// Attach to window for global access from any HTML page
window.WC_API = WC_API;
