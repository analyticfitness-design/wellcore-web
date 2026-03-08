/**
 * RISE Modal Popup — Premium 2026
 * Diseño basado en WELLCORE_TENDENCIAS_2026: sharp edges, Anton font,
 * JetBrains Mono, grid background, animación de escala.
 */

(function() {
    if (sessionStorage.getItem('rise_modal_closed')) return;

    const styles = `
        @import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=JetBrains+Mono:wght@400;600&display=swap');

        .rm-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.88);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .rm-overlay.active { display: flex; }

        @keyframes rm-in {
            from { opacity: 0; transform: scale(0.93) translateY(12px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }

        .rm-card {
            background: #0d0d0d;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 0;
            max-width: 480px;
            width: 100%;
            position: relative;
            overflow: hidden;
            animation: rm-in 0.35s cubic-bezier(0.16,1,0.3,1) both;
        }

        /* Red bottom glow bar */
        .rm-card::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #E31E24, #ff4466, #E31E24);
        }

        /* Grid background */
        .rm-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(227,30,36,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(227,30,36,0.04) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
            z-index: 0;
        }

        /* Close button */
        .rm-close {
            position: absolute;
            top: 16px; right: 16px;
            width: 28px; height: 28px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.5);
            font-size: 14px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.15s;
            z-index: 10;
            border-radius: 0;
            font-family: monospace;
        }
        .rm-close:hover { background: rgba(227,30,36,0.15); border-color: rgba(227,30,36,0.4); color: #fff; }

        /* Header strip */
        .rm-header {
            background: rgba(227,30,36,0.08);
            border-bottom: 1px solid rgba(227,30,36,0.15);
            padding: 14px 24px;
            display: flex; align-items: center; gap: 10px;
            position: relative; z-index: 1;
        }
        .rm-dot {
            width: 7px; height: 7px; border-radius: 50%;
            background: #E31E24;
            box-shadow: 0 0 8px #E31E24;
            animation: rm-pulse 1.8s ease-in-out infinite;
            flex-shrink: 0;
        }
        @keyframes rm-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.3); }
        }
        .rm-badge-text {
            font-family: 'JetBrains Mono', monospace;
            font-size: 9px; letter-spacing: 0.22em;
            text-transform: uppercase; color: #E31E24;
        }

        /* Body */
        .rm-body {
            padding: 28px 28px 24px;
            position: relative; z-index: 1;
        }

        .rm-eyebrow {
            font-family: 'JetBrains Mono', monospace;
            font-size: 9px; letter-spacing: 0.3em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.3);
            margin-bottom: 8px;
        }

        .rm-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(52px, 10vw, 72px);
            line-height: 0.88;
            letter-spacing: 1px;
            color: #fff;
            margin: 0 0 6px;
        }
        .rm-title span { color: #E31E24; }

        .rm-subtitle {
            font-size: 13px;
            color: rgba(255,255,255,0.45);
            line-height: 1.6;
            margin-bottom: 24px;
            max-width: 340px;
        }

        /* Price block */
        .rm-price {
            background: rgba(227,30,36,0.08);
            border: 1px solid rgba(227,30,36,0.2);
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex; align-items: baseline; gap: 10px;
        }
        .rm-price-label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 9px; letter-spacing: 0.2em;
            text-transform: uppercase; color: #E31E24;
        }
        .rm-price-amount {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 48px; line-height: 1;
            color: #E31E24; letter-spacing: 1px;
        }
        .rm-price-currency {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px; color: rgba(255,255,255,0.4);
            letter-spacing: 0.1em;
        }

        /* Feature pills */
        .rm-pills {
            display: flex; flex-wrap: wrap; gap: 6px;
            margin-bottom: 24px;
        }
        .rm-pill {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px; letter-spacing: 0.08em;
            color: rgba(255,255,255,0.55);
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            padding: 5px 10px;
            display: flex; align-items: center; gap: 6px;
        }
        .rm-pill::before {
            content: '✓';
            color: #E31E24;
            font-weight: 700;
        }

        /* Buttons */
        .rm-buttons {
            display: flex; gap: 10px;
        }
        .rm-btn-primary {
            flex: 1;
            background: #E31E24;
            color: #fff;
            border: none;
            padding: 14px 20px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px; letter-spacing: 0.15em;
            text-transform: uppercase;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            transition: background 0.15s;
            border-radius: 0;
        }
        .rm-btn-primary:hover { background: #B8181D; }

        .rm-btn-ghost {
            background: transparent;
            color: rgba(255,255,255,0.4);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 14px 20px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px; letter-spacing: 0.15em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.15s;
            border-radius: 0;
        }
        .rm-btn-ghost:hover { border-color: rgba(255,255,255,0.25); color: rgba(255,255,255,0.7); }

        @media (max-width: 520px) {
            .rm-body { padding: 24px 20px 20px; }
            .rm-buttons { flex-direction: column; }
            .rm-title { font-size: 52px; }
        }
    `;

    const styleSheet = document.createElement('style');
    styleSheet.textContent = styles;
    document.head.appendChild(styleSheet);

    const modalHTML = `
        <div class="rm-overlay" id="riseModalOverlay">
            <div class="rm-card">
                <button class="rm-close" id="riseModalClose">✕</button>

                <div class="rm-header">
                    <span class="rm-dot"></span>
                    <span class="rm-badge-text">Oferta especial · Marzo 2026 · Cupos limitados</span>
                </div>

                <div class="rm-body">
                    <div class="rm-eyebrow">// Reto 30 días</div>
                    <h2 class="rm-title">RETO<br><span>RISE</span></h2>
                    <p class="rm-subtitle">Programa 100% personalizado. Coach que responde en 24h. Plataforma con tracking diario.</p>

                    <div class="rm-price">
                        <div>
                            <div class="rm-price-label">Precio especial</div>
                            <div style="display:flex;align-items:baseline;gap:8px;margin-top:4px;">
                                <span class="rm-price-amount">$27</span>
                                <span class="rm-price-currency">USD · único pago</span>
                            </div>
                        </div>
                    </div>

                    <div class="rm-pills">
                        <span class="rm-pill">Programa gym o casa</span>
                        <span class="rm-pill">Guía nutricional</span>
                        <span class="rm-pill">Tracking diario</span>
                        <span class="rm-pill">Comunidad RISE</span>
                    </div>

                    <div class="rm-buttons">
                        <a href="/rise-enroll.html" class="rm-btn-primary">Inscribirme ahora →</a>
                        <button class="rm-btn-ghost" id="riseModalLearn">Más Info</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    function initRiseModal() {
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        const overlay = document.getElementById('riseModalOverlay');
        const closeBtn = document.getElementById('riseModalClose');
        const learnBtn = document.getElementById('riseModalLearn');

        setTimeout(() => { overlay.classList.add('active'); }, 1200);

        function closeModal() {
            overlay.style.opacity = '0';
            overlay.style.transition = 'opacity 0.25s';
            sessionStorage.setItem('rise_modal_closed', 'true');
            setTimeout(() => overlay.remove(), 280);
        }

        closeBtn.addEventListener('click', closeModal);
        learnBtn.addEventListener('click', () => { closeModal(); window.location.href = '/rise.html'; });
        overlay.addEventListener('click', (e) => { if (e.target === overlay) closeModal(); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRiseModal);
    } else {
        initRiseModal();
    }
})();
