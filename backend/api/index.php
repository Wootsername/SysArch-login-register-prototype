<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

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
    case 'end_session':
        endSession($pdo);
        break;
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function fetchAnnouncements(PDO $pdo, string $audience = 'student', int $limit = 10): array
{
    $audience = in_array($audience, ['student', 'admin', 'all'], true) ? $audience : 'student';
    $limit = max(1, min($limit, 50));

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

    $stmt = $pdo->prepare("SELECT id, id_number, first_name, last_name, course, year_level, email, password_hash, remaining_sessions, role FROM users WHERE id_number = :id_number");
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

    $stmt = $pdo->prepare("INSERT INTO users (id_number, last_name, first_name, middle_name, course, year_level, email, password_hash, address, remaining_sessions)
        VALUES (:id_number, :last_name, :first_name, :middle_name, :course, :year_level, :email, :password_hash, :address, 30)");


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
    $pendingReservations = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'pending'")->fetchColumn();

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

    $stmt = $pdo->prepare("UPDATE sitin_sessions SET status = 'completed', time_out = NOW() WHERE id = :id AND status = 'active'");
    $stmt->execute([':id' => $sessionId]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Session not found or already ended']);
        return;
    }

    echo json_encode(['success' => true, 'message' => 'Session ended successfully']);
}

function getAdminDashboard(PDO $pdo): void
{
    $totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $currentlySitin = (int)$pdo->query("SELECT COUNT(*) FROM sitin_sessions WHERE status = 'active'")->fetchColumn();
    $totalSitin = (int)$pdo->query("SELECT COUNT(*) FROM sitin_sessions")->fetchColumn();

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
            'total_sitin' => $totalSitin
        ],
        'language_usage' => $languageCounts
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

    $userStmt = $pdo->prepare("SELECT id, id_number, last_name, first_name, middle_name, course, year_level, email, address, remaining_sessions, role FROM users WHERE id_number = :id_number AND role = 'student' LIMIT 1");
    $userStmt->execute([':id_number' => $idNumber]);
    $user = $userStmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }

    $activeStmt = $pdo->prepare("SELECT id, lab_room, purpose, status, time_in, time_out, TIMESTAMPDIFF(SECOND, time_in, NOW()) AS duration_seconds, SEC_TO_TIME(TIMESTAMPDIFF(SECOND, time_in, NOW())) AS duration_time FROM sitin_sessions WHERE user_id = :user_id AND status = 'active' ORDER BY time_in DESC");
    $activeStmt->execute([':user_id' => (int)$user['id']]);

    $historyStmt = $pdo->prepare("SELECT id, lab_room, purpose, status, time_in, time_out, TIMESTAMPDIFF(SECOND, time_in, COALESCE(time_out, NOW())) AS duration_seconds, SEC_TO_TIME(TIMESTAMPDIFF(SECOND, time_in, COALESCE(time_out, NOW()))) AS duration_time FROM sitin_sessions WHERE user_id = :user_id ORDER BY time_in DESC LIMIT 25");
    $historyStmt->execute([':user_id' => (int)$user['id']]);

    $reservationStmt = $pdo->prepare("SELECT id, lab_room, purpose, preferred_date, preferred_time, status, created_at FROM reservations WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 25");
    $reservationStmt->execute([':user_id' => (int)$user['id']]);

    $announcements = fetchAnnouncements($pdo, 'student', 10);
    $feedbackStmt = $pdo->prepare("SELECT id, subject, message, status, created_at FROM feedback_entries WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 10");
    $feedbackStmt->execute([':user_id' => (int)$user['id']]);

    unset($user['role']);

    echo json_encode([
        'success' => true,
        'user' => $user,
        'active_sessions' => $activeStmt->fetchAll(),
        'history' => $historyStmt->fetchAll(),
        'reservations' => $reservationStmt->fetchAll(),
        'announcements' => $announcements,
        'feedback' => $feedbackStmt->fetchAll(),
    ]);
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
        $stmt = $pdo->prepare("SELECT r.id, r.user_id, r.lab_room, r.purpose, r.status, u.remaining_sessions FROM reservations r INNER JOIN users u ON u.id = r.user_id WHERE r.id = :id LIMIT 1 FOR UPDATE");
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
