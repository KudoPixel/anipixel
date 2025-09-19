<?php

/**
 * File: src/config.php
 * Description: AniPixel Bot - Configuration Loader
 * This file loads environment variables from the .env file and defines global constants.
 */

// Load the library for reading the .env file
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Define the main application constants from environment variables
define('BOT_TOKEN', $_ENV['BOT_TOKEN'] ?? '');
define('WEB_APP_URL', $_ENV['WEB_APP_URL'] ?? '');
define('ADMIN_CHAT_ID', $_ENV['ADMIN_CHAT_ID'] ?? ''); // Numeric chat ID of the admin for error reports

// Check if the bot token exists
if (empty(BOT_TOKEN)) {
    // In a real app, this error should be logged in the server's main log
    error_log('FATAL ERROR: BOT_TOKEN is not configured.');
    die('Error: BOT_TOKEN is not configured.');
}

