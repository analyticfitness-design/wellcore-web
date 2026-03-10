<?php
/**
 * Migration 017: Create admin_activity_log table
 * Tracks admin activity feed usage for auditing
 */

class Migration017 {
    public static function up($pdo) {
        $sql = "CREATE TABLE IF NOT EXISTS `admin_activity_log` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `admin_id` INT UNSIGNED NOT NULL,
            `action` VARCHAR(50) NOT NULL,
            `filters_used` JSON,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_admin_id` (`admin_id`),
            KEY `idx_action` (`action`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $pdo->exec($sql);
        return true;
    }

    public static function down($pdo) {
        $pdo->exec("DROP TABLE IF EXISTS `admin_activity_log`");
        return true;
    }
}
