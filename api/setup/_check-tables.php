<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');
$db = getDB();
$tables = ['xp_events','client_xp','challenge_participants','biometric_logs','habit_logs','training_logs','weight_logs','progress_photos','coach_notes','push_subscriptions','notification_log','chat_messages','assigned_plans','referrals','video_checkins','academy_progress','rise_programs','auth_tokens','client_profiles','checkins','payments','daily_missions','onboarding_steps','weekly_summaries','celebrations','chat_weekly_limits','coach_presence'];
$result = [];
foreach($tables as $t) {
    try {
        $db->query("SELECT 1 FROM `$t` LIMIT 0");
        $result[$t] = 'EXISTS';
    } catch(\Throwable $e) {
        $result[$t] = 'MISSING';
    }
}
echo json_encode($result, JSON_PRETTY_PRINT);
