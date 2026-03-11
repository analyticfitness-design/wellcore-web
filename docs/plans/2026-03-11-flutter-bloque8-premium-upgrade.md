# WellCore App — Bloque 8: Flutter Premium Upgrade

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Elevar la WellCore app al nivel de apps fitness de clase mundial (Whoop, Apple Fitness+, Arc) mediante la incorporación de packages premium de la industria, un sistema de gauge animado para WellScore, activity rings tipo Apple Watch, un bottom nav con frosted glass, un dashboard Bento Grid, transiciones OpenContainer, y la fuente Outfit via Google Fonts — sin romper ninguna funcionalidad del Bloque 7.

**Architecture:** Layer progresivo sobre el sistema B7 ya implementado. Se agregan 7 nuevos packages al `pubspec.yaml`, 4 nuevos widgets core (`GradientText`, `WellScoreGauge`, `ActivityRings`, `FrostedNavBar`), se refactoriza el dashboard a Bento Grid con `StaggeredGrid`, se mejora el check-in con `WoltModalSheet` multi-página, y se aplican `OpenContainer` transitions en cards de retos y tickets. El tema global adopta la fuente `Outfit` mediante `google_fonts`.

**Tech Stack:** Flutter 3.x · Riverpod · flutter_animate ^4.5.0 · fl_chart ^0.68.0 · phosphor_flutter ^2.1.0 · **skeletonizer ^2.1.3** · **wolt_modal_sheet ^0.11.0** · **animations ^2.1.1** · **gap ^3.0.1** · **gauge_indicator ^0.4.3** · **flutter_staggered_grid_view ^0.7.0** · **google_fonts ^6.2.1**

**Repo:** `C:\Users\GODSF\Herd\App wellcorefitness\wellcore-app\`

---

## Contexto del proyecto

### Estado al inicio de B8 (ya implementado en B7)
1. **WellCoreColors v8** — glow tokens, wellscoreColor(), planColor(), duration constants
2. **WellCoreCard v2** — press animation con GestureDetector + AnimatedScale, variant glow, shimmerLoading
3. **ClientShell** — 5 tabs + FAB rojo central con notch
4. **Dashboard** — SliverAppBar collapsible + WellScore lineal + ActivityFeedCard + GridView 2-col
5. **ProfileScreen** — pantalla "Yo" completa con stats y logout
6. **LoginScreen** — diseño cinematográfico con partículas y animaciones de entrada
7. **SkeletonLoader básico** — shimmerLoading en WellCoreCard (manual, no automático)

### Lo que falta para ser PREMIUM (objetivo B8)
- **Gauge real tipo Whoop** — el WellScore lineal actual no tiene impacto visual suficiente
- **Activity Rings** — métrica visual de movimiento/ejercicio/descanso (Apple Watch style)
- **Frosted Glass NavBar** — el BottomAppBar actual usa color sólido simple
- **Bento Grid dashboard** — el GridView 2-col uniforme desperdicia jerarquía visual
- **OpenContainer transitions** — las navegaciones actuales son push simples
- **Skeletonizer automático** — el shimmerLoading manual no funciona con listas reales
- **WoltModalSheet check-in** — el check-in actual es pantalla completa (mala UX móvil)
- **GradientText + Outfit font** — los headers son texto plano blanco sin personalidad

### Referentes de diseño
- **Whoop** — gauge circular con zonas de color, recovery score prominente
- **Apple Fitness+** — activity rings concéntricos con animación de relleno
- **Arc** — frosted glass navigation con blur backdrop
- **Notion** — bento grid asimétrico para dashboard

---

## Mapa de archivos

### Crear nuevos
- `lib/core/widgets/gradient_text.dart` — ShaderMask con gradiente configurable
- `lib/core/widgets/wellscore_gauge.dart` — gauge animado tipo Whoop con gauge_indicator
- `lib/core/widgets/activity_rings.dart` — anillos concéntricos con CustomPainter + TweenAnimationBuilder
- `lib/core/widgets/frosted_nav_bar.dart` — BackdropFilter glass nav extraído de ClientShell

### Modificar
- `pubspec.yaml` — agregar 7 packages premium + ejecutar `flutter pub get`
- `lib/core/theme/wellcore_theme.dart` — GoogleFonts.outfitTextTheme() como textTheme
- `lib/core/router/app_router.dart` — ClientShell usa FrostedNavBar; FAB integrado sin notch
- `lib/features/dashboard/client_dashboard_screen.dart` — Bento Grid + GradientText header + WellScoreGauge + ActivityRings
- `lib/features/checkins/checkin_screen.dart` — WoltModalSheet 3 páginas + Skeletonizer
- `lib/features/metrics/metrics_screen.dart` — Skeletonizer en listas de métricas
- `lib/features/health/habit_tracking_screen.dart` — Skeletonizer en lista de hábitos
- `lib/features/challenges/challenges_screen.dart` — OpenContainer en cards de retos

---

## Chunk 1: Foundation — Packages + Font + GradientText

### Task B8-T1: Agregar packages premium al pubspec.yaml

**Archivos:**
- Modify: `pubspec.yaml`

- [ ] **Step 1:** Leer el pubspec actual para verificar el bloque `dependencies:`

```bash
cat "pubspec.yaml"
```

- [ ] **Step 2:** Agregar los 7 nuevos packages al bloque `dependencies:` (después de `phosphor_flutter`):

```yaml
  skeletonizer: ^2.1.3
  wolt_modal_sheet: ^0.11.0
  animations: ^2.1.1
  gap: ^3.0.1
  gauge_indicator: ^0.4.3
  flutter_staggered_grid_view: ^0.7.0
  google_fonts: ^6.2.1
```

El bloque `dependencies:` completo debe quedar así (orden alfabético sugerido para nuevos packages):

```yaml
dependencies:
  flutter:
    sdk: flutter
  animations: ^2.1.1
  cached_network_image: ^3.3.1
  dio: ^5.4.3
  fl_chart: ^0.68.0
  flutter_animate: ^4.5.0
  flutter_riverpod: ^2.5.1
  flutter_secure_storage: ^9.0.0
  flutter_staggered_grid_view: ^0.7.0
  gap: ^3.0.1
  gauge_indicator: ^0.4.3
  go_router: ^13.2.0
  google_fonts: ^6.2.1
  health: '10.2.0'
  hive_flutter: ^1.1.0
  image_picker: ^1.0.7
  intl: ^0.19.0
  permission_handler: ^11.3.0
  phosphor_flutter: ^2.1.0
  retrofit: ^4.1.0
  riverpod_annotation: ^2.3.5
  skeletonizer: ^2.1.3
  url_launcher: ^6.2.0
  web_socket_channel: ^2.4.0
  wolt_modal_sheet: ^0.11.0
```

- [ ] **Step 3:** Ejecutar pub get

```bash
cd "C:\Users\GODSF\Herd\App wellcorefitness\wellcore-app"
flutter pub get
```

Expected: `Got dependencies!` sin errores de resolución.

- [ ] **Step 4:** Verificar que no haya conflictos

```bash
flutter pub deps | grep -E "(skeletonizer|wolt_modal|animations|gap|gauge|staggered|google_fonts)"
```

Expected: 7 líneas mostrando los packages con sus versiones resueltas.

- [ ] **Step 5:** Commit

```bash
git add pubspec.yaml pubspec.lock
git commit -m "feat(b8-t1): add 7 premium packages - skeletonizer, wolt_modal_sheet, animations, gap, gauge_indicator, staggered_grid, google_fonts"
```

---

### Task B8-T2: Outfit font via Google Fonts + actualizar tema

**Archivos:**
- Modify: `lib/core/theme/wellcore_theme.dart`

- [ ] **Step 1:** Leer el archivo actual

```bash
cat "lib/core/theme/wellcore_theme.dart"
```

- [ ] **Step 2:** Reemplazar el contenido completo del archivo con la versión que incluye Outfit:

```dart
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'wellcore_colors.dart';
import 'wellcore_spacing.dart';

ThemeData buildWellCoreTheme() {
  // Base text theme con Outfit font (variable weight: 100-900)
  final outfitTextTheme = GoogleFonts.outfitTextTheme(ThemeData.dark().textTheme);

  return ThemeData(
    useMaterial3: true,
    brightness: Brightness.dark,
    // Outfit como font principal; Axiforma como fallback si no hay red
    textTheme: outfitTextTheme.copyWith(
      displayLarge: outfitTextTheme.displayLarge?.copyWith(
        fontWeight: FontWeight.w900,
        letterSpacing: -2,
        color: WellCoreColors.textPrimary,
      ),
      displayMedium: outfitTextTheme.displayMedium?.copyWith(
        fontWeight: FontWeight.w800,
        letterSpacing: -1.5,
        color: WellCoreColors.textPrimary,
      ),
      titleLarge: outfitTextTheme.titleLarge?.copyWith(
        fontWeight: FontWeight.w700,
        letterSpacing: -0.5,
        color: WellCoreColors.textPrimary,
      ),
      titleMedium: outfitTextTheme.titleMedium?.copyWith(
        fontWeight: FontWeight.w600,
        letterSpacing: -0.3,
        color: WellCoreColors.textPrimary,
      ),
      bodyLarge: outfitTextTheme.bodyLarge?.copyWith(
        fontWeight: FontWeight.w400,
        color: WellCoreColors.textPrimary,
      ),
      bodyMedium: outfitTextTheme.bodyMedium?.copyWith(
        color: WellCoreColors.textDim,
      ),
      labelSmall: outfitTextTheme.labelSmall?.copyWith(
        fontWeight: FontWeight.w600,
        letterSpacing: 1.2,
        color: WellCoreColors.textMuted,
      ),
    ),
    scaffoldBackgroundColor: WellCoreColors.canvas,
    colorScheme: const ColorScheme.dark(
      primary: WellCoreColors.primary,
      secondary: WellCoreColors.primaryLight,
      surface: WellCoreColors.surface1,
      error: WellCoreColors.error,
      onPrimary: Colors.white,
      onSecondary: Colors.white,
      onSurface: WellCoreColors.textPrimary,
      onError: Colors.white,
    ),
    cardTheme: CardThemeData(
      color: WellCoreColors.surface1,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(WellCoreSpacing.radiusMd),
        side: const BorderSide(color: WellCoreColors.border, width: 1),
      ),
    ),
    appBarTheme: AppBarTheme(
      backgroundColor: WellCoreColors.surface0,
      elevation: 0,
      scrolledUnderElevation: 0,
      centerTitle: false,
      foregroundColor: WellCoreColors.textPrimary,
      titleTextStyle: GoogleFonts.outfit(
        color: WellCoreColors.textPrimary,
        fontSize: 18,
        fontWeight: FontWeight.w700,
        letterSpacing: -0.3,
      ),
      iconTheme: const IconThemeData(color: WellCoreColors.textPrimary),
    ),
    bottomNavigationBarTheme: const BottomNavigationBarThemeData(
      backgroundColor: WellCoreColors.surface0,
      selectedItemColor: WellCoreColors.primary,
      unselectedItemColor: WellCoreColors.textMuted,
      type: BottomNavigationBarType.fixed,
      elevation: 0,
      selectedLabelStyle: TextStyle(fontSize: 11, fontWeight: FontWeight.w600),
      unselectedLabelStyle: TextStyle(fontSize: 11),
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: WellCoreColors.primary,
        foregroundColor: Colors.white,
        elevation: 0,
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(WellCoreSpacing.radiusMd),
        ),
        textStyle: GoogleFonts.outfit(
          fontSize: 15,
          fontWeight: FontWeight.w700,
          letterSpacing: 0.3,
        ),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: WellCoreColors.surface1,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(WellCoreSpacing.radiusMd),
        borderSide: const BorderSide(color: WellCoreColors.border),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(WellCoreSpacing.radiusMd),
        borderSide: const BorderSide(color: WellCoreColors.border),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(WellCoreSpacing.radiusMd),
        borderSide: const BorderSide(color: WellCoreColors.primary, width: 1.5),
      ),
      labelStyle: const TextStyle(color: WellCoreColors.textDim),
      hintStyle: const TextStyle(color: WellCoreColors.textMuted),
    ),
    dividerTheme: const DividerThemeData(
      color: WellCoreColors.border,
      thickness: 1,
    ),
    progressIndicatorTheme: const ProgressIndicatorThemeData(
      color: WellCoreColors.primary,
      linearTrackColor: WellCoreColors.surface3,
    ),
    pageTransitionsTheme: const PageTransitionsTheme(
      builders: {
        TargetPlatform.android: FadeUpwardsPageTransitionsBuilder(),
        TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
      },
    ),
  );
}
```

- [ ] **Step 3:** Verificar compilación

```bash
flutter analyze lib/core/theme/wellcore_theme.dart
```

Expected: 0 errors, 0 warnings.

- [ ] **Step 4:** Commit

```bash
git add lib/core/theme/wellcore_theme.dart
git commit -m "feat(b8-t2): upgrade theme to Outfit font via google_fonts - variable weight 100-900, tuned text styles"
```

---

### Task B8-T3: GradientText widget

**Archivos:**
- Create: `lib/core/widgets/gradient_text.dart`

- [ ] **Step 1:** Crear el archivo

```bash
touch "lib/core/widgets/gradient_text.dart"
```

- [ ] **Step 2:** Escribir el contenido completo:

```dart
import 'package:flutter/material.dart';

/// Widget que renderiza texto con gradiente usando ShaderMask.
///
/// Uso:
/// ```dart
/// GradientText(
///   '¡Hola, Carlos!',
///   style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w800),
/// )
/// ```
///
/// Para gradiente personalizado:
/// ```dart
/// GradientText(
///   'ELITE',
///   gradient: const LinearGradient(
///     colors: [Color(0xFFD4A853), Color(0xFFFFE08A)],
///   ),
/// )
/// ```
class GradientText extends StatelessWidget {
  final String text;
  final TextStyle? style;
  final Gradient gradient;
  final TextAlign? textAlign;
  final int? maxLines;
  final TextOverflow? overflow;

  const GradientText(
    this.text, {
    super.key,
    this.style,
    this.gradient = const LinearGradient(
      colors: [Color(0xFFFF4444), Color(0xFFFF8C00)],
      begin: Alignment.centerLeft,
      end: Alignment.centerRight,
    ),
    this.textAlign,
    this.maxLines,
    this.overflow,
  });

  /// Gradiente dorado para usuarios Elite / logros premium
  static const Gradient gold = LinearGradient(
    colors: [Color(0xFFD4A853), Color(0xFFFFE08A), Color(0xFFD4A853)],
    begin: Alignment.centerLeft,
    end: Alignment.centerRight,
  );

  /// Gradiente rojo-naranja WellCore (por defecto)
  static const Gradient fire = LinearGradient(
    colors: [Color(0xFFFF4444), Color(0xFFFF8C00)],
    begin: Alignment.centerLeft,
    end: Alignment.centerRight,
  );

  /// Gradiente verde para métricas positivas
  static const Gradient success = LinearGradient(
    colors: [Color(0xFF22C55E), Color(0xFF86EFAC)],
    begin: Alignment.centerLeft,
    end: Alignment.centerRight,
  );

  @override
  Widget build(BuildContext context) {
    return ShaderMask(
      blendMode: BlendMode.srcIn,
      shaderCallback: (bounds) => gradient.createShader(
        Rect.fromLTWH(0, 0, bounds.width, bounds.height),
      ),
      child: Text(
        text,
        style: style,
        textAlign: textAlign,
        maxLines: maxLines,
        overflow: overflow,
      ),
    );
  }
}
```

- [ ] **Step 3:** Exportar desde `wellcore_widgets.dart`

```bash
cat "lib/core/widgets/wellcore_widgets.dart"
```

Agregar la línea de export al final del archivo:

```dart
export 'gradient_text.dart';
```

- [ ] **Step 4:** Verificar

```bash
flutter analyze lib/core/widgets/gradient_text.dart
```

Expected: 0 issues.

- [ ] **Step 5:** Commit

```bash
git add lib/core/widgets/gradient_text.dart lib/core/widgets/wellcore_widgets.dart
git commit -m "feat(b8-t3): GradientText widget - ShaderMask con gradientes fire/gold/success presets"
```

---

## Chunk 2: Premium Widgets — Gauge + Activity Rings

### Task B8-T4: WellScoreGauge — gauge animado tipo Whoop

**Archivos:**
- Create: `lib/core/widgets/wellscore_gauge.dart`

- [ ] **Step 1:** Crear el archivo

```bash
touch "lib/core/widgets/wellscore_gauge.dart"
```

- [ ] **Step 2:** Escribir el widget completo. El gauge reemplaza visualmente al `WellScoreWidget` lineal del dashboard, pero `WellScoreWidget` permanece en el codebase para el header compacto:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:gauge_indicator/gauge_indicator.dart';
import '../theme/wellcore_colors.dart';

/// Gauge premium tipo Whoop para mostrar el WellScore 0-100.
///
/// Zonas de color:
/// - 0-39:  Rojo (mejorar)
/// - 40-69: Ámbar (regular/bien)
/// - 70-84: Verde (óptimo)
/// - 85-100: Dorado (élite)
///
/// Uso en dashboard:
/// ```dart
/// WellScoreGauge(score: wellScore)
/// ```
class WellScoreGauge extends StatelessWidget {
  final int score;
  final int bienestar;
  final int compliance;
  final int streak;

  const WellScoreGauge({
    super.key,
    required this.score,
    this.bienestar = 0,
    this.compliance = 0,
    this.streak = 0,
  });

  String get _label {
    if (score >= 85) return 'Élite';
    if (score >= 70) return 'Óptimo';
    if (score >= 40) return 'En progreso';
    return 'Mejorar';
  }

  @override
  Widget build(BuildContext context) {
    final color = WellCoreColors.wellscoreColor(score);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
      decoration: BoxDecoration(
        color: WellCoreColors.surface1,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withOpacity(0.2), width: 1),
        boxShadow: [
          BoxShadow(
            color: color.withOpacity(0.08),
            blurRadius: 24,
            spreadRadius: -4,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // ── Gauge central ──────────────────────────────────────
          AnimatedRadialGauge(
            duration: const Duration(milliseconds: 1400),
            curve: Curves.easeOutCubic,
            value: score.toDouble(),
            axis: GaugeAxis(
              min: 0,
              max: 100,
              degrees: 240,
              style: GaugeAxisStyle(
                thickness: 16,
                background: const Color(0xFF1A1A1D),
                segmentSpacing: 3,
              ),
              pointer: const GaugePointer.needle(
                width: 6,
                height: 36,
                color: Colors.white,
                borderRadius: 4,
              ),
              progressBar: const GaugeProgressBar.rounded(
                color: Colors.transparent,
              ),
              segments: [
                GaugeSegment(
                  from: 0,
                  to: 40,
                  color: const Color(0xFFEF4444),
                  cornerRadius: const Radius.circular(4),
                ),
                GaugeSegment(
                  from: 40,
                  to: 70,
                  color: const Color(0xFFF59E0B),
                  cornerRadius: const Radius.circular(4),
                ),
                GaugeSegment(
                  from: 70,
                  to: 85,
                  color: const Color(0xFF22C55E),
                  cornerRadius: const Radius.circular(4),
                ),
                GaugeSegment(
                  from: 85,
                  to: 100,
                  color: const Color(0xFFD4A853),
                  cornerRadius: const Radius.circular(4),
                ),
              ],
            ),
            builder: (context, child, value) {
              return Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    '${value.round()}',
                    style: TextStyle(
                      color: color,
                      fontSize: 42,
                      fontWeight: FontWeight.w900,
                      letterSpacing: -3,
                      height: 1,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'WellScore',
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.45),
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      letterSpacing: 1,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                    decoration: BoxDecoration(
                      color: color.withOpacity(0.12),
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: color.withOpacity(0.25)),
                    ),
                    child: Text(
                      _label,
                      style: TextStyle(
                        color: color,
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.5,
                      ),
                    ),
                  ),
                ],
              );
            },
          )
              .animate()
              .scale(
                begin: const Offset(0.85, 0.85),
                duration: 700.ms,
                curve: Curves.elasticOut,
              )
              .fadeIn(duration: 400.ms),

          const SizedBox(height: 20),

          // ── Factores bajo el gauge ────────────────────────────
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _GaugeFactor(
                icon: '😊',
                label: 'Ánimo',
                value: '$bienestar/10',
                color: color,
              ),
              _GaugeDivider(),
              _GaugeFactor(
                icon: '🏋️',
                label: 'Días',
                value: '$compliance/7',
                color: color,
              ),
              _GaugeDivider(),
              _GaugeFactor(
                icon: '🔥',
                label: 'Racha',
                value: '${streak}d',
                color: color,
              ),
            ],
          ).animate(delay: 600.ms).fadeIn(duration: 400.ms).slideY(begin: 0.2),
        ],
      ),
    );
  }
}

class _GaugeFactor extends StatelessWidget {
  final String icon;
  final String label;
  final String value;
  final Color color;

  const _GaugeFactor({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(icon, style: const TextStyle(fontSize: 20)),
        const SizedBox(height: 4),
        Text(
          value,
          style: TextStyle(
            color: color,
            fontSize: 13,
            fontWeight: FontWeight.w800,
          ),
        ),
        Text(
          label,
          style: const TextStyle(
            color: Color(0x73FFFFFF),
            fontSize: 10,
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
    );
  }
}

class _GaugeDivider extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      width: 1,
      height: 32,
      color: Colors.white.withOpacity(0.08),
    );
  }
}
```

- [ ] **Step 3:** Exportar desde `wellcore_widgets.dart`:

```dart
export 'wellscore_gauge.dart';
```

- [ ] **Step 4:** Verificar

```bash
flutter analyze lib/core/widgets/wellscore_gauge.dart
```

Expected: 0 errors. Si hay error de `GaugePointer.needle` o `GaugeProgressBar.rounded`, revisar la API de la versión `^0.4.3` instalada y ajustar el nombre del constructor (puede ser `.triangle` o similar según la versión exacta).

- [ ] **Step 5:** Commit

```bash
git add lib/core/widgets/wellscore_gauge.dart lib/core/widgets/wellcore_widgets.dart
git commit -m "feat(b8-t4): WellScoreGauge - animated radial gauge type Whoop with 4 color zones and needle pointer"
```

---

### Task B8-T5: ActivityRings — anillos tipo Apple Watch

**Archivos:**
- Create: `lib/core/widgets/activity_rings.dart`

- [ ] **Step 1:** Crear el archivo

```bash
touch "lib/core/widgets/activity_rings.dart"
```

- [ ] **Step 2:** Escribir el widget completo con CustomPainter:

```dart
import 'dart:math';
import 'package:flutter/material.dart';
import 'package:gap/gap.dart';

/// Activity Rings al estilo Apple Watch.
///
/// Muestra 3 anillos concéntricos animados:
/// - Externo (rojo):   Move  — porcentaje de calorias/pasos objetivo
/// - Medio (verde):    Exercise — minutos de ejercicio activo
/// - Interno (azul):   Stand — horas de pie / días entrenados
///
/// Valores de 0.0 a 1.0 (pueden exceder 1.0 para dar vuelta completa+)
///
/// Uso:
/// ```dart
/// ActivityRings(
///   move: diasEntrenados / 7,
///   exercise: complianceRate,
///   stand: habitosRate,
///   size: 110,
/// )
/// ```
class ActivityRings extends StatelessWidget {
  final double move;
  final double exercise;
  final double stand;
  final double size;
  final bool showLabels;

  const ActivityRings({
    super.key,
    required this.move,
    required this.exercise,
    required this.stand,
    this.size = 110,
    this.showLabels = false,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        TweenAnimationBuilder<double>(
          tween: Tween(begin: 0, end: 1),
          duration: const Duration(milliseconds: 1600),
          curve: Curves.easeOutCubic,
          builder: (_, progress, __) {
            return CustomPaint(
              size: Size(size, size),
              painter: _RingsPainter(
                move: (move * progress).clamp(0, 2),
                exercise: (exercise * progress).clamp(0, 2),
                stand: (stand * progress).clamp(0, 2),
              ),
            );
          },
        ),
        if (showLabels) ...[
          const Gap(10),
          _RingsLegend(move: move, exercise: exercise, stand: stand),
        ],
      ],
    );
  }
}

class _RingsPainter extends CustomPainter {
  final double move;
  final double exercise;
  final double stand;

  _RingsPainter({
    required this.move,
    required this.exercise,
    required this.stand,
  });

  Paint _bgPaint(Color color, double strokeWidth) => Paint()
    ..color = color.withOpacity(0.18)
    ..strokeWidth = strokeWidth
    ..style = PaintingStyle.stroke
    ..strokeCap = StrokeCap.round;

  Paint _fgPaint(Color color, double strokeWidth) => Paint()
    ..color = color
    ..strokeWidth = strokeWidth
    ..style = PaintingStyle.stroke
    ..strokeCap = StrokeCap.round;

  void _drawRing(
    Canvas canvas,
    Size size,
    double radius,
    double strokeWidth,
    Color color,
    double value,
  ) {
    final cx = size.width / 2;
    final cy = size.height / 2;
    final rect = Rect.fromCircle(
      center: Offset(cx, cy),
      radius: radius,
    );

    // Fondo del anillo (completo, tenue)
    canvas.drawArc(rect, -pi / 2, 2 * pi, false, _bgPaint(color, strokeWidth));

    // Progreso del anillo
    if (value > 0) {
      final sweepAngle = (2 * pi * value).clamp(0, 2 * pi * 1.98);
      canvas.drawArc(rect, -pi / 2, sweepAngle, false, _fgPaint(color, strokeWidth));

      // Punto brillante en la punta
      if (value > 0.02) {
        final endAngle = -pi / 2 + sweepAngle;
        final dotX = cx + radius * cos(endAngle);
        final dotY = cy + radius * sin(endAngle);
        canvas.drawCircle(
          Offset(dotX, dotY),
          strokeWidth / 2,
          Paint()
            ..color = Colors.white
            ..style = PaintingStyle.fill,
        );
      }
    }
  }

  @override
  void paint(Canvas canvas, Size size) {
    final stroke = size.width * 0.093;
    final gap = stroke * 0.55;

    final rOuter = size.width / 2 - stroke / 2;
    final rMid = rOuter - stroke - gap;
    final rInner = rMid - stroke - gap;

    // Anillo externo: Move (rojo WellCore)
    _drawRing(canvas, size, rOuter, stroke, const Color(0xFFE31E24), move);

    // Anillo medio: Exercise (verde)
    _drawRing(canvas, size, rMid, stroke, const Color(0xFF22C55E), exercise);

    // Anillo interno: Stand (azul)
    _drawRing(canvas, size, rInner, stroke, const Color(0xFF3B82F6), stand);
  }

  @override
  bool shouldRepaint(_RingsPainter old) =>
      old.move != move || old.exercise != exercise || old.stand != stand;
}

class _RingsLegend extends StatelessWidget {
  final double move;
  final double exercise;
  final double stand;

  const _RingsLegend({
    required this.move,
    required this.exercise,
    required this.stand,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _LegendItem(color: const Color(0xFFE31E24), label: 'Entrenar', value: move),
        const Gap(3),
        _LegendItem(color: const Color(0xFF22C55E), label: 'Ejercicio', value: exercise),
        const Gap(3),
        _LegendItem(color: const Color(0xFF3B82F6), label: 'Hábitos', value: stand),
      ],
    );
  }
}

class _LegendItem extends StatelessWidget {
  final Color color;
  final String label;
  final double value;

  const _LegendItem({
    required this.color,
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 8,
          height: 8,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const Gap(5),
        Text(
          label,
          style: const TextStyle(color: Color(0x73FFFFFF), fontSize: 10),
        ),
        const Gap(4),
        Text(
          '${(value * 100).round()}%',
          style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w700),
        ),
      ],
    );
  }
}
```

- [ ] **Step 3:** Exportar desde `wellcore_widgets.dart`:

```dart
export 'activity_rings.dart';
```

- [ ] **Step 4:** Verificar

```bash
flutter analyze lib/core/widgets/activity_rings.dart
```

Expected: 0 errors.

- [ ] **Step 5:** Commit

```bash
git add lib/core/widgets/activity_rings.dart lib/core/widgets/wellcore_widgets.dart
git commit -m "feat(b8-t5): ActivityRings widget - 3 concentric rings with CustomPainter, TweenAnimation, glow dot"
```

---

## Chunk 3: Navigation — Frosted Glass NavBar

### Task B8-T6: FrostedNavBar con BackdropFilter + refactor ClientShell

**Archivos:**
- Create: `lib/core/widgets/frosted_nav_bar.dart`
- Modify: `lib/core/router/app_router.dart`

- [ ] **Step 1:** Crear `lib/core/widgets/frosted_nav_bar.dart` con el contenido completo:

```dart
import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:phosphor_flutter/phosphor_flutter.dart';

/// Item de navegación para FrostedNavBar
class FrostedNavItem {
  final PhosphorIconData iconActive;
  final PhosphorIconData iconInactive;
  final String label;
  final String path;

  const FrostedNavItem({
    required this.iconActive,
    required this.iconInactive,
    required this.label,
    required this.path,
  });
}

/// Bottom navigation bar con efecto glassmorphism (BackdropFilter blur).
///
/// El ítem central es el FAB de Check-in integrado sin notch visible.
/// Los iconos cambian entre fill y light con AnimatedSwitcher al cambiar tab.
///
/// Uso (en ClientShell):
/// ```dart
/// Scaffold(
///   extendBody: true,  // IMPORTANTE para que el contenido pase bajo el nav
///   bottomNavigationBar: const FrostedNavBar(),
///   body: child,
/// )
/// ```
class FrostedNavBar extends StatelessWidget {
  static const List<FrostedNavItem> items = [
    FrostedNavItem(
      iconActive: PhosphorIconsBold.house,
      iconInactive: PhosphorIconsLight.house,
      label: 'Inicio',
      path: '/client',
    ),
    FrostedNavItem(
      iconActive: PhosphorIconsBold.users,
      iconInactive: PhosphorIconsLight.users,
      label: 'Comunidad',
      path: '/community',
    ),
    FrostedNavItem(
      // Central — FAB Check-in (label vacío indica que es el FAB)
      iconActive: PhosphorIconsBold.plus,
      iconInactive: PhosphorIconsBold.plus,
      label: '',
      path: '/client/checkin',
    ),
    FrostedNavItem(
      iconActive: PhosphorIconsBold.trophy,
      iconInactive: PhosphorIconsLight.trophy,
      label: 'Retos',
      path: '/challenges',
    ),
    FrostedNavItem(
      iconActive: PhosphorIconsBold.user,
      iconInactive: PhosphorIconsLight.user,
      label: 'Yo',
      path: '/profile',
    ),
  ];

  const FrostedNavBar({super.key});

  @override
  Widget build(BuildContext context) {
    final location = GoRouterState.of(context).matchedLocation;
    final activeIndex = items.indexWhere(
      (item) => item.path.isNotEmpty && item.label.isNotEmpty && location.startsWith(item.path),
    );

    return ClipRect(
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 32, sigmaY: 32),
        child: Container(
          decoration: BoxDecoration(
            color: const Color(0xFF0D0D1A).withOpacity(0.72),
            border: Border(
              top: BorderSide(
                color: Colors.white.withOpacity(0.08),
                width: 1,
              ),
            ),
          ),
          child: SafeArea(
            top: false,
            child: SizedBox(
              height: 64,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceAround,
                children: List.generate(items.length, (i) {
                  final item = items[i];

                  // ── Central: FAB Check-in ──────────────────────
                  if (item.label.isEmpty) {
                    return GestureDetector(
                      onTap: () {
                        HapticFeedback.mediumImpact();
                        context.go(item.path);
                      },
                      child: Container(
                        width: 52,
                        height: 52,
                        decoration: BoxDecoration(
                          gradient: const RadialGradient(
                            colors: [Color(0xFFFF3B3F), Color(0xFFE31E24)],
                          ),
                          shape: BoxShape.circle,
                          boxShadow: [
                            BoxShadow(
                              color: const Color(0xFFE31E24).withOpacity(0.55),
                              blurRadius: 18,
                              spreadRadius: -3,
                            ),
                          ],
                        ),
                        child: const Icon(
                          Icons.add,
                          color: Colors.white,
                          size: 26,
                        ),
                      ),
                    );
                  }

                  // ── Tab normal ─────────────────────────────────
                  final isActive = activeIndex == i;
                  return GestureDetector(
                    behavior: HitTestBehavior.opaque,
                    onTap: () {
                      HapticFeedback.selectionClick();
                      context.go(item.path);
                    },
                    child: SizedBox(
                      width: 60,
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          AnimatedSwitcher(
                            duration: const Duration(milliseconds: 200),
                            switchInCurve: Curves.easeOut,
                            child: Icon(
                              key: ValueKey(isActive),
                              isActive ? item.iconActive : item.iconInactive,
                              color: isActive
                                  ? const Color(0xFFE31E24)
                                  : Colors.white.withOpacity(0.45),
                              size: 24,
                            ),
                          ),
                          const SizedBox(height: 3),
                          AnimatedDefaultTextStyle(
                            duration: const Duration(milliseconds: 200),
                            style: TextStyle(
                              color: isActive
                                  ? const Color(0xFFE31E24)
                                  : Colors.white.withOpacity(0.45),
                              fontSize: 10,
                              fontWeight:
                                  isActive ? FontWeight.w700 : FontWeight.w400,
                            ),
                            child: Text(item.label),
                          ),
                        ],
                      ),
                    ),
                  );
                }),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
```

- [ ] **Step 2:** Actualizar `ClientShell` en `app_router.dart`. Leer el archivo primero:

```bash
cat "lib/core/router/app_router.dart"
```

Reemplazar el bloque `ClientShell` (solo la clase, no el router):

```dart
class ClientShell extends StatelessWidget {
  final Widget child;
  const ClientShell({super.key, required this.child});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBody: true, // contenido pasa bajo el nav glass
      body: child,
      bottomNavigationBar: const FrostedNavBar(),
      // Sin FAB separado — está integrado en FrostedNavBar como ítem central
    );
  }
}
```

- [ ] **Step 3:** Agregar el import de `FrostedNavBar` en `app_router.dart`:

```dart
import '../../core/widgets/frosted_nav_bar.dart';
```

Y eliminar los imports ya no necesarios de `_CheckInFab` y `_WCBottomNav` (esas clases se eliminan del archivo porque ahora están en `frosted_nav_bar.dart`).

- [ ] **Step 4:** Limpiar `app_router.dart` — eliminar las clases `_CheckInFab`, `_WCBottomNav` y `_NavItem` del final del archivo, ya que están reemplazadas por `FrostedNavBar`.

- [ ] **Step 5:** Exportar desde `wellcore_widgets.dart`:

```dart
export 'frosted_nav_bar.dart';
```

- [ ] **Step 6:** Verificar

```bash
flutter analyze lib/core/widgets/frosted_nav_bar.dart lib/core/router/app_router.dart
```

Expected: 0 errors.

- [ ] **Step 7:** Commit

```bash
git add lib/core/widgets/frosted_nav_bar.dart lib/core/router/app_router.dart lib/core/widgets/wellcore_widgets.dart
git commit -m "feat(b8-t6): FrostedNavBar - BackdropFilter glassmorphism nav, AnimatedSwitcher icons, integrated FAB center"
```

---

## Chunk 4: Dashboard — Bento Grid + Premium Header

### Task B8-T7: Dashboard Bento Grid + WellScoreGauge + ActivityRings + GradientText

**Archivos:**
- Modify: `lib/features/dashboard/client_dashboard_screen.dart`

- [ ] **Step 1:** Leer el archivo actual completo

```bash
cat "lib/features/dashboard/client_dashboard_screen.dart"
```

- [ ] **Step 2:** Actualizar los imports al inicio del archivo (agregar los nuevos packages):

```dart
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_staggered_grid_view/flutter_staggered_grid_view.dart';
import 'package:gap/gap.dart';
import 'package:go_router/go_router.dart';
import 'package:phosphor_flutter/phosphor_flutter.dart';
import 'package:skeletonizer/skeletonizer.dart';
import '../../core/api/api_client.dart';
import '../../core/auth/auth_notifier.dart';
import '../../core/theme/wellcore_colors.dart';
import '../../core/widgets/wellcore_card.dart';
import '../../core/widgets/wellscore_gauge.dart';
import '../../core/widgets/wellscore_widget.dart';
import '../../core/widgets/activity_rings.dart';
import '../../core/widgets/activity_feed_card.dart';
import '../../core/widgets/gradient_text.dart';
```

- [ ] **Step 3:** Reemplazar la sección `SliverAppBar` para usar `GradientText` en el header. Reemplazar el bloque de `Text('¡Hola, $firstName! 👋', ...)`:

```dart
GradientText(
  '¡Hola, $firstName!',
  style: const TextStyle(
    fontSize: 26,
    fontWeight: FontWeight.w800,
    letterSpacing: -0.5,
  ),
  gradient: GradientText.fire,
),
```

- [ ] **Step 4:** Reemplazar el bloque "Sección A: WellScore principal" — sustituir `WellScoreWidget` por `WellScoreGauge`:

```dart
// ── Sección A: WellScore principal ──────────────────────
Skeletonizer(
  enabled: isLoading,
  child: WellScoreGauge(
    score: isLoading ? 72 : wellScore,
    bienestar: isLoading ? 7 : bienestar,
    compliance: isLoading ? 4 : diasEntrenados,
    streak: isLoading ? 5 : streak,
  ),
),
```

- [ ] **Step 5:** Agregar `ActivityRings` entre el WellScoreGauge y las acciones primarias. Insertar después del `Gap(20)` tras el gauge:

```dart
const Gap(16),

// ── Sección A2: Activity Rings ──────────────────────────
Skeletonizer(
  enabled: isLoading,
  child: Container(
    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
    decoration: BoxDecoration(
      color: WellCoreColors.surface1,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: Colors.white.withOpacity(0.06)),
    ),
    child: Row(
      children: [
        ActivityRings(
          move: isLoading ? 0.6 : (diasEntrenados / 7).clamp(0, 1),
          exercise: isLoading ? 0.8 : (bienestar / 10).clamp(0, 1),
          stand: isLoading ? 0.4 : (streak > 0 ? 0.9 : 0.2),
          size: 100,
        ),
        const Gap(20),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Actividad',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const Gap(8),
              _RingLegendRow(
                color: const Color(0xFFE31E24),
                label: 'Entrenar',
                value: '$diasEntrenados/7 días',
              ),
              const Gap(4),
              _RingLegendRow(
                color: const Color(0xFF22C55E),
                label: 'Bienestar',
                value: '$bienestar/10',
              ),
              const Gap(4),
              _RingLegendRow(
                color: const Color(0xFF3B82F6),
                label: 'Racha',
                value: '${streak}d activa',
              ),
            ],
          ),
        ),
      ],
    ),
  ),
).animate(delay: 200.ms).fadeIn(duration: 400.ms).slideY(begin: 0.06),

const Gap(20),
```

- [ ] **Step 6:** Reemplazar el `GridView.count` de acciones por `StaggeredGrid`. Reemplazar TODO el bloque de acciones primarias (el `if (isLoading)` con `GridView` y el `else` con `GridView`):

```dart
// ── Sección B: Bento Grid de acciones ──────────────────
const _SectionLabel(label: 'ACCIONES PRINCIPALES'),
const Gap(10),

Skeletonizer(
  enabled: isLoading,
  child: StaggeredGrid.count(
    crossAxisCount: 4,
    mainAxisSpacing: 10,
    crossAxisSpacing: 10,
    children: [
      // Check-in — grande (2x2) con glow
      StaggeredGridTile.count(
        crossAxisCellCount: 2,
        mainAxisCellCount: 2,
        child: _BentoCard(
          emoji: '✅',
          title: 'Check-in\nSemanal',
          subtitle: 'Registra tu semana',
          route: '/client/checkin',
          glow: true,
          large: true,
        ),
      ),
      // Métricas — pequeño (2x1)
      StaggeredGridTile.count(
        crossAxisCellCount: 2,
        mainAxisCellCount: 1,
        child: _BentoCard(
          emoji: '📊',
          title: 'Métricas',
          subtitle: 'Peso y medidas',
          route: '/client/metrics',
        ),
      ),
      // Fotos — pequeño (2x1)
      StaggeredGridTile.count(
        crossAxisCellCount: 2,
        mainAxisCellCount: 1,
        child: _BentoCard(
          emoji: '📸',
          title: 'Fotos',
          subtitle: 'Tu transformación',
          route: '/photos',
        ),
      ),
      // Coach IA — grande (2x2)
      StaggeredGridTile.count(
        crossAxisCellCount: 2,
        mainAxisCellCount: 2,
        child: _BentoCard(
          emoji: '🤖',
          title: 'Coach\nIA',
          subtitle: 'Tu asistente 24/7',
          route: '/ai/conversations',
          large: true,
          accentColor: const Color(0xFF3B82F6),
        ),
      ),
      // Hábitos — pequeño (2x1)
      StaggeredGridTile.count(
        crossAxisCellCount: 2,
        mainAxisCellCount: 1,
        child: _BentoCard(
          emoji: '🎯',
          title: 'Hábitos',
          subtitle: 'Constancia diaria',
          route: '/habits',
        ),
      ),
      // Entrenamiento — pequeño (2x1)
      StaggeredGridTile.count(
        crossAxisCellCount: 2,
        mainAxisCellCount: 1,
        child: _BentoCard(
          emoji: '🏋️',
          title: 'Tracking',
          subtitle: 'Sesiones y PRs',
          route: '/tracking',
        ),
      ),
    ],
  ),
).animate(delay: 300.ms).fadeIn(duration: 400.ms).slideY(begin: 0.06),
```

- [ ] **Step 7:** Agregar las nuevas clases al final del archivo (antes del último `}`):

**`_RingLegendRow`:**
```dart
class _RingLegendRow extends StatelessWidget {
  final Color color;
  final String label;
  final String value;

  const _RingLegendRow({
    required this.color,
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 8,
          height: 8,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const Gap(6),
        Text(
          label,
          style: const TextStyle(color: Color(0x73FFFFFF), fontSize: 11),
        ),
        const Spacer(),
        Text(
          value,
          style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w700),
        ),
      ],
    );
  }
}
```

**`_BentoCard`** (reemplaza `_ActionCard`):
```dart
class _BentoCard extends StatelessWidget {
  final String emoji;
  final String title;
  final String subtitle;
  final String route;
  final bool glow;
  final bool large;
  final Color? accentColor;

  const _BentoCard({
    required this.emoji,
    required this.title,
    required this.subtitle,
    required this.route,
    this.glow = false,
    this.large = false,
    this.accentColor,
  });

  @override
  Widget build(BuildContext context) {
    final accent = accentColor ?? (glow ? WellCoreColors.primary : null);
    return WellCoreCard(
      glow: glow,
      onTap: () {
        HapticFeedback.lightImpact();
        context.go(route);
      },
      child: Padding(
        padding: EdgeInsets.all(large ? 16.0 : 12.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              emoji,
              style: TextStyle(fontSize: large ? 30 : 22),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: accent ?? Colors.white,
                    fontSize: large ? 16 : 12,
                    fontWeight: FontWeight.w800,
                    height: 1.2,
                  ),
                ),
                const Gap(2),
                Text(
                  subtitle,
                  style: const TextStyle(
                    color: Color(0x73FFFFFF),
                    fontSize: 10,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
```

- [ ] **Step 8:** Verificar

```bash
flutter analyze lib/features/dashboard/client_dashboard_screen.dart
```

Expected: 0 errors. Si hay warnings por `_ActionCard` no usado, eliminarlo del archivo.

- [ ] **Step 9:** Commit

```bash
git add lib/features/dashboard/client_dashboard_screen.dart
git commit -m "feat(b8-t7): dashboard Bento Grid + WellScoreGauge + ActivityRings + GradientText header + Skeletonizer"
```

---

## Chunk 5: UX Premium — WoltModalSheet Check-in + OpenContainer

### Task B8-T8: WoltModalSheet para Check-in — 3 páginas

**Archivos:**
- Modify: `lib/features/checkins/checkin_screen.dart`

El objetivo es que el formulario de check-in abra como un `WoltModalSheet` multi-página cuando se llama desde el FAB o desde el dashboard. La `CheckinScreen` existente se convierte en un wrapper que muestra el sheet automáticamente al entrar, o puede llamarse desde cualquier parte.

- [ ] **Step 1:** Leer el archivo actual

```bash
cat "lib/features/checkins/checkin_screen.dart"
```

- [ ] **Step 2:** Agregar los imports necesarios al inicio del archivo:

```dart
import 'package:gap/gap.dart';
import 'package:wolt_modal_sheet/wolt_modal_sheet.dart';
```

- [ ] **Step 3:** Agregar la función estática `showCheckinSheet` que puede llamarse desde cualquier lugar:

Agregar ANTES de la clase `CheckinScreen`:

```dart
/// Abre el check-in como WoltModalSheet de 3 páginas.
/// Llamar con: `CheckinSheet.show(context, ref)`
class CheckinSheet {
  static Future<void> show(BuildContext context, WidgetRef ref) async {
    final pageIndexNotifier = ValueNotifier(0);

    await WoltModalSheet.show(
      context: context,
      pageIndexNotifier: pageIndexNotifier,
      pageListBuilder: (ctx) => [
        _page1Training(ctx, ref, pageIndexNotifier),
        _page2Wellness(ctx, ref, pageIndexNotifier),
        _page3Notes(ctx, ref, pageIndexNotifier),
      ],
      modalTypeBuilder: (context) {
        // En pantallas pequeñas: bottom sheet; en tablet: dialog
        if (MediaQuery.of(context).size.width < 600) {
          return WoltModalType.bottomSheet();
        }
        return WoltModalType.dialog();
      },
      onModalDismissedWithBarrierTap: () {
        pageIndexNotifier.dispose();
        Navigator.of(context).pop();
      },
    );
  }

  // ── Página 1: Entrenamiento ─────────────────────────────────────
  static SliverWoltModalSheetPage _page1Training(
    BuildContext context,
    WidgetRef ref,
    ValueNotifier<int> pageIndex,
  ) {
    final notifier = ref.read(checkinFormProvider.notifier);
    final state = ref.read(checkinFormProvider);

    return SliverWoltModalSheetPage(
      hasSabGradient: false,
      backgroundColor: const Color(0xFF111113),
      topBarTitle: const Text(
        'Check-in Semanal',
        style: TextStyle(
          color: Colors.white,
          fontSize: 16,
          fontWeight: FontWeight.w700,
        ),
      ),
      isTopBarLayerAlwaysVisible: true,
      trailingNavBarWidget: IconButton(
        icon: const Icon(Icons.close, color: Colors.white70),
        onPressed: () => Navigator.of(context).pop(),
      ),
      mainContentSlivers: [
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
          sliver: SliverList(
            delegate: SliverChildListDelegate([
              // ── Días entrenados ──────────────────────────────
              const Text(
                '¿Cuántos días entrenaste esta semana?',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const Gap(12),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(8, (i) {
                  final selected = state.diasEntrenados == i;
                  return GestureDetector(
                    onTap: () {
                      HapticFeedback.selectionClick();
                      notifier.update((s) => s.copyWith(diasEntrenados: i));
                    },
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 180),
                      margin: const EdgeInsets.symmetric(horizontal: 3),
                      width: 36,
                      height: 36,
                      decoration: BoxDecoration(
                        color: selected
                            ? WellCoreColors.primary
                            : const Color(0xFF1E1E22),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(
                          color: selected
                              ? WellCoreColors.primary
                              : Colors.white.withOpacity(0.08),
                        ),
                      ),
                      child: Center(
                        child: Text(
                          '$i',
                          style: TextStyle(
                            color: selected ? Colors.white : Colors.white60,
                            fontWeight: FontWeight.w700,
                            fontSize: 13,
                          ),
                        ),
                      ),
                    ),
                  );
                }),
              ),

              const Gap(24),

              // ── RPE ──────────────────────────────────────────
              const Text(
                'Intensidad percibida (RPE)',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const Gap(8),
              const Text(
                'Del 1 (muy fácil) al 10 (máximo esfuerzo)',
                style: TextStyle(color: Color(0x73FFFFFF), fontSize: 12),
              ),
              const Gap(12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: List.generate(10, (i) {
                  final rpe = i + 1;
                  final selected = state.rpe == rpe;
                  final rpeColor = rpe <= 3
                      ? const Color(0xFF22C55E)
                      : rpe <= 6
                          ? const Color(0xFFF59E0B)
                          : const Color(0xFFEF4444);

                  return GestureDetector(
                    onTap: () {
                      HapticFeedback.selectionClick();
                      notifier.update((s) => s.copyWith(rpe: rpe));
                    },
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 180),
                      width: 44,
                      height: 44,
                      decoration: BoxDecoration(
                        color: selected ? rpeColor.withOpacity(0.2) : const Color(0xFF1E1E22),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(
                          color: selected ? rpeColor : Colors.white.withOpacity(0.08),
                          width: selected ? 1.5 : 1,
                        ),
                      ),
                      child: Center(
                        child: Text(
                          '$rpe',
                          style: TextStyle(
                            color: selected ? rpeColor : Colors.white60,
                            fontWeight: FontWeight.w700,
                            fontSize: 14,
                          ),
                        ),
                      ),
                    ),
                  );
                }),
              ),

              const Gap(28),

              // ── Botón Siguiente ──────────────────────────────
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () {
                    HapticFeedback.lightImpact();
                    pageIndex.value = 1;
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: WellCoreColors.primary,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  child: const Text(
                    'Siguiente →',
                    style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
                  ),
                ),
              ),
            ]),
          ),
        ),
      ],
    );
  }

  // ── Página 2: Bienestar ─────────────────────────────────────────
  static SliverWoltModalSheetPage _page2Wellness(
    BuildContext context,
    WidgetRef ref,
    ValueNotifier<int> pageIndex,
  ) {
    final notifier = ref.read(checkinFormProvider.notifier);

    return SliverWoltModalSheetPage(
      hasSabGradient: false,
      backgroundColor: const Color(0xFF111113),
      topBarTitle: const Text(
        'Bienestar',
        style: TextStyle(
          color: Colors.white,
          fontSize: 16,
          fontWeight: FontWeight.w700,
        ),
      ),
      isTopBarLayerAlwaysVisible: true,
      leadingNavBarWidget: IconButton(
        icon: const Icon(Icons.arrow_back, color: Colors.white70),
        onPressed: () => pageIndex.value = 0,
      ),
      trailingNavBarWidget: IconButton(
        icon: const Icon(Icons.close, color: Colors.white70),
        onPressed: () => Navigator.of(context).pop(),
      ),
      mainContentSlivers: [
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
          sliver: SliverList(
            delegate: SliverChildListDelegate([
              Consumer(
                builder: (_, ref, __) {
                  final state = ref.watch(checkinFormProvider);
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        '¿Cómo te sientes hoy?',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 15,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const Gap(6),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: const [
                          Text('😫', style: TextStyle(fontSize: 20)),
                          Text('😐', style: TextStyle(fontSize: 20)),
                          Text('😊', style: TextStyle(fontSize: 20)),
                          Text('🤩', style: TextStyle(fontSize: 20)),
                        ],
                      ),
                      Slider(
                        value: state.bienestar.toDouble(),
                        min: 1,
                        max: 10,
                        divisions: 9,
                        activeColor: WellCoreColors.primary,
                        inactiveColor: WellCoreColors.primary.withOpacity(0.2),
                        onChanged: (v) {
                          HapticFeedback.selectionClick();
                          notifier.update((s) => s.copyWith(bienestar: v.round()));
                        },
                      ),
                      Center(
                        child: Text(
                          '${state.bienestar}/10',
                          style: const TextStyle(
                            color: WellCoreColors.primary,
                            fontSize: 22,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),

                      const Gap(28),

                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed: () {
                            HapticFeedback.lightImpact();
                            pageIndex.value = 2;
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: WellCoreColors.primary,
                            padding: const EdgeInsets.symmetric(vertical: 16),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                          ),
                          child: const Text(
                            'Siguiente →',
                            style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
                          ),
                        ),
                      ),
                    ],
                  );
                },
              ),
            ]),
          ),
        ),
      ],
    );
  }

  // ── Página 3: Notas + Guardar ───────────────────────────────────
  static SliverWoltModalSheetPage _page3Notes(
    BuildContext context,
    WidgetRef ref,
    ValueNotifier<int> pageIndex,
  ) {
    final notifier = ref.read(checkinFormProvider.notifier);
    final textCtrl = TextEditingController();

    return SliverWoltModalSheetPage(
      hasSabGradient: false,
      backgroundColor: const Color(0xFF111113),
      topBarTitle: const Text(
        'Notas y resumen',
        style: TextStyle(
          color: Colors.white,
          fontSize: 16,
          fontWeight: FontWeight.w700,
        ),
      ),
      isTopBarLayerAlwaysVisible: true,
      leadingNavBarWidget: IconButton(
        icon: const Icon(Icons.arrow_back, color: Colors.white70),
        onPressed: () => pageIndex.value = 1,
      ),
      trailingNavBarWidget: IconButton(
        icon: const Icon(Icons.close, color: Colors.white70),
        onPressed: () => Navigator.of(context).pop(),
      ),
      mainContentSlivers: [
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
          sliver: SliverList(
            delegate: SliverChildListDelegate([
              const Text(
                'Notas libres (opcional)',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const Gap(10),
              TextField(
                controller: textCtrl,
                maxLines: 4,
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  hintText: '¿Algo que quieras destacar de tu semana?',
                  hintStyle: const TextStyle(color: Color(0x73FFFFFF)),
                  filled: true,
                  fillColor: const Color(0xFF1A1A1D),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: BorderSide(color: Colors.white.withOpacity(0.08)),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: BorderSide(color: Colors.white.withOpacity(0.08)),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: WellCoreColors.primary),
                  ),
                ),
                onChanged: (v) => notifier.update((s) => s.copyWith(notas: v)),
              ),

              const Gap(24),

              Consumer(
                builder: (_, ref, __) {
                  final state = ref.watch(checkinFormProvider);
                  return SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: state.isLoading
                          ? null
                          : () async {
                              HapticFeedback.mediumImpact();
                              await notifier.submit(ref);
                              if (context.mounted) Navigator.of(context).pop();
                            },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: WellCoreColors.primary,
                        disabledBackgroundColor: WellCoreColors.primary.withOpacity(0.4),
                        padding: const EdgeInsets.symmetric(vertical: 18),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                      child: state.isLoading
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(
                                color: Colors.white,
                                strokeWidth: 2,
                              ),
                            )
                          : const Text(
                              '✅ Guardar Check-in',
                              style: TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 16,
                              ),
                            ),
                    ),
                  );
                },
              ),
            ]),
          ),
        ),
      ],
    );
  }
}
```

- [ ] **Step 4:** Modificar el `CheckinScreen` para que abra el sheet automáticamente al montar:

Agregar `WidgetsBinding.instance.addPostFrameCallback` en el `initState` (si es StatefulWidget) o usar un `ConsumerStatefulWidget` wrapper:

```dart
/// CheckinScreen es ahora un wrapper que muestra el sheet al entrar.
/// Sirve como destino de ruta '/client/checkin'.
class CheckinScreen extends ConsumerStatefulWidget {
  const CheckinScreen({super.key});

  @override
  ConsumerState<CheckinScreen> createState() => _CheckinScreenState();
}

class _CheckinScreenState extends ConsumerState<CheckinScreen> {
  @override
  void initState() {
    super.initState();
    // Abrir el sheet automáticamente al navegar a esta ruta
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        CheckinSheet.show(context, ref).then((_) {
          // Cuando se cierra el sheet, volver atrás
          if (mounted && context.canPop()) context.pop();
        });
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    // Pantalla de fondo mientras el sheet está visible
    return const Scaffold(
      backgroundColor: Color(0xFF0D0D0F),
      body: Center(
        child: CircularProgressIndicator(color: WellCoreColors.primary),
      ),
    );
  }
}
```

- [ ] **Step 5:** Verificar

```bash
flutter analyze lib/features/checkins/checkin_screen.dart
```

Expected: 0 errors. Posibles ajustes: si `checkinFormProvider` es un `StateNotifierProvider`, adaptar los `.update()` calls a `.state = state.copyWith(...)`.

- [ ] **Step 6:** Commit

```bash
git add lib/features/checkins/checkin_screen.dart
git commit -m "feat(b8-t8): WoltModalSheet check-in - 3 pages (training/wellness/notes), auto-open on route, multi-step UX"
```

---

### Task B8-T9: OpenContainer transitions en Challenges + Tickets

**Archivos:**
- Modify: `lib/features/challenges/challenges_screen.dart`
- Modify: `lib/features/tickets/tickets_screen.dart`

- [ ] **Step 1:** Agregar import `animations` en `challenges_screen.dart`:

```dart
import 'package:animations/animations.dart';
```

- [ ] **Step 2:** Leer el archivo actual para identificar dónde están los cards de retos

```bash
cat "lib/features/challenges/challenges_screen.dart"
```

- [ ] **Step 3:** Envolver cada card de reto existente con `OpenContainer`. Patrón a aplicar:

```dart
import 'package:animations/animations.dart';
import '../challenges/challenge_detail_screen.dart'; // si existe, o crear inline

// En el lugar donde se renderiza cada challenge card:
OpenContainer<bool>(
  transitionType: ContainerTransitionType.fadeThrough,
  transitionDuration: const Duration(milliseconds: 400),
  closedColor: WellCoreColors.surface1,
  openColor: WellCoreColors.canvas,
  closedElevation: 0,
  openElevation: 0,
  closedShape: RoundedRectangleBorder(
    borderRadius: BorderRadius.circular(16),
    side: BorderSide(color: Colors.white.withOpacity(0.07)),
  ),
  closedBuilder: (context, openContainer) => _ChallengeCardContent(
    challenge: challenge,
    onTap: openContainer,
  ),
  openBuilder: (context, _) => ChallengeDetailInline(challenge: challenge),
)
```

Si no existe `ChallengeDetailInline`, crear un widget inline básico en el mismo archivo:

```dart
class ChallengeDetailInline extends StatelessWidget {
  final Map<String, dynamic> challenge;
  const ChallengeDetailInline({super.key, required this.challenge});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: WellCoreColors.canvas,
      appBar: AppBar(
        backgroundColor: WellCoreColors.surface0,
        title: Text(
          challenge['name'] ?? 'Reto',
          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700),
        ),
        leading: IconButton(
          icon: const Icon(Icons.close, color: Colors.white),
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (challenge['description'] != null) ...[
              Text(
                challenge['description'] as String,
                style: const TextStyle(color: Colors.white70, fontSize: 15),
              ),
              const Gap(16),
            ],
            WellCoreCard(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Detalles del reto',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const Gap(8),
                    Text(
                      'Participantes: ${challenge['participant_count'] ?? 0}',
                      style: const TextStyle(color: Colors.white60, fontSize: 13),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
```

- [ ] **Step 4:** Aplicar el mismo patrón en `tickets_screen.dart` para las cards de tickets:

```bash
cat "lib/features/tickets/tickets_screen.dart"
```

Envolver cada ticket card con `OpenContainer` apuntando a `TicketDetailScreen`:

```dart
import 'package:animations/animations.dart';
import 'ticket_detail_screen.dart';

// Por cada ticket en la lista:
OpenContainer<bool>(
  transitionType: ContainerTransitionType.fadeThrough,
  transitionDuration: const Duration(milliseconds: 400),
  closedColor: WellCoreColors.surface1,
  openColor: WellCoreColors.canvas,
  closedElevation: 0,
  openElevation: 0,
  closedShape: RoundedRectangleBorder(
    borderRadius: BorderRadius.circular(16),
    side: BorderSide(color: Colors.white.withOpacity(0.07)),
  ),
  closedBuilder: (context, openContainer) => _TicketCardContent(
    ticket: ticket,
    onTap: openContainer,
  ),
  openBuilder: (context, _) => TicketDetailScreen(ticketId: ticket['id'] as int),
)
```

- [ ] **Step 5:** Verificar ambos archivos

```bash
flutter analyze lib/features/challenges/challenges_screen.dart lib/features/tickets/tickets_screen.dart
```

Expected: 0 errors.

- [ ] **Step 6:** Commit

```bash
git add lib/features/challenges/challenges_screen.dart lib/features/tickets/tickets_screen.dart
git commit -m "feat(b8-t9): OpenContainer transitions - challenges and tickets cards expand with fade-through animation"
```

---

## Chunk 6: Skeleton Loaders + Gap cleanup

### Task B8-T10: Skeletonizer en Metrics + Habits + cleanup Gap

**Archivos:**
- Modify: `lib/features/metrics/metrics_screen.dart`
- Modify: `lib/features/health/habit_tracking_screen.dart`

**Meta:** Reemplazar todos los `CircularProgressIndicator()` y `Center(child: CircularProgressIndicator())` con `Skeletonizer` que mantiene la estructura visual. Adicionalmente, reemplazar todos los `SizedBox(height: N)` / `SizedBox(width: N)` con `Gap(N)` en los archivos modificados en B8.

- [ ] **Step 1:** Leer el archivo de métricas

```bash
cat "lib/features/metrics/metrics_screen.dart"
```

- [ ] **Step 2:** Agregar imports en `metrics_screen.dart`:

```dart
import 'package:skeletonizer/skeletonizer.dart';
import 'package:gap/gap.dart';
```

- [ ] **Step 3:** Encontrar todos los estados de carga en `metrics_screen.dart`. El patrón típico es:

```dart
// ANTES:
data.when(
  loading: () => const Center(child: CircularProgressIndicator()),
  error: (e, _) => Center(child: Text('Error: $e')),
  data: (items) => _buildList(items),
)

// DESPUÉS:
data.when(
  loading: () => Skeletonizer(
    enabled: true,
    child: _buildList(_placeholderMetrics()),
  ),
  error: (e, _) => Center(
    child: Text('Error: $e', style: const TextStyle(color: Colors.white60)),
  ),
  data: (items) => Skeletonizer(
    enabled: false,
    child: _buildList(items),
  ),
)
```

Crear un método privado `_placeholderMetrics()` que retorne datos fake con la misma estructura:

```dart
List<Map<String, dynamic>> _placeholderMetrics() => [
  {'date': '2026-03-11', 'peso': 75.5, 'cintura': 80.0, 'cadera': 95.0, 'talla': 175.0},
  {'date': '2026-03-04', 'peso': 76.0, 'cintura': 81.0, 'cadera': 96.0, 'talla': 175.0},
  {'date': '2026-02-26', 'peso': 76.8, 'cintura': 82.0, 'cadera': 97.0, 'talla': 175.0},
];
```

- [ ] **Step 4:** Leer el archivo de hábitos

```bash
cat "lib/features/health/habit_tracking_screen.dart"
```

- [ ] **Step 5:** Agregar imports en `habit_tracking_screen.dart`:

```dart
import 'package:skeletonizer/skeletonizer.dart';
import 'package:gap/gap.dart';
```

- [ ] **Step 6:** Aplicar el mismo patrón de Skeletonizer en `habit_tracking_screen.dart`. Crear datos placeholder para hábitos:

```dart
List<Map<String, dynamic>> _placeholderHabits() => [
  {'id': 1, 'name': 'Hidratación 2L', 'completed': false, 'icon': '💧'},
  {'id': 2, 'name': 'Dormir 8 horas', 'completed': false, 'icon': '😴'},
  {'id': 3, 'name': 'Sin azúcar añadida', 'completed': false, 'icon': '🍬'},
  {'id': 4, 'name': 'Meditación 10 min', 'completed': false, 'icon': '🧘'},
];
```

- [ ] **Step 7:** En ambos archivos, reemplazar todos los `SizedBox(height: N)` por `Gap(N)` y `SizedBox(width: N)` por `Gap(N)` (con axis horizontal) para consistencia con el nuevo estándar B8.

Ejemplo:
```dart
// ANTES:
const SizedBox(height: 16),
const SizedBox(height: 8),
SizedBox(width: 12),

// DESPUÉS:
const Gap(16),
const Gap(8),
Gap(12), // horizontal — para Row children
```

- [ ] **Step 8:** Verificar ambos archivos

```bash
flutter analyze lib/features/metrics/metrics_screen.dart lib/features/health/habit_tracking_screen.dart
```

Expected: 0 errors.

- [ ] **Step 9:** Verificación general del proyecto completo

```bash
flutter analyze
```

Expected: 0 errors, máximo warnings menores (unused imports de archivos no tocados en B8).

- [ ] **Step 10:** Commit final de B8

```bash
git add lib/features/metrics/metrics_screen.dart lib/features/health/habit_tracking_screen.dart
git commit -m "feat(b8-t10): Skeletonizer in metrics + habits screens, Gap() cleanup replacing SizedBox, complete B8 premium upgrade"
```

---

## Verificación final del Bloque 8

### Checklist de calidad

- [ ] `flutter analyze` → 0 errores
- [ ] `flutter pub get` → sin conflictos de versiones
- [ ] `FrostedNavBar` visible con blur en todos los tabs
- [ ] `WellScoreGauge` anima correctamente al entrar al dashboard
- [ ] `ActivityRings` anima los 3 anillos con datos reales del usuario
- [ ] `GradientText` visible en el header del dashboard
- [ ] `StaggeredGrid` muestra el Bento Grid asimétrico correctamente
- [ ] Check-in abre como `WoltModalSheet` de 3 páginas (no pantalla completa)
- [ ] Cards de retos tienen transición `OpenContainer` al tocar
- [ ] Skeleton visible en Metrics y Habits mientras carga
- [ ] `Gap()` usado consistentemente en todos los archivos modificados
- [ ] Font `Outfit` cargada desde Google Fonts en todo el app
- [ ] Hot reload no rompe ningún estado existente

### Comando de verificación final

```bash
cd "C:\Users\GODSF\Herd\App wellcorefitness\wellcore-app"
flutter analyze --no-fatal-warnings
```

Expected output:
```
Analyzing wellcore_app...
No issues found! (ran in X.Xs)
```

### Verificación en dispositivo

```bash
flutter run --release
```

Navegar manualmente:
1. Login → verificar Outfit font en botón y títulos
2. Dashboard → verificar GradientText header + WellScoreGauge + ActivityRings + Bento Grid
3. FAB central → verificar WoltModalSheet se abre con 3 páginas
4. Tab Comunidad → verificar blur del FrostedNavBar sobre el contenido
5. Retos → verificar OpenContainer transition al tocar un reto
6. Métricas → verificar Skeletonizer mientras carga los datos

---

## Notas de implementación

### Compatibilidad gauge_indicator ^0.4.3
La API de `gauge_indicator` puede variar en versiones menores. Si `GaugePointer.needle` da error en la versión instalada exacta, usar:
```dart
pointer: const GaugePointer.triangle(
  width: 10,
  height: 20,
  color: Colors.white,
),
```
O simplemente eliminar el `pointer:` para usar solo los segmentos coloreados (el gauge sigue siendo visualmente impactante).

### extendBody: true en ClientShell
Con `extendBody: true`, el `body` del Scaffold se extiende detrás del nav glass. Es necesario agregar `padding bottom` equivalente a la altura del nav (~80px + safe area) al `CustomScrollView` o `ListView` del dashboard para que el último elemento no quede oculto:

```dart
SliverPadding(
  padding: const EdgeInsets.only(bottom: 100), // espacio para el nav glass
  sliver: SliverToBoxAdapter(child: const SizedBox.shrink()),
)
```

### WoltModalSheet — versión de API
La API de `wolt_modal_sheet ^0.11.0` usa `SliverWoltModalSheetPage`. Si en la versión instalada el constructor es diferente (e.g., `WoltModalSheetPage.withSingleListComponent`), consultar el CHANGELOG del package y adaptar el `pageListBuilder` al patrón correcto de esa versión.

### Skeletonizer — datos placeholder
Para que `Skeletonizer` funcione correctamente, los widgets hijos deben renderizarse con datos fake (no `null`). Si hay un `ListView.builder` que lee de una lista vacía durante el loading, el skeleton no mostrará nada. Siempre pasar `_placeholderItems()` cuando `isLoading == true`.

---

## Resumen de commits del Bloque 8

| Task | Commit message |
|------|---------------|
| B8-T1 | `feat(b8-t1): add 7 premium packages - skeletonizer, wolt_modal_sheet, animations, gap, gauge_indicator, staggered_grid, google_fonts` |
| B8-T2 | `feat(b8-t2): upgrade theme to Outfit font via google_fonts - variable weight 100-900` |
| B8-T3 | `feat(b8-t3): GradientText widget - ShaderMask con gradientes fire/gold/success presets` |
| B8-T4 | `feat(b8-t4): WellScoreGauge - animated radial gauge type Whoop with 4 color zones` |
| B8-T5 | `feat(b8-t5): ActivityRings widget - 3 concentric rings with CustomPainter + glow dot` |
| B8-T6 | `feat(b8-t6): FrostedNavBar - BackdropFilter glassmorphism nav, AnimatedSwitcher icons` |
| B8-T7 | `feat(b8-t7): dashboard Bento Grid + WellScoreGauge + ActivityRings + GradientText` |
| B8-T8 | `feat(b8-t8): WoltModalSheet check-in - 3 pages (training/wellness/notes), auto-open` |
| B8-T9 | `feat(b8-t9): OpenContainer transitions - challenges and tickets cards expand animation` |
| B8-T10 | `feat(b8-t10): Skeletonizer in metrics + habits, Gap() cleanup, complete B8 upgrade` |

**Total: 10 commits atómicos, un bloque completo y ejecutable.**
