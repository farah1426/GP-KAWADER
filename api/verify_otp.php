
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

$email = trim($_POST['email'] ?? '');
$purpose = $_POST['purpose'] ?? '';
$otp = trim($_POST['otp'] ?? '');

if (
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    !in_array($purpose, ['signup', 'password_reset'], true) ||
    !preg_match('/^\d{6}$/', $otp)
) {
    respond(400, 'Please enter a valid email and six-digit code.');
}

try {
    $stmt = $pdo->prepare(
        'SELECT otp_id, otp_hash, expires_at, attempts, verified
         FROM email_otp
         WHERE email = ? AND purpose = ?
         ORDER BY otp_id DESC
         LIMIT 1'
    );
    $stmt->execute([$email, $purpose]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        respond(400, 'Request a new verification code.');
    }

   if ((int) $record['verified'] === 1) {
    $_SESSION['verified_otp_email'][$purpose] = $email;
    respond(200, 'Email already verified.');
}

    if (strtotime($record['expires_at']) <= time()) {
        respond(400, 'Code expired. Request a new one.');
    }

    if ((int) $record['attempts'] >= 5) {
        respond(429, 'Too many attempts. Request a new code.');
    }

    if (!password_verify($otp, $record['otp_hash'])) {
        $update = $pdo->prepare(
            'UPDATE email_otp SET attempts = attempts + 1 WHERE otp_id = ?'
        );
        $update->execute([$record['otp_id']]);

        respond(400, 'Incorrect code. Please try again.');
    }

    $update = $pdo->prepare(
        'UPDATE email_otp SET verified = 1 WHERE otp_id = ?'
    );
    $update->execute([$record['otp_id']]);

    $_SESSION['verified_otp_email'][$purpose] = $email;

    respond(200, 'Email verified successfully.');

} catch (Throwable $e) {
    error_log('OTP verification failed: ' . $e->getMessage());
    respond(500, 'Something went wrong. Please try again.');
}
