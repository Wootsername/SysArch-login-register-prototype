<?php
/**
 * Leaderboard API — returns top 10 students by reward points.
 * GET /backend/api/leaderboard.php
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

try {
    $limit = max(1, min((int)($_GET['limit'] ?? 10), 50));

    $stmt = $pdo->prepare("
        SELECT
            u.id_number,
            CONCAT(u.first_name, ' ', u.last_name) AS student_name,
            u.reward_points AS total_points
        FROM users u
        WHERE u.role = 'student'
          AND u.reward_points > 0
        ORDER BY u.reward_points DESC, u.last_name ASC, u.first_name ASC
        LIMIT :lim
    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();

    echo json_encode([
        'success'     => true,
        'leaderboard' => $rows
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch leaderboard: ' . $e->getMessage()
    ]);
}