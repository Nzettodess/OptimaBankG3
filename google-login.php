<?php
require_once __DIR__ . '/vendor/autoload.php';

session_start();

$client = new Google_Client();
$client->setClientId("124073192361-hh1e2cqkt39h6octqa85a4gqeci3csp3.apps.googleusercontent.com");
$client->setClientSecret("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");
$client->setRedirectUri("http://localhost/OptimaBankG3/google-callback.php");
$client->addScope("email");
$client->addScope("profile");

$login_url = $client->createAuthUrl();

header("Location: " . $login_url);
exit;
