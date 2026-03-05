#!/usr/bin/env python3
"""
Fix: 3 null-guards que rompen el script antes de inicializar trackingState
1. showSection(): searchResults sin null-check
2. document.addEventListener('click'): userDropdown + searchResults sin null-check
3. searchInput.addEventListener(): crash si searchInput == null
"""
path = 'rise-dashboard.html'
with open(path, 'r', encoding='utf-8') as f:
    html = f.read()

errors = []

# ── Fix 1: showSection - searchResults null check ─────────────────────────
old1 = "            // Hide search results on navigate\n            document.getElementById('searchResults').classList.remove('show');"
new1 = "            // Hide search results on navigate\n            const _sr0 = document.getElementById('searchResults');\n            if (_sr0) _sr0.classList.remove('show');"
if old1 in html:
    html = html.replace(old1, new1, 1)
    print("OK fix1: showSection searchResults null-check")
else:
    errors.append("FAIL fix1: showSection searchResults not found")

# ── Fix 2: document click handler - userDropdown + searchResults null check ─
old2 = "        document.addEventListener('click', () => {\n            document.getElementById('userDropdown').classList.remove('show');\n            document.getElementById('searchResults').classList.remove('show');\n        });"
new2 = "        document.addEventListener('click', () => {\n            const _ud = document.getElementById('userDropdown');\n            const _sr = document.getElementById('searchResults');\n            if (_ud) _ud.classList.remove('show');\n            if (_sr) _sr.classList.remove('show');\n        });"
if old2 in html:
    html = html.replace(old2, new2, 1)
    print("OK fix2: click handler null-checks")
else:
    errors.append("FAIL fix2: click handler not found")

# ── Fix 3: searchInput/searchResults - wrap in null guard ──────────────────
old3 = "        const searchInput   = document.getElementById('searchInput');\n        const searchResults = document.getElementById('searchResults');\n\n        searchInput.addEventListener('input', (e) => {"
new3 = "        const searchInput   = document.getElementById('searchInput');\n        const searchResults = document.getElementById('searchResults');\n\n        if (searchInput && searchResults) { searchInput.addEventListener('input', (e) => {"
if old3 in html:
    html = html.replace(old3, new3, 1)
    print("OK fix3a: searchInput guard open")
else:
    errors.append("FAIL fix3a: searchInput addEventListener not found")

old4 = "        searchInput.addEventListener('click', (e) => e.stopPropagation());"
new4 = "        searchInput.addEventListener('click', (e) => e.stopPropagation()); } // end if searchInput"
if old4 in html:
    html = html.replace(old4, new4, 1)
    print("OK fix3b: searchInput guard close")
else:
    errors.append("FAIL fix3b: searchInput click listener not found")

if errors:
    print("\nERRORES:")
    for e in errors:
        print(e)
    import sys; sys.exit(1)
else:
    with open(path, 'w', encoding='utf-8') as f:
        f.write(html)
    print("\nDone: null-guards aplicados, trackingState fix listo")
