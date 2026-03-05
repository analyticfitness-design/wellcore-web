#!/usr/bin/env python3
"""
Fix hamburger button móvil en rise-dashboard.html:
1. Agregar <button id="hamburgerBtn"> al topbar
2. Corregir CSS: hamburger-menu debe mostrarse en mobile (display:block, no none)
"""
path = 'rise-dashboard.html'
with open(path, 'r', encoding='utf-8') as f:
    html = f.read()

errors = []

# ── Fix 1: Agregar botón hamburger al topbar (antes del logo) ────────────
old_topbar = '''        <div class="topbar-content">
            <a href="/" class="topbar-logo">'''
new_topbar = '''        <div class="topbar-content">
            <button id="hamburgerBtn" class="hamburger-menu" aria-label="Menú">
                <i class="fas fa-bars"></i>
            </button>
            <a href="/" class="topbar-logo">'''
if old_topbar in html:
    html = html.replace(old_topbar, new_topbar, 1)
    print("OK fix1: hamburgerBtn agregado al topbar")
else:
    errors.append("FAIL fix1: topbar-content no encontrado")

# ── Fix 2: CSS mobile — mostrar hamburger, no ocultarlo ─────────────────
old_css = '            .hamburger-menu { display: none; }'
new_css = '            .hamburger-menu { display: flex; align-items: center; justify-content: center; }'
if old_css in html:
    html = html.replace(old_css, new_css, 1)
    print("OK fix2: CSS hamburger corregido (display:flex en mobile)")
else:
    errors.append("FAIL fix2: CSS hamburger-menu no encontrado")

if errors:
    print("\nERRORES:")
    for e in errors: print(e)
    import sys; sys.exit(1)
else:
    with open(path, 'w', encoding='utf-8') as f:
        f.write(html)
    print("\nDone: hamburger móvil listo")
