<?php
require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin($pdo);
        break;
    case 'register':
        handleRegister($pdo);
        break;
    case 'active_sessions':
        getActiveSessions($pdo);
        break;
    case 'stats':
        getStats($pdo);
        break;
    case 'end_session':
        endSession($pdo);
        break;
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
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

    $stmt = $pdo->prepare("SELECT id, id_number, first_name, last_name, course, year_level, email, password_hash, role FROM users WHERE id_number = :id_number");
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

    $stmt = $pdo->prepare("INSERT INTO users (id_number, last_name, first_name, middle_name, course, year_level, email, password_hash, address)
        VALUES (:id_number, :last_name, :first_name, :middle_name, :course, :year_level, :email, :password_hash, :address)");


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
    $stmt = $pdo->prepare("
        SELECT s.id, s.lab_room, s.purpose, s.time_in, s.status,
               u.id_number, u.first_name, u.last_name, u.course, u.year_level
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

    echo json_encode([
        'success' => true,
        'stats' => [
            'active_sessions' => (int)$activeCount,
            'today_sessions' => (int)$todayCount,
            'total_students' => (int)$totalStudents
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

    $stmt = $pdo->prepare("UPDATE sitin_sessions SET status = 'completed', time_out = NOW() WHERE id = :id AND status = 'active'");
    $stmt->execute([':id' => $sessionId]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Session not found or already ended']);
        return;
    }

    echo json_encode(['success' => true, 'message' => 'Session ended successfully']);
}
