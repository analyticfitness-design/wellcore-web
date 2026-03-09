<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();
$db = getDB();
header('Content-Type: text/plain');
foreach (['challenges','academy_content','coach_audio','coach_video_tips','coach_community_posts','client_xp'] as $t) {
    echo "\n=== $t ===\n";
    try {
        foreach ($db->query("DESCRIBE $t")->fetchAll(PDO::FETCH_ASSOC) as $r)
            echo "  {$r['Field']} | {$r['Type']} | {$r['Null']} | {$r['Key']}\n";
        $cnt = $db->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        echo "  [rows: $cnt]\n";
    } catch (\PDOException $e) { echo "  ERROR: " . $e->getMessage() . "\n"; }
}
