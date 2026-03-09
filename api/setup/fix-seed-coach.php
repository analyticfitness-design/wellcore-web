<?php
/**
 * Fix: actualiza coach_id en coach_audio y coach_video_tips
 * de '1' a '3' (coachsilvia — coach real asignado a los clientes demo).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();

$db = getDB();
header('Content-Type: text/plain');

echo "=== Fix coach_id en contenido seeded ===\n\n";

// Actualizar coach_audio: '1' -> '3'
$r = $db->prepare("UPDATE coach_audio SET coach_id = '3' WHERE coach_id = '1'");
$r->execute();
echo "coach_audio: {$r->rowCount()} filas actualizadas (coach_id '1' -> '3')\n";

// Actualizar coach_video_tips: '1' -> '3'
$r = $db->prepare("UPDATE coach_video_tips SET coach_id = '3' WHERE coach_id = '1'");
$r->execute();
echo "coach_video_tips: {$r->rowCount()} filas actualizadas (coach_id '1' -> '3')\n";

// Actualizar coach_community_posts: 1 -> 3 (INT)
$r = $db->prepare("UPDATE coach_community_posts SET coach_id = 3 WHERE coach_id = 1");
$r->execute();
echo "coach_community_posts: {$r->rowCount()} filas actualizadas (coach_id 1 -> 3)\n";

echo "\n=== Verificación post-fix ===\n";
echo "coach_audio coach_ids: " . implode(',', array_column($db->query("SELECT DISTINCT coach_id FROM coach_audio")->fetchAll(PDO::FETCH_ASSOC), 'coach_id')) . "\n";
echo "coach_video_tips coach_ids: " . implode(',', array_column($db->query("SELECT DISTINCT coach_id FROM coach_video_tips")->fetchAll(PDO::FETCH_ASSOC), 'coach_id')) . "\n";
echo "coach_community_posts coach_ids: " . implode(',', array_column($db->query("SELECT DISTINCT coach_id FROM coach_community_posts")->fetchAll(PDO::FETCH_ASSOC), 'coach_id')) . "\n";

// Verificar que el cliente demo puede ver audio
echo "\n=== Test: audio para cliente id=12 (coach_id=3) ===\n";
$q = $db->prepare("SELECT COUNT(*) FROM coach_audio WHERE coach_id = (SELECT coach_id FROM clients WHERE id = 12)");
$q->execute();
echo "Audios visibles para cliente 12: " . $q->fetchColumn() . "\n";
