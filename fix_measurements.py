#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Parche: tabla visual de mediciones en rise-dashboard.html
1. CSS para tabla + tarjeta de resumen
2. HTML del historial actualizado
3. renderMeasurements() con tabla + flechas de tendencia (sin innerHTML)
"""
import sys

path = 'rise-dashboard.html'
with open(path, 'r', encoding='utf-8') as f:
    html = f.read()

errors = []

# =============================================================
# 1. CSS
# =============================================================
old_photo_css = '        .photo-upload-box {'

new_photo_css = '''        /* Tabla mediciones */
        .meas-summary-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:6px; }
        .meas-summary-cell { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.06); padding:10px 8px; text-align:center; }
        .meas-summary-label { font-size:10px; color:var(--gray); text-transform:uppercase; letter-spacing:1px; margin-bottom:4px; }
        .meas-summary-value { font-size:16px; font-weight:700; color:var(--white); font-family:'JetBrains Mono',monospace; }
        .meas-summary-delta { font-size:11px; font-family:'JetBrains Mono',monospace; margin-top:3px; }
        .delta-good { color:#22C55E; } .delta-bad { color:#E31E24; } .delta-neut { color:var(--gray); }
        .meas-table-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; }
        .meas-table { width:100%; border-collapse:collapse; font-size:11px; min-width:340px; }
        .meas-table th { font-family:'JetBrains Mono',monospace; color:var(--gray); font-weight:600; padding:8px 6px; text-align:right; border-bottom:1px solid rgba(255,255,255,0.08); white-space:nowrap; font-size:10px; letter-spacing:0.5px; text-transform:uppercase; }
        .meas-table th:first-child { text-align:left; }
        .meas-table td { padding:8px 6px; text-align:right; border-bottom:1px solid rgba(255,255,255,0.04); color:var(--white); font-family:'JetBrains Mono',monospace; }
        .meas-table td:first-child { text-align:left; color:var(--gray); font-size:10px; }
        .meas-table tr:last-child td { border-bottom:none; }
        .meas-table tbody tr:first-child td { font-weight:600; }
        .trend-up { color:#22C55E; font-size:10px; } .trend-down { color:#E31E24; font-size:10px; }
        /* ───────── */
        .photo-upload-box {'''

if old_photo_css in html:
    html = html.replace(old_photo_css, new_photo_css, 1)
    print("OK CSS tabla")
else:
    errors.append("FAIL CSS: .photo-upload-box no encontrado")

# =============================================================
# 2. HTML historial
# =============================================================
old_hist = '''                <div class="card">
                    <div class="card-title">Historial de Mediciones</div>
                    <div id="measurementHistory">
                        <p style="color: var(--gray); font-size: 13px;">Sin mediciones registradas aún.</p>
                    </div>
                </div>
            </section>

            <!-- ═══════════════════ PHOTOS ═══════════════════ -->'''

new_hist = '''                <div class="card" id="measSummaryCard" style="display:none;">
                    <div class="card-title" style="margin-bottom:14px;"><i class="fas fa-chart-bar" style="color:var(--red);margin-right:6px;"></i> Resumen de Progreso</div>
                    <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--gray);font-family:JetBrains Mono,monospace;margin-bottom:8px;letter-spacing:1px;text-transform:uppercase;">
                        <span>Inicio</span><span style="text-align:center;">Cambio</span><span style="text-align:right;">Actual</span>
                    </div>
                    <div id="measSummaryGrid" class="meas-summary-grid"></div>
                </div>

                <div class="card">
                    <div class="card-title"><i class="fas fa-table" style="color:var(--red);margin-right:6px;"></i> Historial de Mediciones</div>
                    <div id="measurementHistory">
                        <p style="color: var(--gray); font-size: 13px;">Sin mediciones registradas a\u00fan.</p>
                    </div>
                </div>
            </section>

            <!-- \u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550 PHOTOS \u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550\u2550 -->'''

if old_hist in html:
    html = html.replace(old_hist, new_hist, 1)
    print("OK HTML historial")
else:
    errors.append("FAIL HTML historial no encontrado")

# =============================================================
# 3. renderMeasurements() - todo DOM, sin innerHTML
# =============================================================
old_render = '''        function renderMeasurements(list) {
            const container = document.getElementById('measurementHistory');
            if (!list || list.length === 0) {
                container.textContent = 'Sin mediciones registradas aún.';
                return;
            }

            container.textContent = '';
            [...list].reverse().forEach(m => {
                const entry = document.createElement('div');
                entry.style.cssText = 'padding: 15px; background: var(--surface); margin-bottom: 8px; font-size: 12px;';

                const dateEl = document.createElement('div');
                dateEl.style.cssText = 'color: var(--white); font-weight: 600; margin-bottom: 8px; font-family: JetBrains Mono, monospace;';
                dateEl.textContent = m.date;
                entry.appendChild(dateEl);

                const grid = document.createElement('div');
                grid.style.cssText = 'display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;';

                [['Peso', m.weight, 'kg'], ['Pecho', m.chest, 'cm'], ['Cintura', m.waist, 'cm'],
                 ['Cadera', m.hips, 'cm'], ['Muslo', m.thigh, 'cm'], ['Brazo', m.arm, 'cm']]
                .forEach(([label, val, unit]) => {
                    const cell = document.createElement('div');
                    cell.style.color = 'var(--gray)';
                    const strong = document.createElement('span');
                    strong.style.color = 'var(--white)';
                    strong.textContent = val + unit;
                    cell.textContent = label + ': ';
                    cell.appendChild(strong);
                    grid.appendChild(cell);
                });

                entry.appendChild(grid);
                container.appendChild(entry);
            });
        }'''

new_render = r'''        function renderMeasurements(list) {
            var container   = document.getElementById('measurementHistory');
            var summaryCard = document.getElementById('measSummaryCard');
            if (!list || list.length === 0) {
                container.textContent = 'Sin mediciones registradas a\u00fan.';
                if (summaryCard) summaryCard.style.display = 'none';
                return;
            }

            var sorted = [...list].sort(function(a, b) { return (a.date||'').localeCompare(b.date||''); });
            var first  = sorted[0];
            var latest = sorted[sorted.length - 1];
            var fields = [
                { key:'weight', label:'Peso',    unit:'kg', lowerBetter:true  },
                { key:'chest',  label:'Pecho',   unit:'cm', lowerBetter:false },
                { key:'waist',  label:'Cintura', unit:'cm', lowerBetter:true  },
                { key:'hips',   label:'Cadera',  unit:'cm', lowerBetter:false },
                { key:'thigh',  label:'Muslo',   unit:'cm', lowerBetter:false },
                { key:'arm',    label:'Brazo',   unit:'cm', lowerBetter:false }
            ];

            // ── Resumen primer vs. \u00faltimo ─────────────────
            if (summaryCard) {
                if (sorted.length >= 2) {
                    summaryCard.style.display = 'block';
                    var sgrid = document.getElementById('measSummaryGrid');
                    sgrid.textContent = '';
                    fields.forEach(function(f) {
                        var vFirst  = parseFloat(first[f.key])  || 0;
                        var vLatest = parseFloat(latest[f.key]) || 0;
                        if (!vFirst && !vLatest) return;
                        var delta = vLatest - vFirst;
                        var isGood = f.lowerBetter ? delta <= 0 : delta >= 0;
                        var deltaClass = delta === 0 ? 'delta-neut' : (isGood ? 'delta-good' : 'delta-bad');

                        var cell = document.createElement('div');
                        cell.className = 'meas-summary-cell';

                        var lbl = document.createElement('div');
                        lbl.className = 'meas-summary-label';
                        lbl.textContent = f.label;
                        cell.appendChild(lbl);

                        var valDiv = document.createElement('div');
                        valDiv.className = 'meas-summary-value';
                        valDiv.textContent = vLatest || '\u2014';
                        var unitSpan = document.createElement('span');
                        unitSpan.style.cssText = 'font-size:10px;color:var(--gray);';
                        unitSpan.textContent = f.unit;
                        valDiv.appendChild(unitSpan);
                        cell.appendChild(valDiv);

                        var dDiv = document.createElement('div');
                        dDiv.className = 'meas-summary-delta ' + deltaClass;
                        var sign = delta > 0 ? '+' : '';
                        dDiv.textContent = delta !== 0 ? (sign + delta.toFixed(1) + f.unit) : '\u2014';
                        cell.appendChild(dDiv);
                        sgrid.appendChild(cell);
                    });
                } else {
                    summaryCard.style.display = 'none';
                }
            }

            // ── Tabla de historial ──────────────────────────
            container.textContent = '';
            var wrap  = document.createElement('div');
            wrap.className = 'meas-table-wrap';
            var table = document.createElement('table');
            table.className = 'meas-table';
            var thead = document.createElement('thead');
            var headRow = document.createElement('tr');
            ['Fecha','Peso','Pecho','Cintura','Cadera','Muslo','Brazo'].forEach(function(h) {
                var th = document.createElement('th');
                th.textContent = h;
                headRow.appendChild(th);
            });
            thead.appendChild(headRow);
            table.appendChild(thead);

            var tbody = document.createElement('tbody');
            [...sorted].reverse().forEach(function(m, idx, arr) {
                var prevM = arr[idx + 1];
                var tr = document.createElement('tr');
                var tdDate = document.createElement('td');
                tdDate.textContent = m.date;
                tr.appendChild(tdDate);
                fields.forEach(function(f) {
                    var td  = document.createElement('td');
                    var val = parseFloat(m[f.key]);
                    var pv  = prevM ? parseFloat(prevM[f.key]) : null;
                    if (isNaN(val)) {
                        td.textContent = '\u2014';
                        td.style.color = 'rgba(255,255,255,0.2)';
                    } else {
                        td.textContent = val.toFixed(1);
                        if (pv !== null && !isNaN(pv) && pv !== 0 && Math.abs(val - pv) >= 0.1) {
                            var diff = val - pv;
                            var isGood = f.lowerBetter ? diff < 0 : diff > 0;
                            var arrow = document.createElement('span');
                            arrow.className = isGood ? 'trend-up' : 'trend-down';
                            arrow.textContent = diff > 0 ? ' \u25b2' : ' \u25bc';
                            td.appendChild(arrow);
                        }
                    }
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });
            table.appendChild(tbody);
            wrap.appendChild(table);
            container.appendChild(wrap);

            if (sorted.length > 0) {
                var hint = document.createElement('div');
                hint.style.cssText = 'font-size:10px;color:var(--gray);text-align:center;margin-top:10px;font-family:JetBrains Mono,monospace;';
                hint.textContent = sorted.length + ' registro(s)';
                container.appendChild(hint);
            }
        }'''

if old_render in html:
    html = html.replace(old_render, new_render, 1)
    print("OK renderMeasurements() reemplazada")
else:
    errors.append("FAIL renderMeasurements() no encontrada")

# =============================================================
# RESULTADO
# =============================================================
if errors:
    print("\nERRORES:")
    for e in errors:
        print(e)
    sys.exit(1)
else:
    with open(path, 'w', encoding='utf-8') as f:
        f.write(html)
    print("\nDone: tabla de mediciones lista")
