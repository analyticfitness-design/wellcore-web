# WellCore v2 — 10 Features Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Upgrade the WellCore platform with payment integration fixes, engagement systems, social login, AI nutrition improvements, interactive community, and UI fixes across dashboards.

**Architecture:** PHP backend (Laravel Herd / vanilla PHP), MySQL DB, vanilla JS frontend. No framework — direct DOM manipulation. Wompi payment gateway (Colombian). Formspree for external forms. Claude API (Haiku) for AI nutrition. Google Identity Services for OAuth.

**Tech Stack:** PHP 8.x, MySQL 8, vanilla JS/HTML/CSS, Wompi API, Formspree, Google Identity Services, Claude API (Haiku)

**Security Note:** All dynamic HTML rendering MUST sanitize user-generated content using `textContent` for plain text or DOMPurify for HTML. The code examples in this plan use template literals for brevity — implementation MUST add proper escaping/sanitization.

---

## Execution Order and Dependencies

```
Phase 1 — Quick Wins (no dependencies)
  Task 1: Corregir precio RISE $33 -> $27
  Task 2: Fix chatbot mobile position

Phase 2 — Data Foundation
  Task 3: Pagos Wompi -> Admin Panel (DB migration + API + UI)

Phase 3 — Dashboard Improvements
  Task 4: Perfil en rise-dashboard.html
  Task 5: Medidas cliente.html estilo RISE
  Task 6: IA Nutricion — review + mejoras + limites

Phase 4 — Auth and Engagement
  Task 7: Login Social (Google)
  Task 8: Sistema Renovacion + Formspree (dia 27)
  Task 9: 2 Correos Engagement mid-cycle

Phase 5 — Community (biggest feature)
  Task 10: Comunidad Interactiva + Logros
```

---

## Task 1: Corregir Precio RISE $33 -> $27 USD

**Files:**
- Modify: `admin.html:1308` (KPI footer)
- Modify: `admin.html:1485` (precio tabla)
- Modify: `js/rise-modal.js` (precio modal)
- Modify: `rise-enroll.html` (option value text)
- Modify: `api/emails/templates.php` (email template)
- Verify: `index.html`, `rise.html`, `rise-payment.html` (COP price stays $99.900)
- Verify: `api/wompi/config.php` — COP amount stays if $99.900 COP is approx $27 USD

**Step 1: Replace all $33 USD mentions**

1. `admin.html:1308` — Change `$33 USD por inscrito` to `$27 USD por inscrito`
2. `admin.html:1485` — Change `$33 USD` to `$27 USD`
3. `js/rise-modal.js` — Change `$33 USD` to `$27 USD` in `.rise-modal-price-value`
4. `rise-enroll.html` — Change `RISE 30 Dias - $33 USD` to `RISE 30 Dias - $27 USD`
5. `api/emails/templates.php` — Change `'usd' => '~$33 USD'` to `'usd' => '~$27 USD'`

**Step 2: Verify COP prices stay unchanged**

The COP price $99.900 remains the same — only the USD equivalent label changes. These files keep COP as-is:
- `index.html` — `$99.900`
- `rise.html` — `$99.900`
- `rise-payment.html` — `$99.900`
- `admin.html:1487` — `$99.900 COP`
- `api/wompi/config.php` — `'amount_cop' => 99900`

**Step 3: Full-text search verification**

```bash
grep -rn "\\$33" --include="*.html" --include="*.js" --include="*.php" .
```

Expected: Zero results after changes.

**Step 4: Commit**

```bash
git add -A && git commit -m "fix: RISE price $33 -> $27 USD across all pages"
```

---

## Task 2: Fix Chatbot Mobile Position

**Files:**
- Modify: `js/chat-widget.js` — CSS injection for the floating button

**Step 1: Identify the chat widget button CSS**

The chat widget is in `js/chat-widget.js`. It creates a floating button positioned bottom-right. The issue: it overlaps the bottom nav bar on mobile (the red chat icon covers the last nav item).

**Step 2: Add mobile media query**

In `js/chat-widget.js`, find the CSS that positions the floating chat button. Add or modify the positioning so that on mobile (`max-width: 768px`), the button sits above the bottom nav:

```css
@media (max-width: 768px) {
  #wc-chat-toggle {
    bottom: 80px !important; /* above bottom nav (approx 60px height + 20px gap) */
  }
  #wc-chat-panel {
    bottom: 140px !important; /* panel sits above toggle */
  }
}
```

If the widget injects styles via JS, find the element creation code and inject a `<style>` block with the media query.

**Step 3: Test on mobile viewport**

Open `cliente.html` in Chrome DevTools mobile view (375x812). Verify:
- Chat icon does NOT overlap bottom nav bar
- Chat panel opens correctly above the icon
- All bottom nav icons are tappable
- Check both `cliente.html` and `rise-dashboard.html`

**Step 4: Commit**

```bash
git add js/chat-widget.js && git commit -m "fix: chatbot position above bottom nav on mobile"
```

---

## Task 3: Pagos Wompi -> Admin Panel

**Files:**
- Modify: `api/setup/schema.sql:137-155` — Update payments table for Wompi fields
- Create: `api/admin/payments.php` — API endpoint to list payments + KPIs
- Modify: `api/wompi/webhook.php` — Also insert into MySQL payments table on approved
- Modify: `admin.html:2029` — Replace empty DEMO_TRANSACTIONS with API call
- Modify: `admin.html:2383-2397` — Update renderPagos() to use real data
- Modify: `admin.html:1534-1548` — Dynamic KPI calculation from API

### Step 1: Update payments table schema

Run this migration SQL (also update schema.sql for fresh installs):

```sql
ALTER TABLE payments
  ADD COLUMN wompi_reference VARCHAR(100) AFTER payu_response,
  ADD COLUMN wompi_transaction_id VARCHAR(100) AFTER wompi_reference,
  ADD COLUMN payment_method VARCHAR(50) AFTER wompi_transaction_id,
  MODIFY COLUMN plan ENUM('esencial','metodo','elite','rise') NOT NULL,
  MODIFY COLUMN currency VARCHAR(10) DEFAULT 'COP',
  ADD UNIQUE INDEX idx_wompi_ref (wompi_reference);
```

### Step 2: Create GET /api/admin/payments.php

New endpoint that queries the payments table and calculates KPIs:
- List all payments ordered by date DESC (limit 500)
- JOIN with clients table for client name
- Calculate MRR (sum of approved payments this month)
- Calculate ARR (MRR x 12)
- Count active clients and confirmed payments
- Requires admin auth token

### Step 3: Update webhook.php to insert into MySQL payments table

In `api/wompi/webhook.php`, after the client creation/activation block (around line 235), add an INSERT into the payments table using ON DUPLICATE KEY UPDATE to handle re-delivery of webhooks:

Fields to map:
- `client_id` from the created/found client
- `email` from buyer_email
- `wompi_reference` from reference_code
- `wompi_transaction_id` from Wompi transaction ID
- `plan` from parsed plan name
- `amount` in COP (divide amount_in_cents by 100)
- `status` mapped status (approved/pending/declined)
- `buyer_name`, `buyer_phone` from Wompi payload
- `payment_method` from payment_method_type

### Step 4: Update admin.html — replace demo data with API call

Create a `loadPagos()` async function that:
1. Calls `api.get('/admin/payments')`
2. Updates KPI elements: `pagos-mrr`, `pagos-arr`, `pagos-total`, `pagos-mrr-sub`, `pagos-total-sub`
3. Renders the payments table body (`pagosTable`) with real data
4. Uses `textContent` for user-provided data (client names, emails) to prevent XSS
5. Format amounts with `Intl.NumberFormat` for COP currency

Wire `loadPagos()` to be called when the Pagos section becomes active.

### Step 5: Test end-to-end

1. Login as admin (`daniel.esparza` / `RISE2026Admin!SuperPower`)
2. Navigate to Pagos section
3. Verify KPIs show real calculated data
4. Verify transactions table shows real payments from Wompi
5. Check that cesarjoseluna's payment appears
6. Verify pagination/scrolling with multiple records

### Step 6: Commit

```bash
git add -A && git commit -m "feat: connect Wompi payments to admin Pagos panel with real data and KPIs"
```

---

## Task 4: Perfil en rise-dashboard.html

**Files:**
- Modify: `rise-dashboard.html` — Add sidebar nav item + profile section HTML + JS
- Reference: `cliente.html:2498-2600` — Profile section to replicate

### Step 1: Add sidebar nav item for Mi Perfil

In rise-dashboard sidebar nav (after the Support/Tickets section), add:

```html
<a class="nav-item" data-section="profile">
    <i class="fas fa-user"></i> Mi Perfil
</a>
```

### Step 2: Add profile section HTML

Add a new section after the last `</section>` in rise-dashboard.html matching the cliente.html design:

**Content blocks:**
1. **Profile Header Card** — Avatar with initial, name, email, "Plan Activo: RISE 30 Dias" badge, red left border
2. **Personal Info Form** — 2-column grid: nombre, email (readonly), WhatsApp, edad, objetivo (select), ciudad. Red "GUARDAR CAMBIOS" button
3. **Plan Comparison Card** — "NUESTROS PLANES" title + description encouraging upgrade. 3-column grid showing Esencial/Metodo/Elite with features checklist. Elite card with red border highlight. "MEJORAR MI PLAN" button linking to inscripcion.html
4. **Cerrar Sesion Card** — Description + red outline button

### Step 3: Add JS to populate profile from user data

Use the existing RISE auth data (from `/api/auth/me.php` or stored user object) to fill:
- Avatar initial from name
- Name, email, plan label
- Form fields from client_profiles table

### Step 4: Add saveRiseProfile() function

POST updated fields to `/api/client/profile.php` (or equivalent existing endpoint). Show success/error feedback.

### Step 5: Test

1. Login as RISE client
2. Navigate to Mi Perfil in sidebar
3. Verify data loads correctly from DB
4. Edit a field, save, reload — verify persistence
5. Test on mobile viewport (single column layout)
6. Click "MEJORAR MI PLAN" — verify redirects to inscripcion.html

### Step 6: Commit

```bash
git add -A && git commit -m "feat: add Mi Perfil section to rise-dashboard.html"
```

---

## Task 5: Medidas cliente.html — Estilo RISE

**Files:**
- Read: `rise-dashboard.html:1256-1441` — Measurements section (reference)
- Modify: `cliente.html` — Seguimiento section (`sec-seguimiento`, line 1853)

### Step 1: Study RISE measurements implementation

Read `rise-dashboard.html` measurements section to understand:
- Visual measurement guide (how to measure instructions)
- Progress bars with target vs current values
- Historical table with change indicators (arrows up/down, color-coded)
- Chart component for evolution

### Step 2: Redesign cliente.html seguimiento section

Replace the current basic form (just 3 inputs + button) with RISE-style:

1. **Measurement Guide Card** — Brief instructions on how to take measurements correctly
2. **Data Entry Form** — Same fields (peso, %musculo, %grasa) but with better layout
3. **Progress Bars** — Show current vs target for each metric with visual bars
4. **Historical Timeline** — Table with date, values, and colored change indicators (green arrow up for muscle, red arrow down for fat, etc.)
5. **Evolution Chart** — Improve the existing "Evolucion de Peso Corporal" chart to show all 3 metrics

### Step 3: Ensure API compatibility

Both dashboards should use the same measurement API endpoints. Verify data model is consistent.

### Step 4: Test

1. Login as Elite client
2. Navigate to Seguimiento/Medidas
3. Register new measurements — verify they save
4. Verify progress bars update with new data
5. Verify history table shows changes with colored indicators
6. Test mobile view (single column, cards stack)

### Step 5: Commit

```bash
git add -A && git commit -m "feat: upgrade medidas in cliente.html to RISE-style design"
```

---

## Task 6: IA Nutricion — Review + Mejoras + Limites

**Files:**
- Read: `cliente.html:2407-2495` — Nutricion section HTML
- Read: `cliente.html:5096-5189` — submitNutriAnalysis() JS function
- Read/Modify: `api/nutrition/analyze.php` — AI analysis endpoint
- Create: `api/nutrition/rate-limit.php` — Rate limiting per plan
- Create table: `nutrition_logs` — Track usage per client per day
- Modify: `cliente.html` — Improve Nutricion section UI

### Step 1: Verify Claude API connection

Read `api/nutrition/analyze.php` and confirm:
- Uses Claude API with Haiku model
- Correct API key is configured
- Request/response format is working

### Step 2: Create nutrition_logs table

```sql
CREATE TABLE IF NOT EXISTS nutrition_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id   INT UNSIGNED NOT NULL,
    meal_type   VARCHAR(20),
    description TEXT,
    analysis    JSON,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_client_date (client_id, created_at)
);
```

### Step 3: Create rate-limit.php

Rate limiting logic by plan:
- **Elite**: 10 analyses per day
- **Metodo**: 3 analyses per day
- **Esencial**: 0 (blocked — show upgrade prompt)
- **RISE**: 0 (blocked)

Query `nutrition_logs` for today's count by client_id. Return `{allowed, used, limit, remaining}`.

### Step 4: Update analyze.php

Before calling Claude API:
1. Check rate limit — return 429 with upgrade message if blocked
2. After successful analysis, INSERT into `nutrition_logs`
3. Return `remaining` count in the response JSON

### Step 5: Improve Nutricion section UI

In `cliente.html`:
- Add usage indicator near ANALIZAR button: "3/10 analisis usados hoy"
- For Esencial users: show locked overlay with upgrade CTA
- Improve analysis result display: visual macro breakdown with progress bars (not just text)
- Better history section: cards with date, meal type, macro summary instead of flat table
- Consistent dark/red design matching rest of dashboard

### Step 6: Test

1. Login as Elite client -> verify "0/10 analisis usados" shows
2. Submit analysis -> verify counter becomes "1/10"
3. Login as Metodo client -> verify "0/3" limit
4. Login as Esencial -> verify blocked with upgrade prompt
5. Verify Claude API returns proper nutritional analysis
6. Check mobile layout

### Step 7: Commit

```bash
git add -A && git commit -m "feat: nutrition AI with rate limits by plan + improved UI"
```

---

## Task 7: Login Social (Google)

**Files:**
- Modify: `login.html:519-540` — Add Google button + visual separator
- Create: `api/auth/google.php` — Backend Google token verification
- Modify: `api/setup/schema.sql` — Add google_id column to clients table

### Step 1: Add google_id column to clients

```sql
ALTER TABLE clients ADD COLUMN google_id VARCHAR(255) AFTER email;
ALTER TABLE clients ADD INDEX idx_google_id (google_id);
```

### Step 2: Add Google Sign-In button to login.html

After the login form submit button and before the help links, add:
- Visual separator: horizontal line with "O" text in the middle
- Google button: white/light button with Google "G" SVG icon + "Continuar con Google" text
- Style to match the dark theme of login.html

### Step 3: Add Google Identity Services (GIS) script

Load `https://accounts.google.com/gsi/client` and implement:
- `loginWithGoogle()` — initializes GIS with client_id, triggers prompt
- `handleGoogleResponse(response)` — sends credential JWT to backend, handles redirect

### Step 4: Create api/auth/google.php

Backend flow:
1. Receive `{credential}` JWT from frontend POST
2. Verify token with Google (use `https://oauth2.googleapis.com/tokeninfo?id_token=TOKEN`)
3. Extract: google_id (sub), email, name
4. Check DB: find client by google_id OR email
5. If found: update google_id if needed, login
6. If not found: auto-create client (plan='esencial', status='activo')
7. Generate auth_token (24h expiry) in auth_tokens table
8. Return `{ok, token, user, redirect: 'cliente.html'}`

### Step 5: Google Cloud Console setup (manual)

Developer must:
1. Go to https://console.cloud.google.com/
2. Create OAuth 2.0 Client ID (Web application)
3. Add authorized JavaScript origins: `https://wellcorefitness.com`, `https://wellcorefitness.test`
4. Copy Client ID into login.html config and optionally api/.env

### Step 6: Test

1. Click "Continuar con Google" on login.html
2. Google popup appears, select account
3. If email exists in DB -> login and redirect to cliente.html
4. If new email -> auto-create account -> redirect to cliente.html
5. Verify token stored in localStorage and auth works across pages
6. Test on mobile

### Step 7: Commit

```bash
git add -A && git commit -m "feat: Google social login on login.html"
```

---

## Task 8: Sistema Renovacion + Formspree (dia 27)

**Files:**
- Create: `api/cron/renewal-reminder.php` — Cron job runs daily
- Create: `api/client/renewal-status.php` — Check if client is in renewal window
- Modify: `cliente.html` — Show renewal prompt when 3 days before expiry
- Modify: `rise-dashboard.html` — Show RISE-specific upgrade prompt
- Modify: `api/setup/schema.sql` — Add subscription tracking fields

### Step 1: Add subscription tracking

Ensure clients table has:
```sql
ALTER TABLE clients
  ADD COLUMN subscription_start DATE AFTER plan,
  ADD COLUMN subscription_end DATE AFTER subscription_start,
  ADD COLUMN renewal_reminder_sent TINYINT(1) DEFAULT 0 AFTER subscription_end;
```

### Step 2: Create Formspree forms (manual setup)

On formspree.io, create 2 forms:

**Form A — Regular Plans (Esencial/Metodo/Elite):**
- Satisfaction rating (1-10)
- What did you like most?
- What to improve next month?
- Renewal choice: Renew same / Upgrade plan / Cancel
- If upgrade: which plan?
- Final check-in: weight, measurements
- Upload final progress photo

**Form B — RISE Challenge:**
- How was your RISE experience?
- Results obtained
- Want to continue with a personalized plan?
- Plan of interest (Esencial/Metodo/Elite)
- Final check-in data

### Step 3: Create renewal-reminder.php cron

Daily script:
1. Query clients WHERE `subscription_end <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)` AND `renewal_reminder_sent = 0`
2. For each client: send email with personalized Formspree form link
3. Set `renewal_reminder_sent = 1`
4. Differentiate RISE clients (send Form B) vs regular (send Form A)

### Step 4: Create GET /api/client/renewal-status.php

Returns: `{show_form: bool, days_remaining: int, form_url: string, plan: string}`
- Called by frontend on dashboard load
- Returns true if client is within 3-day renewal window

### Step 5: Frontend — renewal prompt in cliente.html

On dashboard load, call renewal-status endpoint. If `show_form` is true:
- Show prominent banner/modal: "Tu plan vence en X dias"
- Button: "Completar formulario de renovacion" (links to Formspree)
- Button: "Subir ultimo check-in del mes"
- Style: red accent, attention-grabbing but not blocking

### Step 6: Frontend — RISE upgrade prompt in rise-dashboard.html

When RISE client is in renewal window:
- Show special card: "Tu reto RISE esta por terminar!"
- Present 3 plans (Esencial/Metodo/Elite) with comparison table
- Explain: "Estos planes tienen una interfaz diferente que llevara tu proceso a otro nivel"
- Formspree form link (Form B)
- CTA: "Unirme a un plan" linking to inscripcion.html

### Step 7: Set up cron

```bash
# Runs daily at 8am
0 8 * * * php /path/to/wellcorefitness/api/cron/renewal-reminder.php
```

### Step 8: Test

1. Set test client subscription_end to 3 days from now
2. Run renewal-reminder.php manually -> verify email sent
3. Login as that client -> verify dashboard shows renewal banner
4. Click form link -> verify Formspree form opens with correct fields
5. Submit form -> verify data arrives in Formspree dashboard
6. Test RISE client variant

### Step 9: Commit

```bash
git add -A && git commit -m "feat: renewal reminder system with Formspree forms (day 27)"
```

---

## Task 9: 2 Correos Engagement Mid-Cycle

**Files:**
- Create: `api/cron/engagement-emails.php` — Cron job (daily)
- Create: `api/emails/engagement-templates.php` — HTML email templates
- Create table: `engagement_emails` — Track sent emails to avoid duplicates

### Step 1: Create engagement_emails table

```sql
CREATE TABLE IF NOT EXISTS engagement_emails (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id   INT UNSIGNED NOT NULL,
    email_type  ENUM('day10_motivation', 'day20_value') NOT NULL,
    cycle_month DATE NOT NULL,
    sent_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_email (client_id, email_type, cycle_month),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);
```

### Step 2: Create engagement email templates

**Email 1 (Day 10) — "Tu Progreso Importa":**
- Personalized greeting with client name
- Motivational message related to their goal
- 2-3 actionable tips specific to day 10 of their program
- Reminder: "Nuestro equipo esta disponible para cualquier apoyo que necesites"
- CTA button: "Accede a tu dashboard"

**Email 2 (Day 20) — "Sigue Asi, Estamos Contigo":**
- Progress acknowledgment
- Value-add tips for the final stretch
- Resource recommendation from Biblioteca
- Reminder about upcoming monthly check-in
- CTA: "Necesitas apoyo? Escribenos" with WhatsApp link

Both emails: branded WellCore design, dark theme, personalized by plan type.

### Step 3: Create engagement-emails.php cron

Daily script logic:
1. Query all active clients with subscription_start
2. Calculate `day_in_cycle = DATEDIFF(CURDATE(), subscription_start) % 30`
3. If day_in_cycle = 10: check engagement_emails for duplicate, send Email 1
4. If day_in_cycle = 20: check engagement_emails for duplicate, send Email 2
5. Log each sent email to engagement_emails table
6. Personalize content based on client plan and goal

### Step 4: Sending mechanism

Use the same email infrastructure as existing notifications (check `api/includes/notify-admin.php` for the mail function pattern). Apply the HTML templates from step 2.

### Step 5: Cron setup

```bash
# Runs daily at 9am (1 hour after renewal cron)
0 9 * * * php /path/to/wellcorefitness/api/cron/engagement-emails.php
```

### Step 6: Test

1. Set test client start date to 10 days ago -> run cron -> verify Email 1 received
2. Set to 20 days ago -> run cron -> verify Email 2 received
3. Run cron again -> verify NO duplicate emails sent
4. Check email content is personalized (name, plan, goal)
5. Verify links in email work (dashboard, WhatsApp)

### Step 7: Commit

```bash
git add -A && git commit -m "feat: 2 engagement emails at day 10 and day 20 of each cycle"
```

---

## Task 10: Comunidad Interactiva + Logros

**This is the largest feature. Broken into sub-tasks.**

**Files:**
- Create: `api/setup/community-schema.sql` — 3 new tables
- Create: `api/community/posts.php` — CRUD posts endpoint
- Create: `api/community/reactions.php` — Toggle reactions endpoint
- Create: `api/community/achievements.php` — List achievements endpoint
- Create: `api/community/check-achievements.php` — Trigger achievement checks
- Create: `js/community.js` — Shared community JS module
- Modify: `rise-dashboard.html:1628-1672` — Upgrade existing community section
- Modify: `cliente.html` — Add new community section + sidebar/nav item

### Sub-task 10.1: Database Schema

```sql
CREATE TABLE IF NOT EXISTS community_posts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id       INT UNSIGNED NOT NULL,
    content         TEXT NOT NULL,
    post_type       ENUM('text','achievement','workout','milestone') DEFAULT 'text',
    achievement_id  INT UNSIGNED NULL,
    parent_id       INT UNSIGNED NULL,
    audience        ENUM('all','rise') DEFAULT 'all',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES community_posts(id) ON DELETE CASCADE,
    INDEX idx_audience_date (audience, created_at DESC)
);

CREATE TABLE IF NOT EXISTS community_reactions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id     INT UNSIGNED NOT NULL,
    client_id   INT UNSIGNED NOT NULL,
    emoji       VARCHAR(10) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reaction (post_id, client_id, emoji),
    FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS achievements (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id       INT UNSIGNED NOT NULL,
    achievement_type VARCHAR(50) NOT NULL,
    title           VARCHAR(100) NOT NULL,
    description     VARCHAR(255),
    icon            VARCHAR(20) DEFAULT 'trophy',
    earned_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_achievement (client_id, achievement_type),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);
```

### Sub-task 10.2: Achievement Definitions

**Universal achievements (all clients):**

| Type | Title | Trigger |
|------|-------|---------|
| `first_week` | Primera Semana | 7 days since subscription_start |
| `first_checkin` | Primer Check-in | First checkin submitted |
| `first_photo` | Primera Foto | First progress photo uploaded |
| `30_days` | 30 Dias Activo | 30 days since start |
| `90_days` | 3 Meses Fuerte | 90 days active |
| `streak_7` | Racha Imparable | 7 consecutive daily check-ins |

**RISE-specific:**

| Type | Title | Trigger |
|------|-------|---------|
| `rise_day7` | RISE Dia 7 | Day 7 of challenge |
| `rise_day15` | RISE Medio Camino | Day 15 |
| `rise_day30` | RISE Completado | Day 30 (challenge finished) |
| `rise_first_measurement` | Primera Medicion RISE | First measurement logged |

**Elite-specific:**

| Type | Title | Trigger |
|------|-------|---------|
| `elite_nutrition_streak` | Nutricion al Dia | 7 days logging nutrition |
| `elite_pr` | Marca Personal | Manual trigger by coach |

### Sub-task 10.3: API Endpoints

**GET /api/community/posts.php?audience=all&page=1&limit=20**
- Returns paginated posts with: author name/initial, reactions grouped by emoji with count, reply count, relative timestamp
- Filter by audience ('all' for regular clients, 'rise' for RISE-only)
- Include replies as nested array (max 3 shown, "ver mas" for rest)

**POST /api/community/posts.php**
- Body: `{content, post_type, parent_id, audience}`
- Sanitize content server-side (strip HTML tags, max 500 chars)
- Returns created post object

**POST /api/community/reactions.php**
- Body: `{post_id, emoji}`
- Toggle: add if not exists, remove if exists
- Allowed emojis: `fire`, `muscle`, `clap`, `heart` (validate server-side)

**GET /api/community/achievements.php?client_id=X**
- Returns earned achievements + locked achievements with progress hints

**POST /api/community/check-achievements.php**
- Called after key actions (checkin, photo upload, measurement)
- Checks all applicable achievements for the client
- Awards new ones + auto-creates community post for each

### Sub-task 10.4: Shared JS Module (js/community.js)

Create a `Community` object/module with methods:
- `init(containerId, audience)` — set up and load initial posts
- `loadPosts(page)` — fetch and render paginated posts
- `renderPost(post)` — create DOM element for a single post with: avatar, name, day badge, time, content, reaction bar, reply button
- `submitPost(content)` — POST new message
- `toggleReaction(postId, emoji)` — toggle reaction with optimistic UI update
- `loadReplies(postId)` — fetch and show thread
- `renderAchievementPost(achievement)` — special styled post for auto-achievements

**All user-generated content MUST use textContent (not HTML injection) to prevent XSS.**

### Sub-task 10.5: UI Design Spec

**Post card layout:**
```
+--------------------------------------------------+
| [Avatar] Name              Day Badge  ·  Time     |
|                                                    |
| Post content text here...                          |
|                                                    |
| [fire 3] [muscle 5] [clap 2] [heart 1]   Reply 2 |
+--------------------------------------------------+
```

**Achievement auto-post (special styling):**
```
+--------------------------------------------------+
| [Trophy]  LOGRO DESBLOQUEADO                       |
| Name obtuvo: "Primera Semana Completa"             |
| 7 dias consecutivos en el programa                 |
|                                                    |
| [fire 12] [muscle 8] [clap 15] [heart 6]          |
+--------------------------------------------------+
```

**Reaction buttons:** Emoji icons with count badges. Highlighted border/glow if current user reacted. Click to toggle.

**Post composer:** Textarea with emoji picker (optional) + character counter (500 max) + red PUBLICAR button.

### Sub-task 10.6: Integrate into rise-dashboard.html

Replace existing basic community section (lines 1628-1672) with the new interactive version:
- Load `js/community.js`
- Init with `Community.init('communityFeed', 'rise')`
- RISE community shows only `audience='rise'` posts

### Sub-task 10.7: Add community to cliente.html

- Add "Comunidad" nav item in sidebar and bottom nav
- Add new section `sec-comunidad` with community container
- Init with `Community.init('communityFeed', 'all')`
- All-plans community shows `audience='all'` posts

### Sub-task 10.8: Achievement trigger integration

In existing code, add calls to `check-achievements.php` after:
- Check-in submission (cliente.html and rise-dashboard.html)
- Photo upload
- Measurement submission
- Daily cron for time-based achievements (first_week, 30_days, rise_day7, etc.)

### Sub-task 10.9: Test

1. Login as RISE client -> post message -> verify it appears in feed
2. Login as different client -> see the post -> click reaction emoji -> verify count updates
3. Reply to a post -> verify thread displays correctly
4. Trigger an achievement (e.g., upload first photo) -> verify auto-post appears in feed
5. Login as regular client (Elite) -> verify community section exists in cliente.html
6. Verify RISE community is separate from regular community (audience filter)
7. Test on mobile viewport — cards stack, reactions are tappable

### Sub-task 10.10: Commit

```bash
git add -A && git commit -m "feat: interactive community with reactions, replies, and achievement system"
```

---

## Appendix A: Database Migrations Summary

All new tables and column changes needed:

```sql
-- Task 3: Payments table upgrade for Wompi
ALTER TABLE payments
  ADD COLUMN wompi_reference VARCHAR(100),
  ADD COLUMN wompi_transaction_id VARCHAR(100),
  ADD COLUMN payment_method VARCHAR(50),
  MODIFY COLUMN plan ENUM('esencial','metodo','elite','rise') NOT NULL,
  MODIFY COLUMN currency VARCHAR(10) DEFAULT 'COP';

-- Task 6: Nutrition usage tracking
CREATE TABLE nutrition_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  meal_type VARCHAR(20),
  description TEXT,
  analysis JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  INDEX idx_client_date (client_id, created_at)
);

-- Task 7: Google social login
ALTER TABLE clients ADD COLUMN google_id VARCHAR(255);
ALTER TABLE clients ADD INDEX idx_google_id (google_id);

-- Task 8: Subscription tracking
ALTER TABLE clients
  ADD COLUMN subscription_start DATE,
  ADD COLUMN subscription_end DATE,
  ADD COLUMN renewal_reminder_sent TINYINT(1) DEFAULT 0;

-- Task 9: Engagement email tracking
CREATE TABLE engagement_emails (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  email_type ENUM('day10_motivation','day20_value') NOT NULL,
  cycle_month DATE NOT NULL,
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_email (client_id, email_type, cycle_month),
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- Task 10: Community system
CREATE TABLE community_posts (...);
CREATE TABLE community_reactions (...);
CREATE TABLE achievements (...);
```

---

## Appendix B: External Service Setup

| Service | Action Required | Owner |
|---------|----------------|-------|
| Google Cloud Console | Create OAuth 2.0 Client ID for Web | Developer |
| Formspree.io | Create 2 forms (regular renewal + RISE renewal) | Developer |
| Server Cron | Add 2 daily cron jobs (renewal 8am + engagement 9am) | DevOps |
| Wompi Dashboard | Verify webhook URL is active and receiving events | Developer |
| Claude API | Verify API key for Haiku model is configured | Developer |

---

## Appendix C: Session Estimates

| Phase | Tasks | Sessions |
|-------|-------|----------|
| Phase 1 | 1 + 2 (Quick wins) | 1 session |
| Phase 2 | 3 (Pagos Wompi) | 1 session |
| Phase 3 | 4 + 5 + 6 (Dashboards) | 2-3 sessions |
| Phase 4 | 7 + 8 + 9 (Auth + Engagement) | 2 sessions |
| Phase 5 | 10 (Community) | 2-3 sessions |
| **Total** | **10 tasks** | **8-10 sessions** |
