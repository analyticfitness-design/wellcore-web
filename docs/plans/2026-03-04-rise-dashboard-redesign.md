# RISE Dashboard Redesign — Design Document

**Date:** March 4, 2026
**Status:** ✅ Approved
**Goal:** Transform RISE dashboard from basic layout to professional, coherent design matching WellCore's design system

---

## Overview

The current `rise-dashboard.html` (~1200 lines) is visually basic and doesn't match the professional design of `cliente.html` (~3600 lines). This redesign applies a **hybrid approach**: adopt the professional architecture of `cliente.html` (sidebar + topbar + navigation) while customizing it specifically for RISE's 30-day challenge experience.

---

## Architecture

### Layout Structure
```
┌─────────────────────────────────────────────────────────┐
│ TOPBAR (sticky, 60px): Logo | Search | User Profile     │
├──────────────┬──────────────────────────────────────────┤
│              │                                          │
│  SIDEBAR     │        MAIN CONTENT AREA                │
│  (260px)     │        (Responsive, scales with screen) │
│  - Navigation│                                          │
│  - Countdown │        Cards, Sections, Tracking        │
│              │                                          │
└──────────────┴──────────────────────────────────────────┘
```

**Key Properties:**
- Sidebar: Fixed position, left: 0, width: 260px, scrollable
- Topbar: Sticky position, z-index: 100, semi-transparent background
- Content: Max-width 1200px, centered, responsive grid
- All components use WellCore CSS variables (`--red`, `--surface`, `--card`, etc.)

---

## Sidebar & Navigation

### Structure
Primary navigation items:
1. **Dashboard** — Landing view with countdown + summary
2. **Mi Programa RISE** (expandable)
   - Entrenamiento — Training program, downloads
   - Nutrición — Nutrition guide, macros
   - Hábitos — 30-day habit tracker
3. **Tracking Diario** — Daily checklist and adherence
4. **Mediciones** — Weight, measurements, historical charts
5. **Fotos Progreso** — Before/after gallery by angle
6. **Comunidad** — Social feed for RISE participants
7. **Soporte** — Coach contact, FAQ, support tickets

### Sidebar Countdown Widget
Placed at bottom of sidebar, always visible:
- Large countdown number: "15 días" (in red `--red`)
- Progress bar: fills left-to-right, red to gray gradient
- Text: "Finaliza el 31 de Marzo"
- Size: ~150px height, full sidebar width

### Styling
- Icons: Font Awesome 6.4 (CDN)
- Active item: White text + red left border (2px) + red background (8% opacity)
- Hover: White text + red left border (30% opacity) + bg (4% opacity)
- Sub-items: Indented padding-left, smaller font
- Transitions: 0.1s linear on all interactive elements

---

## Topbar

### Components

**Left Section:**
- WellCore logo (Bebas Neue font, red accent)
- Appears/disappears on responsive behavior

**Center Section:**
- Search input with Font Awesome magnifying glass icon
- Placeholder: "Buscar en tu programa..."
- Searches: measurements, notes, photos

**Right Section:**
- User avatar (small circle, 40x40px)
- Username and dropdown menu
- Menu options: Ver Perfil, Configuración, Logout
- Separator line

### Styling
- Background: `rgba(17,17,19,0.95)` (semi-transparent with backdrop blur)
- Height: 60px, fixed sticky positioning
- Border-bottom: 1px solid `--border`
- Flex layout with space-between distribution

---

## Main Content Sections

### Dashboard (Primary View)
- **Hero Section:** Countdown display (large, 80px font, red accent)
  - Days remaining: "15 de 30"
  - Progress bar (full width, animated fill)
  - Date: "Finaliza el 31 de Marzo"

- **Summary Grid (2x2 on desktop, 1 column mobile):**
  1. Mi Programa — Latest program section info
  2. Últimas Mediciones — Most recent measurements
  3. Fotos Recientes — 3 latest photos thumbnail
  4. Próximos Hábitos — Next 3 habits to complete

- **Quick Action Buttons:**
  - "Registrar Medición"
  - "Subir Foto"
  - "Marcar Hábitos"

### Mi Programa Sections
- **Entrenamiento:** PDF/video player, weekly routine breakdown
- **Nutrición:** Macro guide, suggested recipes, hydration tracker
- **Hábitos:** 30-day checklist with checkboxes, completion %

### Tracking Diario
- Daily checklist: Training ✓ | Breakfast ✓ | Water ✓ | Sleep ✓ | Photo ✓
- Weekly calendar view
- Adherence % (large display, color-coded)

### Mediciones
- Historical data table (sortable)
- Chart: Weight/measurements over time
- Input form to add new measurements

### Fotos Progreso
- Grid layout by angle: Frente | Perfil | Espalda
- Before/after slider
- Date labels

### Comunidad
- Social feed of RISE participants' posts
- Comment/like functionality
- "Compartir mi progreso" button

### Soporte
- Coach contact form
- FAQ accordion
- Ticket submission

---

## Visual Components

### Cards
- Background: `var(--card)` (#18181b)
- Border: 1px solid `var(--border)`
- Border-radius: 0px (square, modern minimalist)
- Padding: 24px
- Shadow: `0 1px 3px rgba(0,0,0,0.1)`
- Hover: Border color transitions to red (`--red`)

### Countdown Widget
- Number: 48px font, red, bold
- Progress bar: 8px height, red-to-gray gradient fill
- Text: 12px gray, monospace font

### Progress Circles
- 5 circles for daily tracking (Training, Breakfast, Water, Sleep, Photo)
- Completed: green (`--green`)
- Incomplete: gray (`--gray-dim`)
- Size: 48px diameter
- Tooltip on hover

### Badges
- "Día 15 de 30": Red background, white text, monospace font, 12px
- "Adherencia 85%": Green background
- "Meta alcanzada ✓": Green with checkmark icon

### Buttons
- **Primary:** Background `--red`, white text, no border
- **Secondary:** Border `--red`, text `--red`, transparent background
- **Hover:** All darken to `--red-dark` (#B8181D)
- **Disabled:** Opacity 0.5, cursor not-allowed
- Padding: 12px 20px, 0px border-radius (square)
- Font: 13px, weight 600, font-family Inter

---

## Responsive Behavior

### Desktop (>1024px)
- Sidebar always visible, 260px
- Content takes remaining width
- Grid layouts: 2-3 columns

### Tablet (768px - 1024px)
- Sidebar reduces to 200px
- Grid layouts: 2 columns
- Hamburger menu appears in topbar

### Mobile (<768px)
- Sidebar hidden by default, shows as drawer on hamburger click
- Drawer: 70% width, overlay with semi-transparent background
- Content: Full width, single column
- Countdown widget in topbar as small badge ("15d")
- All cards: 1 column layout
- Buttons: Full width on forms

### Behavior on Mobile
- Click hamburger → Sidebar slides in from left with overlay
- Click overlay → Sidebar closes
- Scroll within sidebar if needed
- Topbar always visible

---

## Color Scheme & Branding

**CSS Variables Used:**
- `--bg`: #0a0a0a (main background)
- `--surface`: #111113 (primary surface)
- `--surface-2`: #1a1a1d (secondary surface)
- `--card`: #18181b (card backgrounds)
- `--border`: rgba(255,255,255,0.06) (borders)
- `--red`: #E31E24 (RISE primary accent)
- `--red-dark`: #B8181D (hover state)
- `--red-dim`: rgba(227,30,36,0.10) (subtle backgrounds)
- `--white`: #ffffff (text)
- `--gray`: rgba(255,255,255,0.45) (secondary text)
- `--green`: #22C55E (completion, success)

**RISE-Specific Theming:**
- Countdown and progress indicators use `--red` prominently
- Completion states use `--green`
- All primary CTAs use `--red`
- Cards maintain `--surface` / `--card` for cohesion

---

## Typography

- **Display/Headers:** Bebas Neue (letter-spacing: 0.12em)
- **Body Text:** Inter (weight: 400, 500, 600, 700)
- **Monospace/Labels:** JetBrains Mono (technical labels, stats)
- **Base Font Size:** 13px for small text, 14-16px for body, 18-32px for headers

---

## Interactions & Animations

- **Transitions:** All 0.1s linear (matches `cliente.html`)
- **Hover Effects:** Border color change, background opacity, text color
- **Animations:**
  - Countdown progress bar: Fill animation on load
  - Sidebar drawer: Slide in from left (0.3s ease-out)
  - Cards: Subtle shadow increase on hover
  - Buttons: Color transition on hover
- **Scroll:** Smooth scroll-behavior enabled

---

## Technical Specifications

**File:** `rise-dashboard.html`
**Dependencies:**
- Font Awesome 6.4 (CDN): https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css
- Google Fonts (Bebas Neue, Inter, JetBrains Mono)
- localStorage API (for daily tracking, measurements, photos)

**Structure:**
- Single HTML file with embedded CSS and JavaScript
- CSS reset, variables, flexbox layouts
- localStorage for data persistence
- Responsive media queries for mobile/tablet/desktop

---

## Success Criteria

✅ Sidebar navigation with Font Awesome icons
✅ Countdown widget always visible in sidebar
✅ Professional card-based layout matching `cliente.html`
✅ Responsive design: desktop, tablet, mobile
✅ Color scheme coherent with WellCore platform
✅ All RISE features preserved and enhanced
✅ Smooth animations and transitions
✅ localStorage integration working for tracking
✅ Mobile drawer navigation fully functional

---

## Next Steps

1. Create implementation plan using `writing-plans` skill
2. Execute implementation with proper task breakdown
3. Test responsive behavior across devices
4. Validate color contrast and accessibility
5. Performance optimization (CSS minification, image optimization)
6. Commit and merge to feature branch

---

**Approved By:** User (March 4, 2026)
**Ready for Implementation:** YES ✅
