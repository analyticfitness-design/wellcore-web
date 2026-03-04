# WellCore Admin Panel — Testing Guide

## Issue Status
**CRITICAL BUG FIXED** (Commit: c40f804)
- Admin panel scripts were not executing
- Root cause: CSS `visibility: hidden` was incorrectly placed inside `<body>` after scripts
- Fix: Moved CSS to proper location inside `<head>` section

## Credentials
```
Username: coach
Password: WellCore2026!
Role: admin
```

## Testing Steps

### 1. Quick Verification (Start Here)
Navigate to `https://wellcorefitness.test/diagnostic.html`
- Should display "✓ This script executed successfully!"
- Should show WC_API loaded
- Click "Test Navigation to Admin" button
- Should redirect to admin.html

### 2. Admin Panel Test
Navigate to `https://wellcorefitness.test/login.html`
1. Enter username: `coach`
2. Enter password: `WellCore2026!`
3. Click Login
4. Should redirect to `admin.html` and display the admin panel
5. Should see "DASHBOARD" with KPI cards (clients, MRR, check-ins, etc.)
6. Sidebar should be visible with menu items (Dashboard, Clientes, Check-ins, Pagos, Configuracion)

### 3. Console Debugging
Open DevTools (F12) and check Console tab:
- Should see: `========== ADMIN.HTML SCRIPT LOADED ==========`
- Should see auth check logs starting with `[AUTH]`
- Should NOT see any JavaScript errors

### 4. Storage Inspection
Open DevTools → Application → Local Storage → https://wellcorefitness.test
- Should have: `wc_token` (64-character hex string)
- Should have: `wc_user_type` = "admin"
- Optional: `admin_auth_logs_debug` should contain auth check logs

### 5. Full Feature Test
Once logged in, verify:
- [ ] Dashboard loads with KPI cards
- [ ] Navigation menu works (click each section)
- [ ] Clientes section loads
- [ ] Check-ins section loads
- [ ] Pagos section loads
- [ ] Configuracion section loads
- [ ] Logout button works

## Expected Files
- `admin.html` - Fixed (CSS moved to head)
- `js/api.js` - Modified (auto-appends .php to API paths)
- `diagnostic.html` - New (verification tool)
- `admin-test.html` through `admin-test2.html` - Test pages (can delete)

## If Issues Persist

### Symptom: Page still loads but appears blank
- Hard refresh browser (Ctrl+F5 or Cmd+Shift+R)
- Clear browser cache
- Check console for errors
- Verify localStorage has valid token

### Symptom: Redirect to login.html loops
- Token might be invalid
- Check credentials (coach / WellCore2026!)
- Clear localStorage and try again
- Check server logs for 401 errors

### Symptom: Auth check still failing
- Open DevTools Console
- Look for `[AUTH]` logs showing where it fails
- Check network tab for API response status codes
- Verify API endpoints end with `.php`

## API Endpoints Verified
- POST `/api/auth/login.php` - Returns token ✓
- GET `/api/auth/me.php` - Returns user data ✓

## Database Verified
- User `coach` exists with role `admin` ✓
- Password `WellCore2026!` is correct ✓
