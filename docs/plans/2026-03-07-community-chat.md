# Community Chat Grupal — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a group chat with profanity filter, sales/spam detection, and report system to the WellCore community section.

**Architecture:** New `community_chat` and `chat_reports` tables. PHP API endpoint handles GET (polling with `after_id`) and POST (with server-side profanity + sales filter). JS extends the existing Community IIFE with tab switcher (Feed | Chat), message rendering, and 5s polling. Users can report messages; 3+ reports auto-hides a message and bans the sender from chat for 24h.

**Tech Stack:** PHP 8 + MySQL (existing), vanilla JS DOM manipulation (existing pattern), Font Awesome icons (existing)

---

### Task 1: Database Migration

**Files:**
- Create: `api/setup/migrate-chat.php`

**Step 1: Create the migration file**

Create `api/setup/migrate-chat.php` following the pattern in `api/setup/migrate-community.php`.

Creates 3 tables:
- `community_chat` (id, client_id FK, message VARCHAR(500), hidden TINYINT DEFAULT 0, created_at TIMESTAMP, INDEX on created_at DESC, INDEX on hidden+created_at)
- `chat_reports` (id, chat_message_id FK to community_chat, reporter_id FK to clients, reason VARCHAR(100) DEFAULT 'inappropriate', created_at, UNIQUE on chat_message_id+reporter_id)
- `chat_bans` (id, client_id FK, reason VARCHAR(255), banned_until TIMESTAMP, created_at, INDEX on client_id+banned_until)

**Step 2: Commit**

```
git add api/setup/migrate-chat.php
git commit -m "feat: chat migration — community_chat, chat_reports, chat_bans tables"
```

---

### Task 2: Profanity + Sales Filter

**Files:**
- Create: `api/includes/chat-filter.php`

**Step 1: Create the filter file**

The filter has three layers:
1. **Profanity** — 200+ Spanish bad words array
2. **Sales/spam** — Keywords: vendo, venta, compra, precio, negocio, whatsapp, telegram, URL patterns, phone numbers, crypto/forex terms, MLM terms
3. **Evasion detection** — `normalizeForFilter()` function that: lowercases, removes accents, replaces number look-alikes (0->o, 1->i, 3->e, 4->a), collapses repeated chars (puuuta->puta), removes spaces between single chars (p u t a -> puta)

Main function `filterChatMessage($text)` returns:
```php
['clean' => 'filtered text with ***', 'flagged' => true/false, 'reasons' => ['profanity','sales','phone_number','url']]
```

Also includes `containsPhoneNumber($text)` that strips separators and matches 7+ consecutive digits.

URL detection via regex: `/(https?:\/\/|www\.)\S+/i`

Strategy: Replace matches with `***`, don't block the message.

Bad words array includes: insultos graves (puta, pendejo, cabron, hijueputa, malparido, etc), groserias sexuales (verga, pinga, etc), vulgaridades (mierda, chingar, joder, carajo), discriminatorios, agresiones directas, drogas.

Sales words array includes: vendo, venta, compra, precio, whatsapp, telegram, transferencia, paypal, nequi, enlace, link, http, www, multinivel, mlm, crypto, bitcoin, forex, trading, gana dinero, negocio propio.

**Step 2: Commit**

```
git add api/includes/chat-filter.php
git commit -m "feat: profanity + sales filter — 200+ bad words, sales detection, phone/URL blocking"
```

---

### Task 3: Chat API Endpoint

**Files:**
- Create: `api/community/chat.php`

**Step 1: Create the chat endpoint**

Follow the exact pattern from `api/community/posts.php`:
- Require `database.php`, `cors.php`, `response.php`, `auth.php`, plus `chat-filter.php`
- `requireMethod('GET', 'POST')`
- `$client = authenticateClient()`

**GET handler** — Three modes based on query params:
- `?after_id=X` (polling): `WHERE id > ? AND hidden = 0 ORDER BY created_at ASC LIMIT 50`
- `?before_id=X` (load older): `WHERE id < ? AND hidden = 0 ORDER BY created_at DESC LIMIT 50` then reverse
- No params (initial): `WHERE hidden = 0 ORDER BY created_at DESC LIMIT 50` then reverse

Response: `{ ok: true, messages: [{ id, client_id, message, author_name, author_initial, author_plan, is_mine, created_at }] }`

**POST handler** — Send message:
1. Check ban: `SELECT banned_until FROM chat_bans WHERE client_id = ? AND banned_until > NOW() LIMIT 1` — if found, respond 403
2. Rate limit: `SELECT created_at FROM community_chat WHERE client_id = ? ORDER BY created_at DESC LIMIT 1` — if less than 2 seconds ago, respond 429
3. Validate: `strip_tags()`, length 1-500
4. Filter: `filterChatMessage($message)` — use the cleaned text
5. Insert: `INSERT INTO community_chat (client_id, message) VALUES (?, ?)`
6. Respond 201 with the message object + `filtered: bool`

**Step 2: Commit**

```
git add api/community/chat.php
git commit -m "feat: chat API — GET polling + POST with profanity/sales filter, rate limit, ban check"
```

---

### Task 4: Report API Endpoint

**Files:**
- Create: `api/community/report-chat.php`

**Step 1: Create the report endpoint**

POST only. Body: `{ chat_message_id, reason? }`

Flow:
1. Authenticate client
2. Validate message exists (`SELECT id, client_id FROM community_chat WHERE id = ?`)
3. Block self-report (`client_id === cid` -> error 400)
4. Check duplicate (`SELECT FROM chat_reports WHERE chat_message_id = ? AND reporter_id = ?` -> error 409)
5. Insert report
6. Count reports for message
7. If count >= 3:
   - Hide message: `UPDATE community_chat SET hidden = 1 WHERE id = ?`
   - Ban sender 24h: `INSERT INTO chat_bans (client_id, reason, banned_until) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))`
8. Respond: `{ ok: true, action: 'reported' | 'hidden_and_banned', report_count }`

**Step 2: Commit**

```
git add api/community/report-chat.php
git commit -m "feat: report chat API — 3+ reports auto-hides message + 24h ban for sender"
```

---

### Task 5: Chat UI in community.js — Tab Switcher + Chat Rendering

**Files:**
- Modify: `js/community.js`

This is the largest task. Add chat functionality inside the existing Community IIFE.

**Step 1: Add new state variables** (after `_totalPages` line 11):

```js
var _chatMode = false;
var _pollTimer = null;
var _lastMsgId = 0;
var _clientId = 0;
```

**Step 2: Add chat functions** (before the `return` block, around line 393):

New functions to add:

- `renderTabSwitcher()` — Returns a div with two buttons "Feed" and "Chat". Clicking toggles `_chatMode` and shows/hides `feedWrap`/`chatWrap`. Active tab has red underline. Style: flex row, each button has `padding:10px 24px`, uppercase, font-weight 700, cursor pointer.

- `renderChatUI()` — Returns a div containing:
  - "Cargar anteriores" button at top (hidden initially)
  - Messages container: `id='chatMessages'`, style `height:400px;overflow-y:auto;scroll-behavior:smooth`
  - Input bar at bottom: flex row with text input (placeholder "Escribe un mensaje...", maxLength 500) + send button (red, fa-paper-plane icon). Enter key sends. Counter shows 0/500.

- `renderChatMessage(msg)` — Creates a message bubble div:
  - If `msg.is_mine`: flex-direction row-reverse, background rgba(227,30,36,0.12), border-left 3px solid #E31E24
  - If not mine: flex-direction row, background rgba(255,255,255,0.03), border-left 3px solid rgba(255,255,255,0.1)
  - Contains: avatar (createAvatar with initial, size 28), name+plan+time header, message text
  - Report button (fa-flag icon, tiny, only on OTHER users' messages): calls `reportMessage(msg.id)`
  - All text via textContent (XSS safe)

- `loadChatMessages(mode)` — Calls GET `/api/community/chat.php` with appropriate params:
  - `'init'`: no params, sets `_lastMsgId` to highest id, scrolls to bottom
  - `'poll'`: `?after_id=_lastMsgId`, appends new messages, updates `_lastMsgId`, auto-scrolls only if user is at bottom
  - `'older'`: `?before_id=firstMsgId`, prepends messages at top, preserves scroll position
  - Shows/hides "Cargar anteriores" button

- `sendChatMessage(text, input, counter)` — POST to `/api/community/chat.php`:
  - On success: append message to chat, clear input, scroll to bottom
  - On 403 (banned): show toast with ban message
  - On 429 (rate limit): show toast "Espera un momento"
  - On filtered response: optionally show subtle warning

- `reportMessage(msgId)` — POST to `/api/community/report-chat.php`:
  - On success: show toast "Mensaje reportado"
  - On 409: show toast "Ya reportaste este mensaje"
  - If action is 'hidden_and_banned': remove message from UI

- `startPolling()` — `_pollTimer = setInterval(function() { loadChatMessages('poll'); }, 5000)`. Also adds `visibilitychange` listener to pause/resume.

- `stopPolling()` — `clearInterval(_pollTimer)`

**Step 3: Modify `init()`** function to:
1. Build tab switcher (Feed | Chat)
2. Create `feedWrap` div — move existing achievements, composer, feedList, loadMore into it
3. Create `chatWrap` div (hidden initially)
4. Lazy-init chat on first tab click (call `renderChatUI()`, `loadChatMessages('init')`, `startPolling()`)
5. Extract `_clientId` from first message or add a data attribute

**Step 4: Modify `refresh()`** to also handle chat mode.

**Step 5: Commit**

```
git add js/community.js
git commit -m "feat: chat UI — tab switcher, message bubbles, polling, report button"
```

---

### Task 6: Verify Integration

**Files:**
- Verify: `cliente.html` — no changes needed, Community.init still works
- Verify: `rise-dashboard.html` — same

Both files call `Community.init('communityContainer', 'all')` which now builds tabs internally. No HTML changes needed.

Test locally:
1. Open cliente.html -> Comunidad -> verify Feed and Chat tabs appear
2. Send chat message, verify it appears
3. Try bad word, verify it's filtered
4. Try "vendo algo", verify filtered
5. Report a message, verify toast
6. Open rise-dashboard.html -> Comunidad -> same verification

**Step 1: Commit only if fixes needed**

---

### Task 7: Deploy to Production

**Step 1: Push and deploy**

```
git push origin main
```

Then via Easypanel:
1. Git tab -> Clonar
2. Implementar
3. Console: `cp -r /code/* /var/www/html/`
4. Console: `cd /var/www/html && php api/setup/migrate-chat.php`

**Step 2: Verify endpoint responds**

Navigate to `https://www.wellcorefitness.com/api/community/chat.php` — should return `{"error":"Authentication required"}`

---

### Task 8: End-to-End Verification

Login as test client via admin impersonation and verify:
1. Chat tab appears in Comunidad
2. Can send and receive messages
3. Bad words get filtered to ***
4. Sales/URLs get filtered
5. Report button works
6. Feed tab still works correctly
7. Polling brings new messages every 5s
