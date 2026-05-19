<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

if (php_sapi_name() === 'cli') {
    return;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin($pdo);
        break;
    case 'register':
        handleRegister($pdo);
        break;
    case 'student_dashboard':
        getStudentDashboard($pdo);
        break;
    case 'admin_dashboard':
        getAdminDashboard($pdo);
        break;
    case 'students':
        getStudents($pdo);
        break;
    case 'search_students':
        searchStudents($pdo);
        break;
    case 'create_sitin':
        createSitin($pdo);
        break;
    case 'create_reservation':
        createReservation($pdo);
        break;
    case 'student_reservations':
        getStudentReservations($pdo);
        break;
    case 'admin_reservations':
        getAdminReservations($pdo);
        break;
    case 'approve_reservation':
        approveReservation($pdo);
        break;
    case 'reject_reservation':
        rejectReservation($pdo);
        break;
    case 'current_sitin_records':
        getCurrentSitinRecords($pdo);
        break;
    case 'admin_session_history':
        getAdminSessionHistory($pdo);
        break;
    case 'active_sessions':
        getActiveSessions($pdo);
        break;
    case 'stats':
        getStats($pdo);
        break;
    case 'announcements':
        getAnnouncements($pdo);
        break;
    case 'get_lab_software':
        getLabSoftware($pdo);
        break;
    case 'import_lab_software':
        importLabSoftware($pdo);
        break;
    case 'get_system_settings':
        getSystemSettings($pdo);
        break;
    case 'toggle_reservations':
        toggleReservations($pdo);
        break;
    case 'admin_pc_status':
        getAdminPcStatus($pdo);
        break;
    case 'admin_force_logout':
        adminForceLogout($pdo);
        break;
        break;
    case 'create_announcement':
        createAnnouncement($pdo);
        break;
    case 'feedbacks':
        getFeedbacks($pdo);
        break;
    case 'submit_feedback':
        submitFeedback($pdo);
        break;
    case 'review_feedback':
        reviewFeedback($pdo);
        break;
    case 'delete_feedback':
        deleteFeedback($pdo);
        break;
    case 'delete_announcement':
        deleteAnnouncement($pdo);
        break;
    case 'end_session':
        endSession($pdo);
        break;
    case 'reward_history':
        getRewardHistory($pdo);
        break;
    case 'award_reward_points':
        awardRewardPointsManually($pdo);
        break;
    case 'reset_sessions':
        resetStudentSessions($pdo);
        break;
    case 'lab_occupancy':
        getLabOccupancy($pdo);
        break;
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function fetchAnnouncements(PDO $pdo, string $audience = 'student', int $limit = 10): array
{
    $audience = in_array($audience, ['student', 'admin', 'all'], true) ? $audience : 'student';
    $limit = max(1, min($limit, 50));

    if ($audience === 'all') {
        $stmt = $pdo->query("SELECT id, title, body, audience, posted_by, created_at, updated_at
            FROM announcements
            ORDER BY created_at DESC
            LIMIT $limit");
        return $stmt->fetchAll();
    }

    $stmt = $pdo->prepare("SELECT id, title, body, audience, posted_by, created_at, updated_at
        FROM announcements
        WHERE audience = 'all' OR audience = :audience
        ORDER BY created_at DESC
        LIMIT $limit");
    $stmt->execute([':audience' => $audience]);

    return $stmt->fetchAll();
}

function getAnnouncements(PDO $pdo): void
{
    $audience = trim(strtolower($_GET['audience'] ?? 'student'));
    if (!in_array($audience, ['student', 'admin', 'all'], true)) {
        $audience = 'student';
    }

    echo json_encode([
        'success' => true,
        'announcements' => fetchAnnouncements($pdo, $audience, 20),
    ]);
}

function createAnnouncement(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $title = trim((string)($data['title'] ?? ''));
    $body = trim((string)($data['body'] ?? ''));
    $audience = trim(strtolower((string)($data['audience'] ?? 'all')));
    $postedBy = trim((string)($data['posted_by'] ?? 'CCS Admin')) ?: 'CCS Admin';

    if ($title === '' || $body === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Title and message are required']);
        return;
    }

    if (!in_array($audience, ['student', 'admin', 'all'], true)) {
        $audience = 'all';
    }

    $stmt = $pdo->prepare("INSERT INTO announcements (title, body, audience, posted_by) VALUES (:title, :body, :audience, :posted_by)");
    $stmt->execute([
        ':title' => $title,
        ':body' => $body,
        ':audience' => $audience,
        ':posted_by' => $postedBy,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Announcement posted successfully',
    ]);
}

function deleteAnnouncement(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Announcement ID is required']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Announcement deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Announcement not found']);
    }
}

function calculateRewardPoints(int $durationSeconds): int
{
    return max(1, (int)ceil($durationSeconds / 1800));
}

function awardRewardPoints(PDO $pdo, int $userId, int $sourceId, int $points, string $description, string $sourceType = 'sitin'): void
{
    if ($points <= 0) {
        return;
    }

    $pdo->prepare("UPDATE users SET reward_points = reward_points + :points WHERE id = :user_id")
        ->execute([
            ':points' => $points,
            ':user_id' => $userId,
        ]);

    $pdo->prepare("INSERT INTO reward_events (user_id, source_type, source_id, points, description)
        VALUES (:user_id, :source_type, :source_id, :points, :description)")
        ->execute([
            ':user_id' => $userId,
            ':source_type' => $sourceType,
            ':source_id' => $sourceId,
            ':points' => $points,
            ':description' => $description,
        ]);
}

function getRewardHistory(PDO $pdo): void
{
    $idNumber = trim($_GET['id_number'] ?? '');
    $limit = max(1, min((int)($_GET['limit'] ?? 25), 100));

    $sql = "SELECT r.id, r.user_id, r.source_type, r.source_id, r.points, r.description, r.created_at,
            u.id_number, u.first_name, u.last_name, u.course, u.year_level
        FROM reward_events r
        INNER JOIN users u ON u.id = r.user_id";
    $params = [];

    if ($idNumber !== '') {
        $sql .= " WHERE u.id_number = :id_number";
        $params[':id_number'] = $idNumber;
    }

    $sql .= " ORDER BY r.created_at DESC LIMIT $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'rewards' => $stmt->fetchAll(),
    ]);
}

function awardRewardPointsManually(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $idNumber = trim((string)($data['id_number'] ?? ''));
    $points = (int)($data['points'] ?? 0);
    $description = trim((string)($data['description'] ?? ''));

    if ($idNumber === '' || $points <= 0 || $description === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Number, points, and description are required']);
        return;
    }

    $userStmt = $pdo->prepare("SELECT id FROM users WHERE id_number = :id_number AND role = 'student' LIMIT 1");
    $userStmt->execute([':id_number' => $idNumber]);
    $userId = (int)($userStmt->fetchColumn() ?: 0);

    if ($userId <= 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }

    awardRewardPoints($pdo, $userId, 0, $points, $description, 'manual');

    $pointsStmt = $pdo->prepare("SELECT reward_points FROM users WHERE id = :id LIMIT 1");
    $pointsStmt->execute([':id' => $userId]);

    echo json_encode([
        'success' => true,
        'message' => 'Reward points awarded successfully',
        'reward_points' => (int)($pointsStmt->fetchColumn() ?: 0),
    ]);
}

function resetStudentSessions(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $idNumber = trim((string)($data['id_number'] ?? ''));
    $remainingSessions = (int)($data['remaining_sessions'] ?? 30);

    if ($idNumber === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Number is required']);
        return;
    }

    if ($remainingSessions < 0) {
        $remainingSessions = 0;
    }

    $stmt = $pdo->prepare("UPDATE users SET remaining_sessions = :remaining_sessions WHERE id_number = :id_number AND role = 'student'");
    $stmt->execute([
        ':remaining_sessions' => $remainingSessions,
        ':id_number' => $idNumber,
    ]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }

    $sessionsStmt = $pdo->prepare("SELECT remaining_sessions FROM users WHERE id_number = :id_number AND role = 'student' LIMIT 1");
    $sessionsStmt->execute([':id_number' => $idNumber]);

    echo json_encode([
        'success' => true,
        'message' => 'Sessions reset successfully',
        'remaining_sessions' => (int)($sessionsStmt->fetchColumn() ?: $remainingSessions),
    ]);
}

function getRewardLeaderboard(PDO $pdo, int $limit = 10): array
{
    $limit = max(1, min($limit, 50));
    $stmt = $pdo->query("SELECT
            u.id,
            u.id_number,
            u.first_name,
            u.last_name,
            u.course,
            u.year_level,
            u.remaining_sessions,
            u.reward_points,
            COUNT(CASE WHEN s.status = 'completed' THEN 1 END) AS completed_sessions,
            COALESCE(SUM(CASE WHEN s.status = 'completed' THEN TIMESTAMPDIFF(SECOND, s.time_in, COALESCE(s.time_out, NOW())) ELSE 0 END), 0) AS total_seconds
        FROM users u
        LEFT JOIN sitin_sessions s ON s.user_id = u.id
        WHERE u.role = 'student'
        GROUP BY u.id, u.id_number, u.first_name, u.last_name, u.course, u.year_level, u.remaining_sessions, u.reward_points
    ");

    $rows = $stmt->fetchAll();
    if (!$rows) {
        return [];
    }

    $pointValues = array_map(fn ($row) => (int)$row['reward_points'], $rows);
    $secondValues = array_map(fn ($row) => (int)$row['total_seconds'], $rows);
    $completedValues = array_map(fn ($row) => (int)$row['completed_sessions'], $rows);
    $maxPoints = max(1, max($pointValues));
    $maxSeconds = max(1, max($secondValues));
    $maxCompleted = max(1, max($completedValues));

    foreach ($rows as &$row) {
        $pointScore = ((int)$row['reward_points'] / $maxPoints) * 50;
        $hourScore = ((int)$row['total_seconds'] / $maxSeconds) * 30;
        $taskScore = ((int)$row['completed_sessions'] / $maxCompleted) * 20;
        $row['earned_points_score'] = round($pointScore, 1);
        $row['sit_in_hours_score'] = round($hourScore, 1);
        $row['task_completed_score'] = round($taskScore, 1);
        $row['leaderboard_score'] = round($pointScore + $hourScore + $taskScore, 1);
        $row['total_hours'] = round(((int)$row['total_seconds']) / 3600, 1);
    }
    unset($row);

    usort($rows, function ($left, $right) {
        return ($right['leaderboard_score'] <=> $left['leaderboard_score'])
            ?: ((int)$right['reward_points'] <=> (int)$left['reward_points'])
            ?: ((int)$right['completed_sessions'] <=> (int)$left['completed_sessions'])
            ?: ((int)$right['total_seconds'] <=> (int)$left['total_seconds'])
            ?: strcmp((string)$left['last_name'], (string)$right['last_name'])
            ?: strcmp((string)$left['first_name'], (string)$right['first_name']);
    });

    return array_slice($rows, 0, $limit);
}

function buildStudentNotifications(array $announcements, array $reservations, array $feedbacks, array $rewards): array
{
    $notifications = [];

    foreach ($announcements as $announcement) {
        $notifications[] = [
            'type' => 'announcement',
            'title' => $announcement['title'] ?? 'Announcement',
            'body' => $announcement['body'] ?? '',
            'created_at' => $announcement['created_at'] ?? null,
            'status' => 'info',
        ];
    }

    foreach ($reservations as $reservation) {
        $status = $reservation['status'] ?? 'pending';
        $notifications[] = [
            'type' => 'reservation',
            'title' => 'Reservation ' . ucfirst($status),
            'body' => sprintf('%s in %s for %s', strtoupper((string)($reservation['lab_room'] ?? '-')), (string)($reservation['preferred_date'] ?? '-'), (string)($reservation['purpose'] ?? 'your request')),
            'created_at' => $reservation['created_at'] ?? null,
            'status' => $status,
        ];
    }

    foreach ($feedbacks as $feedback) {
        $notifications[] = [
            'type' => 'feedback',
            'title' => 'Feedback ' . ucfirst((string)($feedback['status'] ?? 'new')),
            'body' => (string)($feedback['subject'] ?? 'Feedback') . ': ' . (string)($feedback['message'] ?? ''),
            'created_at' => $feedback['created_at'] ?? null,
            'status' => $feedback['status'] ?? 'new',
        ];
    }

    foreach ($rewards as $reward) {
        $notifications[] = [
            'type' => 'reward',
            'title' => 'Reward +' . ((int)($reward['points'] ?? 0)) . ' points',
            'body' => (string)($reward['description'] ?? 'Reward activity'),
            'created_at' => $reward['created_at'] ?? null,
            'status' => 'success',
        ];
    }

    usort($notifications, function ($left, $right) {
        return strcmp((string)($right['created_at'] ?? ''), (string)($left['created_at'] ?? ''));
    });

    return array_slice($notifications, 0, 30);
}

function getFeedbacks(PDO $pdo): void
{
    $stmt = $pdo->query("SELECT f.id, f.user_id, f.subject, f.message, f.status, f.created_at, f.updated_at,
            u.id_number, u.first_name, u.last_name, u.course, u.year_level, u.email
        FROM feedback_entries f
        INNER JOIN users u ON u.id = f.user_id
        ORDER BY f.created_at DESC
        LIMIT 100");

    echo json_encode([
        'success' => true,
        'feedbacks' => $stmt->fetchAll(),
    ]);
}

function submitFeedback(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $idNumber = trim((string)($data['id_number'] ?? ''));
    $subject = trim((string)($data['subject'] ?? ''));
    $message = trim((string)($data['message'] ?? ''));

    if ($idNumber === '' || $subject === '' || $message === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Number, subject, and message are required']);
        return;
    }

    $userStmt = $pdo->prepare("SELECT id FROM users WHERE id_number = :id_number AND role = 'student' LIMIT 1");
    $userStmt->execute([':id_number' => $idNumber]);
    $userId = (int)($userStmt->fetchColumn() ?: 0);

    if ($userId <= 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }

    $stmt = $pdo->prepare("INSERT INTO feedback_entries (user_id, subject, message, status) VALUES (:user_id, :subject, :message, 'new')");
    $stmt->execute([
        ':user_id' => $userId,
        ':subject' => $subject,
        ':message' => $message,
    ]);

    echo json_encode(['success' => true, 'message' => 'Feedback submitted successfully']);
}

function reviewFeedback(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $feedbackId = (int)($data['feedback_id'] ?? 0);
    if ($feedbackId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid feedback ID']);
        return;
    }

    $stmt = $pdo->prepare("UPDATE feedback_entries SET status = 'reviewed' WHERE id = :id");
    $stmt->execute([':id' => $feedbackId]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Feedback not found or already reviewed']);
        return;
    }

    echo json_encode(['success' => true, 'message' => 'Feedback marked as reviewed']);
}

function deleteFeedback(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Feedback ID is required']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM feedback_entries WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Feedback deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Feedback not found']);
    }
}

function handleLogin(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $idNumber = trim($data['id_number'] ?? '');
    $password = $data['password'] ?? '';

    if (empty($idNumber) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Number and Password are required']);
        return;
    }

    $stmt = $pdo->prepare("SELECT id, id_number, first_name, last_name, course, year_level, email, password_hash, remaining_sessions, reward_points, role FROM users WHERE id_number = :id_number");
    $stmt->execute([':id_number' => $idNumber]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid ID Number or Password']);
        return;
    }

    unset($user['password_hash']);
    echo json_encode(['success' => true, 'message' => 'Login successful', 'user' => $user]);
}

function handleRegister(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    $required = ['id_number', 'last_name', 'first_name', 'course', 'year_level', 'email', 'password', 'confirm_password'];
    foreach ($required as $field) {
        if (empty(trim($data[$field] ?? ''))) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
            return;
        }
    }

    if ($data['password'] !== $data['confirm_password']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        return;
    }

    if (strlen($data['password']) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        return;
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        return;
    }

    $allowedCourses = ['BSIT', 'BSCS', 'BSIS', 'ACT'];
    if (!in_array($data['course'], $allowedCourses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid course selected']);
        return;
    }

    $yearLevel = (int)$data['year_level'];
    if ($yearLevel < 1 || $yearLevel > 4) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Year level must be between 1 and 4']);
        return;
    }

    // Check for duplicate ID number or email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id_number = :id_number OR email = :email");
    $stmt->execute([':id_number' => trim($data['id_number']), ':email' => trim($data['email'])]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'ID Number or Email already registered']);
        return;
    }

    $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("INSERT INTO users (id_number, last_name, first_name, middle_name, course, year_level, email, password_hash, address, remaining_sessions, reward_points)
        VALUES (:id_number, :last_name, :first_name, :middle_name, :course, :year_level, :email, :password_hash, :address, 30, 0)");


    $stmt->execute([
        ':id_number'   => trim($data['id_number']),
        ':last_name'   => trim($data['last_name']),
        ':first_name'  => trim($data['first_name']),
        ':middle_name' => trim($data['middle_name'] ?? ''),
        ':course'      => $data['course'],
        ':year_level'  => $yearLevel,
        ':email'       => trim($data['email']),
        ':password_hash' => $passwordHash,
        ':address'     => trim($data['address'] ?? ''),
    ]);

    echo json_encode(['success' => true, 'message' => 'Registration successful! You can now login.']);
}

function getActiveSessions(PDO $pdo): void
{
    activateScheduledReservations($pdo);
    $stmt = $pdo->prepare("
        SELECT s.id, s.lab_room, s.purpose, s.time_in, s.status,
               u.id_number, u.first_name, u.last_name, u.course, u.year_level, u.remaining_sessions,
               TIMESTAMPDIFF(SECOND, s.time_in, NOW()) AS elapsed_seconds,
               SEC_TO_TIME(TIMESTAMPDIFF(SECOND, s.time_in, NOW())) AS elapsed_time
        FROM sitin_sessions s
        JOIN users u ON s.user_id = u.id
        WHERE s.status = 'active'
        ORDER BY s.time_in DESC
    ");
    $stmt->execute();
    $sessions = $stmt->fetchAll();

    echo json_encode(['success' => true, 'sessions' => $sessions]);
}

function getStats(PDO $pdo): void
{
    $activeCount = $pdo->query("SELECT COUNT(*) FROM sitin_sessions WHERE status = 'active'")->fetchColumn();
    $todayCount = $pdo->query("SELECT COUNT(*) FROM sitin_sessions WHERE DATE(time_in) = CURDATE()")->fetchColumn();
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $pendingReservations = $pdo->query("SELECT COUNT(*) FROM reservations WHERE LOWER(TRIM(status)) = 'pending'")->fetchColumn();

    echo json_encode([
        'success' => true,
        'stats' => [
            'active_sessions' => (int)$activeCount,
            'today_sessions' => (int)$todayCount,
            'total_students' => (int)$totalStudents,
            'pending_reservations' => (int)$pendingReservations
        ]
    ]);
}

function endSession(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $sessionId = (int)($data['session_id'] ?? 0);

    if ($sessionId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid session ID']);
        return;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id, user_id, time_in FROM sitin_sessions WHERE id = :id AND status = 'active' LIMIT 1 FOR UPDATE");
        $stmt->execute([':id' => $sessionId]);
        $session = $stmt->fetch();

        if (!$session) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Session not found or already ended']);
            return;
        }

        $updateStmt = $pdo->prepare("UPDATE sitin_sessions SET status = 'completed', time_out = NOW() WHERE id = :id AND status = 'active'");
        $updateStmt->execute([':id' => $sessionId]);

        if ($updateStmt->rowCount() === 0) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Session not found or already ended']);
            return;
        }

        $durationStmt = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, time_in, NOW()) FROM sitin_sessions WHERE id = :id LIMIT 1");
        $durationStmt->execute([':id' => $sessionId]);
        $durationSeconds = (int)($durationStmt->fetchColumn() ?: 0);
        $points = calculateRewardPoints($durationSeconds);
        $description = 'Completed sit-in session #' . $sessionId;
        awardRewardPoints($pdo, (int)$session['user_id'], $sessionId, $points, $description, 'sitin');

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to end session: ' . $e->getMessage()]);
        return;
    }

    echo json_encode(['success' => true, 'message' => 'Session ended successfully']);
}

function getAdminDashboard(PDO $pdo): void
{
    activateScheduledReservations($pdo);
    $totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $currentlySitin = (int)$pdo->query("SELECT COUNT(*) FROM sitin_sessions WHERE status = 'active'")->fetchColumn();
    $totalSitin = (int)$pdo->query("SELECT COUNT(*) FROM sitin_sessions")->fetchColumn();
    $totalRewardPoints = (int)$pdo->query("SELECT COALESCE(SUM(reward_points), 0) FROM users WHERE role = 'student'")->fetchColumn();

    $languageLabels = [
        'C Programming',
        'Java',
        'Python',
        'Web Development',
        'Database',
        'Other'
    ];

    $purposeStmt = $pdo->query("SELECT purpose FROM sitin_sessions");
    $languageCounts = array_fill_keys($languageLabels, 0);

    foreach ($purposeStmt->fetchAll() as $row) {
        $purpose = strtolower(trim((string)($row['purpose'] ?? '')));
        if ($purpose === '') {
            continue;
        }

        if (str_contains($purpose, 'c programming') || preg_match('/\bc\b/', $purpose)) {
            $languageCounts['C Programming']++;
        } elseif (str_contains($purpose, 'java')) {
            $languageCounts['Java']++;
        } elseif (str_contains($purpose, 'python')) {
            $languageCounts['Python']++;
        } elseif (str_contains($purpose, 'web') || str_contains($purpose, 'html') || str_contains($purpose, 'css') || str_contains($purpose, 'javascript') || str_contains($purpose, 'php')) {
            $languageCounts['Web Development']++;
        } elseif (str_contains($purpose, 'database') || str_contains($purpose, 'sql')) {
            $languageCounts['Database']++;
        } else {
            $languageCounts['Other']++;
        }
    }

    echo json_encode([
        'success' => true,
        'stats' => [
            'registered_students' => $totalStudents,
            'currently_sitin' => $currentlySitin,
            'total_sitin' => $totalSitin,
            'total_reward_points' => $totalRewardPoints,
        ],
        'language_usage' => $languageCounts,
        'leaderboard' => getRewardLeaderboard($pdo, 10),
    ]);
}

function getStudents(PDO $pdo): void
{
    $stmt = $pdo->query("SELECT id, id_number, last_name, first_name, middle_name, course, year_level, email, address
        FROM users
        WHERE role = 'student'
        ORDER BY last_name ASC, first_name ASC");

    echo json_encode([
        'success' => true,
        'students' => $stmt->fetchAll()
    ]);
}

function searchStudents(PDO $pdo): void
{
    $query = trim($_GET['query'] ?? '');

    if ($query === '') {
        getStudents($pdo);
        return;
    }

    $like = '%' . $query . '%';
    $stmt = $pdo->prepare("SELECT id, id_number, last_name, first_name, middle_name, course, year_level, email, address
        FROM users
        WHERE role = 'student'
          AND (
              id_number LIKE :like_id
              OR first_name LIKE :like_first
              OR last_name LIKE :like_last
              OR middle_name LIKE :like_middle
              OR email LIKE :like_email
              OR course LIKE :like_course
          )
        ORDER BY last_name ASC, first_name ASC
        LIMIT 50");
    $stmt->execute([
        ':like_id' => $like,
        ':like_first' => $like,
        ':like_last' => $like,
        ':like_middle' => $like,
        ':like_email' => $like,
        ':like_course' => $like,
    ]);

    echo json_encode([
        'success' => true,
        'students' => $stmt->fetchAll()
    ]);
}

function createSitin(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    $idNumber = trim((string)($data['id_number'] ?? ''));
    $labRoom = trim((string)($data['lab_room'] ?? ''));
    $purpose = trim((string)($data['purpose'] ?? ''));

    if ($idNumber === '' || $labRoom === '' || $purpose === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Number, Lab Room, and Purpose are required']);
        return;
    }

    // Validate PC Selection
    if (!preg_match('/^S([1-9]|[1-4][0-9]|50)$/', $labRoom)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid PC selection']);
        return;
    }

    $userStmt = $pdo->prepare("SELECT id FROM users WHERE id_number = :id_number AND role = 'student' LIMIT 1");
    $userStmt->execute([':id_number' => $idNumber]);
    $user = $userStmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }

    $remainingStmt = $pdo->prepare("SELECT remaining_sessions FROM users WHERE id = :user_id LIMIT 1");
    $remainingStmt->execute([':user_id' => (int)$user['id']]);
    $remainingSessions = (int)($remainingStmt->fetchColumn() ?: 0);
    if ($remainingSessions <= 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'No remaining sessions left']);
        return;
    }

    // Check PC occupancy first
    $pcCheck = $pdo->prepare("SELECT id FROM sitin_sessions WHERE lab_room = :lab_room AND status = 'active' LIMIT 1");
    $pcCheck->execute([':lab_room' => $labRoom]);
    if ($pcCheck->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'PC ' . $labRoom . ' is already occupied by an active session']);
        return;
    }

    $activeCheck = $pdo->prepare("SELECT id FROM sitin_sessions WHERE user_id = :user_id AND status = 'active' LIMIT 1");
    $activeCheck->execute([':user_id' => (int)$user['id']]);
    if ($activeCheck->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Student already has an active sit-in session']);
        return;
    }

    $insertStmt = $pdo->prepare("INSERT INTO sitin_sessions (user_id, lab_room, purpose, status, time_in)
        VALUES (:user_id, :lab_room, :purpose, 'active', NOW())");
    $insertStmt->execute([
        ':user_id' => (int)$user['id'],
        ':lab_room' => $labRoom,
        ':purpose' => $purpose,
    ]);

    $pdo->prepare("UPDATE users SET remaining_sessions = remaining_sessions - 1 WHERE id = :user_id AND remaining_sessions > 0")
        ->execute([':user_id' => (int)$user['id']]);

    echo json_encode(['success' => true, 'message' => 'Sit-in session created successfully']);
}

function getCurrentSitinRecords(PDO $pdo): void
{
    activateScheduledReservations($pdo);
    $stmt = $pdo->prepare("SELECT
            s.id,
            s.time_in,
            s.time_out,
            s.lab_room,
            s.purpose,
            s.status,
            u.id_number,
            u.first_name,
            u.last_name,
            u.course,
            u.year_level,
            u.remaining_sessions,
            TIMESTAMPDIFF(SECOND, s.time_in, NOW()) AS elapsed_seconds,
            SEC_TO_TIME(TIMESTAMPDIFF(SECOND, s.time_in, NOW())) AS elapsed_time
        FROM sitin_sessions s
        INNER JOIN users u ON u.id = s.user_id
        WHERE s.status = 'active'
        ORDER BY s.time_in DESC");
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'records' => $stmt->fetchAll()
    ]);
}

function getAdminSessionHistory(PDO $pdo): void
{
    $stmt = $pdo->prepare("SELECT
            s.id,
            s.lab_room,
            s.purpose,
            s.status,
            s.time_in,
            s.time_out,
            u.id_number,
            u.first_name,
            u.last_name,
            u.remaining_sessions,
            TIMESTAMPDIFF(SECOND, s.time_in, COALESCE(s.time_out, NOW())) AS duration_seconds,
            SEC_TO_TIME(TIMESTAMPDIFF(SECOND, s.time_in, COALESCE(s.time_out, NOW()))) AS duration_time
        FROM sitin_sessions s
        INNER JOIN users u ON u.id = s.user_id
        ORDER BY s.time_in DESC
        LIMIT 100");
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'history' => $stmt->fetchAll()
    ]);
}

function getStudentDashboard(PDO $pdo): void
{
    $idNumber = trim($_GET['id_number'] ?? '');
    if ($idNumber === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID number is required']);
        return;
    }

    $userStmt = $pdo->prepare("SELECT id, id_number, last_name, first_name, middle_name, course, year_level, email, address, remaining_sessions, reward_points, role FROM users WHERE id_number = :id_number AND role = 'student' LIMIT 1");
    $userStmt->execute([':id_number' => $idNumber]);
    $user = $userStmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }

    // Auto-activate scheduled reservations for this user
    activateScheduledReservations($pdo, (int)$user['id']);

    // Re-fetch user to get latest remaining_sessions
    $userStmt->execute([':id_number' => $idNumber]);
    $user = $userStmt->fetch();

    $activeStmt = $pdo->prepare("SELECT id, lab_room, purpose, status, time_in, time_out, TIMESTAMPDIFF(SECOND, time_in, NOW()) AS duration_seconds, SEC_TO_TIME(TIMESTAMPDIFF(SECOND, time_in, NOW())) AS duration_time FROM sitin_sessions WHERE user_id = :user_id AND status = 'active' ORDER BY time_in DESC");
    $activeStmt->execute([':user_id' => (int)$user['id']]);

    $historyStmt = $pdo->prepare("SELECT id, lab_room, purpose, status, time_in, time_out, TIMESTAMPDIFF(SECOND, time_in, COALESCE(time_out, NOW())) AS duration_seconds, SEC_TO_TIME(TIMESTAMPDIFF(SECOND, time_in, COALESCE(time_out, NOW()))) AS duration_time FROM sitin_sessions WHERE user_id = :user_id ORDER BY time_in DESC LIMIT 25");
    $historyStmt->execute([':user_id' => (int)$user['id']]);

    $reservationStmt = $pdo->prepare("SELECT id, lab_room, purpose, preferred_date, preferred_time, status, created_at FROM reservations WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 25");
    $reservationStmt->execute([':user_id' => (int)$user['id']]);

    $announcements = fetchAnnouncements($pdo, 'student', 10);
    $feedbackStmt = $pdo->prepare("SELECT id, subject, message, status, created_at FROM feedback_entries WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 10");
    $feedbackStmt->execute([':user_id' => (int)$user['id']]);
    $rewardStmt = $pdo->prepare("SELECT id, source_type, source_id, points, description, created_at FROM reward_events WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 10");
    $rewardStmt->execute([':user_id' => (int)$user['id']]);
    $reservations = $reservationStmt->fetchAll();
    $feedbacks = $feedbackStmt->fetchAll();
    $rewards = $rewardStmt->fetchAll();
    $leaderboard = getRewardLeaderboard($pdo, 50);
    $notifications = buildStudentNotifications($announcements, $reservations, $feedbacks, $rewards);

    $statsStmt = $pdo->prepare("SELECT COUNT(id) as session_count, 
           COALESCE(SUM(TIMESTAMPDIFF(SECOND, time_in, COALESCE(time_out, NOW()))), 0) as total_seconds,
           COALESCE(MAX(TIMESTAMPDIFF(SECOND, time_in, COALESCE(time_out, NOW()))), 0) as longest_seconds
           FROM sitin_sessions WHERE user_id = :user_id AND status = 'completed'");
    $statsStmt->execute([':user_id' => (int)$user['id']]);
    $summaryRaw = $statsStmt->fetch();

    $sessionCount = (int)$summaryRaw['session_count'];
    $totalSeconds = (int)$summaryRaw['total_seconds'];
    $longestSeconds = (int)$summaryRaw['longest_seconds'];
    $averageSeconds = $sessionCount > 0 ? (int)round($totalSeconds / $sessionCount) : 0;

    $summary_stats = [
        'session_count' => $sessionCount,
        'total_hours' => round($totalSeconds / 3600, 1),
        'average_duration_formatted' => formatDurationLabel($averageSeconds),
        'longest_session_formatted' => formatDurationLabel($longestSeconds),
    ];

    unset($user['role']);

    echo json_encode([
        'success' => true,
        'user' => $user,
        'summary_stats' => $summary_stats,
        'active_sessions' => $activeStmt->fetchAll(),
        'history' => $historyStmt->fetchAll(),
        'reservations' => $reservations,
        'announcements' => $announcements,
        'feedback' => $feedbacks,
        'rewards' => $rewards,
        'leaderboard' => $leaderboard,
        'notifications' => $notifications,
    ]);
}

function getLabSoftware(PDO $pdo): void
{
    $stmt = $pdo->query("SELECT id, lab_room, software_name FROM lab_software ORDER BY lab_room, software_name");
    echo json_encode([
        'success' => true,
        'software' => $stmt->fetchAll()
    ]);
}

function importLabSoftware(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $labRoom = trim((string)($data['lab_room'] ?? ''));
    $softwareList = $data['software_list'] ?? [];

    if ($labRoom === '' || !is_array($softwareList) || empty($softwareList)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Lab room and software list are required']);
        return;
    }

    $pdo->prepare("DELETE FROM lab_software WHERE lab_room = :lab")->execute([':lab' => $labRoom]);

    $stmt = $pdo->prepare("INSERT INTO lab_software (lab_room, software_name) VALUES (:lab, :software)");
    foreach ($softwareList as $sw) {
        if (trim((string)$sw) !== '') {
            $stmt->execute([':lab' => $labRoom, ':software' => trim((string)$sw)]);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Software imported successfully']);
}

function getSystemSettings(PDO $pdo): void
{
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    echo json_encode(['success' => true, 'settings' => $settings]);
}

function toggleReservations(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $enabled = isset($data['enabled']) && $data['enabled'] ? '1' : '0';
    
    $pdo->prepare("UPDATE system_settings SET setting_value = :val WHERE setting_key = 'reservations_enabled'")->execute([':val' => $enabled]);
    echo json_encode(['success' => true, 'message' => 'Reservation settings updated']);
}

function getAdminPcStatus(PDO $pdo): void
{
    $pc = trim($_GET['pc'] ?? '');
    if ($pc === '') {
        http_response_code(400);
        return;
    }

    $stmt = $pdo->prepare("SELECT s.id, s.user_id, s.purpose, s.time_in, TIMESTAMPDIFF(SECOND, s.time_in, NOW()) AS duration_seconds, u.first_name, u.last_name, u.id_number 
        FROM sitin_sessions s 
        INNER JOIN users u ON u.id = s.user_id 
        WHERE s.lab_room = :pc AND s.status = 'active' LIMIT 1");
    $stmt->execute([':pc' => $pc]);
    $activeSession = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'active_session' => $activeSession ?: null
    ]);
}

function adminForceLogout(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $sessionId = (int)($data['session_id'] ?? 0);

    $stmt = $pdo->prepare("UPDATE sitin_sessions SET status = 'completed', time_out = NOW() WHERE id = :id AND status = 'active'");
    $stmt->execute([':id' => $sessionId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'User logged out successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Session not found or already completed']);
    }
}

function activateScheduledReservations(PDO $pdo, ?int $userId = null): void
{
    $sql = "SELECT r.id, r.user_id, r.lab_room, r.purpose, u.remaining_sessions 
            FROM reservations r 
            INNER JOIN users u ON u.id = r.user_id 
            WHERE r.status = 'approved' 
              AND r.preferred_date <= CURDATE()";
    
    if ($userId !== null) {
        $sql .= " AND r.user_id = :user_id";
    }
    
    $stmt = $pdo->prepare($sql);
    if ($userId !== null) {
        $stmt->execute([':user_id' => $userId]);
    } else {
        $stmt->execute();
    }
    
    $reservations = $stmt->fetchAll();
    foreach ($reservations as $res) {
        $activeCheck = $pdo->prepare("SELECT id FROM sitin_sessions WHERE user_id = :user_id AND status = 'active' LIMIT 1");
        $activeCheck->execute([':user_id' => (int)$res['user_id']]);
        if ($activeCheck->fetch()) {
            continue;
        }
        
        $pcCheck = $pdo->prepare("SELECT id FROM sitin_sessions WHERE lab_room = :lab_room AND status = 'active' LIMIT 1");
        $pcCheck->execute([':lab_room' => $res['lab_room']]);
        if ($pcCheck->fetch()) {
            continue;
        }
        
        if ((int)$res['remaining_sessions'] <= 0) {
            continue;
        }
        
        $inTransaction = $pdo->inTransaction();
        if (!$inTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $userStmt = $pdo->prepare("SELECT remaining_sessions FROM users WHERE id = :id FOR UPDATE");
            $userStmt->execute([':id' => (int)$res['user_id']]);
            $rem = (int)$userStmt->fetchColumn();
            if ($rem <= 0) {
                if (!$inTransaction) {
                    $pdo->rollBack();
                }
                continue;
            }
            
            $pdo->prepare("INSERT INTO sitin_sessions (user_id, lab_room, purpose, status, time_in) VALUES (:user_id, :lab_room, :purpose, 'active', NOW())")
                ->execute([
                    ':user_id' => (int)$res['user_id'],
                    ':lab_room' => $res['lab_room'],
                    ':purpose' => $res['purpose']
                ]);
                
            $pdo->prepare("UPDATE users SET remaining_sessions = remaining_sessions - 1 WHERE id = :user_id")
                ->execute([':user_id' => (int)$res['user_id']]);
                
            if (!$inTransaction) {
                $pdo->commit();
            }
        } catch (Exception $e) {
            if (!$inTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }
}

function createReservation(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $idNumber = trim((string)($data['id_number'] ?? ''));
    $labRoom = trim((string)($data['lab_room'] ?? ''));
    $purpose = trim((string)($data['purpose'] ?? ''));
    $preferredDate = trim((string)($data['preferred_date'] ?? '')) ?: null;
    $preferredTime = trim((string)($data['preferred_time'] ?? '')) ?: null;

    if ($idNumber === '' || $labRoom === '' || $purpose === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Number, Lab Room, and Purpose are required']);
        return;
    }

    // Validate PC ID format S1 to S50
    if (!preg_match('/^S([1-9]|[1-4][0-9]|50)$/', $labRoom)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid PC selection']);
        return;
    }

    // Validate date and time
    if ($preferredDate !== null) {
        $today = date('Y-m-d');
        if ($preferredDate < $today) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Preferred date cannot be in the past']);
            return;
        }

        if ($preferredDate === $today && $preferredTime !== null) {
            if ($preferredTime < date('H:i', strtotime('-15 minutes'))) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Preferred time cannot be in the past']);
                return;
            }
        }
    }

    $settingStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'reservations_enabled'");
    $isEnabled = $settingStmt->fetchColumn();
    if ($isEnabled !== false && $isEnabled === '0') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Reservations are currently disabled by the administrator.']);
        return;
    }

    $userStmt = $pdo->prepare("SELECT id, remaining_sessions FROM users WHERE id_number = :id_number AND role = 'student' LIMIT 1");
    $userStmt->execute([':id_number' => $idNumber]);
    $user = $userStmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }

    if ((int)$user['remaining_sessions'] <= 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'No remaining sessions left']);
        return;
    }

    // Check if the student already has an active session today if they are trying to reserve for today
    if ($preferredDate === date('Y-m-d')) {
        $activeCheck = $pdo->prepare("SELECT id FROM sitin_sessions WHERE user_id = :user_id AND status = 'active' LIMIT 1");
        $activeCheck->execute([':user_id' => (int)$user['id']]);
        if ($activeCheck->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'You already have an active sit-in session today']);
            return;
        }
    }

    // Check if the student already has a pending reservation for the same date
    if ($preferredDate !== null) {
        $stmt = $pdo->prepare("SELECT id FROM reservations WHERE user_id = :user_id AND preferred_date = :preferred_date AND status = 'pending' LIMIT 1");
        $stmt->execute([
            ':user_id' => $user['id'],
            ':preferred_date' => $preferredDate
        ]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'You already have a pending reservation for this date']);
            return;
        }
    }

    // Check if the PC is already reserved for the same date and time by another student
    if ($preferredDate !== null && $preferredTime !== null) {
        $stmt = $pdo->prepare("SELECT id FROM reservations WHERE lab_room = :lab_room AND preferred_date = :preferred_date AND preferred_time = :preferred_time AND status IN ('pending', 'approved') LIMIT 1");
        $stmt->execute([
            ':lab_room' => $labRoom,
            ':preferred_date' => $preferredDate,
            ':preferred_time' => $preferredTime
        ]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'This PC is already reserved for the selected date and time']);
            return;
        }
    }

    $insertStmt = $pdo->prepare("INSERT INTO reservations (user_id, lab_room, purpose, preferred_date, preferred_time, status) VALUES (:user_id, :lab_room, :purpose, :preferred_date, :preferred_time, 'pending')");
    $insertStmt->execute([
        ':user_id' => (int)$user['id'],
        ':lab_room' => $labRoom,
        ':purpose' => $purpose,
        ':preferred_date' => $preferredDate,
        ':preferred_time' => $preferredTime,
    ]);

    echo json_encode(['success' => true, 'message' => 'Reservation submitted successfully']);
}

function getStudentReservations(PDO $pdo): void
{
    $idNumber = trim($_GET['id_number'] ?? '');
    if ($idNumber === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID number is required']);
        return;
    }

    $userStmt = $pdo->prepare("SELECT id FROM users WHERE id_number = :id_number AND role = 'student' LIMIT 1");
    $userStmt->execute([':id_number' => $idNumber]);
    $userId = (int)($userStmt->fetchColumn() ?: 0);
    if ($userId <= 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }

    $stmt = $pdo->prepare("SELECT id, lab_room, purpose, preferred_date, preferred_time, status, created_at FROM reservations WHERE user_id = :user_id ORDER BY created_at DESC");
    $stmt->execute([':user_id' => $userId]);

    echo json_encode(['success' => true, 'reservations' => $stmt->fetchAll()]);
}

function getAdminReservations(PDO $pdo): void
{
    activateScheduledReservations($pdo);
    $stmt = $pdo->prepare("SELECT r.id, r.lab_room, r.purpose, r.preferred_date, r.preferred_time, r.status, r.created_at, u.id_number, u.first_name, u.last_name, u.remaining_sessions FROM reservations r INNER JOIN users u ON u.id = r.user_id ORDER BY r.created_at DESC");
    $stmt->execute();

    echo json_encode(['success' => true, 'reservations' => $stmt->fetchAll()]);
}

function approveReservation(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $reservationId = (int)($data['reservation_id'] ?? 0);
    if ($reservationId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid reservation ID']);
        return;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT r.id, r.user_id, r.lab_room, r.purpose, r.status, r.preferred_date, r.preferred_time, u.remaining_sessions FROM reservations r INNER JOIN users u ON u.id = r.user_id WHERE r.id = :id LIMIT 1 FOR UPDATE");
        $stmt->execute([':id' => $reservationId]);
        $reservation = $stmt->fetch();

        if (!$reservation) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Reservation not found']);
            return;
        }

        if ($reservation['status'] !== 'pending') {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Reservation already processed']);
            return;
        }

        if ((int)$reservation['remaining_sessions'] <= 0) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Student has no remaining sessions']);
            return;
        }

        $preferredDate = $reservation['preferred_date'];
        $isFuture = ($preferredDate !== null && $preferredDate > date('Y-m-d'));

        if ($isFuture) {
            // Decoupled approval: Set status to approved, do NOT start the session yet.
            $pdo->prepare("UPDATE reservations SET status = 'approved' WHERE id = :id")
                ->execute([':id' => $reservationId]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Reservation approved successfully']);
        } else {
            // Immediate approval (today or past/none): start session now.
            // Check PC occupancy first
            $pcCheck = $pdo->prepare("SELECT id FROM sitin_sessions WHERE lab_room = :lab_room AND status = 'active' LIMIT 1");
            $pcCheck->execute([':lab_room' => $reservation['lab_room']]);
            if ($pcCheck->fetch()) {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'PC ' . $reservation['lab_room'] . ' is already occupied by an active session']);
                return;
            }

            // Check if student already has an active session
            $activeCheck = $pdo->prepare("SELECT id FROM sitin_sessions WHERE user_id = :user_id AND status = 'active' LIMIT 1");
            $activeCheck->execute([':user_id' => (int)$reservation['user_id']]);
            if ($activeCheck->fetch()) {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Student already has an active session']);
                return;
            }

            $pdo->prepare("INSERT INTO sitin_sessions (user_id, lab_room, purpose, status, time_in) VALUES (:user_id, :lab_room, :purpose, 'active', NOW())")
                ->execute([
                    ':user_id' => (int)$reservation['user_id'],
                    ':lab_room' => $reservation['lab_room'],
                    ':purpose' => $reservation['purpose'],
                ]);

            $pdo->prepare("UPDATE users SET remaining_sessions = remaining_sessions - 1 WHERE id = :user_id AND remaining_sessions > 0")
                ->execute([':user_id' => (int)$reservation['user_id']]);

            $pdo->prepare("UPDATE reservations SET status = 'approved' WHERE id = :id")
                ->execute([':id' => $reservationId]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Reservation approved and session started']);
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to approve reservation: ' . $e->getMessage()]);
    }
}

function rejectReservation(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $reservationId = (int)($data['reservation_id'] ?? 0);
    if ($reservationId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid reservation ID']);
        return;
    }

    $stmt = $pdo->prepare("UPDATE reservations SET status = 'rejected' WHERE id = :id AND status = 'pending'");
    $stmt->execute([':id' => $reservationId]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Reservation not found or already processed']);
        return;
    }

    echo json_encode(['success' => true, 'message' => 'Reservation rejected']);
}

function formatDurationLabel(int $seconds): string
{
    if ($seconds < 60) {
        return $seconds . ' sec';
    }

    $minutes = intdiv($seconds, 60);
    if ($minutes < 60) {
        return $minutes . ' min';
    }

    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;
    return $hours . ' hr' . ($hours > 1 ? 's' : '') . ($remainingMinutes > 0 ? ' ' . $remainingMinutes . ' min' : '');
}

function getLabOccupancy(PDO $pdo): void
{
    $stmt = $pdo->query("SELECT DISTINCT lab_room FROM sitin_sessions WHERE status = 'active' ORDER BY lab_room");
    $occupied = [];
    foreach ($stmt->fetchAll() as $row) {
        $occupied[] = $row['lab_room'];
    }

    echo json_encode([
        'success' => true,
        'occupied' => $occupied,
    ]);
}
