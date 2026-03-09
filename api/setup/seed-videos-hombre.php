<?php
/**
 * Seed: Videos técnicos hombre (61 videos de YouTube)
 * ======================================================
 * Inserta videos de técnica de ejercicios en coach_video_tips.
 * Idempotente: verifica si ya existen >= 60 videos antes de insertar.
 *
 * EJECUCIÓN: php /code/api/setup/seed-videos-hombre.php
 *            o via Bearer token admin en HTTP
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();

$db = getDB();
header('Content-Type: text/plain; charset=utf-8');

$coachId = '3'; // coachsilvia
$now     = date('Y-m-d H:i:s');

// Verificar cuántos videos ya existen
$existing = (int)$db->query("SELECT COUNT(*) FROM coach_video_tips")->fetchColumn();
echo "Videos actuales en DB: $existing\n";

if ($existing >= 60) {
    echo "SKIP: Ya hay $existing videos — sin cambios.\n";
    exit;
}

// ── VIDEOS AGRUPADOS POR MÚSCULO ──────────────────────────────────────────
// Thumbnail automático: https://img.youtube.com/vi/{ID}/hqdefault.jpg

$videos = [
    // ─── PECHO ───────────────────────────────────────────────
    ['Aperturas inclinado con mancuernas',        'OU9b_aRjz0Y', 'pecho',    10],
    ['Aperturas inclinado en máquina',            'HDPKH1BBhhE', 'pecho',    11],
    ['Press Banca plano con barra',               'dSKZeei9KJ4', 'pecho',    12],
    ['Press Banca plano con barra v2',            'FPJS6TTZWL8', 'pecho',    13],
    ['Press plano con mancuernas',                'cma9jYjBRIw', 'pecho',    14],
    ['Press plano en máquina',                    'D7wyM1rwbNE', 'pecho',    15],
    ['Press plano en smith',                      'uPP6AJp1sT0', 'pecho',    16],
    ['Press inclinado con barra',                 'X2WPUgFQbWk', 'pecho',    17],
    ['Press inclinado con mancuernas',            '1iL_WlAYBAs', 'pecho',    18],
    ['Press inclinado con máquina',               'jGDYhsGlMCs', 'pecho',    19],
    ['Press inclinado en smith',                  'LXYKjob0KMo', 'pecho',    20],
    ['Press en máquina (chest press)',            'ojlEhV37FkU', 'pecho',    21],
    ['Press en smith',                            'jYrcwuseZaM', 'pecho',    22],
    ['Press árbol con mancuernas',                'zAXy_By--o0', 'pecho',    23],
    ['Press con barra (variación)',               'AANwuvNPPSU', 'pecho',    24],
    ['Press de agarre cerrado en banco',          'Q0n0Q4hxRLA', 'pecho',    25],
    ['Cruce en polea',                            'ZGUsf_jioSk', 'pecho',    26],
    ['Cruce inferior poleas',                     'GyZKQvsGaeM', 'pecho',    27],
    ['Cruce invertido con polea',                 'T4__GSGtnoU', 'pecho',    28],
    ['Cruce polea baja',                          'BvuFsc_Co2E', 'pecho',    29],
    ['Fondos en banco',                           'SswE_mcoZLA', 'pecho',    30],
    ['Fondos en banco v2',                        'AJWUCcTZvwY', 'pecho',    31],
    ['Fondos en banco v3',                        'ueQ-QSMueXQ', 'pecho',    32],
    ['Pullover',                                  'SANzF6jptFs', 'pecho',    33],

    // ─── HOMBROS ─────────────────────────────────────────────
    ['Press militar con mancuernas',              'RhPWH-D6SRc', 'hombros',  40],
    ['Elevaciones laterales con mancuerna',       'Oj3T6YBfRCE', 'hombros',  41],
    ['Elevaciones laterales en máquina',          'a_ltR5M_itA', 'hombros',  42],
    ['Elevaciones laterales en polea',            'ADOXpWcHsZ4', 'hombros',  43],
    ['Elevación lateral a una mano',              '6vRVVU9AzgE', 'hombros',  44],
    ['Elevación lateral con banco inclinado',     'dCimwp911N0', 'hombros',  45],
    ['Elevaciones con mancuernas Dropset',        'uiYMBTuXhf0', 'hombros',  46],
    ['Elevación frontal con mancuernas',          'vV5bQObGHRE', 'hombros',  47],
    ['Elevación frontal con barra sentado',       'zlbmI4BthLk', 'hombros',  48],
    ['Elevación frontal con disco',               '10e8v6Lna4k', 'hombros',  49],
    ['Elevación frontal con polea',               '6yoOlcgHvhA', 'hombros',  50],
    ['Facepull',                                  'iLnhqZ_oLsQ', 'hombros',  51],
    ['Facepull variante jalón',                   'x6cgN0bTFRo', 'hombros',  52],
    ['Vuelos posteriores con mancuerna',          'ow-y0-3HSKs', 'hombros',  53],
    ['Levantamiento de hombros (Shrug)',          'glX87IEgh6M', 'hombros',  54],

    // ─── TRÍCEPS ─────────────────────────────────────────────
    ['Copa de tríceps',                           'CjqtisT2B2Y', 'triceps',  60],
    ['Copa de tríceps v2',                        'QvburrzD-7Q', 'triceps',  61],
    ['Extensión de tríceps con soga',             'q052hSZWh0M', 'triceps',  62],
    ['Extensión de tríceps con barra',            'JQa9YeIzF44', 'triceps',  63],
    ['Extensión de tríceps con barra v2',         'hDpQ6jcrT18', 'triceps',  64],
    ['Extensión de tríceps a una mano',           'p3qIwP5ablo', 'triceps',  65],
    ['Extensión de tríceps sobre la cabeza',      '_j18KAzEKmI', 'triceps',  66],
    ['Extensión de tríceps por encima de cabeza', 'bX4zOCT_Na8', 'triceps',  67],
    ['Extensión de tríceps en banco inclinado',   'OAJXHoY2_2I', 'triceps',  68],
    ['Extensión de tríceps sentado en máquina',   'YeCM-Vl98gE', 'triceps',  69],
    ['Extensión de tríceps poleas altas',         'oTRVf2y4kEA', 'triceps',  70],
    ['Patada de tríceps con mancuerna',           'qwtikDRLKN4', 'triceps',  71],
    ['Rompecráneos con mancuerna',                'Sxlw9N3qACs', 'triceps',  72],
    ['Rompecráneos con barra',                    'vVf4jueIBHo', 'triceps',  73],
    ['Fondos en máquina v1',                      'rRw-yiVkE3M', 'triceps',  74],
    ['Fondos en máquina v2',                      '7U5J8j0m850', 'triceps',  75],
    ['Smith Close Grip Triceps',                  'Rf9Bx5coELg', 'triceps',  76],

    // ─── ESPALDA ─────────────────────────────────────────────
    ['Jalón al pecho',                            'MHhvz5IBFXk', 'espalda',  80],
    ['Jalón al pecho agarre estrecho',            'sH7p91ExA0c', 'espalda',  81],
    ['Remo unilateral con mancuerna',             '1UL6Sb17RRI', 'espalda',  82],
    ['Remo con polea baja a una mano',            'Gp-pRgcqWCE', 'espalda',  83],
    ['Remo en máquina',                           'tfbBm9tWAWo', 'espalda',  84],
];

echo "Videos a insertar: " . count($videos) . "\n\n";

$stmt = $db->prepare("
    INSERT INTO coach_video_tips
        (coach_id, title, video_url, thumbnail_url, duration_sec, sort_order, is_active, created_at)
    VALUES (?, ?, ?, ?, 0, ?, 1, ?)
");

$db->beginTransaction();
$inserted = 0;
$errors   = [];

foreach ($videos as [$title, $ytId, $category, $sortOrder]) {
    $videoUrl     = "https://www.youtube.com/watch?v={$ytId}";
    $thumbnailUrl = "https://img.youtube.com/vi/{$ytId}/hqdefault.jpg";

    try {
        $stmt->execute([$coachId, $title, $videoUrl, $thumbnailUrl, $sortOrder, $now]);
        $inserted++;
        echo "  OK  sort={$sortOrder} [{$category}] {$title}\n";
    } catch (\PDOException $e) {
        $errors[] = "{$title}: " . $e->getMessage();
        echo "  ERR [{$category}] {$title}: " . $e->getMessage() . "\n";
    }
}

$db->commit();

echo "\n=== Resultado ===\n";
echo "Insertados: $inserted\n";
echo "Errores: " . count($errors) . "\n";
echo "Total en DB: " . $db->query("SELECT COUNT(*) FROM coach_video_tips")->fetchColumn() . "\n";
