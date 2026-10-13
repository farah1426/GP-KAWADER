<?php
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

$credentialsFile = __DIR__ . '/../Database_kawader/google_oauth/client_secret.json';

if (!file_exists($credentialsFile)) {
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
$client->setPrompt('consent');

$authUrl = $client->createAuthUrl();

header('Location: ' . $authUrl);
exit;