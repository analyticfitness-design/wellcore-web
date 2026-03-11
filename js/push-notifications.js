/**
 * WellCore Fitness — Push Notifications (M38)
 * Manages browser push subscription via Web Push API + VAPID.
 *
 * Requires:
 *   - Service Worker registered at /sw.js (push + notificationclick handlers)
 *   - Backend: POST/DELETE /api/notifications/subscribe
 *   - Button: #push-toggle-btn in the page
 *
 * Usage (call after DOM ready, user logged in):
 *   initPushNotifications();
 *   // or attach toggle to a button manually:
 *   togglePushNotifications();
 */

(function () {
  'use strict';

  const VAPID_PUBLIC_KEY =
    'BDFT-BlbGextWpeux4kFpgfR8vebxrYXBquZolnvz74SmijMp6EdXo9C-ji8yoAUcLJjHh65fkGcpVaMrZIRPRE';

  const SUBSCRIBE_ENDPOINT = '/api/notifications/subscribe.php';

  // ── Utility ────────────────────────────────────────────────────────────

  /**
   * Converts a VAPID base64url public key to a Uint8Array for PushManager.
   */
  function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    return Uint8Array.from([...rawData].map((c) => c.charCodeAt(0)));
  }

  /**
   * Encodes an ArrayBuffer to a base64url string (for sending keys to server).
   */
  function arrayBufferToBase64Url(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary  = '';
    bytes.forEach((b) => { binary += String.fromCharCode(b); });
    return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
  }

  /**
   * Returns the auth token from localStorage (same key used by api.js).
   */
  function getAuthToken() {
    return localStorage.getItem('wc_token') || '';
  }

  // ── Button UI helper ───────────────────────────────────────────────────

  function updateButtonUI(subscribed) {
    const btn = document.getElementById('push-toggle-btn');
    if (!btn) return;
    if (subscribed) {
      btn.textContent = '🔕 Desactivar notificaciones';
      btn.classList.add('push-subscribed');
      btn.classList.remove('push-unsubscribed');
    } else {
      btn.textContent = '🔔 Activar notificaciones';
      btn.classList.add('push-unsubscribed');
      btn.classList.remove('push-subscribed');
    }
    btn.disabled = false;
  }

  function setButtonLoading(btn) {
    if (!btn) return;
    btn.disabled    = true;
    btn.textContent = '⏳ Procesando…';
  }

  // ── Subscribe ──────────────────────────────────────────────────────────

  /**
   * Requests notification permission, subscribes via PushManager,
   * and registers the subscription on the server.
   */
  async function subscribeToPush() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
      console.warn('[Push] Browser does not support Web Push.');
      return false;
    }

    // Ask for permission
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      console.warn('[Push] Permission denied.');
      return false;
    }

    const registration = await navigator.serviceWorker.ready;

    const subscription = await registration.pushManager.subscribe({
      userVisibleOnly:      true,
      applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
    });

    const keys    = subscription.toJSON().keys;
    const payload = {
      endpoint: subscription.endpoint,
      p256dh:   keys.p256dh,
      auth:     keys.auth,
    };

    const token = getAuthToken();
    const res   = await fetch(SUBSCRIBE_ENDPOINT, {
      method:  'POST',
      headers: {
        'Content-Type':  'application/json',
        'Authorization': 'Bearer ' + token,
      },
      body: JSON.stringify(payload),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      console.error('[Push] Server registration failed:', err);
      return false;
    }

    console.info('[Push] Subscribed successfully.');
    updateButtonUI(true);
    return true;
  }

  // ── Unsubscribe ────────────────────────────────────────────────────────

  /**
   * Unsubscribes from PushManager and notifies the server to deactivate.
   */
  async function unsubscribeFromPush() {
    if (!('serviceWorker' in navigator)) return false;

    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.getSubscription();

    if (!subscription) {
      updateButtonUI(false);
      return true;
    }

    const endpoint = subscription.endpoint;
    await subscription.unsubscribe();

    const token = getAuthToken();
    await fetch(SUBSCRIBE_ENDPOINT, {
      method:  'DELETE',
      headers: {
        'Content-Type':  'application/json',
        'Authorization': 'Bearer ' + token,
      },
      body: JSON.stringify({ endpoint }),
    }).catch((e) => console.warn('[Push] Server unsubscribe error:', e));

    console.info('[Push] Unsubscribed.');
    updateButtonUI(false);
    return true;
  }

  // ── Check current state ────────────────────────────────────────────────

  /**
   * Returns true if the browser currently has an active push subscription.
   */
  async function checkPushSubscription() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
      return false;
    }
    try {
      const registration = await navigator.serviceWorker.ready;
      const subscription = await registration.pushManager.getSubscription();
      return subscription !== null;
    } catch (e) {
      return false;
    }
  }

  // ── Toggle (called from button) ────────────────────────────────────────

  /**
   * Toggles push subscription on/off.
   * Safe to call directly from a button onclick.
   */
  async function togglePushNotifications() {
    const btn = document.getElementById('push-toggle-btn');
    setButtonLoading(btn);

    try {
      const isSubscribed = await checkPushSubscription();
      if (isSubscribed) {
        await unsubscribeFromPush();
      } else {
        await subscribeToPush();
      }
    } catch (e) {
      console.error('[Push] Toggle error:', e);
      // Restore button state
      const stillSubscribed = await checkPushSubscription();
      updateButtonUI(stillSubscribed);
    }
  }

  // ── Init ───────────────────────────────────────────────────────────────

  /**
   * Initialises push notification state.
   * Call once after the DOM is ready and the user is logged in.
   * Updates #push-toggle-btn to reflect current subscription state.
   */
  async function initPushNotifications() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
      // Hide the toggle button — push not supported in this browser
      const btn = document.getElementById('push-toggle-btn');
      if (btn) btn.style.display = 'none';
      return;
    }

    const isSubscribed = await checkPushSubscription();
    updateButtonUI(isSubscribed);

    // Attach toggle handler to button
    const btn = document.getElementById('push-toggle-btn');
    if (btn && !btn.dataset.pushInit) {
      btn.dataset.pushInit = '1';
      btn.addEventListener('click', togglePushNotifications);
    }
  }

  // ── Exports ────────────────────────────────────────────────────────────
  window.initPushNotifications    = initPushNotifications;
  window.togglePushNotifications  = togglePushNotifications;
  window.subscribeToPush          = subscribeToPush;
  window.unsubscribeFromPush      = unsubscribeFromPush;
  window.checkPushSubscription    = checkPushSubscription;
})();
