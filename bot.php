<?php

/**
 * File: bot.php
 * Description: AniPixel Bot - Main Entry Point
 * This file initializes the bot, loads dependencies, and routes incoming updates.
 */

// Bootstrap the bot using Composer's autoloader
require 'vendor/autoload.php';

// Use namespaces to access the modular classes
use AniPixel\Handlers;
use AniPixel\Logger;
use AniPixel\TelegramAPI;
use AniPixel\AniListAPI;

// Load configuration and secret variables
require_once 'src/config.php';

// Enable error reporting for debugging (should be disabled in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log the start of a new request
Logger::logInfo('================ NEW REQUEST ================');

try {
    // Instantiate the required classes
    $telegram = new TelegramAPI(BOT_TOKEN);
    $anilist = new AniListAPI();
    
    // Inject dependencies into the main logic class
    $handlers = new Handlers($telegram, $anilist);

    // Get the update from Telegram
    $update = json_decode(file_get_contents('php://input'), true);

    if (!$update) {
        Logger::logInfo('Received empty update.');
        exit('No update received.');
    }

    // Log the received update for debugging
    Logger::logInfo('Update Received: ' . json_encode($update, JSON_UNESCAPED_UNICODE));

    // Route the update to the appropriate handler
    if (isset($update['callback_query'])) {
        $handlers->handleCallbackQuery($update['callback_query']);
    } elseif (isset($update['message'])) {
        $handlers->handleMessage($update['message']);
    }

} catch (Exception $e) {
    // Log any uncaught exceptions to the log file
    Logger::logError('FATAL ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    // (Optional) Send an error message to the admin
    // if (defined('ADMIN_CHAT_ID') && !empty(ADMIN_CHAT_ID)) {
    //     $telegram->sendMessage(ADMIN_CHAT_ID, 'A fatal error occurred in the bot!');
    // }
}

Logger::logInfo('================ END REQUEST =================');

