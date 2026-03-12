/**
 * WellCore — Sound Effects System
 * Centralized audio feedback for UI interactions.
 * Uses Web Audio API for low-latency, no-file-dependency sounds.
 */
(function() {
  'use strict';

  var audioCtx = null;
  var enabled = localStorage.getItem('wc_sounds') !== 'off';

  function getCtx() {
    if (!audioCtx) {
      try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); }
      catch(e) { return null; }
    }
    return audioCtx;
  }

  // Resume audio context on first user interaction (browser policy)
  function resumeOnInteraction() {
    var events = ['click', 'touchstart', 'keydown'];
    function resume() {
      var ctx = getCtx();
      if (ctx && ctx.state === 'suspended') ctx.resume();
      events.forEach(function(e) { document.removeEventListener(e, resume); });
    }
    events.forEach(function(e) { document.addEventListener(e, resume, { once: false, passive: true }); });
  }
  resumeOnInteraction();

  function playTone(freq, duration, type, vol, ramp) {
    if (!enabled) return;
    var ctx = getCtx();
    if (!ctx) return;
    var osc = ctx.createOscillator();
    var gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.type = type || 'sine';
    osc.frequency.setValueAtTime(freq, ctx.currentTime);
    gain.gain.setValueAtTime(vol || 0.15, ctx.currentTime);
    if (ramp) gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration);
    osc.start(ctx.currentTime);
    osc.stop(ctx.currentTime + duration);
  }

  function playSequence(notes, baseDelay) {
    if (!enabled) return;
    notes.forEach(function(n, i) {
      setTimeout(function() {
        playTone(n.freq, n.dur || 0.12, n.type || 'sine', n.vol || 0.13, true);
      }, (baseDelay || 0) + i * (n.gap || 120));
    });
  }

  // ── Sound Library ──────────────────────────────────

  var sounds = {
    // Habit completed — short cheerful chime
    habitComplete: function() {
      playSequence([
        { freq: 523, dur: 0.08 },
        { freq: 659, dur: 0.08, gap: 80 },
        { freq: 784, dur: 0.15, gap: 80 }
      ]);
    },

    // All habits done — triumphant chord
    allHabitsComplete: function() {
      playSequence([
        { freq: 523, dur: 0.1 },
        { freq: 659, dur: 0.1, gap: 100 },
        { freq: 784, dur: 0.1, gap: 100 },
        { freq: 1047, dur: 0.25, gap: 120, vol: 0.18 }
      ]);
    },

    // XP gained — ascending pop
    xpGain: function() {
      playSequence([
        { freq: 880, dur: 0.06, type: 'triangle' },
        { freq: 1108, dur: 0.06, type: 'triangle', gap: 60 },
        { freq: 1318, dur: 0.12, type: 'triangle', gap: 60, vol: 0.16 }
      ]);
    },

    // Celebration — fanfare
    celebration: function() {
      playSequence([
        { freq: 523, dur: 0.12, type: 'square', vol: 0.08 },
        { freq: 659, dur: 0.12, type: 'square', gap: 130, vol: 0.08 },
        { freq: 784, dur: 0.12, type: 'square', gap: 130, vol: 0.08 },
        { freq: 1047, dur: 0.3, type: 'square', gap: 150, vol: 0.1 }
      ]);
      // Add shimmer
      setTimeout(function() {
        playSequence([
          { freq: 1318, dur: 0.08, type: 'sine', vol: 0.06 },
          { freq: 1568, dur: 0.08, type: 'sine', gap: 70, vol: 0.05 },
          { freq: 2093, dur: 0.15, type: 'sine', gap: 70, vol: 0.04 }
        ]);
      }, 500);
    },

    // New message from coach — gentle bell
    messageReceived: function() {
      playTone(830, 0.15, 'sine', 0.12, true);
      setTimeout(function() { playTone(1245, 0.2, 'sine', 0.08, true); }, 150);
    },

    // Mission completed — satisfying ding
    missionComplete: function() {
      playSequence([
        { freq: 698, dur: 0.08, type: 'triangle' },
        { freq: 880, dur: 0.15, type: 'triangle', gap: 90, vol: 0.16 }
      ]);
    },

    // All missions done — bonus fanfare
    allMissionsComplete: function() {
      playSequence([
        { freq: 698, dur: 0.1, type: 'triangle' },
        { freq: 880, dur: 0.1, type: 'triangle', gap: 100 },
        { freq: 1047, dur: 0.1, type: 'triangle', gap: 100 },
        { freq: 1397, dur: 0.25, type: 'triangle', gap: 130, vol: 0.18 }
      ]);
    },

    // Check-in submitted — warm confirmation
    checkinSubmit: function() {
      playSequence([
        { freq: 440, dur: 0.1 },
        { freq: 554, dur: 0.1, gap: 100 },
        { freq: 659, dur: 0.18, gap: 100, vol: 0.16 }
      ]);
    },

    // Error — short low buzz
    error: function() {
      playTone(220, 0.2, 'sawtooth', 0.06, true);
    },

    // Success — quick bright ping
    success: function() {
      playTone(880, 0.12, 'sine', 0.12, true);
    },

    // Photo uploaded — camera shutter
    photoUploaded: function() {
      playTone(1200, 0.04, 'square', 0.08, false);
      setTimeout(function() { playTone(800, 0.06, 'square', 0.06, true); }, 50);
    },

    // Notification arrival — subtle bell
    notification: function() {
      playTone(987, 0.12, 'sine', 0.1, true);
      setTimeout(function() { playTone(1319, 0.18, 'sine', 0.07, true); }, 130);
    },

    // Level up — epic
    levelUp: function() {
      playSequence([
        { freq: 523, dur: 0.1, type: 'square', vol: 0.08 },
        { freq: 659, dur: 0.1, type: 'square', gap: 100, vol: 0.08 },
        { freq: 784, dur: 0.1, type: 'square', gap: 100, vol: 0.09 },
        { freq: 1047, dur: 0.15, type: 'square', gap: 100, vol: 0.1 },
        { freq: 1318, dur: 0.25, type: 'sine', gap: 150, vol: 0.12 }
      ]);
    },

    // Streak milestone — fire
    streakMilestone: function() {
      playSequence([
        { freq: 440, dur: 0.08, type: 'triangle' },
        { freq: 554, dur: 0.08, type: 'triangle', gap: 80 },
        { freq: 659, dur: 0.08, type: 'triangle', gap: 80 },
        { freq: 880, dur: 0.08, type: 'triangle', gap: 80 },
        { freq: 1047, dur: 0.2, type: 'triangle', gap: 100, vol: 0.18 }
      ]);
    },

    // Timer done (rest timer in RISE)
    timerDone: function() {
      playSequence([
        { freq: 880, dur: 0.15, type: 'sine', vol: 0.15 },
        { freq: 880, dur: 0.15, type: 'sine', gap: 200, vol: 0.15 },
        { freq: 1175, dur: 0.25, type: 'sine', gap: 200, vol: 0.18 }
      ]);
    },

    // Toggle on
    toggleOn: function() {
      playTone(660, 0.06, 'sine', 0.08, true);
    },

    // Toggle off
    toggleOff: function() {
      playTone(440, 0.06, 'sine', 0.06, true);
    }
  };

  // ── Public API ────────────────────────────────────

  window.WCSound = {
    play: function(name) {
      if (sounds[name]) sounds[name]();
    },
    enable: function() { enabled = true; localStorage.setItem('wc_sounds', 'on'); },
    disable: function() { enabled = false; localStorage.setItem('wc_sounds', 'off'); },
    toggle: function() {
      enabled = !enabled;
      localStorage.setItem('wc_sounds', enabled ? 'on' : 'off');
      return enabled;
    },
    isEnabled: function() { return enabled; }
  };

})();
