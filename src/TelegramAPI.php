<?php

/**
 * File: src/TelegramAPI.php
 * Description: AniPixel Bot - Telegram API Wrapper
 * This class handles all communications with the Telegram Bot API.
 */

namespace AniPixel;

use Exception;

class TelegramAPI {
    private $apiUrl;

    public function __construct(string $token) {
        $this->apiUrl = 'https://api.telegram.org/bot' . $token . '/';
    }

    /**
     * Main method for sending requests to the Telegram API.
     * @param string $method
     * @param array $parameters
     * @return array
     * @throws Exception
     */
    public function apiRequest(string $method, array $parameters): array {
        $url = $this->apiUrl . $method;
        
        // For GET requests, build query. Telegram API supports it for most methods.
        if (!empty($parameters)) {
            $url .= "?" . http_build_query($parameters);
        }

        Logger::logInfo("Telegram Request: {$method} | Params: " . json_encode($parameters));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('cURL Error: ' . $error);
        }

        curl_close($ch);
        $result = json_decode($response, true);

        if (!$result['ok']) {
            Logger::logError("Telegram API Error: " . ($result['description'] ?? 'Unknown error'));
        }

        return $result;
    }

    /**
     * Sends a text message.
     * FIX: Added explicit nullable type `?array` for PHP 8+ compatibility.
     */
    public function sendMessage(int $chat_id, string $text, ?array $reply_markup = null) {
        $params = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'Markdown'];
        if ($reply_markup) {
            $params['reply_markup'] = json_encode($reply_markup);
        }
        return $this->apiRequest('sendMessage', $params);
    }

    /**
     * Sends a photo.
     * FIX: Added explicit nullable type `?array` for PHP 8+ compatibility.
     */
    public function sendPhoto(int $chat_id, string $photo_url, string $caption, ?array $reply_markup = null) {
        $params = [
            'chat_id' => $chat_id,
            'photo' => $photo_url,
            'caption' => $caption,
            'parse_mode' => 'Markdown'
        ];
        if ($reply_markup) {
            $params['reply_markup'] = json_encode($reply_markup);
        }
        return $this->apiRequest('sendPhoto', $params);
    }
    
    /**
     * Edits the text of an existing message.
     * FIX: Added explicit nullable type `?array` for PHP 8+ compatibility.
     */
    public function editMessageText(int $chat_id, int $message_id, string $text, ?array $reply_markup = null) {
        $params = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $text,
            'parse_mode' => 'Markdown'
        ];
        if ($reply_markup) {
            $params['reply_markup'] = json_encode($reply_markup);
        }
        return $this->apiRequest('editMessageText', $params);
    }

    /**
     * Answers a callback query.
     */
    public function answerCallbackQuery(string $callback_query_id) {
        return $this->apiRequest('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
    }
}

