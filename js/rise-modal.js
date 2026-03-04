/**
 * RISE Modal Popup
 * Muestra un modal promocional al entrar a la página
 * Solo aparece una vez por sesión (localStorage)
 */

(function() {
    // No mostrar si ya fue cerrado en esta sesión
    if (sessionStorage.getItem('rise_modal_closed')) {
        return;
    }

    // Crear estilos del modal
    const styles = `
        .rise-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease-in;
        }

        .rise-modal-overlay.active {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .rise-modal-content {
            background: #111113;
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 4px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            position: relative;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            animation: slideUp 0.4s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .rise-modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            color: #E31E24;
            font-size: 24px;
            cursor: pointer;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s;
        }

        .rise-modal-close:hover {
            transform: scale(1.2);
        }

        .rise-modal-badge {
            display: inline-block;
            background: rgba(227, 30, 36, 0.2);
            border: 1px solid #E31E24;
            color: #E31E24;
            padding: 6px 12px;
            border-radius: 16px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
        }

        .rise-modal-title {
            color: #fff;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 12px;
            line-height: 1.2;
        }

        .rise-modal-subtitle {
            color: #aaa;
            font-size: 15px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .rise-modal-price {
            background: rgba(227, 30, 36, 0.1);
            border: 1px solid rgba(227, 30, 36, 0.3);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .rise-modal-price-label {
            color: #E31E24;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .rise-modal-price-value {
            color: #E31E24;
            font-size: 24px;
            font-weight: 700;
        }

        .rise-modal-features {
            margin-bottom: 20px;
        }

        .rise-modal-feature {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
            font-size: 13px;
            color: #ccc;
        }

        .rise-modal-feature-icon {
            color: #E31E24;
            margin-right: 8px;
            font-weight: 700;
            min-width: 20px;
        }

        .rise-modal-buttons {
            display: flex;
            gap: 10px;
        }

        .rise-modal-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .rise-modal-btn-primary {
            background: #E31E24;
            color: white;
        }

        .rise-modal-btn-primary:hover {
            background: #B8181D;
        }

        .rise-modal-btn-secondary {
            background: transparent;
            color: #E31E24;
            border: 1px solid #E31E24;
        }

        .rise-modal-btn-secondary:hover {
            background: rgba(227, 30, 36, 0.1);
        }

        @media (max-width: 600px) {
            .rise-modal-content {
                padding: 30px;
            }

            .rise-modal-title {
                font-size: 24px;
            }

            .rise-modal-buttons {
                flex-direction: column;
            }
        }
    `;

    // Inyectar estilos
    const styleSheet = document.createElement('style');
    styleSheet.textContent = styles;
    document.head.appendChild(styleSheet);

    // HTML del modal
    const modalHTML = `
        <div class="rise-modal-overlay" id="riseModalOverlay">
            <div class="rise-modal-content">
                <button class="rise-modal-close" id="riseModalClose">&times;</button>

                <div class="rise-modal-badge">🚀 Oferta Especial Marzo</div>
                <h2 class="rise-modal-title">Reto RISE</h2>
                <p class="rise-modal-subtitle">30 días transformando tu entrenamiento con programa 100% personalizado</p>

                <div class="rise-modal-price">
                    <div class="rise-modal-price-label">Precio especial</div>
                    <div class="rise-modal-price-value">$33 USD</div>
                </div>

                <div class="rise-modal-features">
                    <div class="rise-modal-feature">
                        <span class="rise-modal-feature-icon">✓</span>
                        <span>Programa personalizado (gym o casa)</span>
                    </div>
                    <div class="rise-modal-feature">
                        <span class="rise-modal-feature-icon">✓</span>
                        <span>Guía de nutrición y hábitos</span>
                    </div>
                    <div class="rise-modal-feature">
                        <span class="rise-modal-feature-icon">✓</span>
                        <span>Trazabilidad y tracking diario</span>
                    </div>
                    <div class="rise-modal-feature">
                        <span class="rise-modal-feature-icon">✓</span>
                        <span>Acceso a comunidad RISE</span>
                    </div>
                </div>

                <div class="rise-modal-buttons">
                    <a href="/rise-enroll.html" class="rise-modal-btn rise-modal-btn-primary">Inscribirse</a>
                    <button class="rise-modal-btn rise-modal-btn-secondary" id="riseModalLearn">Más Info</button>
                </div>
            </div>
        </div>
    `;

    // Esperar a que DOM esté listo
    function initRiseModal() {
        // Insertar HTML
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Referencias
        const overlay = document.getElementById('riseModalOverlay');
        const closeBtn = document.getElementById('riseModalClose');
        const learnBtn = document.getElementById('riseModalLearn');

        // Mostrar modal después de 1 segundo
        setTimeout(() => {
            overlay.classList.add('active');
        }, 1000);

        // Cerrar modal
        function closeModal() {
            overlay.classList.remove('active');
            sessionStorage.setItem('rise_modal_closed', 'true');
            setTimeout(() => {
                overlay.remove();
            }, 300);
        }

        closeBtn.addEventListener('click', closeModal);

        // Más info
        learnBtn.addEventListener('click', () => {
            closeModal();
            window.location.href = '/rise.html';
        });

        // Cerrar al clickear fuera del modal
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                closeModal();
            }
        });
    }

    // Ejecutar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRiseModal);
    } else {
        initRiseModal();
    }
})();
