<?php

/**
 * File: src/Handlers.php
 * Description: AniPixel Bot - Core Logic & Handlers
 * This class contains the main logic for processing messages and callbacks.
 */

namespace AniPixel;

class Handlers {
    private $telegram;
    private $anilist;

    public function __construct(TelegramAPI $telegram, AniListAPI $anilist) {
        $this->telegram = $telegram;
        $this->anilist = $anilist;
    }

    /**
     * Handles incoming messages.
     * @param array $message
     */
    public function handleMessage(array $message): void {
        $chat_id = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $command = explode(' ', $text)[0];

        switch ($command) {
            case '/start':
                // UX CHANGE: Send a welcome message with a main menu keyboard
                $this->sendMainMenu($chat_id, "Welcome to AniPixel! 🚀\n\nSelect a category to explore or just send me an anime name to search!");
                break;

            default:
                if (!empty($text)) {
                    // Treat any other text as a search query
                    $this->updateToList($chat_id, null, 'search', 1, $text);
                } else {
                    Logger::logInfo("Received an empty or non-text message from chat_id: {$chat_id}");
                }
                break;
        }
    }

    /**
     * Handles clicks on inline keyboard buttons.
     * @param array $callback_query
     */
    public function handleCallbackQuery(array $callback_query): void {
        $chat_id = $callback_query['message']['chat']['id'];
        $message_id = $callback_query['message']['message_id'];
        $data = $callback_query['data'];

        $this->telegram->answerCallbackQuery($callback_query['id']);

        $parts = explode('_', $data);
        $action = $parts[0];
        
        // UX CHANGE: New routing system for better state management
        switch ($action) {
            case 'menu':
                $this->sendMainMenu($chat_id, "Here are the categories:", $message_id);
                break;
            
            case 'list': // Format: list_{type}_{page}
                $type = $parts[1];
                $page = (int)$parts[2];
                $this->updateToList($chat_id, $message_id, $type, $page);
                break;
            
            case 'detail': // Format: detail_{id}_{prev_type}_{prev_page}_{prev_query?}
                $id = (int)$parts[1];
                $callback_query_id = $callback_query['id']; // Get the ID here
                array_shift($parts); // remove 'detail'
                array_shift($parts); // remove id
                $prevState = implode('_', $parts); // Rebuild the state string
                // Pass the ID to the function
                $this->sendDetailCard($chat_id, $message_id, $id, $prevState, $callback_query_id);
                break;
        }
    }

    /**
     * Sends or edits to the main menu.
     */
    private function sendMainMenu(int $chat_id, string $text, ?int $message_id = null): void {
        $keyboard = [
            [['text' => '🔥 Trending', 'callback_data' => 'list_trending_1'], ['text' => '⭐ Popular', 'callback_data' => 'list_popular_1']],
            [['text' => '💖 Romance', 'callback_data' => 'list_romance_1'], ['text' => '😂 Comedy', 'callback_data' => 'list_comedy_1']],
            [['text' => '🕵️‍♂️ Mystery', 'callback_data' => 'list_detective_1']]
        ];

        if ($message_id) {
            $this->telegram->editMessageText($chat_id, $message_id, $text, ['inline_keyboard' => $keyboard]);
        } else {
            $this->telegram->sendMessage($chat_id, $text, ['inline_keyboard' => $keyboard]);
        }
    }

    /**
     * Universal function to send or edit to an anime list.
     * FIX: This function now handles returning from a photo message.
     * It will delete the previous message and send a new one to avoid API errors.
     */
    private function updateToList(int $chat_id, ?int $message_id, string $type, int $page, ?string $query = null): void {
        $variables = ['page' => $page];
        if ($query) $variables['search'] = $query;
        
        $data = $this->anilist->fetch($type, $variables);

        // First, if there's a previous message, get rid of it.
        // This works whether it's a text or photo message.
        if ($message_id) {
            $this->telegram->apiRequest('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
        }

        if (empty($data['media'])) {
            $text = "Sorry, I couldn't find any results for that.";
            // Since we deleted the old message, we must send a new one.
            $this->telegram->sendMessage($chat_id, $text);
            return;
        }

        $keyboard = $this->buildListKeyboard($data, $type, $page, $query);
        $text = "Here are the results for: *" . ucfirst($type) . ($query ? " - {$query}" : "") . "*\nPage: {$page}";
        
        // Always send a new message because the old one is gone.
        $this->telegram->sendMessage($chat_id, $text, ['inline_keyboard' => $keyboard]);
    }

    /**
     * Sends or edits to a detail card.
     * We now accept the callback_query_id to show alerts on failure.
     */
    private function sendDetailCard(int $chat_id, int $message_id, int $anime_id, string $prevState, string $callback_query_id): void {
        $data = $this->anilist->fetch('detail_anime', ['id' => $anime_id]);

        if (empty($data)) {
            // Use the passed-in ID to show an alert to the user
            $this->telegram->answerCallbackQuery($callback_query_id, ['text' => 'Could not fetch details!', 'show_alert' => true]);
            return;
        }

        $title = $data['title']['english'] ?? $data['title']['romaji'];
        $photo_url = $data['coverImage']['extraLarge'];
        $genres = implode(', ', $data['genres']);
        $score = $data['averageScore'] ? $data['averageScore'] . '%' : 'N/A';
        $web_app_url = WEB_APP_URL . '?animeId=' . $anime_id;

        $caption = "🎬 *{$title}*\n\n*Genre:* {$genres}\n*Score:* {$score}";
        
        // UX CHANGE: The magic "Back" button!
        $keyboard = [
            [['text' => '🚀 View in Mini App', 'web_app' => ['url' => $web_app_url]]],
            [['text' => '⬅️ Back to List', 'callback_data' => 'list_' . $prevState]]
        ];
        
        // We can't edit a text message to a photo message, so we delete the old one and send a new one.
        // This is a Telegram limitation.
        $this->telegram->apiRequest('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
        $this->telegram->sendPhoto($chat_id, $photo_url, $caption, ['inline_keyboard' => $keyboard]);
    }

    /**
     * Builds the keyboard for an anime list with stateful buttons.
     */
    private function buildListKeyboard(array $data, string $type, int $page, ?string $query = null): array {
        $keyboard = [];
        $prevState = $type . '_' . $page . ($query ? '_' . $query : '');

        foreach ($data['media'] as $anime) {
            $title = $anime['title']['english'] ?? $anime['title']['romaji'];
            $title = mb_strlen($title) > 40 ? mb_substr($title, 0, 37) . '...' : $title;
            $keyboard[] = [['text' => $title, 'callback_data' => 'detail_' . $anime['id'] . '_' . $prevState]];
        }

        $pagination_row = [];
        if ($page > 1) {
            $pagination_row[] = ['text' => '◀️ Prev', 'callback_data' => 'list_' . $type . '_' . ($page - 1) . ($query ? '_' . $query : '')];
        }
        if ($data['pageInfo']['hasNextPage']) {
            $pagination_row[] = ['text' => 'Next ▶️', 'callback_data' => 'list_' . $type . '_' . ($page + 1) . ($query ? '_' . $query : '')];
        }

        if (!empty($pagination_row)) {
            $keyboard[] = $pagination_row;
        }
        
        $keyboard[] = [['text' => '🏠 Main Menu', 'callback_data' => 'menu']];
        return $keyboard;
    }
}



