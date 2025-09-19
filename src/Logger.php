<?php

namespace AniPixel;

/**
 * File: src/Logger.php
 * Description: AniPixel Bot - Logging Utility
 * A simple static class to handle writing logs to a file.
 */
class Logger {
    private static $logFile = __DIR__ . '/../bot.log';

    /**
     * Logs an informational message to the log file.
     * @param string $message
     */
    public static function logInfo(string $message): void {
        self::logToFile('INFO', $message);
    }

    /**
     * Logs an error message to the log file.
     * @param string $message
     */
    public static function logError(string $message): void {
        self::logToFile('ERROR', $message);
    }

    /**
     * The main method for writing to the log file with a specific format.
     * @param string $level
     * @param string $message
     */
    private static function logToFile(string $level, string $message): void {
        $date = date('Y-m-d H:i:s');
        $logEntry = "[{$date}] [{$level}] - {$message}" . PHP_EOL;
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND);
    }
}

