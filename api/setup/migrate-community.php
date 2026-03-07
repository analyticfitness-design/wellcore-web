<?php
/**
 * Migration: Community + Achievements tables
 * Creates community_posts, community_reactions, achievements
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireSetupAuth();
$db = getDB();

// community_posts
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS community_posts (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id       INT UNSIGNED NOT NULL,
            content         TEXT NOT NULL,
            post_type       ENUM('text','achievement','workout','milestone') DEFAULT 'text',
            achievement_id  INT UNSIGNED NULL,
            parent_id       INT UNSIGNED NULL,
            audience        ENUM('all','rise') DEFAULT 'all',
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
            INDEX idx_audience_date (audience, created_at DESC),
            INDEX idx_parent (parent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Created table: community_posts\n";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'already exists')) {
        echo "Table community_posts already exists\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// community_reactions
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS community_reactions (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id     INT UNSIGNED NOT NULL,
            client_id   INT UNSIGNED NOT NULL,
            emoji       VARCHAR(10) NOT NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_reaction (post_id, client_id, emoji),
            FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Created table: community_reactions\n";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'already exists')) {
        echo "Table community_reactions already exists\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// achievements
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS achievements (
            id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id        INT UNSIGNED NOT NULL,
            achievement_type VARCHAR(50) NOT NULL,
            title            VARCHAR(100) NOT NULL,
            description      VARCHAR(255),
            icon             VARCHAR(20) DEFAULT 'trophy',
            earned_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_achievement (client_id, achievement_type),
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Created table: achievements\n";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'already exists')) {
        echo "Table achievements already exists\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "\nMigration complete.\n";
