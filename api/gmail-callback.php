
<?php


session_start();

require_once __DIR__ . '/../vendor/autoload.php';

$credentialsFile = __DIR__ . '/../Database_kawader/google_oauth/client_secret.json';
$tokenFile = __DIR__ . '/../Database_kawader/google_oauth/token.json';

if (!isset($_GET['code'])) {
    http_response_code(400);
    exit('Authorization code is missing.');
}

if (!file_exists($credentialsFile)) {
    http_response_code(500);
    exit('OAuth credentials file not found.');
}

$client = new Google\Client();
$client->setAuthConfig($credentialsFile);
$client->setRedirectUri(
    'http://localhost:8888/GP-KAWADER/api/gmail-callback.php'
);
$client->setScopes([
    'https://www.googleapis.com/auth/gmail.send'
]);
$client->setAccessType('offline');

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

if (isset($token['error'])) {
    echo 'Google authorization failed: ';
    echo htmlspecialchars($token['error_description'] ?? $token['error']);
    exit;
}

if (!isset($token['refresh_token'])) {
    http_response_code(500);
    exit('Refresh token missing. Re-authorize Google access.');
}

if (!is_dir(dirname($tokenFile))) {
    mkdir(dirname($tokenFile), 0700, true);
}

if (file_put_contents(
    $tokenFile,
    json_encode($token, JSON_PRETTY_PRINT),
    LOCK_EX
) === false) {
    http_response_code(500);
    exit('Could not save OAuth token.');
}

@chmod($tokenFile, 0600);

echo 'Google authorization completed successfully.';
