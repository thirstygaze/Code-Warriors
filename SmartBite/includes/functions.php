<?php
require_once __DIR__ . '/db.php';


function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


function peso($amount): string
{
    return '₱' . number_format((float)$amount, 2);
}

/* ---------------- CSRF ---------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function check_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || $token === '' || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(400);
        flash('error', 'Your session expired or the form was resubmitted. Please try again.');
        $back = $_SERVER['HTTP_REFERER'] ?? (APP_URL . '/index.php');
        header('Location: ' . $back);
        exit;
    }
}

/* ---------------- Flash messages ---------------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}


function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/* ---------------- Auth ---------------- */

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!user()) {
        flash('error', 'Please log in to continue.');
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

/** @param string[] $roles */
function require_role(array $roles): void
{
    require_login();
    if (!in_array(user()['role'], $roles, true)) {
        http_response_code(403);
        flash('error', "You don't have access to that page.");
        header('Location: ' . APP_URL . '/menu.php');
        exit;
    }
}

function staff_category_id(): ?int
{
    $u = user();
    if (!$u || $u['role'] !== 'staff') return null;
    return $u['assigned_category_id'] !== null ? (int)$u['assigned_category_id'] : null;
}

function staff_stall_name(): ?string
{
    $u = user();
    return ($u && $u['role'] === 'staff' && !empty($u['stall_name'])) ? $u['stall_name'] : null;
}

function can_manage_category(int $categoryId): bool
{
    $u = user();
    if (!$u) return false;
    if ($u['role'] === 'admin') return true;
    if ($u['role'] !== 'staff') return false;
    $mine = staff_category_id();
    return $mine === null || $mine === $categoryId;
}

function delete_or_deactivate_user(int $userId): string
{
    $pdo = db();
    try {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
        return 'deleted';
    } catch (Throwable $e) {
        $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")->execute([$userId]);
        return 'deactivated';
    }
}

/* ---------------- Activity log ---------------- */

function log_activity(?int $userId, string $action, string $details = ''): void
{
    try {
        $stmt = db()->prepare('INSERT INTO activity_logs (user_id, action, details) VALUES (?,?,?)');
        $stmt->execute([$userId, $action, $details]);
    } catch (Throwable $e) {
        // Never let logging failures break the request.
        error_log('log_activity failed: ' . $e->getMessage());
    }
}

/* ---------------- Notifications ---------------- */

function notify(int $userId, string $title, string $message): void
{
    $stmt = db()->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)');
    $stmt->execute([$userId, $title, $message]);
}

function unread_notification_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/* ---------------- Pickup codes ---------------- */

/** Generates a short, unambiguous code the student shows at the counter. */
function generate_pickup_code(PDO $pdo): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I to avoid confusion
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $stmt = $pdo->prepare('SELECT 1 FROM orders WHERE pickup_code = ?');
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    return $code;
}

/* ---------------- Image uploads ---------------- */

function handle_image_upload(string $fieldName): ?string
{
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$fieldName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Image upload failed. Please try a smaller file.');
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        throw new Exception('Image is too large (max 3MB).');
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        throw new Exception('That file is not a valid image.');
    }
    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];
    if (!isset($allowed[$info[2]])) {
        throw new Exception('Please upload a JPG, PNG, or WEBP image.');
    }
    $ext = $allowed[$info[2]];
    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    $destDir = __DIR__ . '/../assets/img/items/uploads';
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    $destPath = $destDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new Exception('Could not save the uploaded image.');
    }
    return 'assets/img/items/uploads/' . $filename;
}

/* ---------------- Order status badges ---------------- */

function status_badge_class(string $status): string
{
    return match ($status) {
        'pending' => 'pill orange',
        'preparing' => 'pill blue',
        'ready' => 'pill green',
        'completed' => 'pill grey',
        'cancelled' => 'pill red',
        default => 'pill',
    };
}
