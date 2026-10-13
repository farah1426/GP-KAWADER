
<?php
session_start();

require_once __DIR__ . '/../Database_kawader/db.php';
require_once __DIR__ . '/send_otp.php';

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

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(400, 'Please enter a valid email address.');
}

if (!in_array($purpose, ['signup', 'password_reset'], true)) {
    respond(400, 'Invalid verification purpose.');
}

try {
    if ($purpose === 'signup') {
        $check = $pdo->prepare('SELECT user_id FROM user WHERE email = ?');
        $check->execute([$email]);

        if ($check->fetch()) {
            respond(400, 'This email is already registered.');
        }
    } else {
        $check = $pdo->prepare('SELECT user_id FROM user WHERE email = ?');
        $check->execute([$email]);

        if (!$check->fetch()) {
            respond(400, 'Unable to process this email address.');
        }
    }

    // Limit repeated requests: one new code every 60 seconds.
    $recent = $pdo->prepare(
        'SELECT otp_id FROM email_otp
         WHERE email = ? AND purpose = ?
         AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)
         LIMIT 1'
    );
    $recent->execute([$email, $purpose]);

    if ($recent->fetch()) {
        respond(429, 'Please wait 60 seconds before requesting another code.');
    }

    $otp = (string) random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', time() + 600);

    $pdo->beginTransaction();

    $delete = $pdo->prepare(
        'DELETE FROM email_otp WHERE email = ? AND purpose = ?'
    );
    $delete->execute([$email, $purpose]);

    $insert = $pdo->prepare(
        'INSERT INTO email_otp
         (email, purpose, otp_hash, expires_at)
         VALUES (?, ?, ?, ?)'
    );
    $insert->execute([$email, $purpose, $otpHash, $expiresAt]);

    $pdo->commit();

    if (!sendOtpEmail($email, $otp)) {
        $delete = $pdo->prepare(
            'DELETE FROM email_otp WHERE email = ? AND purpose = ?'
        );
        $delete->execute([$email, $purpose]);

        respond(500, 'Unable to send the verification email. Please try again later.');
    }

    respond(200, 'Verification code sent to your email.');

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('OTP request failed: ' . $e->getMessage());
    respond(500, 'Something went wrong. Please try again.');
}
