# Flutter 2025-2026: Guía Definitiva para WellCore Fitness
## Investigación Exhaustiva de Técnicas Avanzadas y Packages Modernos

> **Fecha:** Marzo 2026 | **Stack Base:** Flutter 3.41+, Riverpod 3.x, GoRouter 17.x, flutter_animate 4.x, fl_chart 1.x
> **Audiencia:** Equipo de desarrollo WellCore Fitness — App de coaching premium para LATAM
> **Propósito:** Elevar WellCore al siguiente nivel de diseño y arquitectura Flutter

---

## INTRODUCCIÓN EJECUTIVA — Qué Cambiaría Más el Juego para WellCore

Después de una investigación profunda de las tendencias Flutter 2025-2026, identificamos **cinco áreas de impacto máximo** que deben priorizarse:

### Impacto Inmediato (implementar en las próximas 2 semanas)
1. **Skeletonizer** — Reemplazar todos los `CircularProgressIndicator` por skeleton loaders automáticos. Impacto percibido: enorme.
2. **flutter_animate ScrollAdapter** — Animar elementos del dashboard al hacer scroll. Zero-cost en rendimiento, máximo impacto visual.
3. **WoltModalSheet** — Bottom sheets multi-página para el flujo de check-in semanal. Experiencia premium instantánea.
4. **Phosphor Icons** (duotone weight) — Reemplazar Material Icons. La diferencia visual es dramática.
5. **ShaderMask para gradientes en texto** — El título del dashboard con gradiente WellCore Red → naranja.

### Impacto Medio (próximo sprint)
6. **Fragment Shaders GLSL** — Efecto aurora/liquid en la pantalla de logros y XP.
7. **AnimatedRadialGauge** — Gauge premium para el "Strain Score" tipo Whoop.
8. **fl_chart con gradientes y animaciones** — Elevar las gráficas de métricas.
9. **Rive** — Animación del mascote/coach IA interactivo con state machines.
10. **Hero Animations** — Transición de la tarjeta de habito al detalle.

### Impacto Arquitectónico (refactor planificado)
11. **Riverpod Generator + Freezed** — Eliminar todo el boilerplate, queries type-safe.
12. **AsyncNotifier pattern** — Migrar de StateNotifier a AsyncNotifier en todos los controladores.
13. **Drift** — Para el storage local offline-first de métricas y hábitos.

---

## SECCIÓN 1: SHADERS Y EFECTOS VISUALES AVANZADOS

### 1.1 Fragment Shaders en Flutter — La Guía Completa

Los Fragment Shaders se introdujeron formalmente en Flutter 3.7 y han madurado significativamente con Impeller. Permiten ejecutar código GLSL directamente en la GPU desde Flutter.

**Configuración en pubspec.yaml:**
```yaml
flutter:
  shaders:
    - shaders/aurora.frag
    - shaders/liquid_metal.frag
    - shaders/noise.frag
```

**Estructura básica de un shader GLSL:**
```glsl
// shaders/aurora.frag
#version 460 core
#include <flutter/runtime_effect.glsl>

uniform float uTime;
uniform float uWidth;
uniform float uHeight;
uniform vec4 uColor1;   // WellCore Red: (0.89, 0.118, 0.141, 1.0)
uniform vec4 uColor2;   // Naranja accent
out vec4 fragColor;

void main() {
  vec2 uv = FlutterFragCoord().xy / vec2(uWidth, uHeight);

  // Efecto aurora ondulante
  float wave1 = sin(uv.x * 3.0 + uTime * 0.5) * 0.3;
  float wave2 = sin(uv.x * 5.0 - uTime * 0.7 + 1.5) * 0.2;
  float aurora = smoothstep(0.4 + wave1, 0.5 + wave1, uv.y)
               * smoothstep(0.7, 0.5, uv.y + wave2);

  vec4 color = mix(uColor1, uColor2, uv.x + sin(uTime) * 0.1);
  fragColor = color * aurora * 0.7;
}
```

**Usando el shader en un widget CustomPainter:**
```dart
class AuroraShaderWidget extends StatefulWidget {
  const AuroraShaderWidget({super.key});

  @override
  State<AuroraShaderWidget> createState() => _AuroraShaderWidgetState();
}

class _AuroraShaderWidgetState extends State<AuroraShaderWidget>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  FragmentShader? _shader;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 8),
    )..repeat();
    _loadShader();
  }

  Future<void> _loadShader() async {
    final program = await FragmentProgram.fromAsset('shaders/aurora.frag');
    setState(() => _shader = program.fragmentShader());
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_shader == null) return const SizedBox.shrink();
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        return CustomPaint(
          painter: _AuroraPainter(_shader!, _controller.value * 2 * pi),
          child: child,
        );
      },
      child: Container(), // tu contenido aquí
    );
  }
}

class _AuroraPainter extends CustomPainter {
  final FragmentShader shader;
  final double time;

  _AuroraPainter(this.shader, this.time);

  @override
  void paint(Canvas canvas, Size size) {
    shader.setFloat(0, time);
    shader.setFloat(1, size.width);
    shader.setFloat(2, size.height);
    // WellCore Red
    shader.setFloat(3, 0.89);   // R
    shader.setFloat(4, 0.118);  // G
    shader.setFloat(5, 0.141);  // B
    shader.setFloat(6, 1.0);    // A
    // Accent Orange
    shader.setFloat(7, 1.0);
    shader.setFloat(8, 0.5);
    shader.setFloat(9, 0.0);
    shader.setFloat(10, 1.0);

    canvas.drawRect(
      Rect.fromLTWH(0, 0, size.width, size.height),
      Paint()..shader = shader,
    );
  }

  @override
  bool shouldRepaint(_AuroraPainter old) => old.time != time;
}
```

**Shader de Noise Texture para fondos WellCore:**
```glsl
// shaders/noise_bg.frag
#version 460 core
#include <flutter/runtime_effect.glsl>

uniform float uTime;
uniform float uWidth;
uniform float uHeight;
out vec4 fragColor;

float hash(vec2 p) {
  return fract(sin(dot(p, vec2(127.1, 311.7))) * 43758.5453);
}

float noise(vec2 p) {
  vec2 i = floor(p);
  vec2 f = fract(p);
  f = f * f * (3.0 - 2.0 * f); // smoothstep
  return mix(
    mix(hash(i), hash(i + vec2(1,0)), f.x),
    mix(hash(i + vec2(0,1)), hash(i + vec2(1,1)), f.x),
    f.y
  );
}

void main() {
  vec2 uv = FlutterFragCoord().xy / vec2(uWidth, uHeight);
  float n = noise(uv * 4.0 + uTime * 0.1) * 0.5
          + noise(uv * 8.0 - uTime * 0.15) * 0.25
          + noise(uv * 16.0) * 0.125;
  // Dark base con ruido sutil para textura
  fragColor = vec4(vec3(0.05 + n * 0.04), 1.0);
}
```

### 1.2 flutter_shaders Package — Utilidades de Producción

El package `flutter_shaders` (v0.1.3, 728k downloads) simplifica el trabajo con shaders:

```dart
dependencies:
  flutter_shaders: ^0.1.3
```

`AnimatedSampler` captura cualquier widget como textura para pasársela a un shader:

```dart
import 'package:flutter_shaders/flutter_shaders.dart';

// Aplicar pixelation shader sobre cualquier widget
AnimatedSampler(
  (image, size, canvas) {
    shader
      ..setFloat(0, 20)         // pixeles en X
      ..setFloat(1, 20)         // pixeles en Y
      ..setFloat(2, size.width)
      ..setFloat(3, size.height)
      ..setImageSampler(0, image);
    canvas.drawRect(
      Rect.fromLTWH(0, 0, size.width, size.height),
      Paint()..shader = shader,
    );
  },
  child: YourWidget(),
)
```

### 1.3 Importante: Impeller y Shaders

Con Flutter 3.41, Impeller es el renderer por defecto en iOS (desde 3.10) y Android (desde 3.16). Esto elimina el "shader compilation jank" que existía con Skia — los primeros frames ya no tienen drops.

**Lo que cambia para WellCore:**
- Los shaders de `BackdropFilter` (glassmorphism) ya no causan jank en Android
- `ImageFilter.shader()` solo funciona con Impeller (no Skia)
- Menor necesidad de `warming up shaders` manualmente

### 1.4 CustomPainter Avanzado — Particle System

```dart
class ParticleSystemPainter extends CustomPainter {
  final List<Particle> particles;
  final double time;

  ParticleSystemPainter(this.particles, this.time);

  @override
  void paint(Canvas canvas, Size size) {
    for (final p in particles) {
      final opacity = (1.0 - p.life).clamp(0.0, 1.0);
      final paint = Paint()
        ..color = const Color(0xFFE31E24).withOpacity(opacity * 0.8)
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 4);

      canvas.drawCircle(
        Offset(
          p.x + sin(time * p.speedX) * 20,
          p.y - p.life * size.height * 0.3,
        ),
        p.radius * (1 - p.life * 0.5),
        paint,
      );
    }
  }

  @override
  bool shouldRepaint(ParticleSystemPainter old) => true;
}

class Particle {
  double x, y, radius, speedX, life;
  Particle({
    required this.x, required this.y,
    required this.radius, required this.speedX,
    this.life = 0,
  });
}
```

### 1.5 Rive vs Lottie vs flutter_animate — Cuándo Usar Cada Uno

| Criterio | Rive | Lottie | flutter_animate |
|----------|------|--------|----------------|
| **Performance** | Excelente (renderer propio) | Buena (renderiza AE) | Excelente (nativamente Flutter) |
| **Interactividad** | State machines reactivas | Solo reproducción lineal | Programático |
| **Tamaño archivo** | Pequeño (.riv binario) | Medio (JSON AE) | Zero (código) |
| **Diseñador → Dev** | Rive editor (online) | After Effects + plugin | Solo código |
| **Casos de uso WellCore** | Mascota coach IA, iconos animados, onboarding | Celebraciones de logros, animaciones complejas | Transiciones UI, micro-interactions |
| **Precio herramienta** | Freemium (Rive editor) | Free (AE requiere Adobe) | Free |
| **Versión Flutter** | 0.14.4 | 3.3.2 | 4.5.2 |

**Recomendación para WellCore:**
- **Rive** → Avatar del AI Coach con expressions reactivas al score de bienestar
- **Lottie** → Animación de confetti al completar un challenge de comunidad
- **flutter_animate** → Todo lo demás (transiciones, entradas de cards, stagger en listas)

---

## SECCIÓN 2: ANIMACIONES PREMIUM 2025-2026

### 2.1 flutter_animate — Patrones Avanzados

flutter_animate 4.5.2 (Flutter Favorite, 4.15k likes, 685k downloads) es la herramienta definitiva para animaciones en WellCore.

**Stagger en listas — Patrón usado en apps top:**
```dart
// Lista de hábitos con stagger de entrada
ListView.builder(
  itemCount: habits.length,
  itemBuilder: (context, index) {
    return HabitCard(habit: habits[index])
      .animate(delay: Duration(milliseconds: 80 * index))
      .fadeIn(duration: 400.ms, curve: Curves.easeOut)
      .slideX(begin: 0.3, end: 0, curve: Curves.easeOutCubic);
  },
);
```

**Encadenamiento avanzado con ThenEffect:**
```dart
// Secuencia: fade → slide → escala → glow
XPWidget(xp: 1250)
  .animate()
  .fadeIn(duration: 300.ms)
  .then(delay: 100.ms)
  .slideY(begin: 0.2, end: 0, duration: 400.ms, curve: Curves.elasticOut)
  .then()
  .shimmer(duration: 600.ms, color: const Color(0xFFE31E24))
  .shake(duration: 300.ms, hz: 4);
```

**ScrollAdapter — Animaciones driven por el scroll:**
```dart
class AnimatedDashboard extends StatefulWidget {
  @override
  State<AnimatedDashboard> createState() => _AnimatedDashboardState();
}

class _AnimatedDashboardState extends State<AnimatedDashboard> {
  final ScrollController _scrollController = ScrollController();

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      controller: _scrollController,
      slivers: [
        SliverToBoxAdapter(
          child: MetricCard(label: 'Peso', value: '78.5 kg')
            .animate(
              adapter: ScrollAdapter(_scrollController, begin: 0, end: 200),
            )
            .fadeIn()
            .scaleXY(begin: 0.9, end: 1.0)
            .slideY(begin: 0.15, end: 0),
        ),
        // más slivers...
      ],
    );
  }
}
```

**ToggleEffect — Reaccionar a cambios de estado:**
```dart
// El botón de habito cambia cuando se completa
Consumer(
  builder: (context, ref, child) {
    final isCompleted = ref.watch(habitProvider(habitId)).isCompleted;
    return HabitButton(habit: habit)
      .animate(target: isCompleted ? 1 : 0)
      .scaleXY(end: 1.1, duration: 200.ms, curve: Curves.easeInOut)
      .then()
      .scaleXY(end: 1.0, duration: 150.ms)
      .shimmer(color: Colors.white, duration: 500.ms);
  },
)
```

**AnimateList — Para listas completas:**
```dart
AnimateList(
  interval: 60.ms,
  effects: [
    FadeEffect(duration: 300.ms),
    SlideEffect(begin: const Offset(0, 0.15), end: Offset.zero),
  ],
  children: challenges.map((c) => ChallengeCard(challenge: c)).toList(),
)
```

**Efecto custom — Glow pulsante WellCore:**
```dart
extension WellCoreAnimations on AnimateManager {
  AnimateManager wellcoreGlow({Color color = const Color(0xFFE31E24)}) {
    return addEffect(CustomEffect(
      duration: 1500.ms,
      curve: Curves.easeInOut,
      builder: (context, value, child) {
        return Container(
          decoration: BoxDecoration(
            boxShadow: [
              BoxShadow(
                color: color.withOpacity(value * 0.6),
                blurRadius: value * 20,
                spreadRadius: value * 2,
              ),
            ],
          ),
          child: child,
        );
      },
    ));
  }
}

// Uso:
ScoreWidget()
  .animate(onPlay: (controller) => controller.repeat(reverse: true))
  .wellcoreGlow(color: const Color(0xFFE31E24));
```

### 2.2 Hero Animations entre Pantallas

Las Hero animations son perfectas para WellCore al navegar de la lista de challenges al detalle.

```dart
// En la lista de challenges
Hero(
  tag: 'challenge_${challenge.id}',
  child: ChallengeCard(challenge: challenge),
)

// En la pantalla de detalle
Hero(
  tag: 'challenge_${challenge.id}',
  // Flutter maneja la transición automáticamente
  child: ChallengeDetailHeader(challenge: challenge),
)

// Navegación con GoRouter preservando Hero
context.push('/challenges/${challenge.id}');
```

**Hero con CustomFlightShuttleBuilder para efectos avanzados:**
```dart
Hero(
  tag: 'metric_card_weight',
  flightShuttleBuilder: (context, animation, direction, from, to) {
    return AnimatedBuilder(
      animation: animation,
      builder: (context, child) {
        return Transform.scale(
          scale: Curves.easeInOut.transform(animation.value),
          child: direction == HeroFlightDirection.push
              ? to.widget
              : from.widget,
        );
      },
    );
  },
  child: MetricCard(label: 'Peso', value: '78.5 kg'),
)
```

### 2.3 Material Motion Transitions con `animations` Package

El package `animations` (v2.1.1, flutter.dev, 6.8k likes) provee los patrones de Material Motion:

```dart
dependencies:
  animations: ^2.1.1
```

**Container Transform (card → detalle):**
```dart
OpenContainer(
  transitionType: ContainerTransitionType.fade,
  transitionDuration: const Duration(milliseconds: 400),
  openElevation: 0,
  closedElevation: 0,
  closedShape: RoundedRectangleBorder(
    borderRadius: BorderRadius.circular(16),
  ),
  closedColor: const Color(0xFF1A1A2E),
  openColor: const Color(0xFF0D0D1A),
  closedBuilder: (context, openContainer) {
    return HabitCard(
      habit: habit,
      onTap: openContainer,
    );
  },
  openBuilder: (context, closeContainer) {
    return HabitDetailPage(habit: habit);
  },
)
```

**Shared Axis para navegación entre tabs:**
```dart
PageTransitionSwitcher(
  duration: const Duration(milliseconds: 300),
  transitionBuilder: (child, animation, secondaryAnimation) {
    return SharedAxisTransition(
      animation: animation,
      secondaryAnimation: secondaryAnimation,
      transitionType: SharedAxisTransitionType.horizontal,
      child: child,
    );
  },
  child: _buildTabContent(currentTabIndex),
)
```

### 2.4 Slivers Avanzados para el Dashboard de WellCore

```dart
CustomScrollView(
  slivers: [
    // AppBar que colapsa con parallax
    SliverAppBar(
      expandedHeight: 280,
      pinned: true,
      flexibleSpace: FlexibleSpaceBar(
        background: Stack(
          children: [
            // Imagen de fondo con parallax nativo
            Positioned.fill(
              child: Image.asset('assets/bg_hero.jpg', fit: BoxFit.cover),
            ),
            // Gradiente WellCore sobre la imagen
            Positioned.fill(
              child: DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [
                      Colors.transparent,
                      const Color(0xFF0D0D1A).withOpacity(0.9),
                      const Color(0xFF0D0D1A),
                    ],
                  ),
                ),
              ),
            ),
            // Header con XP y saludo
            const Positioned(
              bottom: 24,
              left: 24,
              child: DashboardHeader(),
            ),
          ],
        ),
        collapseMode: CollapseMode.parallax,
      ),
    ),

    // Sección de métricas sticky
    SliverPersistentHeader(
      pinned: true,
      delegate: _MetricsStickyHeaderDelegate(),
    ),

    // Lista de hábitos con padding
    SliverPadding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 100),
      sliver: SliverList(
        delegate: SliverChildBuilderDelegate(
          (context, index) => HabitCard(habit: habits[index])
            .animate(delay: (80 * index).ms)
            .fadeIn(duration: 300.ms)
            .slideY(begin: 0.1),
          childCount: habits.length,
        ),
      ),
    ),
  ],
)

// Delegate para el header sticky de métricas
class _MetricsStickyHeaderDelegate extends SliverPersistentHeaderDelegate {
  @override
  double get minExtent => 72;
  @override
  double get maxExtent => 72;

  @override
  Widget build(BuildContext context, double shrinkOffset, bool overlapsContent) {
    final blur = (shrinkOffset / maxExtent * 20).clamp(0.0, 20.0);
    return ClipRect(
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: blur, sigmaY: blur),
        child: Container(
          color: const Color(0xFF0D0D1A).withOpacity(0.85),
          child: const MetricsQuickView(),
        ),
      ),
    );
  }

  @override
  bool shouldRebuild(covariant _MetricsStickyHeaderDelegate old) => false;
}
```

### 2.5 GoRouter 17.x — Transiciones de Página Custom

```dart
final router = GoRouter(
  routes: [
    GoRoute(
      path: '/dashboard',
      pageBuilder: (context, state) => CustomTransitionPage(
        key: state.pageKey,
        child: const DashboardPage(),
        transitionDuration: const Duration(milliseconds: 400),
        transitionsBuilder: (context, animation, secondaryAnimation, child) {
          return FadeTransition(
            opacity: CurveTween(curve: Curves.easeOutCubic).animate(animation),
            child: child,
          );
        },
      ),
    ),
    GoRoute(
      path: '/challenges/:id',
      pageBuilder: (context, state) {
        final id = state.pathParameters['id']!;
        return CustomTransitionPage(
          key: state.pageKey,
          child: ChallengeDetailPage(id: id),
          transitionDuration: const Duration(milliseconds: 350),
          transitionsBuilder: (context, animation, secondaryAnimation, child) {
            final curved = CurvedAnimation(
              parent: animation,
              curve: Curves.easeOutCubic,
            );
            return SlideTransition(
              position: Tween<Offset>(
                begin: const Offset(0, 0.05),
                end: Offset.zero,
              ).animate(curved),
              child: FadeTransition(opacity: curved, child: child),
            );
          },
        );
      },
    ),
  ],
);
```

---

## SECCIÓN 3: DISEÑO UI PATTERNS MODERNOS

### 3.1 Glassmorphism 2.0 — Implementación Avanzada WellCore

```dart
class GlassCard extends StatelessWidget {
  final Widget child;
  final double borderRadius;
  final double blur;
  final Color borderColor;
  final Color backgroundColor;

  const GlassCard({
    super.key,
    required this.child,
    this.borderRadius = 20,
    this.blur = 16,
    this.borderColor = const Color(0x33FFFFFF),
    this.backgroundColor = const Color(0x1AFFFFFF),
  });

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(borderRadius),
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: blur, sigmaY: blur),
        child: Container(
          decoration: BoxDecoration(
            color: backgroundColor,
            borderRadius: BorderRadius.circular(borderRadius),
            border: Border.all(color: borderColor, width: 1),
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [
                Colors.white.withOpacity(0.12),
                Colors.white.withOpacity(0.04),
              ],
            ),
          ),
          child: child,
        ),
      ),
    );
  }
}

// Glassmorphism con WellCore Red accent
class WellcoreAccentGlass extends StatelessWidget {
  final Widget child;

  const WellcoreAccentGlass({super.key, required this.child});

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(20),
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 20, sigmaY: 20),
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [
                const Color(0xFFE31E24).withOpacity(0.15),
                const Color(0xFF1A1A2E).withOpacity(0.7),
              ],
            ),
            border: Border.all(
              color: const Color(0xFFE31E24).withOpacity(0.3),
              width: 1,
            ),
          ),
          child: child,
        ),
      ),
    );
  }
}
```

**Variable Blur con Scroll — Efecto que varía el blur según posición:**
```dart
class VariableBlurHeader extends StatelessWidget {
  final ScrollController controller;
  final Widget child;

  const VariableBlurHeader({
    super.key,
    required this.controller,
    required this.child,
  });

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: controller,
      builder: (context, _) {
        final blur = (controller.hasClients
            ? (controller.offset / 100 * 20).clamp(0.0, 20.0)
            : 0.0);
        return ClipRect(
          child: BackdropFilter(
            filter: ImageFilter.blur(sigmaX: blur, sigmaY: blur),
            child: child,
          ),
        );
      },
    );
  }
}
```

### 3.2 Bento Grid — Layout tipo Apple para el Dashboard

```dart
// Requiere: flutter_staggered_grid_view: ^0.7.0
import 'package:flutter_staggered_grid_view/flutter_staggered_grid_view.dart';

class WellCoreBentoDashboard extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(12),
      child: StaggeredGrid.count(
        crossAxisCount: 4,
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        children: [
          // Tile grande — Score de bienestar
          StaggeredGridTile.count(
            crossAxisCellCount: 2,
            mainAxisCellCount: 2,
            child: _WellnessScoreTile(),
          ),
          // Tile pequeño — Hidratación
          StaggeredGridTile.count(
            crossAxisCellCount: 1,
            mainAxisCellCount: 1,
            child: _HydrationTile(),
          ),
          // Tile pequeño — Sueño
          StaggeredGridTile.count(
            crossAxisCellCount: 1,
            mainAxisCellCount: 1,
            child: _SleepTile(),
          ),
          // Tile ancho — Progreso semanal
          StaggeredGridTile.count(
            crossAxisCellCount: 4,
            mainAxisCellCount: 1,
            child: _WeeklyProgressTile(),
          ),
          // Tile medio — Próxima cita
          StaggeredGridTile.count(
            crossAxisCellCount: 2,
            mainAxisCellCount: 1,
            child: _NextAppointmentTile(),
          ),
          // Tile medio — Reto activo
          StaggeredGridTile.count(
            crossAxisCellCount: 2,
            mainAxisCellCount: 1,
            child: _ActiveChallengeTile(),
          ),
        ],
      ),
    );
  }
}
```

### 3.3 Dynamic Island-style Container — El "Score Pill" de WellCore

```dart
class WellcoreScorePill extends StatefulWidget {
  final int score;
  final bool isExpanded;
  final VoidCallback onTap;

  const WellcoreScorePill({
    super.key,
    required this.score,
    required this.isExpanded,
    required this.onTap,
  });

  @override
  State<WellcoreScorePill> createState() => _WellcoreScorePillState();
}

class _WellcoreScorePillState extends State<WellcoreScorePill> {
  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: widget.onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 400),
        curve: Curves.easeInOutCubic,
        width: widget.isExpanded ? 280 : 120,
        height: widget.isExpanded ? 160 : 44,
        decoration: BoxDecoration(
          color: const Color(0xFF1A1A2E),
          borderRadius: BorderRadius.circular(widget.isExpanded ? 28 : 22),
          border: Border.all(
            color: const Color(0xFFE31E24).withOpacity(0.4),
            width: 1.5,
          ),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFFE31E24).withOpacity(0.2),
              blurRadius: 20,
              spreadRadius: 0,
            ),
          ],
        ),
        clipBehavior: Clip.antiAlias,
        child: AnimatedSwitcher(
          duration: const Duration(milliseconds: 300),
          child: widget.isExpanded
              ? _ExpandedContent(score: widget.score)
              : _CollapsedContent(score: widget.score),
        ),
      ),
    );
  }
}
```

### 3.4 Bottom Sheet Premium con WoltModalSheet

```dart
// pubspec: wolt_modal_sheet: ^0.11.0

void showCheckinSheet(BuildContext context) {
  WoltModalSheet.show(
    context: context,
    pageListBuilder: (modalContext) => [
      // Página 1: ¿Cómo te sientes?
      SliverWoltModalSheetPage(
        hasSabGradient: false,
        backgroundColor: const Color(0xFF1A1A2E),
        topBarTitle: Text(
          'Check-in Semanal',
          style: Theme.of(context).textTheme.titleMedium,
        ),
        isTopBarLayerAlwaysVisible: true,
        mainContentSliversBuilder: (context) => [
          SliverPadding(
            padding: const EdgeInsets.all(24),
            sliver: SliverToBoxAdapter(
              child: MoodSelector(),
            ),
          ),
        ],
        stickyActionBar: Padding(
          padding: const EdgeInsets.all(16),
          child: WellcoreButton(
            label: 'Continuar',
            onTap: () => WoltModalSheet.of(modalContext).showNext(),
          ),
        ),
      ),

      // Página 2: Métricas rápidas
      WoltModalSheetPage(
        hasSabGradient: false,
        backgroundColor: const Color(0xFF1A1A2E),
        topBarTitle: Text('Métricas de la semana',
          style: Theme.of(context).textTheme.titleMedium),
        child: const QuickMetricsForm(),
        stickyActionBar: Padding(
          padding: const EdgeInsets.all(16),
          child: WellcoreButton(
            label: 'Guardar Check-in',
            onTap: () => Navigator.pop(context),
          ),
        ),
      ),
    ],
  );
}
```

### 3.5 Skeleton Loaders con Skeletonizer

```dart
// pubspec: skeletonizer: ^2.1.3

class HabitListScreen extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final habitsAsync = ref.watch(habitsProvider);

    return habitsAsync.when(
      loading: () => Skeletonizer(
        enabled: true,
        effect: ShimmerEffect(
          baseColor: const Color(0xFF2A2A3E),
          highlightColor: const Color(0xFF3A3A5E),
          duration: const Duration(seconds: 2),
        ),
        child: _HabitListContent(habits: _fakHabits), // datos falsos para layout
      ),
      data: (habits) => _HabitListContent(habits: habits),
      error: (e, st) => ErrorWidget(error: e),
    );
  }

  // Lista de hábitos falsos del mismo shape que los reales
  static final _fakHabits = List.generate(5, (i) => Habit.placeholder());
}
```

### 3.6 Pull-to-Refresh Animado Custom

```dart
class WellcoreRefreshIndicator extends StatelessWidget {
  final Widget child;
  final Future<void> Function() onRefresh;

  const WellcoreRefreshIndicator({
    super.key,
    required this.child,
    required this.onRefresh,
  });

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: onRefresh,
      color: const Color(0xFFE31E24),
      backgroundColor: const Color(0xFF1A1A2E),
      strokeWidth: 2.5,
      displacement: 60,
      // Para refresh personalizado completo, usar custom sliver
      child: child,
    );
  }
}
```

### 3.7 Micro-interactions — Tap Feedback Premium

```dart
class WellcoreTapFeedback extends StatelessWidget {
  final Widget child;
  final VoidCallback? onTap;
  final double scaleFactor;

  const WellcoreTapFeedback({
    super.key,
    required this.child,
    this.onTap,
    this.scaleFactor = 0.96,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: child
        .animate(
          onPlay: (controller) => controller.stop(),
          autoPlay: false,
        )
        .custom(
          duration: 100.ms,
          begin: 1.0,
          end: scaleFactor,
          builder: (context, value, child) => Transform.scale(
            scale: value,
            child: child,
          ),
        ),
    );
  }
}
```

---

## SECCIÓN 4: ICONOS Y TIPOGRAFÍA PREMIUM

### 4.1 Phosphor Icons — La Mejor Elección para WellCore

Phosphor Flutter (v2.1.0, 161 likes, 57.2k downloads) ofrece 5 pesos de iconos más duotone.

**Configuración:**
```yaml
dependencies:
  phosphor_flutter: ^2.1.0
```

**Uso de los diferentes pesos:**
```dart
import 'package:phosphor_flutter/phosphor_flutter.dart';

// Regular (default)
PhosphorIcon(PhosphorIcons.heartbeat())

// Bold (más presencia visual)
PhosphorIcon(PhosphorIcons.heartbeat(PhosphorIconsStyle.bold))

// Duotone (premium — dos colores)
PhosphorIcon(
  PhosphorIcons.heartbeat(PhosphorIconsStyle.duotone),
  color: const Color(0xFFE31E24),
  // El color secundario es automático al 20% de opacidad
  size: 28,
)

// Fill (sólido, para estados activos)
PhosphorIcon(
  PhosphorIcons.heartbeat(PhosphorIconsStyle.fill),
  color: const Color(0xFFE31E24),
)
```

**Sistema de iconos WellCore con Phosphor:**
```dart
// Definición centralizada en constants/icons.dart
class WcIcons {
  // Navigation
  static PhosphorIconData get home => PhosphorIcons.house();
  static PhosphorIconData get homeActive => PhosphorIcons.house(PhosphorIconsStyle.fill);

  static PhosphorIconData get dashboard => PhosphorIcons.chartLine();
  static PhosphorIconData get habits => PhosphorIcons.checkCircle();
  static PhosphorIconData get habitsActive => PhosphorIcons.checkCircle(PhosphorIconsStyle.fill);
  static PhosphorIconData get community => PhosphorIcons.users();
  static PhosphorIconData get ai => PhosphorIcons.robot();

  // Metrics
  static PhosphorIconData get weight => PhosphorIcons.scales();
  static PhosphorIconData get heart => PhosphorIcons.heartbeat();
  static PhosphorIconData get sleep => PhosphorIcons.moon();
  static PhosphorIconData get water => PhosphorIcons.drop();
  static PhosphorIconData get calories => PhosphorIcons.flame();
  static PhosphorIconData get steps => PhosphorIcons.footprints();

  // Gamification
  static PhosphorIconData get xp => PhosphorIcons.lightning();
  static PhosphorIconData get trophy => PhosphorIcons.trophy();
  static PhosphorIconData get medal => PhosphorIcons.medal();
  static PhosphorIconData get star => PhosphorIcons.star();
  static PhosphorIconData get starActive => PhosphorIcons.star(PhosphorIconsStyle.fill);

  // Actions
  static PhosphorIconData get camera => PhosphorIcons.camera();
  static PhosphorIconData get edit => PhosphorIcons.pencilSimple();
  static PhosphorIconData get share => PhosphorIcons.shareNetwork();
  static PhosphorIconData get bell => PhosphorIcons.bell();
  static PhosphorIconData get settings => PhosphorIcons.gear();
}
```

### 4.2 HugeIcons — Alternativa con 4,700+ Iconos SVG

```yaml
dependencies:
  hugeicons: ^1.1.5
```

```dart
// Ventaja: iconos únicos que Phosphor no tiene
HugeIcon(
  icon: HugeIcons.strokeRoundedDumbbell,
  color: const Color(0xFFE31E24),
  size: 28.0,
)
```

### 4.3 Texto con Gradiente — ShaderMask

```dart
// Gradiente en texto — efecto premium para títulos
class GradientText extends StatelessWidget {
  final String text;
  final TextStyle style;
  final Gradient gradient;

  const GradientText({
    super.key,
    required this.text,
    required this.style,
    required this.gradient,
  });

  @override
  Widget build(BuildContext context) {
    return ShaderMask(
      shaderCallback: (bounds) => gradient.createShader(
        Rect.fromLTWH(0, 0, bounds.width, bounds.height),
      ),
      blendMode: BlendMode.srcIn,
      child: Text(text, style: style.copyWith(color: Colors.white)),
    );
  }
}

// Uso en el dashboard
GradientText(
  text: 'Bienvenido, Carlos',
  style: const TextStyle(
    fontSize: 28,
    fontWeight: FontWeight.w800,
    letterSpacing: -0.5,
  ),
  gradient: const LinearGradient(
    colors: [Color(0xFFE31E24), Color(0xFFFF6B35)],
  ),
)
```

### 4.4 Variable Fonts y Tipografía Premium

```yaml
# pubspec.yaml — Google Fonts con variable font support
dependencies:
  google_fonts: ^6.2.1
```

```dart
// Axiforma-style con Outfit (variable font)
// Para letra premium tipo Linear, Raycast, Vercel
import 'package:google_fonts/google_fonts.dart';

ThemeData wellcoreTheme() {
  return ThemeData.dark().copyWith(
    textTheme: GoogleFonts.outfitTextTheme().copyWith(
      displayLarge: GoogleFonts.outfit(
        fontSize: 48,
        fontWeight: FontWeight.w800,
        letterSpacing: -2,
        color: Colors.white,
      ),
      headlineLarge: GoogleFonts.outfit(
        fontSize: 32,
        fontWeight: FontWeight.w700,
        letterSpacing: -1,
        color: Colors.white,
      ),
      titleLarge: GoogleFonts.outfit(
        fontSize: 20,
        fontWeight: FontWeight.w600,
        letterSpacing: -0.3,
        color: Colors.white,
      ),
      bodyLarge: GoogleFonts.outfit(
        fontSize: 16,
        fontWeight: FontWeight.w400,
        letterSpacing: 0,
        color: const Color(0xFFB0B0C0),
      ),
      labelSmall: GoogleFonts.outfit(
        fontSize: 11,
        fontWeight: FontWeight.w500,
        letterSpacing: 0.8,  // tracking positivo para labels
        color: const Color(0xFF808090),
      ),
    ),
  );
}
```

---

## SECCIÓN 5: PERFORMANCE Y ARQUITECTURA 2025

### 5.1 Riverpod 3.x — Patrones Definitivos

**flutter_riverpod 3.3.1** (publicado hace 38 horas al momento de esta investigación).

**AsyncNotifier vs StateNotifier — El veredicto:**

```dart
// ❌ OLD — StateNotifier (deprecated pattern)
class HabitsController extends StateNotifier<AsyncValue<List<Habit>>> {
  HabitsController(this.ref) : super(const AsyncLoading()) {
    _load();
  }
  final Ref ref;

  Future<void> _load() async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() => ref.read(habitsRepo).getAll());
  }
}

final habitsProvider = StateNotifierProvider<HabitsController, AsyncValue<List<Habit>>>(
  (ref) => HabitsController(ref),
);

// ✅ NEW — AsyncNotifier con code generation
@riverpod
class HabitsController extends _$HabitsController {
  @override
  Future<List<Habit>> build() async {
    return ref.read(habitsRepositoryProvider).getAll();
  }

  Future<void> toggleHabit(String habitId) async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() async {
      await ref.read(habitsRepositoryProvider).toggle(habitId);
      return ref.read(habitsRepositoryProvider).getAll();
    });
  }

  Future<void> refresh() async {
    ref.invalidateSelf();
    await future;
  }
}
```

**Provider con parámetros — Family pattern con code gen:**
```dart
// Antes (verbose)
final habitByIdProvider = FutureProvider.family<Habit, String>(
  (ref, id) => ref.read(habitsRepositoryProvider).getById(id),
);

// Ahora (elegante)
@riverpod
Future<Habit> habitById(Ref ref, String id) async {
  return ref.read(habitsRepositoryProvider).getById(id);
}

// En el widget
final habit = ref.watch(habitByIdProvider('habit_123'));
```

**Riverpod Generator setup:**
```yaml
dependencies:
  flutter_riverpod: ^3.3.1
  riverpod_annotation: ^2.6.1

dev_dependencies:
  riverpod_generator: ^4.0.3
  build_runner: ^2.4.13
  riverpod_lint: ^2.6.5
  custom_lint: ^0.7.3
```

```dart
// part file obligatorio
part 'habits_controller.g.dart';

// Correr: dart run build_runner watch --delete-conflicting-outputs
```

**Arquitectura Layer-First para WellCore:**
```
lib/
├── core/
│   ├── constants/           # colores, strings, tamaños
│   ├── extensions/          # BuildContext extensions, etc.
│   ├── theme/               # ThemeData, TextStyles
│   └── utils/               # helpers
├── data/
│   ├── repositories/        # implementaciones de repos
│   ├── datasources/         # API calls, local DB
│   └── models/              # DTOs con freezed
├── domain/
│   ├── entities/            # modelos de negocio (puros Dart)
│   └── repositories/        # interfaces abstractas
└── features/
    ├── dashboard/
    │   ├── presentation/    # widgets, pages
    │   └── application/     # controllers (AsyncNotifier)
    ├── habits/
    ├── checkin/
    ├── metrics/
    ├── community/
    └── ai_coach/
```

### 5.2 Freezed — Modelos Inmutables con Union Types

```yaml
dependencies:
  freezed_annotation: ^3.0.0
  json_annotation: ^4.9.0

dev_dependencies:
  freezed: ^3.2.5
  json_serializable: ^6.9.3
  build_runner: ^2.4.13
```

```dart
// Modelo Habit con Freezed
@freezed
class Habit with _$Habit {
  const factory Habit({
    required String id,
    required String name,
    required HabitCategory category,
    required bool isCompleted,
    required int streakDays,
    @Default(false) bool isPinned,
    DateTime? completedAt,
  }) = _Habit;

  factory Habit.fromJson(Map<String, dynamic> json) => _$HabitFromJson(json);

  // Factory para placeholder (skeletonizer)
  factory Habit.placeholder() => const Habit(
    id: 'placeholder',
    name: 'Loading habit name here...',
    category: HabitCategory.wellness,
    isCompleted: false,
    streakDays: 0,
  );
}

// Union type para estados de métricas
@freezed
class MetricState with _$MetricState {
  const factory MetricState.initial() = _Initial;
  const factory MetricState.loading() = _Loading;
  const factory MetricState.loaded(List<BiometricLog> logs) = _Loaded;
  const factory MetricState.error(String message) = _Error;
}

// Uso con pattern matching (Dart 3+)
Widget build(BuildContext context, WidgetRef ref) {
  final state = ref.watch(metricsControllerProvider);
  return switch (state) {
    _Initial() => const SizedBox(),
    _Loading() => const CircularProgressIndicator(),
    _Loaded(:final logs) => MetricsChart(logs: logs),
    _Error(:final message) => ErrorWidget(message: message),
  };
}
```

### 5.3 RepaintBoundary Estratégico

```dart
// Envolver widgets que se animan frecuentemente
// para evitar que re-pinten otros widgets

class DashboardPage extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        // Este widget se anima cada frame (XP pulsante)
        // → aislarlo con RepaintBoundary
        RepaintBoundary(
          child: AnimatedXPWidget(),
        ),

        // Gauge que se actualiza con el corazón
        RepaintBoundary(
          child: HeartRateGauge(),
        ),

        // El resto del dashboard no necesita RepaintBoundary
        // porque no se anima independientemente
        HabitsSection(),
        ChallengesSection(),
      ],
    );
  }
}
```

### 5.4 Const Widgets y Optimización

```dart
// ❌ Incorrecto — se recrea cada build
Widget build(BuildContext context) {
  return Column(
    children: [
      SizedBox(height: 16),  // NO const
      Text('Dashboard'),     // NO const
    ],
  );
}

// ✅ Correcto — se reutiliza
Widget build(BuildContext context) {
  return const Column(
    children: [
      SizedBox(height: 16),  // const
      Text('Dashboard'),     // const
    ],
  );
}

// Regla: cualquier widget sin parámetros dinámicos → const
// Usar flutter_lints para detectar oportunidades de const
```

### 5.5 Flutter 3.41 — Novedades Clave

Basado en la documentación oficial (release de 2026-02-11):

- **Hot Reload en Web** ya no requiere flag experimental (desde 3.35)
- **Widget Previewer** con integración IDE para VS Code y Android Studio
- **iOS 26 / Xcode 26 / macOS 26** completamente soportados
- **UIScene Lifecycle** de Apple — migration guide disponible
- **New Getting Started experience** rediseñado en docs.flutter.dev
- **Dart Dot Shorthands** — sintaxis más concisa (3.38)

---

## SECCIÓN 6: CHARTS Y DATA VISUALIZATION

### 6.1 fl_chart 1.1.1 — Uso Avanzado para WellCore

**Line Chart con gradiente y animación:**
```dart
import 'package:fl_chart/fl_chart.dart';

class WeightProgressChart extends StatefulWidget {
  final List<BiometricLog> logs;

  const WeightProgressChart({super.key, required this.logs});

  @override
  State<WeightProgressChart> createState() => _WeightProgressChartState();
}

class _WeightProgressChartState extends State<WeightProgressChart>
    with SingleTickerProviderStateMixin {
  late AnimationController _animController;
  late Animation<double> _animation;

  @override
  void initState() {
    super.initState();
    _animController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1200),
    );
    _animation = CurvedAnimation(
      parent: _animController,
      curve: Curves.easeInOutCubic,
    );
    _animController.forward();
  }

  @override
  void dispose() {
    _animController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _animation,
      builder: (context, _) {
        return LineChart(
          LineChartData(
            gridData: FlGridData(
              show: true,
              drawHorizontalLine: true,
              drawVerticalLine: false,
              getDrawingHorizontalLine: (_) => FlLine(
                color: Colors.white.withOpacity(0.05),
                strokeWidth: 1,
              ),
            ),
            titlesData: FlTitlesData(
              rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
              topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
              bottomTitles: AxisTitles(
                sideTitles: SideTitles(
                  showTitles: true,
                  getTitlesWidget: (value, meta) {
                    final date = widget.logs[value.toInt()].date;
                    return Text(
                      DateFormat('d/M').format(date),
                      style: const TextStyle(color: Color(0xFF808090), fontSize: 10),
                    );
                  },
                ),
              ),
            ),
            borderData: FlBorderData(show: false),
            lineBarsData: [
              LineChartBarData(
                spots: widget.logs.asMap().entries.map((e) {
                  return FlSpot(
                    e.key.toDouble(),
                    e.value.weight * _animation.value,
                  );
                }).toList(),
                isCurved: true,
                curveSmoothness: 0.3,
                color: const Color(0xFFE31E24),
                barWidth: 2.5,
                isStrokeCapRound: true,
                dotData: const FlDotData(show: false),
                belowBarData: BarAreaData(
                  show: true,
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [
                      const Color(0xFFE31E24).withOpacity(0.3 * _animation.value),
                      const Color(0xFFE31E24).withOpacity(0),
                    ],
                  ),
                ),
              ),
            ],
            lineTouchData: LineTouchData(
              touchTooltipData: LineTouchTooltipData(
                getTooltipColor: (_) => const Color(0xFF2A2A3E),
                getTooltipItems: (spots) => spots.map((spot) {
                  return LineTooltipItem(
                    '${spot.y.toStringAsFixed(1)} kg',
                    const TextStyle(
                      color: Color(0xFFE31E24),
                      fontWeight: FontWeight.w600,
                    ),
                  );
                }).toList(),
              ),
            ),
          ),
          duration: Duration.zero, // Animación manejada manualmente
        );
      },
    );
  }
}
```

**Sparkline — Mini gráfica en las tarjetas de métricas:**
```dart
class MetricSparkline extends StatelessWidget {
  final List<double> data;
  final Color color;

  const MetricSparkline({super.key, required this.data, required this.color});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 80,
      height: 32,
      child: LineChart(
        LineChartData(
          gridData: const FlGridData(show: false),
          titlesData: const FlTitlesData(show: false),
          borderData: FlBorderData(show: false),
          lineBarsData: [
            LineChartBarData(
              spots: data.asMap().entries
                  .map((e) => FlSpot(e.key.toDouble(), e.value))
                  .toList(),
              isCurved: true,
              color: color,
              barWidth: 1.5,
              dotData: const FlDotData(show: false),
              belowBarData: BarAreaData(
                show: true,
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [color.withOpacity(0.2), Colors.transparent],
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

### 6.2 Gauge de "Strain Score" Estilo Whoop

```dart
// pubspec: gauge_indicator: ^0.4.3

import 'package:gauge_indicator/gauge_indicator.dart';

class WellcoreStrainGauge extends StatelessWidget {
  final double value;  // 0.0 — 1.0
  final int score;     // 0 — 100

  const WellcoreStrainGauge({
    super.key,
    required this.value,
    required this.score,
  });

  @override
  Widget build(BuildContext context) {
    return AnimatedRadialGauge(
      duration: const Duration(milliseconds: 1500),
      curve: Curves.easeOutCubic,
      radius: 100,
      value: value * 100,
      axis: GaugeAxis(
        min: 0,
        max: 100,
        degrees: 270,
        style: const GaugeAxisStyle(
          thickness: 12,
          background: Color(0xFF1A1A2E),
          segmentSpacing: 4,
        ),
        pointer: const GaugePointer.needle(
          height: 60,
          width: 12,
          color: Color(0xFFE31E24),
          borderRadius: 6,
        ),
        progressBar: const GaugeProgressBar.rounded(
          color: Color(0xFFE31E24),
        ),
        segments: [
          const GaugeSegment(from: 0, to: 33, color: Color(0xFF2ECC71), cornerRadius: Radius.circular(4)),
          const GaugeSegment(from: 33, to: 66, color: Color(0xFFF39C12), cornerRadius: Radius.circular(4)),
          const GaugeSegment(from: 66, to: 100, color: Color(0xFFE31E24), cornerRadius: Radius.circular(4)),
        ],
      ),
      builder: (context, child, value) {
        return Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              '$score',
              style: const TextStyle(
                fontSize: 40,
                fontWeight: FontWeight.w800,
                color: Colors.white,
                letterSpacing: -2,
              ),
            ),
            const Text('SCORE', style: TextStyle(
              fontSize: 11,
              letterSpacing: 2,
              color: Color(0xFF808090),
              fontWeight: FontWeight.w500,
            )),
          ],
        );
      },
    );
  }
}
```

### 6.3 Activity Rings — Estilo Apple Watch en Flutter

```dart
class ActivityRings extends StatelessWidget {
  final double moveProgress;    // 0.0 — 1.0
  final double exerciseProgress;
  final double standProgress;

  const ActivityRings({
    super.key,
    required this.moveProgress,
    required this.exerciseProgress,
    required this.standProgress,
  });

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      size: const Size(120, 120),
      painter: _ActivityRingsPainter(
        moveProgress: moveProgress,
        exerciseProgress: exerciseProgress,
        standProgress: standProgress,
      ),
    );
  }
}

class _ActivityRingsPainter extends CustomPainter {
  final double moveProgress, exerciseProgress, standProgress;

  _ActivityRingsPainter({
    required this.moveProgress,
    required this.exerciseProgress,
    required this.standProgress,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, size.height / 2);
    final rings = [
      _RingData(
        radius: 54,
        strokeWidth: 10,
        progress: moveProgress,
        color: const Color(0xFFE31E24),       // WellCore Red — Calorías
        bgColor: const Color(0xFF3D0808),
      ),
      _RingData(
        radius: 40,
        strokeWidth: 10,
        progress: exerciseProgress,
        color: const Color(0xFF2ECC71),       // Verde — Ejercicio
        bgColor: const Color(0xFF0A2D1A),
      ),
      _RingData(
        radius: 26,
        strokeWidth: 10,
        progress: standProgress,
        color: const Color(0xFF00BCD4),       // Azul — Pasos
        bgColor: const Color(0xFF002030),
      ),
    ];

    for (final ring in rings) {
      // Fondo del anillo
      canvas.drawCircle(
        center,
        ring.radius,
        Paint()
          ..color = ring.bgColor
          ..strokeWidth = ring.strokeWidth
          ..style = PaintingStyle.stroke
          ..strokeCap = StrokeCap.round,
      );

      // Progreso del anillo
      canvas.drawArc(
        Rect.fromCircle(center: center, radius: ring.radius),
        -pi / 2,                              // Empieza desde arriba
        2 * pi * ring.progress.clamp(0, 1),  // Progreso
        false,
        Paint()
          ..color = ring.color
          ..strokeWidth = ring.strokeWidth
          ..style = PaintingStyle.stroke
          ..strokeCap = StrokeCap.round,
      );
    }
  }

  @override
  bool shouldRepaint(_ActivityRingsPainter old) {
    return old.moveProgress != moveProgress ||
           old.exerciseProgress != exerciseProgress ||
           old.standProgress != standProgress;
  }
}

class _RingData {
  final double radius, strokeWidth, progress;
  final Color color, bgColor;
  _RingData({required this.radius, required this.strokeWidth,
    required this.progress, required this.color, required this.bgColor});
}
```

---

## SECCIÓN 7: PACKAGES ESENCIALES 2025-2026

### 7.1 Tabla Comparativa — Iconos

| Package | Iconos | Estilos | SVG | Duotone | Descargas | Veredicto WellCore |
|---------|--------|---------|-----|---------|-----------|---------------------|
| **phosphor_flutter** | 772 | 5 pesos | Font | Sí | 57.2k | **ELEGIR** — Premium, duotone, ligero |
| **hugeicons** | 4,700+ | Stroke rounded | SVG | No | 21.6k | Alternativa si necesitas más variedad |
| **solar_icons** | 1,200+ | 7 estilos | Font | No | 2.1k | Joven, menor adopción |
| **Material Icons** | 2,000+ | 5 pesos | Font | No | Built-in | Default Flutter — genérico |

### 7.2 Tabla Comparativa — State Management

| Package | Paradigma | Boilerplate | Performance | Testing | Curva | Veredicto WellCore |
|---------|-----------|-------------|-------------|---------|-------|---------------------|
| **Riverpod 3.x** | Reactive/Provider | Bajo (con gen) | Excelente | Excelente | Media | **YA TIENES** — mantener |
| **BLoC** | Event/State | Alto | Excelente | Excelente | Alta | Corporativo, verboso |
| **Signals** | Reactive fine-grained | Muy bajo | Excelente | Medio | Baja | Prometedor, madurando |
| **GetX** | Reactivo | Muy bajo | Buena | Difícil | Muy baja | Anti-pattern, evitar |

### 7.3 Tabla Comparativa — Local Storage

| Package | Tipo | Performance | Queries | Reactive | Migraciones | Veredicto WellCore |
|---------|------|-------------|---------|----------|-------------|---------------------|
| **Isar 3.x** | NoSQL | 🏆 Fastest | Dart type-safe | Streams | Manual | Para caché y offline rápido |
| **Drift 2.x** | SQL (SQLite) | Excelente | SQL + Dart fluent | Streams | Auto | **ELEGIR** para métricas históricas |
| **Hive** | Key-Value | Muy buena | Limitado | No | Manual | Simple, para preferencias |
| **SharedPrefs** | Key-Value | Buena | No | No | No | Solo settings simples |

### 7.4 Tabla Comparativa — Charts

| Package | Tipos | Gradientes | Animación | Performance | Licencia | Veredicto WellCore |
|---------|-------|------------|-----------|-------------|----------|---------------------|
| **fl_chart 1.x** | 6 tipos | Sí | Sí | Muy buena | MIT | **YA TIENES** — suficiente |
| **graphic** | Flexible (Grammar) | Sí | Transición | Buena | MIT | Para casos complejos |
| **Syncfusion** | 30+ tipos | Sí | Excelente | Excelente | Comercial $$$ | Excesivo para WellCore |

### 7.5 Tabla Comparativa — Animaciones

| Package | Casos de uso | Integración diseño | Performance | File size | Veredicto |
|---------|--------------|--------------------|-------------|-----------|-----------|
| **flutter_animate** | UI micro-interactions, stagger | Solo código | GPU-based | Zero | **PRINCIPAL** |
| **Rive** | Personajes, iconos animados interactivos | Rive Editor | GPU propio | .riv binario | Para AI Coach avatar |
| **Lottie** | After Effects exportados | AE + plugin | CPU-heavy | JSON grande | Solo para celebraciones |

### 7.6 Packages Nuevos 2024-2025 que Cambian el Juego

```yaml
# Stack recomendado completo para WellCore 2026

dependencies:
  # Core
  flutter_riverpod: ^3.3.1
  riverpod_annotation: ^2.6.1
  go_router: ^17.1.0

  # UI y Animaciones
  flutter_animate: ^4.5.2
  rive: ^0.14.4
  lottie: ^3.3.2
  animations: ^2.1.1

  # Icons
  phosphor_flutter: ^2.1.0

  # Fonts
  google_fonts: ^6.2.1

  # Data visualization
  fl_chart: ^1.1.1
  gauge_indicator: ^0.4.3
  percent_indicator: ^4.2.5

  # Local Storage
  drift: ^2.32.0
  shared_preferences: ^2.3.5

  # Network
  dio: ^5.9.2
  cached_network_image: ^3.4.1

  # UX Premium
  skeletonizer: ^2.1.3
  shimmer: ^3.0.0
  wolt_modal_sheet: ^0.11.0
  smooth_page_indicator: ^1.2.0

  # Layout
  flutter_staggered_grid_view: ^0.7.0
  gap: ^3.0.1

  # Data / Models
  freezed_annotation: ^3.0.0
  json_annotation: ^4.9.0
  flutter_svg: ^2.2.4

  # Shaders
  flutter_shaders: ^0.1.3

dev_dependencies:
  riverpod_generator: ^4.0.3
  build_runner: ^2.4.13
  freezed: ^3.2.5
  json_serializable: ^6.9.3
  riverpod_lint: ^2.6.5
  custom_lint: ^0.7.3
```

---

## SECCIÓN 8: PATRONES ESPECÍFICOS PARA FITNESS APPS

### 8.1 Cómo Whoop Diseña su Interface

Whoop usa un principio central: **un número = un estado emocional**. Su "Strain Score" circular es la jerarquía visual máxima. Para WellCore:

```dart
// Pantalla principal: UN número dominante primero
class WellcoreScoreScreen extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        // 1. Score dominante — mayor del 60% del viewport
        Expanded(
          flex: 3,
          child: Center(
            child: WellcoreStrainGauge(value: 0.78, score: 78),
          ),
        ),
        // 2. Contexto secundario — qué significa el score
        const Expanded(
          flex: 1,
          child: ScoreContextRow(),
        ),
        // 3. Acción primaria
        const WellcoreButton(label: 'Ver detalles'),
      ],
    );
  }
}
```

### 8.2 Progress Ring Animado (Apple Watch Style)

Integrar ActivityRings con animación de entrada:

```dart
class AnimatedActivityRings extends StatelessWidget {
  final double move, exercise, stand;

  const AnimatedActivityRings({
    super.key,
    required this.move,
    required this.exercise,
    required this.stand,
  });

  @override
  Widget build(BuildContext context) {
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: const Duration(milliseconds: 1800),
      curve: Curves.easeOutCubic,
      builder: (context, progress, child) {
        return ActivityRings(
          moveProgress: move * progress,
          exerciseProgress: exercise * progress,
          standProgress: stand * progress,
        );
      },
    );
  }
}
```

### 8.3 Heat Map de Actividad — Estilo GitHub Contributions

```dart
class ActivityHeatMap extends StatelessWidget {
  final Map<DateTime, int> activityData; // fecha → intensidad (0-4)

  const ActivityHeatMap({super.key, required this.activityData});

  @override
  Widget build(BuildContext context) {
    final weeks = _buildWeeks();
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: weeks.map((week) {
          return Column(
            children: week.map((day) {
              final intensity = activityData[day] ?? 0;
              return Container(
                width: 14,
                height: 14,
                margin: const EdgeInsets.all(1.5),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(3),
                  color: _intensityColor(intensity),
                ),
              );
            }).toList(),
          );
        }).toList(),
      ),
    );
  }

  Color _intensityColor(int intensity) {
    const colors = [
      Color(0xFF1A1A2E),    // 0 — sin actividad
      Color(0xFF4D0A0C),    // 1 — baja
      Color(0xFF8B1517),    // 2 — media
      Color(0xFFBF1C1F),    // 3 — alta
      Color(0xFFE31E24),    // 4 — máxima
    ];
    return colors[intensity.clamp(0, 4)];
  }

  List<List<DateTime>> _buildWeeks() {
    final now = DateTime.now();
    final start = now.subtract(const Duration(days: 365));
    final weeks = <List<DateTime>>[];
    var current = start;
    while (current.isBefore(now)) {
      final week = <DateTime>[];
      for (var i = 0; i < 7; i++) {
        week.add(current.add(Duration(days: i)));
      }
      weeks.add(week);
      current = current.add(const Duration(days: 7));
    }
    return weeks;
  }
}
```

### 8.4 Card de Habito Premium — Diseño WellCore

```dart
class HabitCard extends StatelessWidget {
  final Habit habit;
  final VoidCallback onToggle;

  const HabitCard({super.key, required this.habit, required this.onToggle});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onToggle,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeOutCubic,
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          gradient: habit.isCompleted
              ? LinearGradient(
                  colors: [
                    const Color(0xFFE31E24).withOpacity(0.15),
                    const Color(0xFF1A1A2E),
                  ],
                )
              : null,
          color: habit.isCompleted ? null : const Color(0xFF1A1A2E),
          border: Border.all(
            color: habit.isCompleted
                ? const Color(0xFFE31E24).withOpacity(0.4)
                : Colors.white.withOpacity(0.08),
            width: 1,
          ),
        ),
        child: Row(
          children: [
            // Checkbox animado
            AnimatedContainer(
              duration: const Duration(milliseconds: 200),
              width: 28,
              height: 28,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: habit.isCompleted
                    ? const Color(0xFFE31E24)
                    : Colors.transparent,
                border: Border.all(
                  color: habit.isCompleted
                      ? const Color(0xFFE31E24)
                      : Colors.white.withOpacity(0.3),
                  width: 2,
                ),
              ),
              child: habit.isCompleted
                  ? const Icon(Icons.check, color: Colors.white, size: 16)
                  : null,
            ),

            const Gap(14),

            // Info del hábito
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    habit.name,
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                      color: habit.isCompleted
                          ? Colors.white
                          : Colors.white.withOpacity(0.9),
                      decoration: habit.isCompleted
                          ? TextDecoration.lineThrough
                          : null,
                    ),
                  ),
                  if (habit.streakDays > 0)
                    Row(
                      children: [
                        PhosphorIcon(
                          PhosphorIcons.flame(PhosphorIconsStyle.fill),
                          color: const Color(0xFFFF6B35),
                          size: 12,
                        ),
                        const Gap(4),
                        Text(
                          '${habit.streakDays} días seguidos',
                          style: const TextStyle(
                            fontSize: 12,
                            color: Color(0xFFFF6B35),
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                ],
              ),
            ),

            // Sparkline de la semana
            MetricSparkline(
              data: habit.weekHistory,
              color: habit.isCompleted
                  ? const Color(0xFFE31E24)
                  : const Color(0xFF404050),
            ),
          ],
        ),
      ),
    );
  }
}
```

---

## SECCIÓN 9: FLUTTER WEB Y RESPONSIVE

### 9.1 Adaptive Layout para WellCore

```dart
class AdaptiveScaffold extends StatelessWidget {
  final Widget mobileBody;
  final Widget? tabletBody;
  final Widget? desktopBody;

  const AdaptiveScaffold({
    super.key,
    required this.mobileBody,
    this.tabletBody,
    this.desktopBody,
  });

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        if (constraints.maxWidth >= 1200 && desktopBody != null) {
          return desktopBody!;
        } else if (constraints.maxWidth >= 600 && tabletBody != null) {
          return tabletBody!;
        }
        return mobileBody;
      },
    );
  }
}

// Uso en el dashboard
class DashboardPage extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return AdaptiveScaffold(
      mobileBody: const MobileDashboard(),
      tabletBody: const TabletDashboard(),
    );
  }
}

// Layout tablet con NavigationRail + contenido
class TabletDashboard extends StatefulWidget {
  const TabletDashboard({super.key});

  @override
  State<TabletDashboard> createState() => _TabletDashboardState();
}

class _TabletDashboardState extends State<TabletDashboard> {
  int _selectedIndex = 0;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Row(
        children: [
          NavigationRail(
            backgroundColor: const Color(0xFF1A1A2E),
            selectedIndex: _selectedIndex,
            onDestinationSelected: (i) => setState(() => _selectedIndex = i),
            destinations: [
              NavigationRailDestination(
                icon: PhosphorIcon(PhosphorIcons.house()),
                selectedIcon: PhosphorIcon(PhosphorIcons.house(PhosphorIconsStyle.fill)),
                label: const Text('Dashboard'),
              ),
              NavigationRailDestination(
                icon: PhosphorIcon(PhosphorIcons.checkCircle()),
                selectedIcon: PhosphorIcon(PhosphorIcons.checkCircle(PhosphorIconsStyle.fill)),
                label: const Text('Hábitos'),
              ),
              NavigationRailDestination(
                icon: PhosphorIcon(PhosphorIcons.chartLine()),
                selectedIcon: PhosphorIcon(PhosphorIcons.chartLine(PhosphorIconsStyle.fill)),
                label: const Text('Métricas'),
              ),
              NavigationRailDestination(
                icon: PhosphorIcon(PhosphorIcons.users()),
                selectedIcon: PhosphorIcon(PhosphorIcons.users(PhosphorIconsStyle.fill)),
                label: const Text('Comunidad'),
              ),
            ],
          ),
          const VerticalDivider(thickness: 1, width: 1, color: Color(0xFF2A2A3E)),
          Expanded(
            child: [
              const DashboardContent(),
              const HabitsContent(),
              const MetricsContent(),
              const CommunityContent(),
            ][_selectedIndex],
          ),
        ],
      ),
    );
  }
}
```

### 9.2 Frosted Glass Navigation Bar

```dart
class WellcoreFrostedNavBar extends StatelessWidget {
  final int currentIndex;
  final ValueChanged<int> onTap;

  const WellcoreFrostedNavBar({
    super.key,
    required this.currentIndex,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Positioned(
      bottom: 0,
      left: 0,
      right: 0,
      child: ClipRect(
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 30, sigmaY: 30),
          child: Container(
            decoration: BoxDecoration(
              color: const Color(0xFF0D0D1A).withOpacity(0.7),
              border: Border(
                top: BorderSide(
                  color: Colors.white.withOpacity(0.08),
                  width: 1,
                ),
              ),
            ),
            child: SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceAround,
                  children: [
                    _NavItem(
                      icon: PhosphorIcons.house(),
                      activeIcon: PhosphorIcons.house(PhosphorIconsStyle.fill),
                      label: 'Inicio',
                      isSelected: currentIndex == 0,
                      onTap: () => onTap(0),
                    ),
                    _NavItem(
                      icon: PhosphorIcons.checkCircle(),
                      activeIcon: PhosphorIcons.checkCircle(PhosphorIconsStyle.fill),
                      label: 'Hábitos',
                      isSelected: currentIndex == 1,
                      onTap: () => onTap(1),
                    ),
                    _NavItem(
                      icon: PhosphorIcons.chartLine(),
                      activeIcon: PhosphorIcons.chartLine(PhosphorIconsStyle.fill),
                      label: 'Métricas',
                      isSelected: currentIndex == 2,
                      onTap: () => onTap(2),
                    ),
                    _NavItem(
                      icon: PhosphorIcons.users(),
                      activeIcon: PhosphorIcons.users(PhosphorIconsStyle.fill),
                      label: 'Comunidad',
                      isSelected: currentIndex == 3,
                      onTap: () => onTap(3),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _NavItem extends StatelessWidget {
  final PhosphorIconData icon, activeIcon;
  final String label;
  final bool isSelected;
  final VoidCallback onTap;

  const _NavItem({
    required this.icon,
    required this.activeIcon,
    required this.label,
    required this.isSelected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: SizedBox(
        width: 64,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 200),
              child: PhosphorIcon(
                isSelected ? activeIcon : icon,
                key: ValueKey(isSelected),
                color: isSelected
                    ? const Color(0xFFE31E24)
                    : const Color(0xFF606070),
                size: 24,
              ),
            ),
            const Gap(2),
            AnimatedDefaultTextStyle(
              duration: const Duration(milliseconds: 200),
              style: TextStyle(
                fontSize: 10,
                fontWeight: isSelected ? FontWeight.w600 : FontWeight.w400,
                color: isSelected
                    ? const Color(0xFFE31E24)
                    : const Color(0xFF606070),
                letterSpacing: 0.3,
              ),
              child: Text(label),
            ),
          ],
        ),
      ),
    );
  }
}
```

---

## SECCIÓN 10: ROADMAP DE IMPLEMENTACIÓN

### Fase 1 — Máximo Impacto Visual (Semana 1-2)

**Día 1-2: Iconos y Tipografía**
- [ ] Agregar `phosphor_flutter: ^2.1.0`
- [ ] Crear `WcIcons` class centralizada
- [ ] Reemplazar todos los `Icons.xxx` del proyecto con Phosphor equivalentes
- [ ] Implementar `GradientText` para el header del dashboard
- [ ] Instalar `google_fonts` y ajustar tipografía con Outfit

**Día 3-4: Skeleton Loaders**
- [ ] Agregar `skeletonizer: ^2.1.3`
- [ ] Crear `Habit.placeholder()` factory
- [ ] Envolver todas las pantallas con estados de carga en `Skeletonizer`
- [ ] Eliminar todos los `CircularProgressIndicator` de loading states

**Día 5-6: Animaciones de Entrada**
- [ ] Confirmar `flutter_animate: ^4.5.2` en pubspec
- [ ] Implementar stagger en lista de hábitos (`80ms` delay por item)
- [ ] Agregar entrada animada en el dashboard al cargar
- [ ] Micro-interactions en botones (tap scale 0.96)

**Día 7-8: Navigation Bar Frosted**
- [ ] Reemplazar BottomNavigationBar por `WellcoreFrostedNavBar`
- [ ] Agregar BackdropFilter con blur 30px
- [ ] Implementar transición de iconos con AnimatedSwitcher

**Día 9-10: Glass Cards**
- [ ] Crear `GlassCard` y `WellcoreAccentGlass` reutilizables
- [ ] Aplicar glassmorphism a las tarjetas de métricas del dashboard
- [ ] Agregar `gap: ^3.0.1` y reemplazar todos los `SizedBox` de spacing

### Fase 2 — Experiencia Premium (Semana 3-4)

**Semana 3: Charts y Gauges**
- [ ] Agregar `gauge_indicator: ^0.4.3`
- [ ] Implementar `WellcoreStrainGauge` con gradiente de colores
- [ ] Elevar el line chart de peso con gradiente y animación de entrada
- [ ] Implementar `ActivityRings` con painter custom
- [ ] Agregar sparklines en tarjetas de métricas

**Semana 4: Sheets y Transiciones**
- [ ] Agregar `wolt_modal_sheet: ^0.11.0`
- [ ] Migrar el check-in semanal a WoltModalSheet multi-página
- [ ] Agregar `animations: ^2.1.1`
- [ ] Implementar OpenContainer transitions en cards de desafíos
- [ ] Hero animations: lista de challenge → detalle

### Fase 3 — Arquitectura y Performance (Mes 2)

**Sprint 1: Riverpod Generator + Freezed**
- [ ] Instalar `riverpod_generator`, `freezed`, `json_serializable`
- [ ] Migrar modelos existentes a `@freezed`
- [ ] Migrar todos los providers a `@riverpod` con code generation
- [ ] Migrar StateNotifier → AsyncNotifier en todos los controllers
- [ ] Agregar `riverpod_lint` para detectar anti-patterns

**Sprint 2: Bento Dashboard**
- [ ] Agregar `flutter_staggered_grid_view: ^0.7.0`
- [ ] Rediseñar el dashboard principal con layout Bento Grid
- [ ] Implementar `SliverPersistentHeader` custom para la sección de métricas
- [ ] Scroll-driven animations con ScrollAdapter de flutter_animate

**Sprint 3: Shaders Visuales**
- [ ] Agregar `flutter_shaders: ^0.1.3`
- [ ] Crear shader aurora para la pantalla de logros
- [ ] Implementar shader de noise para fondos de pantallas clave
- [ ] Agregar particle system en la pantalla de XP levelup

**Sprint 4: Rive AI Coach**
- [ ] Crear personaje del coach en Rive editor
- [ ] Implementar state machines: idle, celebrando, animando
- [ ] Conectar estado del usuario (score bajo/alto) con la animación del coach
- [ ] Agregar `lottie` para confetti de challenges completados

---

## REFERENCIAS Y RECURSOS

### Documentación Oficial
- **Flutter Docs** — https://docs.flutter.dev
- **Flutter Blog** — https://blog.flutter.dev
- **Flutter Releases** — https://docs.flutter.dev/release/whats-new
- **Fragment Shaders** — https://docs.flutter.dev/ui/advanced/shaders
- **Riverpod Docs** — https://riverpod.dev
- **GoRouter Docs** — https://pub.dev/packages/go_router

### Packages en pub.dev
- flutter_animate → https://pub.dev/packages/flutter_animate
- Riverpod → https://pub.dev/packages/flutter_riverpod
- Phosphor Icons → https://pub.dev/packages/phosphor_flutter
- fl_chart → https://pub.dev/packages/fl_chart
- Skeletonizer → https://pub.dev/packages/skeletonizer
- WoltModalSheet → https://pub.dev/packages/wolt_modal_sheet
- Freezed → https://pub.dev/packages/freezed
- Drift → https://pub.dev/packages/drift
- flutter_staggered_grid_view → https://pub.dev/packages/flutter_staggered_grid_view
- gauge_indicator → https://pub.dev/packages/gauge_indicator
- animations → https://pub.dev/packages/animations

### Recursos de Aprendizaje
- **codewithandrea.com** — Arquitectura Riverpod, AsyncNotifier, testing
- **Very Good Ventures Blog** — https://verygood.ventures/blog
- **Rive Docs** — https://rive.app/docs/
- **The Book of Shaders** — https://thebookofshaders.com (base para GLSL)
- **Shadertoy** — https://www.shadertoy.com (inspiración de shaders)
- **gskinner Flutter Animate** — https://github.com/gskinnerTeam/flutter-animate
- **Flutter Samples** — https://github.com/flutter/samples (incluye simple_shader)
- **Material Motion** — https://m3.material.io/styles/motion/overview

### GitHub Repos de Referencia
- `flutter/samples` — Ejemplos oficiales incluyendo shaders
- `gskinnerTeam/flutter-animate` — Source de flutter_animate con ejemplos
- `simolus3/drift` — Drift ORM para Flutter
- `Milad-Akarie/skeletonizer` — Skeletonizer con ejemplos
- `woltapp/wolt_modal_sheet` — Bottom sheets multi-página

### Herramientas de Diseño
- **Rive Editor** — https://rive.app (animaciones interactivas)
- **LottieFiles** — https://lottiefiles.com (biblioteca de animaciones AE)
- **Phosphor Icons Figma** — https://www.figma.com/@phosphor
- **Shadertoy** — https://www.shadertoy.com (playground GLSL)

---

*Documento generado: Marzo 2026 | WellCore Fitness — Stack: Flutter 3.41, Riverpod 3.x, GoRouter 17.x*

*Esta guía debe revisarse cada trimestre para actualizar versiones de packages y nuevas features de Flutter.*
