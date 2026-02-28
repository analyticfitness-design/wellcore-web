<?php
/**
 * WellCore Fitness v3 — Public Coach Profile
 * Server-rendered PHP page for SEO. No auth required.
 * URL: /coach-profile.php?coach=carlos-vega
 */

require_once __DIR__ . '/api/config/database.php';

$slug = trim($_GET['coach'] ?? '');
if (!$slug || !preg_match('/^[a-z0-9-]+$/', $slug)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>404</title></head><body style="background:#0a0a0a;color:#fff;font-family:sans-serif;text-align:center;padding:100px"><h1>Coach no encontrado</h1><a href="/" style="color:#E31E24">Volver al inicio</a></body></html>';
    exit;
}

$db = getDB();

// Get coach profile
$stmt = $db->prepare("
    SELECT a.name, a.created_at as joined_at, cp.*
    FROM coach_profiles cp
    JOIN admins a ON a.id = cp.admin_id
    WHERE cp.slug = ? AND cp.public_visible = 1
");
$stmt->execute([$slug]);
$coach = $stmt->fetch();

if (!$coach) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>404</title></head><body style="background:#0a0a0a;color:#fff;font-family:sans-serif;text-align:center;padding:100px"><h1>Coach no encontrado</h1><a href="/" style="color:#E31E24">Volver al inicio</a></body></html>';
    exit;
}

// Get achievements
$achStmt = $db->prepare("SELECT label, icon, earned_at FROM coach_achievements WHERE admin_id = ? ORDER BY earned_at DESC");
$achStmt->execute([$coach['admin_id']]);
$achievements = $achStmt->fetchAll();

// Get active client count
$clientStmt = $db->prepare("SELECT COUNT(*) FROM clients WHERE coach_id = ? AND status = 'activo'");
$clientStmt->execute([$coach['admin_id']]);
$activeClients = (int) $clientStmt->fetchColumn();

$specs = json_decode($coach['specializations'] ?? '[]', true) ?: [];
$color = $coach['color_primary'] ?: '#E31E24';
$joinedYear = date('Y', strtotime($coach['joined_at']));

// Icon map for achievements (simple text, no emojis)
$iconMap = [
    'trophy'   => '[T]',
    'calendar' => '[C]',
    'users'    => '[U]',
    'link'     => '[L]',
    'dollar'   => '[$]',
    'star'     => '[*]',
    'fire'     => '[F]',
    'medal'    => '[M]',
    'target'   => '[X]',
    'check'    => '[+]',
    'heart'    => '[H]',
    'bolt'     => '[!]',
];

function getAchIcon(string $key, array $map): string {
    return $map[$key] ?? $map['star'];
}

// Coach initials for avatar fallback
$nameParts = explode(' ', trim($coach['name']));
$initials = '';
foreach ($nameParts as $part) {
    if ($part !== '') $initials .= mb_strtoupper(mb_substr($part, 0, 1));
}
$initials = mb_substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($coach['name']) ?> &mdash; Coach WellCore Fitness</title>
<meta name="description" content="<?= htmlspecialchars(substr($coach['bio'] ?? 'Coach profesional en WellCore Fitness', 0, 160)) ?>">
<meta property="og:title" content="<?= htmlspecialchars($coach['name']) ?> &mdash; WellCore Fitness">
<meta property="og:type" content="profile">
<meta property="og:description" content="<?= htmlspecialchars(substr($coach['bio'] ?? 'Coach profesional en WellCore Fitness', 0, 160)) ?>">
<meta property="og:url" content="https://wellcorefitness.com/coach-profile.php?coach=<?= htmlspecialchars($slug) ?>">
<?php if (!empty($coach['photo_url'])): ?>
<meta property="og:image" content="<?= htmlspecialchars($coach['photo_url']) ?>">
<?php endif; ?>
<link rel="canonical" href="https://wellcorefitness.com/coach-profile.php?coach=<?= htmlspecialchars($slug) ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=JetBrains+Mono:wght@300;400;500;700&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/wellcore-base.css">

<style>
/* Coach color override */
:root { --red: <?= htmlspecialchars($color) ?>; }

body { padding-top: 72px; }

/* ============================================================
   NAVBAR
============================================================ */
#navbar {
  position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
  height: 72px; display: flex; align-items: center;
  background: rgba(10,10,10,0.95);
  border-bottom: 1px solid var(--border);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
}
.nav-inner {
  width: 100%; max-width: 1280px; margin: 0 auto;
  padding: 0 32px; display: flex;
  align-items: center; justify-content: space-between; gap: 16px;
}
.nav-logo {
  font-family: var(--font-head);
  font-size: 20px; letter-spacing: 2px;
  color: var(--white); white-space: nowrap; flex-shrink: 0;
}
.nav-logo span { color: var(--red); }
.nav-logo img {
  display: inline-block; height: 34px;
  width: auto; vertical-align: middle;
}
.nav-links {
  display: flex; gap: 36px; list-style: none;
}
.nav-links a {
  font-family: var(--font-body); font-size: 9px;
  font-weight: 500; letter-spacing: 3px;
  text-transform: uppercase; color: rgba(255,255,255,0.3);
  transition: color 0.1s linear; position: relative;
}
.nav-links a::after {
  content: ''; position: absolute;
  bottom: -5px; left: 50%; width: 100%; height: 1px;
  background: var(--red);
  transform: translateX(-50%) scaleX(0);
  transform-origin: center; transition: transform 0.1s linear;
}
.nav-links a:hover { color: var(--white); }
.nav-links a:hover::after { transform: translateX(-50%) scaleX(1); }
.nav-cta {
  font-family: var(--font-body); font-size: 9px;
  font-weight: 600; letter-spacing: 2px;
  text-transform: uppercase; color: var(--red);
  display: flex; align-items: center; gap: 8px;
  transition: gap 0.1s linear; flex-shrink: 0;
}
.nav-cta:hover { gap: 12px; color: var(--red); }
.nav-account {
  font-family: 'JetBrains Mono', monospace;
  font-size: 8px; font-weight: 700;
  letter-spacing: 0.12em; text-transform: uppercase;
  color: #fff; background: var(--red);
  border: 2px solid var(--red);
  padding: 6px 12px; border-radius: 0;
  transition: background 0.1s linear, border-color 0.1s linear;
}
.nav-account:hover { background: var(--red-dark); border-color: var(--red-dark); }

/* Hamburger */
.nav-hamburger {
  display: none; background: none;
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 0; width: 40px; height: 36px;
  align-items: center; justify-content: center;
  cursor: pointer; color: rgba(255,255,255,0.6);
  font-size: 16px; padding: 0; flex-shrink: 0;
}

/* Mobile menu */
#navMobileMenu {
  display: none; position: fixed;
  top: 72px; left: 0; right: 0;
  background: rgba(10,10,10,0.97);
  border-bottom: 1px solid var(--border);
  backdrop-filter: blur(16px); z-index: 999;
  padding: 24px 32px 32px;
}
#navMobileMenu.open { display: block; }
#navMobileMenu ul { list-style: none; padding: 0; margin: 0 0 24px; }
#navMobileMenu ul li { border-bottom: 1px solid var(--border); }
#navMobileMenu ul li a {
  display: block; padding: 14px 0;
  font-family: var(--font-body); font-size: 11px;
  font-weight: 500; letter-spacing: 3px;
  text-transform: uppercase; color: rgba(255,255,255,0.5);
  transition: color 0.1s linear;
}
#navMobileMenu ul li a:hover { color: var(--white); }
#navMobileMenu .nav-mobile-cta {
  display: inline-flex; align-items: center; gap: 10px;
  background: var(--red); color: var(--white);
  font-family: var(--font-body); font-size: 11px;
  font-weight: 700; letter-spacing: 2px;
  text-transform: uppercase; padding: 13px 28px;
  border-radius: 0; width: 100%; justify-content: center;
}

@media (max-width: 991px) {
  .nav-links { display: none; }
  .nav-hamburger { display: flex; }
  .nav-cta { display: none; }
  .nav-inner > .nav-account { display: none; }
}

/* ============================================================
   HERO SECTION
============================================================ */
.cp-hero {
  padding: 80px 32px 48px;
  text-align: center;
  max-width: 800px;
  margin: 0 auto;
}
.cp-avatar {
  width: 140px; height: 140px;
  border-radius: 0; overflow: hidden;
  margin: 0 auto 28px;
  border: 2px solid var(--border);
  position: relative;
}
.cp-avatar img {
  width: 100%; height: 100%;
  object-fit: cover; display: block;
}
.cp-avatar-initials {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  background: var(--surface);
  font-family: var(--font-head);
  font-size: 52px; letter-spacing: 2px;
  color: var(--red);
}
.cp-name {
  font-family: var(--font-head);
  font-size: 56px; letter-spacing: 2px;
  line-height: 1; margin-bottom: 12px;
}
.cp-meta {
  font-family: var(--font-mono);
  font-size: 11px; letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--gray); margin-bottom: 24px;
}
.cp-meta-sep { color: var(--red); margin: 0 4px; }
.cp-specs {
  display: flex; flex-wrap: wrap;
  justify-content: center; gap: 8px;
}
.cp-spec-badge {
  font-family: var(--font-mono);
  font-size: 9px; font-weight: 500;
  letter-spacing: 1.5px; text-transform: uppercase;
  color: var(--white); background: var(--surface);
  border: 1px solid var(--border);
  padding: 6px 14px; border-radius: 0;
}

/* ============================================================
   BIO SECTION
============================================================ */
.cp-bio-section {
  max-width: 800px; margin: 0 auto;
  padding: 0 32px 48px;
}
.cp-bio-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-left: 3px solid var(--red);
  padding: 32px;
  border-radius: 0;
}
.cp-bio-card p {
  font-size: 15px; line-height: 1.75;
  color: rgba(255,255,255,0.75);
}

/* ============================================================
   STATS ROW
============================================================ */
.cp-stats {
  max-width: 800px; margin: 0 auto;
  padding: 0 32px 48px;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0;
}
.cp-stat {
  text-align: center;
  padding: 28px 16px;
  border: 1px solid var(--border);
  background: var(--surface);
}
.cp-stat + .cp-stat { border-left: none; }
.cp-stat-num {
  font-family: var(--font-head);
  font-size: 36px; letter-spacing: 1px;
  color: var(--red); line-height: 1;
  margin-bottom: 6px;
}
.cp-stat-label {
  font-family: var(--font-mono);
  font-size: 9px; letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--gray);
}

/* ============================================================
   ACHIEVEMENTS SECTION
============================================================ */
.cp-achievements-section {
  max-width: 800px; margin: 0 auto;
  padding: 0 32px 48px;
}
.cp-section-title {
  font-family: var(--font-head);
  font-size: 32px; letter-spacing: 2px;
  margin-bottom: 24px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border);
}
.cp-achievements-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
}
.cp-achievement {
  display: flex; align-items: center; gap: 12px;
  background: var(--surface);
  border: 1px solid var(--border);
  padding: 16px; border-radius: 0;
}
.cp-achievement-icon {
  font-size: 22px; line-height: 1;
  flex-shrink: 0;
}
.cp-achievement-label {
  font-family: var(--font-mono);
  font-size: 11px; font-weight: 500;
  letter-spacing: 0.5px;
  color: rgba(255,255,255,0.75);
}
.cp-achievement-date {
  font-family: var(--font-mono);
  font-size: 9px; color: var(--gray);
  margin-top: 2px;
}

/* ============================================================
   CTA SECTION
============================================================ */
.cp-cta-section {
  max-width: 800px; margin: 0 auto;
  padding: 0 32px 80px;
  text-align: center;
}
.cp-cta-divider {
  width: 100%; height: 1px;
  background: var(--border);
  margin-bottom: 48px;
}
.cp-cta-btn {
  display: inline-flex; align-items: center; gap: 10px;
  background: var(--red); color: var(--white);
  font-family: var(--font-head);
  font-size: 22px; letter-spacing: 3px;
  text-transform: uppercase;
  padding: 16px 48px; border-radius: 0;
  border: 2px solid var(--red);
  transition: background 0.1s linear, border-color 0.1s linear;
  cursor: pointer;
}
.cp-cta-btn:hover {
  background: var(--red-dark);
  border-color: var(--red-dark);
}
.cp-cta-sub {
  margin-top: 20px;
}
.cp-cta-ig {
  font-family: var(--font-mono);
  font-size: 11px; letter-spacing: 1px;
  color: var(--gray);
  transition: color 0.1s linear;
}
.cp-cta-ig:hover { color: var(--white); }

/* ============================================================
   FOOTER
============================================================ */
#footer {
  background: #0a0a0a;
  border-top: 1px solid var(--border);
  padding: 72px 0 0;
}
.footer-inner {
  max-width: 1280px; margin: 0 auto;
  padding: 0 32px;
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 48px;
}
.footer-logo {
  font-family: var(--font-head);
  font-size: 22px; letter-spacing: 2px;
  color: var(--white); margin-bottom: 14px;
}
.footer-logo span { color: var(--red); }
.footer-tagline {
  font-size: 13px; color: var(--gray);
  line-height: 1.65; margin-bottom: 24px;
  max-width: 240px;
}
.footer-socials { display: flex; gap: 12px; }
.footer-social {
  width: 36px; height: 36px;
  border: 1px solid var(--border); border-radius: 0;
  display: flex; align-items: center; justify-content: center;
  color: var(--gray); font-size: 14px;
  transition: border-color 0.1s linear, color 0.1s linear;
}
.footer-social:hover { border-color: var(--red); color: var(--red); }
.footer-col-title {
  font-family: var(--font-mono);
  font-size: 9px; letter-spacing: 3px;
  text-transform: uppercase;
  color: rgba(255,255,255,0.3); margin-bottom: 20px;
}
.footer-links { list-style: none; }
.footer-links li { margin-bottom: 10px; }
.footer-links a {
  font-size: 13px; color: var(--gray);
  transition: color 0.1s linear;
}
.footer-links a:hover { color: var(--white); }
.footer-contact-item {
  display: flex; align-items: center; gap: 10px;
  font-size: 13px; color: var(--gray); margin-bottom: 10px;
}
.footer-bottom {
  border-top: 1px solid var(--border);
  margin-top: 56px; padding: 20px 0;
}
.footer-bottom-inner {
  display: flex; align-items: center;
  justify-content: space-between; flex-wrap: wrap;
  gap: 12px; max-width: 1280px;
  margin: 0 auto; padding: 0 32px;
}
.footer-copy {
  font-family: var(--font-mono);
  font-size: 9px; letter-spacing: 1.5px;
  color: var(--gray-dim);
}
.footer-legal { display: flex; gap: 24px; }
.footer-legal a {
  font-family: var(--font-mono);
  font-size: 9px; letter-spacing: 1.5px;
  text-transform: uppercase;
  color: var(--gray-dim);
  transition: color 0.1s linear;
}
.footer-legal a:hover { color: var(--white); }

/* ============================================================
   WHATSAPP FLOAT
============================================================ */
.wa-float {
  position: fixed; bottom: 28px; right: 28px;
  width: 56px; height: 56px;
  background: #25D366; border-radius: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700;
  font-family: var(--font-mono);
  color: var(--white);
  z-index: 900;
  border: 2px solid rgba(255,255,255,0.1);
  transition: background 0.1s linear;
  letter-spacing: 1px;
}
.wa-float:hover { background: #1DA851; }

/* ============================================================
   RESPONSIVE
============================================================ */
@media (max-width: 767px) {
  .cp-hero { padding: 56px 20px 32px; }
  .cp-name { font-size: 40px; }
  .cp-avatar { width: 110px; height: 110px; }
  .cp-avatar-initials { font-size: 40px; }
  .cp-bio-section,
  .cp-stats,
  .cp-achievements-section,
  .cp-cta-section { padding-left: 20px; padding-right: 20px; }
  .cp-stats { grid-template-columns: 1fr; }
  .cp-stat + .cp-stat { border-left: 1px solid var(--border); border-top: none; }
  .cp-stat-num { font-size: 28px; }
  .cp-bio-card { padding: 24px; }
  .cp-achievements-grid { grid-template-columns: 1fr; }
  .cp-cta-btn { font-size: 18px; padding: 14px 32px; }
  .footer-inner { grid-template-columns: 1fr; gap: 32px; }
  .footer-bottom-inner { flex-direction: column; align-items: flex-start; padding: 0 20px; }
  .footer-legal { gap: 16px; flex-wrap: wrap; }
  #navMobileMenu { padding: 24px 20px 32px; }
}

@media (max-width: 480px) {
  .nav-inner { padding: 0 16px; }
  .nav-logo img { height: 28px; }
  .cp-hero { padding: 40px 16px 24px; }
  .cp-name { font-size: 34px; }
  .cp-bio-section,
  .cp-stats,
  .cp-achievements-section,
  .cp-cta-section { padding-left: 16px; padding-right: 16px; }
}
</style>
</head>
<body>

<!-- ============================================================
     NAVBAR
============================================================ -->
<nav id="navbar">
  <div class="nav-inner">
    <a href="/" class="nav-logo">
      <img src="images/logo/imagotipo-blanco.png" alt="WellCore Fitness" style="height:34px;">
    </a>

    <ul class="nav-links">
      <li><a href="metodo.html">M&eacute;todo</a></li>
      <li><a href="nosotros.html">Nosotros</a></li>
      <li><a href="proceso.html">Proceso</a></li>
      <li><a href="planes.html">Planes</a></li>
      <li><a href="tienda.html">Tienda</a></li>
      <li><a href="blog/index.html">Blog</a></li>
      <li><a href="faq.html">FAQ</a></li>
      <li><a href="coaches.html">Coaches</a></li>
    </ul>

    <a href="login.html" class="nav-account">MI CUENTA</a>
    <a href="/inscripcion.html" class="nav-cta">Empezar &rarr;</a>

    <button class="nav-hamburger" id="navToggle" aria-label="Abrir men&uacute;">&#9776;</button>
  </div>
</nav>

<!-- Mobile Menu -->
<div id="navMobileMenu" role="navigation" aria-label="Men&uacute; m&oacute;vil">
  <ul>
    <li><a href="metodo.html" class="nav-mobile-link">M&eacute;todo</a></li>
    <li><a href="nosotros.html" class="nav-mobile-link">Nosotros</a></li>
    <li><a href="proceso.html" class="nav-mobile-link">Proceso</a></li>
    <li><a href="planes.html" class="nav-mobile-link">Planes</a></li>
    <li><a href="tienda.html" class="nav-mobile-link">Tienda</a></li>
    <li><a href="blog/index.html" class="nav-mobile-link">Blog</a></li>
    <li><a href="faq.html" class="nav-mobile-link">FAQ</a></li>
    <li><a href="coaches.html" class="nav-mobile-link">Coaches</a></li>
    <li><a href="login.html" class="nav-mobile-link" style="color:#fff;background:var(--red);padding:8px 16px;text-align:center;font-weight:700;font-size:10px;letter-spacing:0.1em;">MI CUENTA</a></li>
  </ul>
  <a href="inscripcion.html" class="nav-mobile-cta">Empezar &rarr;</a>
</div>

<!-- ============================================================
     HERO
============================================================ -->
<section class="cp-hero">
  <div class="cp-avatar">
    <?php if (!empty($coach['photo_url'])): ?>
      <img src="<?= htmlspecialchars($coach['photo_url']) ?>" alt="<?= htmlspecialchars($coach['name']) ?>">
    <?php else: ?>
      <div class="cp-avatar-initials"><?= htmlspecialchars($initials) ?></div>
    <?php endif; ?>
  </div>

  <h1 class="cp-name"><?= htmlspecialchars($coach['name']) ?></h1>

  <div class="cp-meta">
    <?php if (!empty($coach['city'])): ?>
      <?= htmlspecialchars($coach['city']) ?> <span class="cp-meta-sep">//</span>
    <?php endif; ?>
    Miembro desde <?= htmlspecialchars($joinedYear) ?>
  </div>

  <?php if (!empty($specs)): ?>
  <div class="cp-specs">
    <?php foreach ($specs as $spec): ?>
      <span class="cp-spec-badge"><?= htmlspecialchars($spec) ?></span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<!-- ============================================================
     BIO
============================================================ -->
<?php if (!empty($coach['bio'])): ?>
<section class="cp-bio-section">
  <div class="cp-bio-card">
    <p><?= nl2br(htmlspecialchars($coach['bio'])) ?></p>
  </div>
</section>
<?php endif; ?>

<!-- ============================================================
     STATS
============================================================ -->
<section class="cp-stats">
  <div class="cp-stat">
    <div class="cp-stat-num"><?= $activeClients ?></div>
    <div class="cp-stat-label">Clientes Activos</div>
  </div>
  <div class="cp-stat">
    <div class="cp-stat-num"><?= htmlspecialchars($coach['experience'] ?: '0') ?></div>
    <div class="cp-stat-label">A&ntilde;os Experiencia</div>
  </div>
  <div class="cp-stat">
    <div class="cp-stat-num"><?= htmlspecialchars($joinedYear) ?></div>
    <div class="cp-stat-label">Miembro Desde</div>
  </div>
</section>

<!-- ============================================================
     ACHIEVEMENTS
============================================================ -->
<?php if (!empty($achievements)): ?>
<section class="cp-achievements-section">
  <h2 class="cp-section-title">Logros</h2>
  <div class="cp-achievements-grid">
    <?php foreach ($achievements as $ach): ?>
    <div class="cp-achievement">
      <div class="cp-achievement-icon"><?= getAchIcon($ach['icon'] ?? 'star', $iconMap) ?></div>
      <div>
        <div class="cp-achievement-label"><?= htmlspecialchars($ach['label']) ?></div>
        <div class="cp-achievement-date"><?= date('M Y', strtotime($ach['earned_at'])) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- ============================================================
     CTA
============================================================ -->
<section class="cp-cta-section">
  <div class="cp-cta-divider"></div>

  <?php if (!empty($coach['whatsapp'])): ?>
    <a href="https://wa.me/<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $coach['whatsapp'])) ?>?text=<?= rawurlencode('Hola, vi tu perfil en WellCore Fitness y me gustaria entrenar contigo.') ?>" target="_blank" rel="noopener" class="cp-cta-btn">Entrena Conmigo &rarr;</a>
  <?php else: ?>
    <a href="proceso.html?ref=<?= htmlspecialchars($slug) ?>" class="cp-cta-btn">Entrena Conmigo &rarr;</a>
  <?php endif; ?>

  <?php if (!empty($coach['instagram'])): ?>
  <div class="cp-cta-sub">
    <a href="https://instagram.com/<?= htmlspecialchars(ltrim($coach['instagram'], '@')) ?>" target="_blank" rel="noopener" class="cp-cta-ig">@<?= htmlspecialchars(ltrim($coach['instagram'], '@')) ?></a>
  </div>
  <?php endif; ?>
</section>

<!-- ============================================================
     FOOTER
============================================================ -->
<footer id="footer">
  <div class="footer-inner">

    <div>
      <div class="footer-logo">WELLCORE <span>FITNESS</span></div>
      <p class="footer-tagline">Coaching online 1:1 basado en ciencia. Sin milagros, sin contratos, sin suplementos innecesarios.</p>
      <div class="footer-socials">
        <a href="https://www.instagram.com/wellcore.fitness/" target="_blank" rel="noopener" class="footer-social">IG</a>
        <a href="https://www.youtube.com/@Wellcorefitness" target="_blank" rel="noopener" class="footer-social">YT</a>
        <a href="https://www.tiktok.com/@wellcore.fitness" target="_blank" rel="noopener" class="footer-social">TK</a>
        <a href="/postulate.html" class="footer-social">@</a>
      </div>
    </div>

    <div>
      <div class="footer-col-title">Navegaci&oacute;n</div>
      <ul class="footer-links">
        <li><a href="metodo.html">M&eacute;todo</a></li>
        <li><a href="nosotros.html">Nosotros</a></li>
        <li><a href="proceso.html">Proceso</a></li>
        <li><a href="planes.html">Planes</a></li>
        <li><a href="blog/index.html">Blog</a></li>
        <li><a href="faq.html">FAQ</a></li>
      </ul>
    </div>

    <div>
      <div class="footer-col-title">Contacto</div>
      <div class="footer-contact-item">@ info@wellcorefitness.com</div>
      <div class="footer-contact-item">IG @wellcore.fitness</div>
    </div>

  </div>

  <div class="footer-bottom">
    <div class="footer-bottom-inner">
      <span class="footer-copy">WellCore Fitness &copy; <?= date('Y') ?> &mdash; Todos los derechos reservados.</span>
      <div class="footer-legal">
        <a href="/legal/privacidad.html">Pol&iacute;tica de Privacidad</a>
        <a href="/legal/terminos.html">T&eacute;rminos</a>
        <a href="/legal/cookies.html">Cookies</a>
      </div>
    </div>
  </div>
</footer>

<!-- ============================================================
     WHATSAPP FLOAT (only if coach has WhatsApp)
============================================================ -->
<?php if (!empty($coach['whatsapp'])): ?>
<a href="https://wa.me/<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $coach['whatsapp'])) ?>" target="_blank" rel="noopener" class="wa-float" aria-label="WhatsApp">WA</a>
<?php endif; ?>

<!-- ============================================================
     SCRIPTS
============================================================ -->
<script>
(function () {
  var toggle = document.getElementById('navToggle');
  var menu   = document.getElementById('navMobileMenu');
  if (!toggle || !menu) return;

  toggle.addEventListener('click', function () {
    var isOpen = menu.classList.contains('open');
    if (isOpen) {
      menu.classList.remove('open');
      toggle.textContent = '\u2630';
      toggle.setAttribute('aria-label', 'Abrir men\u00fa');
    } else {
      menu.classList.add('open');
      toggle.textContent = '\u2715';
      toggle.setAttribute('aria-label', 'Cerrar men\u00fa');
    }
  });

  var mobileLinks = menu.querySelectorAll('a');
  for (var i = 0; i < mobileLinks.length; i++) {
    mobileLinks[i].addEventListener('click', function () {
      menu.classList.remove('open');
      toggle.textContent = '\u2630';
    });
  }
})();
</script>

</body>
</html>
