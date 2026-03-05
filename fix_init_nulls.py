#!/usr/bin/env python3
"""Fix: null-guard en hamburgerBtn para que el script llegue a const trackingState"""
path = 'rise-dashboard.html'
with open(path, 'r', encoding='utf-8') as f:
    html = f.read()

errors = []

# Fix: hamburgerBtn null guard
old = "        document.getElementById('hamburgerBtn').addEventListener('click', () => {\n            document.getElementById('sidebar').classList.toggle('show');\n            document.getElementById('sidebarOverlay').classList.toggle('show');\n        });"
new = "        const _hambBtn = document.getElementById('hamburgerBtn');\n        if (_hambBtn) _hambBtn.addEventListener('click', () => {\n            document.getElementById('sidebar').classList.toggle('show');\n            document.getElementById('sidebarOverlay').classList.toggle('show');\n        });"

if old in html:
    html = html.replace(old, new, 1)
    print("OK fix: hamburgerBtn null-guard")
else:
    errors.append("FAIL: hamburgerBtn block not found")

if errors:
    print("\nERRORES:")
    for e in errors: print(e)
    import sys; sys.exit(1)
else:
    with open(path, 'w', encoding='utf-8') as f:
        f.write(html)
    print("Done")
