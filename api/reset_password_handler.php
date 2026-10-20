
<?php
session_start();

require_once __DIR__ . '/../Database_kawader/db.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode([
        'success' => $status === 200,
        'message' => $message
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, 'Invalid request method.');
}

$verifiedEmail = $_SESSION['verified_otp_email']['password_reset'] ?? '';

if (!is_string($verifiedEmail) || !filter_var($verifiedEmail, FILTER_VALIDATE_EMAIL)) {
    respond(403, 'Please verify your email first.');
}

$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (
    strlen($password) < 8 ||
    !preg_match('/[A-Z]/', $password) ||
    !preg_match('/[a-z]/', $password) ||
    !preg_match('/[0-9]/', $password) ||
    !preg_match('/[^A-Za-z0-9]/', $password)
) {
    respond(400, 'Password does not meet the requirements.');
}

if ($password !== $confirmPassword) {
    respond(400, 'Passwords do not match.');
}

try {
    $stmt = $pdo->prepare(
        'UPDATE `user` SET password_hash = ? WHERE email = ?'
    );

    $stmt->execute([
        password_hash($password, PASSWORD_DEFAULT),
        $verifiedEmail
    ]);

    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare('SELECT user_id FROM `user` WHERE email = ?');
        $check->execute([$verifiedEmail]);

        if (!$check->fetch()) {
            respond(400, 'Unable to update this account. Please try again.');
        }
    }

    unset($_SESSION['verified_otp_email']['password_reset']);

    $delete = $pdo->prepare(
        'DELETE FROM email_otp WHERE email = ? AND purpose = ?'
    );
    $delete->execute([$verifiedEmail, 'password_reset']);

    respond(200, 'Password updated successfully. Redirecting to sign in...');

} catch (Throwable $e) {
    error_log('Password reset failed: ' . $e->getMessage());
    respond(500, 'Unable to update your password. Please try again.');
}


