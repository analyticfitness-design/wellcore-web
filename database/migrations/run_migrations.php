<?php
// Migration runner — reads DB credentials from /code/api/.env (server) or local api/.env
$envFile = file_exists('/code/api/.env') ? '/code/api/.env' : __DIR__ . '/../../api/.env';
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if ($line[0] === '#') continue;
    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
    putenv("$k=$v");
}
$host = getenv('DB_HOST'); $name = getenv('DB_NAME');
$user = getenv('DB_USER'); $pass = getenv('DB_PASS');
$port = getenv('DB_PORT') ?: '3306';

$pdo = new PDO("mysql:host=$host;port=$port;dbname=$name", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function col($p,$t,$n){return (int)$p->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$n'")->fetchColumn();}
function idx($p,$t,$i){return (int)$p->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND INDEX_NAME='$i'")->fetchColumn();}
function ctype($p,$t,$n){$r=$p->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$n'");return $r?$r->fetchColumn():'';}
function run($p,$s,$n){try{$p->query($s);echo "OK: $n\n";}catch(Exception $e){echo "ERR[$n]: ".$e->getMessage()."\n";}}

echo "=== Migration 011: CREATE tables for M01/M12/M15/M16/M18/M26/M35/M37 ===\n";
$sql011 = file_get_contents('/code/database/migrations/011_modules_m01_m12_m15_m16_m18_m26_m35_m37.sql');
// Strip -- comments and split by semicolon (safe for pure CREATE TABLE files)
$lines = explode("\n", $sql011);
$clean = implode("\n", array_filter($lines, fn($l) => !preg_match('/^\s*--/', $l)));
foreach (array_filter(array_map('trim', explode(';', $clean))) as $stmt) {
    if ($stmt) run($pdo, $stmt, '011 '.substr(trim(preg_replace('/\s+/',' ',$stmt)),0,60));
}

echo "\n=== Migration 012: auto_message_log ===\n";
if(!col($pdo,'auto_message_log','date_sent'))
    run($pdo,"ALTER TABLE auto_message_log ADD COLUMN date_sent DATE DEFAULT NULL","012 date_sent");
else echo "SKIP: 012 date_sent already exists\n";
$pdo->query("UPDATE auto_message_log SET date_sent=DATE(sent_at) WHERE date_sent IS NULL");
echo "012 backfill done\n";
if(!idx($pdo,'auto_message_log','uq_client_trigger_day'))
    run($pdo,"ALTER TABLE auto_message_log ADD UNIQUE KEY uq_client_trigger_day (client_id,trigger_type,date_sent)","012 unique key");
else echo "SKIP: 012 unique key already exists\n";

echo "\n=== Migration 013: biometric_logs ===\n";
$cols=['weight_kg'=>'DECIMAL(5,2) DEFAULT NULL','body_fat_pct'=>'DECIMAL(4,1) DEFAULT NULL','waist_cm'=>'DECIMAL(5,1) DEFAULT NULL','hip_cm'=>'DECIMAL(5,1) DEFAULT NULL','energy_level'=>'TINYINT DEFAULT NULL','notes'=>'TEXT DEFAULT NULL','updated_at'=>'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'];
foreach($cols as $c=>$t){
    if(!col($pdo,'biometric_logs',$c))
        run($pdo,"ALTER TABLE biometric_logs ADD COLUMN $c $t","013 $c");
    else echo "SKIP: 013 $c already exists\n";
}

echo "\n=== Migration 014: payment_methods + auto_charge_log ===\n";
if(col($pdo,'payment_methods','brand')&&!col($pdo,'payment_methods','card_brand'))
    run($pdo,"ALTER TABLE payment_methods CHANGE COLUMN brand card_brand VARCHAR(30) DEFAULT NULL","014 brand->card_brand");
else echo "SKIP: 014 brand rename\n";
if(col($pdo,'payment_methods','last4')&&!col($pdo,'payment_methods','last_four'))
    run($pdo,"ALTER TABLE payment_methods CHANGE COLUMN last4 last_four CHAR(4) DEFAULT NULL","014 last4->last_four");
else echo "SKIP: 014 last4 rename\n";
if(col($pdo,'payment_methods','active')&&!col($pdo,'payment_methods','is_active'))
    run($pdo,"ALTER TABLE payment_methods CHANGE COLUMN active is_active TINYINT(1) DEFAULT 1","014 active->is_active");
else echo "SKIP: 014 active rename\n";
if(!col($pdo,'payment_methods','card_holder'))
    run($pdo,"ALTER TABLE payment_methods ADD COLUMN card_holder VARCHAR(100) DEFAULT NULL","014 card_holder");
else echo "SKIP: 014 card_holder already exists\n";
if(col($pdo,'auto_charge_log','wompi_txn_id')&&!col($pdo,'auto_charge_log','wompi_transaction_id'))
    run($pdo,"ALTER TABLE auto_charge_log CHANGE COLUMN wompi_txn_id wompi_transaction_id VARCHAR(100) DEFAULT NULL","014 txn_id");
else echo "SKIP: 014 txn_id rename\n";
if(col($pdo,'auto_charge_log','wompi_ref')&&!col($pdo,'auto_charge_log','reference'))
    run($pdo,"ALTER TABLE auto_charge_log CHANGE COLUMN wompi_ref reference VARCHAR(100) DEFAULT NULL","014 ref");
else echo "SKIP: 014 ref rename\n";
if(!col($pdo,'auto_charge_log','payment_method_id'))
    run($pdo,"ALTER TABLE auto_charge_log ADD COLUMN payment_method_id INT DEFAULT NULL","014 payment_method_id");
else echo "SKIP: 014 payment_method_id already exists\n";
if(!col($pdo,'auto_charge_log','error_message'))
    run($pdo,"ALTER TABLE auto_charge_log ADD COLUMN error_message TEXT DEFAULT NULL","014 error_message");
else echo "SKIP: 014 error_message already exists\n";

echo "\n=== Migration 015: auto_charge_log ENUM ===\n";
$et=ctype($pdo,'auto_charge_log','status');
if($et&&strpos($et,'success')===false)
    run($pdo,"ALTER TABLE auto_charge_log MODIFY COLUMN status ENUM('pending','success','failed','approved','declined','error') DEFAULT 'pending'","015 enum");
else echo "SKIP: 015 enum already has 'success'\n";

echo "\n=== Migration 016: challenges + challenge_participants ===\n";
if(col($pdo,'challenges','name')&&!col($pdo,'challenges','title'))
    run($pdo,"ALTER TABLE challenges CHANGE COLUMN name title VARCHAR(160) NOT NULL","016 name->title");
else echo "SKIP: 016 name rename\n";
if(col($pdo,'challenges','active')&&!col($pdo,'challenges','is_active'))
    run($pdo,"ALTER TABLE challenges CHANGE COLUMN active is_active TINYINT(1) DEFAULT 1","016 active->is_active");
else echo "SKIP: 016 active rename\n";
if(col($pdo,'challenges','target_value')&&!col($pdo,'challenges','goal_value'))
    run($pdo,"ALTER TABLE challenges CHANGE COLUMN target_value goal_value INT UNSIGNED NOT NULL DEFAULT 1","016 target->goal");
else echo "SKIP: 016 target rename\n";
$ct=ctype($pdo,'challenges','challenge_type');
if($ct&&strpos($ct,'weight_loss')===false)
    run($pdo,"ALTER TABLE challenges MODIFY COLUMN challenge_type ENUM('steps','checkins','weight_loss','streak') NOT NULL DEFAULT 'checkins'","016 challenge_type enum");
else echo "SKIP: 016 challenge_type enum ok\n";
if(col($pdo,'challenge_participants','current_value')&&!col($pdo,'challenge_participants','progress'))
    run($pdo,"ALTER TABLE challenge_participants CHANGE COLUMN current_value progress DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0","016 current_value->progress");
else echo "SKIP: 016 current_value rename\n";
if(!col($pdo,'challenge_participants','rank'))
    run($pdo,"ALTER TABLE challenge_participants ADD COLUMN `rank` INT DEFAULT NULL","016 rank");
else echo "SKIP: 016 rank already exists\n";
if(!col($pdo,'challenge_participants','completed_at'))
    run($pdo,"ALTER TABLE challenge_participants ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL","016 completed_at");
else echo "SKIP: 016 completed_at already exists\n";

echo "\n=== Migration 017: coach_messages ===\n";
$sql017 = file_get_contents(__DIR__ . '/017_coach_messages.sql');
$lines017 = explode("\n", $sql017);
$clean017 = implode("\n", array_filter($lines017, fn($l) => !preg_match('/^\s*--/', $l)));
foreach (array_filter(array_map('trim', explode(';', $clean017))) as $stmt) {
    if ($stmt) run($pdo, $stmt, '017 '.substr(trim(preg_replace('/\s+/',' ',$stmt)),0,60));
}

echo "\n=== Migration 018: coach_community_posts + coach_pwa_config ===\n";
$sql018 = file_get_contents(__DIR__ . '/018_coach_community_pwa.sql');
$lines018 = explode("\n", $sql018);
$clean018 = implode("\n", array_filter($lines018, fn($l) => !preg_match('/^\s*--/', $l)));
foreach (array_filter(array_map('trim', explode(';', $clean018))) as $stmt) {
    if ($stmt) run($pdo, $stmt, '018 '.substr(trim(preg_replace('/\s+/',' ',$stmt)),0,60));
}

echo "\n=== Migration 019: client_profiles bio + avatar_url ===\n";
if (!col($pdo, 'client_profiles', 'bio'))
    run($pdo, "ALTER TABLE client_profiles ADD COLUMN bio TEXT DEFAULT NULL", "019 bio");
else echo "SKIP: 019 bio already exists\n";
if (!col($pdo, 'client_profiles', 'avatar_url'))
    run($pdo, "ALTER TABLE client_profiles ADD COLUMN avatar_url VARCHAR(512) DEFAULT NULL", "019 avatar_url");
else echo "SKIP: 019 avatar_url already exists\n";

echo "\n=== Migration 020: client_profiles dashboard_video_url ===\n";
if (!col($pdo, 'client_profiles', 'dashboard_video_url'))
    run($pdo, "ALTER TABLE client_profiles ADD COLUMN dashboard_video_url VARCHAR(512) DEFAULT NULL", "020 dashboard_video_url");
else echo "SKIP: 020 dashboard_video_url already exists\n";

echo "\n=== ALL MIGRATIONS DONE ===\n";
