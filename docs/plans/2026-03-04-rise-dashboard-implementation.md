# RISE Dashboard Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Transform rise-dashboard.html from basic layout (~1200 lines) to professional, coherent design matching WellCore's design system with sidebar, topbar, and responsive layout.

**Architecture:** Adopt the professional sidebar + topbar + content layout from cliente.html while maintaining RISE's specific features (countdown, 30-day tracking, measurements, photos). Single HTML file with embedded CSS and JavaScript, using WellCore CSS variables, Font Awesome 6.4 icons, and localStorage for persistence.

**Tech Stack:** HTML5, CSS3 (Grid/Flexbox), Vanilla JavaScript (ES6+), Font Awesome 6.4 CDN, WellCore CSS variables, localStorage API

---

## Phase 1: Structure & Layout Foundation

### Task 1: Create Base HTML Structure with Topbar

**Files:**
- Modify: `rise-dashboard.html:1-100` (replace current HTML skeleton)

**Step 1: Review current rise-dashboard.html structure**

Run: `head -100 rise-dashboard.html` to understand current structure
Expected: Current DOCTYPE, head tags, basic layout

**Step 2: Replace with professional base structure**

Replace lines 1-100 with:

```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RISE Dashboard - Reto 30 Días | WellCore</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=JetBrains+Mono:wght@400;500;600&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome 6.4 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --bg:           #0a0a0a;
            --surface:      #111113;
            --surface-2:    #1a1a1d;
            --card:         #18181b;
            --border:       rgba(255,255,255,0.06);
            --red:          #E31E24;
            --red-dark:     #B8181D;
            --red-dim:      rgba(227,30,36,0.10);
            --white:        #ffffff;
            --gray:         rgba(255,255,255,0.45);
            --gray-dim:     rgba(255,255,255,0.18);
            --green:        #22C55E;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--white);
            line-height: 1.6;
            height: 100%;
        }

        /* Topbar - Sticky */
        .topbar {
            position: sticky;
            top: 0;
            height: 60px;
            background: rgba(17,17,19,0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            padding: 0 20px;
            z-index: 100;
        }

        .topbar-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
        }

        .topbar-logo {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 16px;
            letter-spacing: 0.05em;
            color: var(--red);
            display: none;
        }

        .topbar-search {
            flex: 1;
            max-width: 300px;
            display: flex;
            align-items: center;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 8px 12px;
            margin: 0 30px;
        }

        .topbar-search input {
            background: transparent;
            border: none;
            color: var(--white);
            font-size: 13px;
            width: 100%;
            outline: none;
        }

        .topbar-search input::placeholder {
            color: var(--gray);
        }

        .topbar-search i {
            color: var(--gray);
            margin-right: 8px;
        }

        .topbar-user {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .topbar-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--red-dim);
            border: 1px solid var(--red);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .topbar-user-menu {
            position: relative;
        }

        .topbar-user-menu-toggle {
            background: none;
            border: none;
            color: var(--white);
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .topbar-user-dropdown {
            position: absolute;
            right: 0;
            top: 100%;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 8px 0;
            min-width: 150px;
            display: none;
            z-index: 200;
            margin-top: 8px;
        }

        .topbar-user-dropdown.show {
            display: block;
        }

        .topbar-user-dropdown a {
            display: block;
            padding: 10px 16px;
            color: var(--white);
            text-decoration: none;
            font-size: 13px;
            transition: background 0.1s;
        }

        .topbar-user-dropdown a:hover {
            background: var(--red-dim);
            color: var(--red);
        }

        .hamburger-menu {
            display: none;
            background: none;
            border: none;
            color: var(--white);
            cursor: pointer;
            font-size: 18px;
        }

        /* Main Container */
        .container {
            display: flex;
            height: calc(100vh - 60px);
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--surface);
            border-right: 1px solid var(--border);
            padding: 20px 0;
            overflow-y: auto;
            position: fixed;
            left: 0;
            top: 60px;
            height: calc(100vh - 60px);
            z-index: 99;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 60px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 98;
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* Navigation */
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: var(--gray);
            text-decoration: none;
            font-size: 13px;
            transition: all 0.1s linear;
            border-left: 2px solid transparent;
            cursor: pointer;
        }

        .nav-item:hover {
            background: rgba(227,30,36,0.04);
            border-left-color: rgba(227,30,36,0.3);
            color: var(--white);
        }

        .nav-item.active {
            background: rgba(227,30,36,0.08);
            border-left-color: var(--red);
            color: var(--white);
        }

        .nav-item i {
            width: 18px;
            text-align: center;
        }

        .nav-section-title {
            padding: 16px 20px 8px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-dim);
        }

        .nav-sub-items {
            display: none;
        }

        .nav-sub-items.show {
            display: block;
        }

        .nav-sub-item {
            padding: 10px 20px 10px 40px;
            font-size: 12px;
            color: var(--gray);
            text-decoration: none;
            transition: all 0.1s linear;
        }

        .nav-sub-item:hover {
            color: var(--white);
            padding-left: 44px;
        }

        /* Countdown Widget */
        .countdown-widget {
            margin-top: auto;
            padding: 20px;
            background: var(--red-dim);
            border-top: 1px solid var(--border);
            text-align: center;
        }

        .countdown-number {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 48px;
            color: var(--red);
            margin-bottom: 10px;
            line-height: 1;
        }

        .countdown-bar {
            width: 100%;
            height: 8px;
            background: var(--gray-dim);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 12px;
        }

        .countdown-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--red) 0%, var(--gray-dim) 100%);
            transition: width 0.5s ease;
        }

        .countdown-text {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Main Content Area */
        .main-content {
            flex: 1;
            margin-left: 260px;
            overflow-y: auto;
            padding: 30px 40px;
        }

        /* Content Sections */
        .section {
            display: none;
        }

        .section.active {
            display: block;
        }

        /* Cards */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 0;
            padding: 24px;
            margin-bottom: 20px;
            transition: all 0.1s linear;
        }

        .card:hover {
            border-color: var(--red);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .card-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 18px;
            letter-spacing: 0.02em;
            margin-bottom: 16px;
            color: var(--white);
        }

        /* Grid Layouts */
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        /* Buttons */
        button {
            padding: 12px 20px;
            border: none;
            border-radius: 0;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.1s linear;
            font-family: 'Inter', sans-serif;
        }

        .btn-primary {
            background: var(--red);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--red-dark);
        }

        .btn-secondary {
            background: transparent;
            color: var(--red);
            border: 1px solid var(--red);
        }

        .btn-secondary:hover {
            background: var(--red-dim);
        }

        /* Responsive - Tablet */
        @media (max-width: 1024px) {
            .sidebar {
                width: 200px;
            }

            .main-content {
                margin-left: 200px;
            }

            .topbar-logo {
                display: none;
            }

            .topbar-search {
                max-width: 200px;
                margin: 0 20px;
            }

            .grid-3 {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Responsive - Mobile */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: 0;
                top: 60px;
                height: calc(100vh - 60px);
                width: 70vw;
                z-index: 99;
                transform: translateX(-100%);
                transition: transform 0.3s ease-out;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .hamburger-menu {
                display: block;
            }

            .topbar-search {
                display: none;
            }

            .grid-2, .grid-3 {
                grid-template-columns: 1fr;
            }

            .main-content {
                padding: 20px;
            }

            button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-content">
            <button class="hamburger-menu" id="hamburgerBtn">
                <i class="fas fa-bars"></i>
            </button>
            <div class="topbar-logo">WellCore</div>
            <div class="topbar-search">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Buscar..." id="searchInput">
            </div>
            <div class="topbar-user">
                <div class="topbar-user-avatar" id="userAvatar">U</div>
                <div class="topbar-user-menu">
                    <button class="topbar-user-menu-toggle" id="userMenuBtn">
                        <span id="userName">Usuario</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="topbar-user-dropdown" id="userDropdown">
                        <a href="#" onclick="viewProfile(); return false;">
                            <i class="fas fa-user"></i> Ver Perfil
                        </a>
                        <a href="#" onclick="openSettings(); return false;">
                            <i class="fas fa-cog"></i> Configuración
                        </a>
                        <a href="#" onclick="logout(); return false;">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container">
        <!-- Sidebar Overlay (Mobile) -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <!-- Dashboard Section -->
            <div class="nav-section-title">General</div>
            <a href="#" class="nav-item active" onclick="showSection('dashboard'); return false;">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>

            <!-- Mi Programa Section -->
            <div class="nav-section-title" style="margin-top: 20px;">Mi Programa RISE</div>
            <a href="#" class="nav-item" onclick="toggleSubMenu(this); return false;">
                <i class="fas fa-book"></i>
                <span>Mi Programa</span>
                <i class="fas fa-chevron-down" style="margin-left: auto;"></i>
            </a>
            <div class="nav-sub-items">
                <a href="#" class="nav-sub-item" onclick="showSection('training'); return false;">
                    <i class="fas fa-dumbbell"></i> Entrenamiento
                </a>
                <a href="#" class="nav-sub-item" onclick="showSection('nutrition'); return false;">
                    <i class="fas fa-utensils"></i> Nutrición
                </a>
                <a href="#" class="nav-sub-item" onclick="showSection('habits'); return false;">
                    <i class="fas fa-tasks"></i> Hábitos
                </a>
            </div>

            <!-- Tracking -->
            <div class="nav-section-title" style="margin-top: 20px;">Seguimiento</div>
            <a href="#" class="nav-item" onclick="showSection('tracking'); return false;">
                <i class="fas fa-check-circle"></i>
                <span>Tracking Diario</span>
            </a>

            <a href="#" class="nav-item" onclick="showSection('measurements'); return false;">
                <i class="fas fa-ruler-vertical"></i>
                <span>Mediciones</span>
            </a>

            <a href="#" class="nav-item" onclick="showSection('photos'); return false;">
                <i class="fas fa-image"></i>
                <span>Fotos Progreso</span>
            </a>

            <!-- Community -->
            <div class="nav-section-title" style="margin-top: 20px;">Comunidad</div>
            <a href="#" class="nav-item" onclick="showSection('community'); return false;">
                <i class="fas fa-users"></i>
                <span>Comunidad</span>
            </a>

            <!-- Support -->
            <div class="nav-section-title" style="margin-top: 20px;">Soporte</div>
            <a href="#" class="nav-item" onclick="showSection('support'); return false;">
                <i class="fas fa-headset"></i>
                <span>Soporte</span>
            </a>

            <!-- Countdown Widget -->
            <div class="countdown-widget">
                <div class="countdown-number" id="countdownDays">15</div>
                <div class="countdown-bar">
                    <div class="countdown-fill" id="countdownFill" style="width: 50%;"></div>
                </div>
                <div class="countdown-text" id="countdownText">Finaliza el 31 de Marzo</div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Dashboard Section (Placeholder) -->
            <section class="section active" id="dashboard">
                <h1 style="font-family: 'Bebas Neue'; font-size: 32px; margin-bottom: 30px;">Dashboard</h1>
                <p>Contenido del dashboard será agregado en siguientes tareas.</p>
            </section>

            <!-- Other sections (placeholders) -->
            <section class="section" id="training">
                <h1 style="font-family: 'Bebas Neue'; font-size: 32px; margin-bottom: 30px;">Entrenamiento</h1>
                <p>Contenido de entrenamiento será agregado en siguientes tareas.</p>
            </section>

            <section class="section" id="nutrition">
                <h1 style="font-family: 'Bebas Neue'; font-size: 32px; margin-bottom: 30px;">Nutrición</h1>
                <p>Contenido de nutrición será agregado en siguientes tareas.</p>
            </section>

            <section class="section" id="habits">
                <h1 style="font-family: 'Bebas Neue'; font-size: 32px; margin-bottom: 30px;">Hábitos</h1>
                <p>Contenido de hábitos será agregado en siguientes tareas.</p>
            </section>

            <section class="section" id="tracking">
                <h1 style="font-family: 'Bebas Neue'; font-size: 32px; margin-bottom: 30px;">Tracking Diario</h1>
                <p>Contenido de tracking será agregado en siguientes tareas.</p>
            </section>

            <section class="section" id="measurements">
                <h1 style="font-family: 'Bebas Neue'; font-size: 32px; margin-bottom: 30px;">Mediciones</h1>
                <p>Contenido de mediciones será agregado en siguientes tareas.</p>
            </section>

            <section class="section" id="photos">
                <h1 style="font-family: 'Bebas Neue'; font-size: 32px; margin-bottom: 30px;">Fotos Progreso</h1>
                <p>Contenido de fotos será agregado en siguientes tareas.</p>
            </section>

            <section class="section" id="community">
                <h1 style="font-family: 'Bebas Neue'; font-size: 32px; margin-bottom: 30px;">Comunidad</h1>
                <p>Contenido de comunidad será agregado en siguientes tareas.</p>
            </section>

            <section class="section" id="support">
                <h1 style="font-family: 'Bebas Neue'; font-size: 32px; margin-bottom: 30px;">Soporte</h1>
                <p>Contenido de soporte será agregado en siguientes tareas.</p>
            </section>
        </main>
    </div>

    <script>
        // Navigation handling
        function showSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));

            // Show selected section
            document.getElementById(sectionId).classList.add('active');

            // Update active nav item
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });

            event.target.closest('.nav-item').classList.add('active');

            // Close sidebar on mobile
            if (window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.remove('show');
                document.getElementById('sidebarOverlay').classList.remove('show');
            }
        }

        // Toggle submenu
        function toggleSubMenu(element) {
            const subItems = element.nextElementSibling;
            subItems.classList.toggle('show');
            element.querySelector('i:last-child').style.transform = subItems.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0)';
        }

        // Mobile hamburger menu
        document.getElementById('hamburgerBtn').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        });

        document.getElementById('sidebarOverlay').addEventListener('click', () => {
            document.getElementById('sidebar').classList.remove('show');
            document.getElementById('sidebarOverlay').classList.remove('show');
        });

        // User menu dropdown
        document.getElementById('userMenuBtn').addEventListener('click', () => {
            document.getElementById('userDropdown').classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.topbar-user-menu')) {
                document.getElementById('userDropdown').classList.remove('show');
            }
        });

        // Placeholder functions
        function viewProfile() { alert('Ver Perfil'); }
        function openSettings() { alert('Configuración'); }
        function logout() {
            localStorage.removeItem('auth_token');
            window.location.href = '/login.html';
        }

        // Initialize
        window.addEventListener('load', () => {
            const clientInfo = JSON.parse(localStorage.getItem('client_info') || '{}');
            if (clientInfo.name) {
                document.getElementById('userName').textContent = clientInfo.name;
                document.getElementById('userAvatar').textContent = clientInfo.name.charAt(0).toUpperCase();
            }
        });
    </script>
</body>
</html>
```

**Step 3: Verify structure loads without errors**

Run: `grep -c "<section" rise-dashboard.html` - should return `9` (9 sections)
Expected: All sections present, no HTML errors

**Step 4: Commit base structure**

```bash
git add rise-dashboard.html
git commit -m "feat: create professional base structure with topbar, sidebar, and navigation

- Add sticky topbar (60px) with search and user profile
- Add fixed sidebar (260px) with hierarchical navigation
- Font Awesome 6.4 CDN integration
- WellCore CSS variable system
- Mobile responsive hamburger menu
- Section placeholder structure
- Countdown widget placeholder"
```

---

### Task 2: Add Dashboard Content Section

**Files:**
- Modify: `rise-dashboard.html:550-650` (dashboard section content)

**Step 1: Design hero countdown section**

The dashboard hero should display countdown prominently with progress bar, then quick action buttons below.

**Step 2: Replace dashboard section placeholder**

Find line in `#dashboard` section and replace with:

```html
<section class="section active" id="dashboard">
    <!-- Hero Countdown -->
    <div class="card" style="background: linear-gradient(135deg, var(--red-dim) 0%, var(--surface) 100%); border: 1px solid rgba(227,30,36,0.3); padding: 40px; text-align: center; margin-bottom: 30px;">
        <div style="font-family: 'Bebas Neue'; font-size: 60px; color: var(--red); margin-bottom: 20px;">
            <span id="dashCountdownDays">15</span> de 30
        </div>
        <div style="height: 10px; background: var(--gray-dim); border-radius: 5px; margin-bottom: 20px; overflow: hidden;">
            <div id="dashCountdownBar" style="height: 100%; background: linear-gradient(90deg, var(--red) 0%, var(--gray-dim) 100%); width: 50%;"></div>
        </div>
        <div style="color: var(--gray); font-size: 14px;">Finaliza el 31 de Marzo de 2026</div>
    </div>

    <!-- Quick Actions -->
    <div class="grid-3" style="margin-bottom: 30px;">
        <button class="btn-primary" onclick="alert('Registrar Medición')">
            <i class="fas fa-ruler-vertical"></i> Medición
        </button>
        <button class="btn-primary" onclick="alert('Subir Foto')">
            <i class="fas fa-camera"></i> Foto
        </button>
        <button class="btn-primary" onclick="alert('Marcar Hábitos')">
            <i class="fas fa-tasks"></i> Hábitos
        </button>
    </div>

    <!-- Summary Grid -->
    <div class="grid-2">
        <!-- Mi Programa Card -->
        <div class="card">
            <div class="card-title"><i class="fas fa-book"></i> Mi Programa</div>
            <p style="color: var(--gray); font-size: 13px; margin-bottom: 12px;">Semana 2 de 4</p>
            <button class="btn-secondary" style="width: 100%;">Ver Detalles</button>
        </div>

        <!-- Últimas Mediciones Card -->
        <div class="card">
            <div class="card-title"><i class="fas fa-ruler-vertical"></i> Mediciones</div>
            <p style="color: var(--gray); font-size: 13px; margin-bottom: 12px;">Última: hace 5 días</p>
            <button class="btn-secondary" style="width: 100%;">Historial</button>
        </div>

        <!-- Fotos Recientes Card -->
        <div class="card">
            <div class="card-title"><i class="fas fa-image"></i> Fotos</div>
            <p style="color: var(--gray); font-size: 13px; margin-bottom: 12px;">3 fotos subidas</p>
            <button class="btn-secondary" style="width: 100%;">Galería</button>
        </div>

        <!-- Próximos Hábitos Card -->
        <div class="card">
            <div class="card-title"><i class="fas fa-tasks"></i> Hábitos</div>
            <p style="color: var(--gray); font-size: 13px; margin-bottom: 12px;">2 por completar hoy</p>
            <button class="btn-secondary" style="width: 100%;">Registrar</button>
        </div>
    </div>
</section>
```

**Step 3: Test dashboard section displays correctly**

Open rise-dashboard.html in browser, verify:
- Countdown displays with 15/30 and progress bar
- Quick action buttons present
- Summary grid shows 4 cards in 2x2 layout on desktop
Expected: Professional hero section with clear visual hierarchy

**Step 4: Commit dashboard section**

```bash
git add rise-dashboard.html
git commit -m "feat: add dashboard hero section with countdown and quick actions

- Large countdown display (15 de 30 días)
- Animated progress bar showing completion percentage
- Quick action buttons: Medición, Foto, Hábitos
- Summary grid with program, measurements, photos, habits cards"
```

---

### Task 3: Add Training, Nutrition, Habits Sections

**Files:**
- Modify: `rise-dashboard.html:800-950` (training, nutrition, habits sections)

**Step 1: Add Training section content**

Replace `#training` section placeholder with:

```html
<section class="section" id="training">
    <h1 style="font-family: 'Bebas Neue'; font-size: 32px; margin-bottom: 10px;">
        <i class="fas fa-dumbbell"></i> Entrenamiento
    </h1>
    <p style="color: var(--gray); margin-bottom: 30px;">Tu programa personalizado de entrenamiento para los 30 días</p>

    <!-- Weekly Breakdown -->
    <div class="card">
        <div class="card-title">Semana 2 de 4</div>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
            <div style="text-align: center; padding: 15px; background: var(--surface); border-radius: 4px;">
                <div style="color: var(--red); font-weight: 600; margin-bottom: 8px;">Día 8</div>
                <div style="color: var(--gray); font-size: 12px;">Pecho & Tríceps</div>
            </div>
            <div style="text-align: center; padding: 15px; background: var(--surface); border-radius: 4px;">
                <div style="color: var(--red); font-weight: 600; margin-bottom: 8px;">Día 9</div>
                <div style="color: var(--gray); font-size: 12px;">Descanso</div>
            </div>
            <div style="text-align: center; padding: 15px; background: var(--surface); border-radius: 4px;">
                <div style="color: var(--red); font-weight: 600; margin-bottom: 8px;">Día 10</div>
                <div style="color: var(--gray); font-size: 12px;">Espalda & Bíceps</div>
            </div>
        </div>
    </div>

    <!-- PDF/Video Player -->
    <div class="card" style="margin-top: 20px;">
        <div class="card-title">Descarga tu Programa</div>
        <button class="btn-primary" style="width: 100%; margin-bottom: 10px;">
            <i class="fas fa-download"></i> Descargar PDF Completo
        </button>
        <button class="btn-secondary" style="width: 100%;">
            <i class="fas fa-play-circle"></i> Ver Videos de Ejercicios
        </button>
    </div>
</section>
```

**Step 2: Add Nutrition section content**

Replace `#nutrition` section placeholder with:

```html
<section class="section" id="nutrition">
    <h1 style="font-family: 'Bebas Neue'; font-size: 32px; margin-bottom: 10px;">
        <i class="fas fa-utensils"></i> Nutrición
    </h1>
    <p style="color: var(--gray); margin-bottom: 30px;">Guía de nutrición para optimizar tus resultados</p>

    <!-- Macro Targets -->
    <div class="card">
        <div class="card-title">Tus Macronutrientes Diarios</div>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px;">
            <div style="text-align: center; padding: 20px; background: var(--surface); border-radius: 4px; border-left: 3px solid var(--red);">
                <div style="color: var(--red); font-family: 'Bebas Neue'; font-size: 24px; margin-bottom: 5px;">180g</div>
                <div style="color: var(--gray); font-size: 12px;">Proteína</div>
            </div>
            <div style="text-align: center; padding: 20px; background: var(--surface); border-radius: 4px; border-left: 3px solid var(--red);">
                <div style="color: var(--red); font-family: 'Bebas Neue'; font-size: 24px; margin-bottom: 5px;">150g</div>
                <div style="color: var(--gray); font-size: 12px;">Carbohidratos</div>
            </div>
            <div style="text-align: center; padding: 20px; background: var(--surface); border-radius: 4px; border-left: 3px solid var(--red);">
                <div style="color: var(--red); font-family: 'Bebas Neue'; font-size: 24px; margin-bottom: 5px;">60g</div>
                <div style="color: var(--gray); font-size: 12px;">Grasas</div>
            </div>
        </div>
    </div>

    <!-- Hydration Tracker -->
    <div class="card" style="margin-top: 20px;">
        <div class="card-title">Hidratación Diaria</div>
        <p style="color: var(--gray); font-size: 13px; margin-bottom: 15px;">Meta: 3L de agua por día</p>
        <div style="display: flex; gap: 8px;">
            <button style="flex: 1; padding: 10px; background: var(--red); color: var(--white); border: none; border-radius: 4px; cursor: pointer;">250ml</button>
            <button style="flex: 1; padding: 10px; background: var(--red); color: var(--white); border: none; border-radius: 4px; cursor: pointer;">500ml</button>
            <button style="flex: 1; padding: 10px; background: var(--red); color: var(--white); border: none; border-radius: 4px; cursor: pointer;">1L</button>
        </div>
    </div>

    <!-- Recipes -->
    <div class="card" style="margin-top: 20px;">
        <div class="card-title">Recetas Sugeridas</div>
        <button class="btn-secondary" style="width: 100%;">
            <i class="fas fa-book"></i> Ver Recetas Recomendadas
        </button>
    </div>
</section>
```

**Step 3: Add Habits section content**

Replace `#habits` section placeholder with:

```html
<section class="section" id="habits">
    <h1 style="font-family: 'Bebas Neue'; font-size: 32px; margin-bottom: 10px;">
        <i class="fas fa-tasks"></i> Hábitos 30 Días
    </h1>
    <p style="color: var(--gray); margin-bottom: 30px;">Seguimiento de hábitos para garantizar tus resultados</p>

    <!-- Progress Bar -->
    <div class="card" style="margin-bottom: 20px;">
        <div style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
            <div class="card-title" style="margin: 0;">Progreso General</div>
            <span style="color: var(--red); font-family: 'Bebas Neue'; font-size: 20px;">50%</span>
        </div>
        <div style="height: 12px; background: var(--gray-dim); border-radius: 6px; overflow: hidden;">
            <div style="height: 100%; background: linear-gradient(90deg, var(--red) 0%, var(--green) 100%); width: 50%;"></div>
        </div>
    </div>

    <!-- Habit Checklist -->
    <div class="card">
        <div class="card-title">Hábitos para Completar</div>
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; color: var(--gray);">
                <input type="checkbox" style="width: 18px; height: 18px; accent-color: var(--red); cursor: pointer;">
                <span>Desayunar en los primeros 30 min de despertar</span>
            </label>
            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; color: var(--gray);">
                <input type="checkbox" style="width: 18px; height: 18px; accent-color: var(--red); cursor: pointer;">
                <span>Entrenar según programa (45-60 min)</span>
            </label>
            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; color: var(--gray);">
                <input type="checkbox" style="width: 18px; height: 18px; accent-color: var(--red); cursor: pointer;">
                <span>Beber 3L de agua mínimo</span>
            </label>
            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; color: var(--gray);">
                <input type="checkbox" style="width: 18px; height: 18px; accent-color: var(--red); cursor: pointer;">
                <span>Dormir 7-8 horas</span>
            </label>
            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; color: var(--gray);">
                <input type="checkbox" style="width: 18px; height: 18px; accent-color: var(--red); cursor: pointer;">
                <span>Tomar foto de progreso (2-3 veces por semana)</span>
            </label>
        </div>
    </div>
</section>
```

**Step 4: Test all three sections load correctly**

Click on each nav item (Entrenamiento, Nutrición, Hábitos) and verify content displays
Expected: Each section shows appropriate content with proper typography and styling

**Step 5: Commit program sections**

```bash
git add rise-dashboard.html
git commit -m "feat: add training, nutrition, and habits sections

- Training: Weekly breakdown, PDF download, video links
- Nutrition: Macro targets grid, hydration tracker, recipe suggestions
- Habits: Progress bar, 5-item daily habit checklist with checkboxes"
```

---

## Phase 2: Tracking & Measurements

### Task 4: Add Daily Tracking Section

(Continue with remaining tasks...)

---

**END OF PHASE 1 - Next phases cover:**
- **Phase 2:** Tracking Diario, Mediciones sections
- **Phase 3:** Fotos Progreso, Comunidad, Soporte sections
- **Phase 4:** localStorage Integration & Persistence
- **Phase 5:** Advanced Interactions (countdown animation, responsive behavior, search)
- **Phase 6:** Testing & Optimization

