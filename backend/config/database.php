<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host = 'localhost';
$dbname = 'ccs_sitin_monitoring';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $columnCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = :schema AND table_name = 'users' AND column_name = 'remaining_sessions'");
    $columnCheck->execute([':schema' => $dbname]);
    if ((int)$columnCheck->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN remaining_sessions INT NOT NULL DEFAULT 30 AFTER password_hash");
    }

    $tableCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :schema AND table_name = 'reservations'");
    $tableCheck->execute([':schema' => $dbname]);
    if ((int)$tableCheck->fetchColumn() === 0) {
        $pdo->exec("CREATE TABLE reservations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            lab_room VARCHAR(50) NOT NULL,
            purpose VARCHAR(255) NOT NULL,
            preferred_date DATE DEFAULT NULL,
            preferred_time TIME DEFAULT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
    }

    $tableCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :schema AND table_name = 'announcements'");
    $tableCheck->execute([':schema' => $dbname]);
    if ((int)$tableCheck->fetchColumn() === 0) {
        $pdo->exec("CREATE TABLE announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(150) NOT NULL,
            body TEXT NOT NULL,
            audience ENUM('student', 'admin', 'all') NOT NULL DEFAULT 'student',
            posted_by VARCHAR(100) NOT NULL DEFAULT 'CCS Admin',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    }

    $tableCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :schema AND table_name = 'feedback_entries'");
    $tableCheck->execute([':schema' => $dbname]);
    if ((int)$tableCheck->fetchColumn() === 0) {
        $pdo->exec("CREATE TABLE feedback_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            subject VARCHAR(150) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('new', 'reviewed') NOT NULL DEFAULT 'new',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}
