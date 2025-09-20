<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

session_start();

// Load .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize Google Client
$client = new Google_Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
$client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);
$client->addScope("email");
$client->addScope("profile");

// Create login URL
$login_url = $client->createAuthUrl();

// Redirect to Google login
header("Location: " . $login_url);
exit;
