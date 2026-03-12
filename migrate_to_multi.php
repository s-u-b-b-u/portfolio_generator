<?php
require_once 'db_connect.php';

try {
    // 1. Structural Changes (DDL doesn't support transactions in MySQL anyway)
    echo "Updating schema...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS portfolios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        job_title VARCHAR(255),
        location VARCHAR(255),
        phone VARCHAR(20),
        bio TEXT,
        skills TEXT,
        education TEXT,
        experience TEXT,
        projects_text TEXT,
        linkedin_url VARCHAR(255),
        github_url VARCHAR(255),
        twitter_url VARCHAR(255),
        profile_pic VARCHAR(255) DEFAULT 'default_avatar.png',
        hero_bg VARCHAR(255) DEFAULT 'default_bg.jpg',
        theme_choice ENUM('Professional Dark', 'Minimalist Light', 'Academic Blue', 'Modern Glass') DEFAULT 'Minimalist Light',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Check if column exists before adding
    $cols = $pdo->query("DESCRIBE projects")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('portfolio_id', $cols)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN portfolio_id INT AFTER user_id");
    }

    // 2. Data Migration
    echo "Migrating data...\n";
    $stmt = $pdo->query("SELECT * FROM users");
    $users = $stmt->fetchAll();
    
    foreach ($users as $user) {
        // Only create if not exists
        $check = $pdo->prepare("SELECT id FROM portfolios WHERE user_id = ? LIMIT 1");
        $check->execute([$user['id']]);
        $existing = $check->fetch();
        
        if (!$existing) {
            $ins = $pdo->prepare("INSERT INTO portfolios 
                (user_id, name, email, job_title, location, phone, bio, skills, education, experience, projects_text, linkedin_url, github_url, twitter_url, profile_pic, hero_bg, theme_choice)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([
                $user['id'], $user['name'], $user['email'], $user['job_title'], $user['location'], $user['phone'],
                $user['bio'], $user['skills'], $user['education'], $user['experience'], $user['projects_text'],
                $user['linkedin_url'], $user['github_url'], $user['twitter_url'], $user['profile_pic'], $user['hero_bg'], $user['theme_choice']
            ]);
            $portfolio_id = $pdo->lastInsertId();
            
            // Link projects
            $link = $pdo->prepare("UPDATE projects SET portfolio_id = ? WHERE user_id = ?");
            $link->execute([$portfolio_id, $user['id']]);
            echo "Migrated user {$user['id']} to portfolio $portfolio_id\n";
        } else {
            echo "User {$user['id']} already has a portfolio entry ({$existing['id']}). Skipping.\n";
        }
    }

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
