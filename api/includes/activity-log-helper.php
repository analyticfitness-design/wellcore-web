<?php
/**
 * Activity Log Helper
 * Utilities for logging admin activity
 */

function logActivityFeedUsage($pdo, $admin_id, $action, $filters = []) {
    try {
        $sql = "INSERT INTO `admin_activity_log` (`admin_id`, `action`, `filters_used`)
                VALUES (?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $admin_id,
            $action,
            json_encode($filters)
        ]);

        return true;
    } catch (Exception $e) {
        error_log('Activity log error: ' . $e->getMessage());
        return false;
    }
}
