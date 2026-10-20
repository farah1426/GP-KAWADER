
<?php

require_once __DIR__ . '/../vendor/autoload.php';

function sendOtpEmail(string $recipient, string $otp): bool
{
    $credentialsFile = __DIR__ . '/../Database_kawader/google_oauth/client_secret.json';
    $tokenFile = __DIR__ . '/../Database_kawader/google_oauth/token.json';

    if (!file_exists($credentialsFile) || !file_exists($tokenFile)) {
        error_log('Gmail OAuth files are missing.');
        return false;
    }

    try {
        $client = new Google\Client();
        $client->setAuthConfig($credentialsFile);
        $client->setScopes([
            'https://www.googleapis.com/auth/gmail.send'
        ]);

        $token = json_decode(file_get_contents($tokenFile), true);

        if (!is_array($token)) {
            return false;
        }

        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            if (empty($token['refresh_token'])) {
                error_log('Gmail refresh token is missing.');
                return false;
            }

            $newToken = $client->fetchAccessTokenWithRefreshToken(
                $token['refresh_token']
            );

             if (isset($newToken['error'])) {
    error_log('Gmail token refresh failed.');
    return false;
}

            $token = array_merge($token, $newToken);

            file_put_contents(
                $tokenFile,
                json_encode($token, JSON_PRETTY_PRINT),
                LOCK_EX
            );

            $client->setAccessToken($token);
        }

        $service = new Google\Service\Gmail($client);

        $subject = 'Kawader Email Verification Code';

        $body = "Your Kawader verification code is: {$otp}\r\n\r\n";
        $body .= "This code expires in 10 minutes.\r\n";
        $body .= "If you did not request this code, ignore this email.";

        $messageText = "To: {$recipient}\r\n";
        $messageText .= "Subject: {$subject}\r\n";
        $messageText .= "MIME-Version: 1.0\r\n";
        $messageText .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $messageText .= $body;

        $raw = rtrim(
            strtr(base64_encode($messageText), '+/', '-_'),
            '='
        );

        $message = new Google\Service\Gmail\Message();
        $message->setRaw($raw);

        $service->users_messages->send('me', $message);

        return true;

    } catch (Throwable $e) {
        error_log('Gmail OTP sending failed: ' . $e->getMessage());
        return false;
    }
}
