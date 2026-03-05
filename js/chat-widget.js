/**
 * WellCore Fitness — Chat Widget con IA de reglas
 * Widget flotante de soporte inteligente. Sin API keys, 100% offline.
 * Incluir con: <script src="js/chat-widget.js" defer></script>
 *
 * SEGURIDAD: Todo contenido de usuario se inserta con textContent (nunca innerHTML).
 * El único innerHTML usado es la plantilla estática del widget (sin datos de usuario).
 */
(function () {
  'use strict';

  /* ─── CONFIG ─────────────────────────────────────────────── */
  var WC = {
    brand:       'WellCore AI',
    subhead:     'Asistente de Fitness',
    color:       '#E31E24',
    bg:          '#0a0a0a',
    surface:     '#111113',
    surface2:    '#1a1a1d',
    border:      '#252528',
    gray:        '#A1A1AA',
    green:       '#00D9FF',
    waLink:      'https://wa.me/573124904720',
    coachEmail:  'info@wellcorefitness.com',
    storageKey:  'wc_chat_history',
    sessionKey:  'wc_chat_session',
    typingDelay: 900,
    aiEnabled:   true,
    aiEndpoint:  '/api/ai/chat',
    aiFallbackEndpoint: '/api/ai/chat',
  };

  /* ─── KNOWLEDGE BASE ─────────────────────────────────────── */
  // Fallback minimo — se enriquece con fetch a /api/data/knowledge-base.json
  var KB = [
    {
      tags: ['hola','buenos dias','buenas tardes','buenas noches','saludos','hey','hi','hello','buenas'],
      answer: 'Hola! Soy el asistente de WellCore Fitness. Puedo ayudarte con informacion sobre nuestros planes, metodologia, nutricion y entrenamiento. Que quieres saber?',
      quick: ['Que planes tienen?','Como funciona WellCore?','Quiero inscribirme']
    },
    {
      tags: ['plan','planes','precio','precios','costo','cuanto cuesta','valor'],
      answer: 'Tenemos 3 planes mensuales:\n\nESENCIAL - $95 USD/mes\nMETODO - $120 USD/mes (Mas popular)\nELITE - $150 USD/mes (Todo incluido)\n\nTe ayudo a elegir?',
      quick: ['Que incluye el Elite?','Quiero inscribirme']
    },
    {
      tags: ['inscribir','inscribirme','empezar','comenzar','registro','comprar'],
      answer: 'Para empezar:\n1. Elige tu plan en nuestra pagina de pagos\n2. Completa tus datos y realiza el pago\n3. En menos de 24h recibes tus credenciales\n4. En 48h tienes tu programa personalizado',
      quick: ['Ver planes','Hablar con el coach'],
      action: { label: 'Ir a planes', url: 'pagar.html' }
    },
    {
      tags: ['contacto','whatsapp','email','correo'],
      answer: 'Contactanos por:\nEmail: info@wellcorefitness.com\nWhatsApp: +57 312 4904720\nDisponibles 7 dias a la semana.',
      quick: ['Hablar por WhatsApp','Ver planes']
    },
    {
      tags: ['__fallback__'],
      answer: 'Esa pregunta no la tengo en mi base de conocimiento todavia. Pero el coach puede responderte directamente. Quieres contactarlo?',
      quick: ['Hablar por WhatsApp','Que planes tienen?']
    }
  ];

  // Cargar KB completa desde servidor (enriquece el fallback)
  (function loadExternalKB() {
    try {
      var xhr = new XMLHttpRequest();
      xhr.open('GET', '/api/data/knowledge-base.json', true);
      xhr.timeout = 5000;
      xhr.onload = function() {
        if (xhr.status === 200) {
          try {
            var data = JSON.parse(xhr.responseText);
            if (Array.isArray(data) && data.length > 0) {
              // Convertir formato externo (keywords/content) a formato widget (tags/answer)
              var extra = [];
              for (var i = 0; i < data.length; i++) {
                var e = data[i];
                if (e.tags && e.answer) {
                  extra.push(e); // ya en formato widget
                } else if (e.keywords && e.content) {
                  extra.push({ tags: e.keywords, answer: e.content, quick: ['Ver planes','Hablar con el coach'] });
                }
              }
              // Insertar antes del fallback
              var fallback = KB[KB.length - 1];
              KB = KB.slice(0, KB.length - 1).concat(extra).concat([fallback]);
            }
          } catch(parseErr) { /* KB parse error — keep fallback */ }
        }
      };
      xhr.send();
    } catch(e) { /* fetch error — keep fallback KB */ }
  })();

  /* ─── NLP UTILS ─────────────────────────────────────────── */
  function normalize(str) {
    return str
      .toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9\s]/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function tokenize(str) {
    return normalize(str).split(' ').filter(function(w){ return w.length > 2; });
  }

  function findBestMatch(input) {
    var tokens = tokenize(input);
    if (!tokens.length) return null;

    var best = null, bestScore = 0;

    for (var i = 0; i < KB.length; i++) {
      var entry = KB[i];
      if (entry.tags[0] === '__fallback__') continue;

      var score = 0;
      for (var j = 0; j < tokens.length; j++) {
        for (var k = 0; k < entry.tags.length; k++) {
          var tag = normalize(entry.tags[k]);
          if (tag.indexOf(tokens[j]) !== -1 || tokens[j].indexOf(tag) !== -1) {
            score += (tag === tokens[j]) ? 2 : 1;
          }
        }
      }
      var normalizedScore = score / Math.sqrt(entry.tags.length);
      if (normalizedScore > bestScore) {
        bestScore = normalizedScore;
        best = entry;
      }
    }

    return bestScore >= 0.5 ? best : KB[KB.length - 1];
  }

  /* ─── HISTORY ────────────────────────────────────────────── */
  function loadHistory() {
    try { return JSON.parse(localStorage.getItem(WC.storageKey)) || []; }
    catch(e) { return []; }
  }

  function saveHistory(h) {
    try { localStorage.setItem(WC.storageKey, JSON.stringify(h.slice(-40))); }
    catch(e) {}
  }

  /* ─── DOM HELPERS ────────────────────────────────────────── */
  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function(k) {
        if (k === 'style') {
          Object.assign(node.style, attrs[k]);
        } else if (k === 'class') {
          node.className = attrs[k];
        } else if (k === 'text') {
          node.textContent = attrs[k];
        } else {
          node.setAttribute(k, attrs[k]);
        }
      });
    }
    if (children) {
      children.forEach(function(c) { if (c) node.appendChild(c); });
    }
    return node;
  }

  /* ─── BUILD WIDGET DOM ───────────────────────────────────── */
  function buildWidget() {
    /* SVG helpers */
    function svgEl(paths, w, h) {
      var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('width', w || 24);
      svg.setAttribute('height', h || 24);
      svg.setAttribute('viewBox', '0 0 24 24');
      svg.setAttribute('fill', 'none');
      svg.setAttribute('stroke', 'currentColor');
      svg.setAttribute('stroke-width', '2');
      svg.setAttribute('stroke-linecap', 'round');
      svg.setAttribute('stroke-linejoin', 'round');
      paths.forEach(function(d) {
        var p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        p.setAttribute('d', d);
        svg.appendChild(p);
      });
      return svg;
    }

    function svgLines(lines, w, h) {
      var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('width', w || 24);
      svg.setAttribute('height', h || 24);
      svg.setAttribute('viewBox', '0 0 24 24');
      svg.setAttribute('fill', 'none');
      svg.setAttribute('stroke', 'currentColor');
      svg.setAttribute('stroke-width', '2.5');
      svg.setAttribute('stroke-linecap', 'round');
      lines.forEach(function(coords) {
        var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        line.setAttribute('x1', coords[0]); line.setAttribute('y1', coords[1]);
        line.setAttribute('x2', coords[2]); line.setAttribute('y2', coords[3]);
        svg.appendChild(line);
      });
      return svg;
    }

    function svgSend() {
      var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('width', 18); svg.setAttribute('height', 18);
      svg.setAttribute('viewBox', '0 0 24 24');
      svg.setAttribute('fill', 'none'); svg.setAttribute('stroke', 'currentColor');
      svg.setAttribute('stroke-width', '2'); svg.setAttribute('stroke-linecap', 'round');
      var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      line.setAttribute('x1','22'); line.setAttribute('y1','2');
      line.setAttribute('x2','11'); line.setAttribute('y2','13');
      var poly = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
      poly.setAttribute('points','22 2 15 22 11 13 2 9 22 2');
      svg.appendChild(line); svg.appendChild(poly);
      return svg;
    }

    var closeSvgTrigger = svgLines([[18,6,6,18],[6,6,18,18]], 22, 22);
    closeSvgTrigger.id = 'wc-icon-close';
    closeSvgTrigger.style.display = 'none';

    var openSvg = svgEl(['M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z']);
    openSvg.id = 'wc-icon-open';

    var notifBadge = el('span', { id: 'wc-notif', style: { display: 'none' } });

    var trigger = el('button', { id: 'wc-trigger', 'aria-label': 'Abrir chat WellCore' }, [
      openSvg, closeSvgTrigger, notifBadge
    ]);

    /* Header */
    var avatar = el('div', { id: 'wc-avatar', text: 'W' });
    var brandDiv = el('div', { id: 'wc-brand', text: WC.brand });
    var dot = el('span', { id: 'wc-dot' });
    var statusDiv = el('div', { id: 'wc-status' }, [dot]);
    statusDiv.appendChild(document.createTextNode(WC.subhead));
    var headerInfo = el('div', { id: 'wc-header-info' }, [brandDiv, statusDiv]);
    var closeSvgHeader = svgLines([[18,6,6,18],[6,6,18,18]], 16, 16);
    var closeBtn = el('button', { id: 'wc-close-btn', 'aria-label': 'Cerrar chat' }, [closeSvgHeader]);
    var header = el('div', { id: 'wc-header' }, [avatar, headerInfo, closeBtn]);

    /* Messages + quick */
    var msgsDiv = el('div', { id: 'wc-msgs' });
    var quickDiv = el('div', { id: 'wc-quick' });

    /* Footer */
    var inputEl = el('input', {
      id: 'wc-input', type: 'text',
      placeholder: 'Escribe tu pregunta...',
      autocomplete: 'off', maxlength: '200'
    });
    var sendBtn = el('button', { id: 'wc-send', 'aria-label': 'Enviar' }, [svgSend()]);
    var footer = el('div', { id: 'wc-footer' }, [inputEl, sendBtn]);

    /* Powered */
    var poweredA = el('a', { href: 'mailto:' + WC.coachEmail, text: WC.coachEmail });
    var powered = el('div', { id: 'wc-powered' });
    powered.appendChild(document.createTextNode('Powered by WellCore AI · '));
    powered.appendChild(poweredA);

    /* Window */
    var win = el('div', {
      id: 'wc-window',
      role: 'dialog',
      'aria-label': 'Chat WellCore Fitness',
      'aria-hidden': 'true'
    }, [header, msgsDiv, quickDiv, footer, powered]);

    /* Root */
    var root = el('div', { id: 'wc-chat-root' }, [trigger, win]);
    document.body.appendChild(root);
  }

  /* ─── STYLES ─────────────────────────────────────────────── */
  function injectStyles() {
    var css = [
      '#wc-chat-root *{box-sizing:border-box;margin:0;padding:0}',
      '#wc-chat-root{position:fixed;bottom:24px;right:24px;z-index:99999;font-family:Inter,-apple-system,sans-serif}',
      '#wc-trigger{width:56px;height:56px;border-radius:50%;background:' + WC.color + ';border:3px solid #fff;cursor:pointer;color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:none;transition:border-color .1s linear;position:relative}',
      '#wc-trigger:hover{border-color:' + WC.color + '}',
      '#wc-trigger:active{opacity:.9}',
      '#wc-notif{position:absolute;top:-3px;right:-3px;background:' + WC.green + ';color:#000;width:18px;height:18px;border-radius:50%;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;border:2px solid ' + WC.bg + '}',
      '#wc-window{position:absolute;bottom:70px;right:0;width:340px;max-height:520px;background:' + WC.surface + ';border:4px solid ' + WC.border + ';border-radius:0;box-shadow:0 16px 48px rgba(0,0,0,.6);display:flex;flex-direction:column;overflow:hidden;opacity:0;transform:translateY(12px) scale(.97);pointer-events:none;transition:opacity .1s linear,transform .1s linear}',
      '#wc-window.open{opacity:1;transform:translateY(0) scale(1);pointer-events:all}',
      '#wc-header{display:flex;align-items:center;gap:10px;padding:14px 16px;background:' + WC.surface2 + ';border-bottom:1px solid ' + WC.border + ';flex-shrink:0}',
      '#wc-avatar{width:36px;height:36px;border-radius:50%;background:' + WC.color + ';display:flex;align-items:center;justify-content:center;font-family:"Bebas Neue",cursive;font-size:18px;color:#fff;flex-shrink:0}',
      '#wc-brand{font-family:"Bebas Neue",cursive;font-size:15px;color:#fff;letter-spacing:.06em}',
      '#wc-status{font-size:11px;color:' + WC.gray + ';display:flex;align-items:center;gap:5px}',
      '#wc-dot{width:7px;height:7px;border-radius:50%;background:' + WC.green + ';animation:wc-pulse 2s ease-in-out infinite;flex-shrink:0}',
      '#wc-close-btn{margin-left:auto;background:none;border:none;color:' + WC.gray + ';cursor:pointer;padding:4px;border-radius:0;transition:color .1s linear;display:flex}',
      '#wc-close-btn:hover{color:#fff}',
      '#wc-msgs{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:10px;scroll-behavior:smooth}',
      '#wc-msgs::-webkit-scrollbar{width:4px}',
      '#wc-msgs::-webkit-scrollbar-track{background:transparent}',
      '#wc-msgs::-webkit-scrollbar-thumb{background:' + WC.border + ';border-radius:4px}',
      '.wc-msg{display:flex;gap:8px;animation:wc-slide-in .2s ease}',
      '.wc-msg.bot{align-items:flex-start}',
      '.wc-msg.user{flex-direction:row-reverse}',
      '.wc-msg-ico{width:28px;height:28px;border-radius:50%;background:' + WC.color + ';flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:12px;color:#fff;font-family:"Bebas Neue",cursive;margin-top:2px}',
      '.wc-bubble{max-width:80%;padding:9px 13px;border-radius:0;font-size:13px;line-height:1.5;white-space:pre-wrap;word-break:break-word}',
      '.wc-msg.bot .wc-bubble{background:' + WC.surface2 + ';color:#e0e0e0}',
      '.wc-msg.user .wc-bubble{background:' + WC.color + ';color:#fff}',
      '.wc-action-btn{display:inline-block;margin-top:8px;padding:6px 12px;border-radius:0;background:' + WC.color + ';color:#fff;font-size:12px;font-weight:600;text-decoration:none;transition:opacity .1s linear}',
      '.wc-action-btn:hover{opacity:.85}',
      '.wc-typing-dot{width:6px;height:6px;border-radius:50%;background:' + WC.gray + ';animation:wc-bounce 1.2s ease-in-out infinite;display:inline-block}',
      '.wc-typing-wrap{display:flex;align-items:center;gap:4px;padding:4px 0}',
      '#wc-quick{padding:0 12px 10px;display:flex;flex-wrap:wrap;gap:6px;flex-shrink:0}',
      '.wc-qr{padding:5px 11px;border:1px solid ' + WC.border + ';border-radius:0;background:none;color:' + WC.gray + ';font-size:11px;cursor:pointer;transition:border-color .1s linear,color .1s linear;white-space:nowrap}',
      '.wc-qr:hover{border-color:' + WC.color + ';color:#fff}',
      '#wc-footer{display:flex;align-items:center;gap:8px;padding:10px 12px;border-top:1px solid ' + WC.border + ';background:' + WC.surface2 + ';flex-shrink:0}',
      '#wc-input{flex:1;background:' + WC.bg + ';border:1px solid ' + WC.border + ';border-radius:0;padding:8px 12px;color:#fff;font-size:13px;outline:none;transition:border-color .1s linear;font-family:inherit}',
      '#wc-input:focus{border-color:rgba(227,30,36,.5)}',
      '#wc-input::placeholder{color:' + WC.gray + '}',
      '#wc-send{width:36px;height:36px;background:' + WC.color + ';border:none;border-radius:0;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:opacity .1s linear}',
      '#wc-send:hover{opacity:.85}',
      '#wc-powered{text-align:center;font-size:10px;color:' + WC.gray + ';padding:6px;border-top:1px solid ' + WC.border + ';flex-shrink:0}',
      '#wc-powered a{color:' + WC.gray + ';text-decoration:none}',
      '#wc-powered a:hover{color:#fff}',
      '@keyframes wc-pulse{0%,100%{opacity:1}50%{opacity:.4}}',
      '@keyframes wc-bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-4px)}}',
      '@keyframes wc-slide-in{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}',
      '@media(max-width:400px){#wc-window{width:calc(100vw - 32px);right:-4px}#wc-chat-root{right:16px;bottom:16px}}',
      '@media(max-width:768px){#wc-chat-root{right:16px;bottom:16px}}'
    ].join('');

    var style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);
  }

  /* ─── RENDER ─────────────────────────────────────────────── */
  var msgsEl, quickEl, inputEl;
  var history = [];
  var isOpen = false;

  function scrollBottom() { msgsEl.scrollTop = msgsEl.scrollHeight; }

  function renderMsg(role, text, action) {
    var wrap = document.createElement('div');
    wrap.className = 'wc-msg ' + role;

    if (role === 'bot') {
      var ico = document.createElement('div');
      ico.className = 'wc-msg-ico';
      ico.textContent = 'W';
      wrap.appendChild(ico);
    }

    var bubble = document.createElement('div');
    bubble.className = 'wc-bubble';
    bubble.textContent = text;   // SAFE: textContent only

    if (action) {
      var br = document.createElement('br');
      var a = document.createElement('a');
      a.href = action.url;
      a.className = 'wc-action-btn';
      a.textContent = action.label;   // SAFE: textContent only
      bubble.appendChild(br);
      bubble.appendChild(a);
    }

    wrap.appendChild(bubble);
    msgsEl.appendChild(wrap);
    scrollBottom();
  }

  function showTyping() {
    var wrap = document.createElement('div');
    wrap.className = 'wc-msg bot';
    wrap.id = 'wc-typing-wrap-el';

    var ico = document.createElement('div');
    ico.className = 'wc-msg-ico';
    ico.textContent = 'W';

    var bubble = document.createElement('div');
    bubble.className = 'wc-bubble';

    var dotsWrap = document.createElement('div');
    dotsWrap.className = 'wc-typing-wrap';

    for (var i = 0; i < 3; i++) {
      var dot = document.createElement('span');
      dot.className = 'wc-typing-dot';
      dot.style.animationDelay = (i * 0.2) + 's';
      dotsWrap.appendChild(dot);   // No innerHTML — pure DOM
    }

    bubble.appendChild(dotsWrap);
    wrap.appendChild(ico);
    wrap.appendChild(bubble);
    msgsEl.appendChild(wrap);
    scrollBottom();
  }

  function removeTyping() {
    var el = document.getElementById('wc-typing-wrap-el');
    if (el) el.remove();
  }

  function setQuickReplies(list) {
    quickEl.textContent = '';   // clear safely
    if (!list || !list.length) return;
    list.forEach(function(label) {
      var btn = document.createElement('button');
      btn.className = 'wc-qr';
      btn.textContent = label;   // SAFE: textContent only
      btn.addEventListener('click', function() { handleInput(label); });
      quickEl.appendChild(btn);
    });
  }

  /* ─── INPUT HANDLER ──────────────────────────────────────── */
  function handleInput(text) {
    text = String(text || '').trim();
    if (!text) return;
    inputEl.value = '';

    renderMsg('user', text);
    quickEl.textContent = '';
    history.push({ role: 'user', text: text });

    var norm = text.toLowerCase();

    // Special actions
    var specials = {
      'hablar con el coach': function() { respond('Te conecto con el coach en WhatsApp 👇', { label: '→ Abrir WhatsApp', url: WC.waLink }, ['¿Qué planes tienen?','¿Cómo me inscribo?']); },
      'hablar por whatsapp': function() { respond('Te conecto con el coach en WhatsApp 👇', { label: '→ Abrir WhatsApp', url: WC.waLink }, ['¿Qué planes tienen?','¿Cómo me inscribo?']); },
      'ver planes y precios': function() { respond('Aquí puedes ver todos los planes 👇', { label: '→ Ver planes', url: 'pagar.html' }, ['¿Cuál me recomiendas?','¿Cómo me inscribo?']); },
      'ver planes': function() { respond('Aquí puedes ver todos los planes 👇', { label: '→ Ver planes', url: 'pagar.html' }, ['¿Cuál me recomiendas?','¿Cómo me inscribo?']); },
      'ir al login': function() { respond('Aquí puedes ingresar a tu portal 👇', { label: '→ Ir al portal', url: 'login.html' }, ['¿Qué incluye el portal?']); },
      'ir a pagar': function() { respond('Te llevo al checkout 👇', { label: '→ Elegir mi plan', url: 'pagar.html' }, ['Hablar con el coach']); },
      'quiero inscribirme': function() { respond('¡Genial! Te llevo al checkout 👇', { label: '→ Elegir mi plan', url: 'pagar.html' }, ['Hablar con el coach']); },
      'quiero el plan élite': function() { respond('¡Excelente elección! Te llevo directo 👇', { label: '→ Plan Élite', url: 'pagar.html?plan=elite' }, ['Hablar con el coach']); }
    };

    var matched = null;
    Object.keys(specials).forEach(function(k) {
      if (norm.indexOf(k) !== -1) matched = specials[k];
    });

    if (matched) {
      setTimeout(function() {
        showTyping();
        setTimeout(function() { removeTyping(); matched(); }, WC.typingDelay);
      }, 80);
    } else if (WC.aiEnabled) {
      // Dify RAG handles all non-special queries
      showTyping();
      callAIBackend(text);
    } else {
      // AI disabled: use local KB matching
      var match = findBestMatch(text);
      setTimeout(function() {
        showTyping();
        setTimeout(function() {
          removeTyping();
          if (match) {
            renderMsg('bot', match.answer, match.action || null);
            setQuickReplies(match.quick || []);
            history.push({ role: 'bot', text: match.answer });
          }
          saveHistory(history);
        }, WC.typingDelay + Math.floor(Math.random() * 200));
      }, 80);
    }
    saveHistory(history);
  }

  function respond(text, action, quick) {
    renderMsg('bot', text, action);
    setQuickReplies(quick || []);
    history.push({ role: 'bot', text: text });
    saveHistory(history);
  }

  /* ─── AI BACKEND ─────────────────────────────────────────── */
  function getSessionId() {
    var sid = null;
    try { sid = localStorage.getItem(WC.sessionKey); } catch(e) {}
    if (!sid) {
      sid = 'wc_' + Date.now() + '_' + Math.random().toString(36).substr(2, 8);
      try { localStorage.setItem(WC.sessionKey, sid); } catch(e) {}
    }
    return sid;
  }

  function callAIBackend(userText) {
    var token = null;
    try { token = localStorage.getItem('wc_token'); } catch(e) {}

    var headers = { 'Content-Type': 'application/json' };
    if (token) headers['Authorization'] = 'Bearer ' + token;

    var payload = {
      message: userText,
      session_id: getSessionId()
    };

    fetch(WC.aiEndpoint, {
      method: 'POST',
      headers: headers,
      body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      removeTyping();
      if (data.ok && data.response) {
        renderMsg('bot', data.response);
        setQuickReplies(['Ver planes y precios', 'Hablar con el coach']);
        history.push({ role: 'bot', text: data.response });
        // Update session_id if server provided one
        if (data.session_id) {
          try { localStorage.setItem(WC.sessionKey, data.session_id); } catch(e) {}
        }
      } else {
        // Fallback to KB fallback message on error
        var fallbackMsg = data.error || 'Lo siento, no pude procesar tu pregunta. Intenta de nuevo o contacta al coach.';
        renderMsg('bot', fallbackMsg);
        setQuickReplies(['Hablar por WhatsApp', 'Ver planes']);
        history.push({ role: 'bot', text: fallbackMsg });
      }
      saveHistory(history);
    })
    .catch(function() {
      removeTyping();
      // Network error: try local KB match first, then generic fallback
      var match = findBestMatch(userText);
      var isFallback = match && match.tags && match.tags[0] === '__fallback__';
      var msg = (!match || isFallback)
        ? 'No pude conectarme al servidor. Contacta al coach por WhatsApp o intenta de nuevo.'
        : match.answer;
      var quick = (!match || isFallback)
        ? ['Hablar por WhatsApp', 'Ver planes']
        : (match.quick || []);
      renderMsg('bot', msg, (!match || isFallback) ? null : (match.action || null));
      setQuickReplies(quick);
      history.push({ role: 'bot', text: msg });
      saveHistory(history);
    });
  }

  /* ─── OPEN / CLOSE ───────────────────────────────────────── */
  function openChat() {
    isOpen = true;
    document.getElementById('wc-window').classList.add('open');
    document.getElementById('wc-window').setAttribute('aria-hidden', 'false');
    document.getElementById('wc-icon-open').style.display = 'none';
    document.getElementById('wc-icon-close').style.display = 'block';
    document.getElementById('wc-notif').style.display = 'none';

    if (!msgsEl.children.length) {
      setTimeout(function() {
        showTyping();
        setTimeout(function() {
          removeTyping();
          renderMsg('bot', '¡Hola! Soy el asistente de WellCore Fitness 💪\nEstoy aquí para responder tus preguntas sobre planes, entrenamiento y nutrición.');
          setQuickReplies(['¿Qué planes tienen?','¿Cómo funciona WellCore?','Quiero inscribirme']);
        }, 600);
      }, 100);
    }
    setTimeout(function() { inputEl.focus(); }, 300);
  }

  function closeChat() {
    isOpen = false;
    document.getElementById('wc-window').classList.remove('open');
    document.getElementById('wc-window').setAttribute('aria-hidden', 'true');
    document.getElementById('wc-icon-open').style.display = 'block';
    document.getElementById('wc-icon-close').style.display = 'none';
  }

  /* ─── INIT ───────────────────────────────────────────────── */
  function init() {
    if (window.location.pathname.indexOf('admin') !== -1) return;

    injectStyles();
    buildWidget();

    msgsEl  = document.getElementById('wc-msgs');
    quickEl = document.getElementById('wc-quick');
    inputEl = document.getElementById('wc-input');

    history = loadHistory();
    if (history.length) {
      history.slice(-10).forEach(function(m) { renderMsg(m.role, m.text); });
    }

    document.getElementById('wc-trigger').addEventListener('click', function() {
      isOpen ? closeChat() : openChat();
    });
    document.getElementById('wc-close-btn').addEventListener('click', closeChat);
    inputEl.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleInput(inputEl.value); }
    });
    document.getElementById('wc-send').addEventListener('click', function() { handleInput(inputEl.value); });

    // Show notif badge after 3s on first visit
    setTimeout(function() {
      if (!isOpen && !history.length) {
        var notif = document.getElementById('wc-notif');
        notif.textContent = '1';
        notif.style.display = 'flex';
      }
    }, 3000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
