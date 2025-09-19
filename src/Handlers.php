<?php

namespace AniPixel;

/**
 * File: src/Handlers.php
 * Description: AniPixel Bot - Core Logic & Handlers
 * This class contains the main logic for processing messages and callbacks.
 */
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
                $reply = "Welcome to AniPixel! 🚀\n\nI can help you discover new anime. Try one of these commands:\n\n/trending - See what's hot right now\n/popular - Browse all-time favorites\n/romance - For the lovers\n\nOr just send me an anime name to search!";
                $this->telegram->sendMessage($chat_id, $reply);
                break;

            case '/trending':
            case '/popular':
            case '/romance':
                $list_type = ltrim($command, '/');
                $this->sendAnimeList($chat_id, $list_type, 1);
                break;

            default:
                if (!empty($text)) {
                    $this->sendAnimeList($chat_id, 'search', 1, $text);
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

        // Answer immediately to remove the button's loading state
        $this->telegram->answerCallbackQuery($callback_query['id']);

        $parts = explode('_', $data);
        $type = $parts[0];

        if ($type === 'anime' && count($parts) > 1 && is_numeric($parts[1])) {
            $anime_id = (int)$parts[1];
            $this->sendDetailCard($chat_id, $anime_id);
        } elseif (count($parts) >= 2 && is_numeric($parts[1])) {
            $page = (int)$parts[1];
            $list_type_from_callback = $parts[0];
            $search_query = count($parts) > 2 ? implode('_', array_slice($parts, 2)) : null;
            $this->updateAnimeList($chat_id, $message_id, $list_type_from_callback, $page, $search_query);
        }
    }

    /**
     * Sends a list of anime.
     */
    private function sendAnimeList(int $chat_id, string $list_type, int $page, ?string $search_query = null): void {
        $variables = ['page' => $page];
        if ($search_query) $variables['search'] = $search_query;
        
        $anime_list = $this->anilist->fetch($list_type, $variables);

        if (empty($anime_list['media'])) {
            $this->telegram->sendMessage($chat_id, "Sorry, I couldn't find any anime for that search. Try a different name!");
            return;
        }

        $keyboard = $this->buildAnimeListKeyboard($anime_list, $list_type, $page, $search_query);
        $message_text = "Here are the results for: *" . ucfirst($list_type) . ($search_query ? " - {$search_query}" : "") . "*\nPage: {$page}";

        $this->telegram->sendMessage($chat_id, $message_text, ['inline_keyboard' => $keyboard]);
    }

    /**
     * Updates an anime list (for pagination buttons).
     */
    private function updateAnimeList(int $chat_id, int $message_id, string $list_type, int $page, ?string $search_query = null): void {
        $variables = ['page' => $page];
        if ($search_query) $variables['search'] = $search_query;
        
        $anime_list = $this->anilist->fetch($list_type, $variables);
        if (empty($anime_list['media'])) {
             $this->telegram->editMessageText($chat_id, $message_id, "No more results found.");
             return;
        }

        $keyboard = $this->buildAnimeListKeyboard($anime_list, $list_type, $page, $search_query);
        $message_text = "Here are the results for: *" . ucfirst($list_type) . ($search_query ? " - {$search_query}" : "") . "*\nPage: {$page}";

        $this->telegram->editMessageText($chat_id, $message_id, $message_text, ['inline_keyboard' => $keyboard]);
    }

    /**
     * Sends a detail card for a specific anime.
     */
    private function sendDetailCard(int $chat_id, int $anime_id): void {
        $anime_data = $this->anilist->fetch('detail_anime', ['id' => $anime_id]);

        if (empty($anime_data)) {
            $this->telegram->sendMessage($chat_id, 'Could not fetch details for this anime.');
            return;
        }

        $title = $anime_data['title']['english'] ?? $anime_data['title']['romaji'];
        $photo_url = $anime_data['coverImage']['extraLarge'];
        $genres = implode(', ', $anime_data['genres']);
        $score = $anime_data['averageScore'] ? $anime_data['averageScore'] . '%' : 'N/A';
        
        // Build the link to open the Mini App with a parameter
        $web_app_url = WEB_APP_URL . '?animeId=' . $anime_id;

        $caption = "🎬 *{$title}*\n\n*Genre:* {$genres}\n*Score:* {$score}";
        $keyboard = [
            [['text' => '🚀 View in Mini App', 'web_app' => ['url' => $web_app_url]]]
        ];

        $this->telegram->sendPhoto($chat_id, $photo_url, $caption, ['inline_keyboard' => $keyboard]);
    }

    /**
     * Builds the inline keyboard for an anime list.
     */
    private function buildAnimeListKeyboard(array $anime_list_data, string $list_type, int $page, ?string $search_query = null): array {
        $keyboard = [];
        $media = $anime_list_data['media'];
        
        foreach ($media as $anime) {
            $title = $anime['title']['english'] ?? $anime['title']['romaji'];
            $title = mb_strlen($title) > 40 ? mb_substr($title, 0, 37) . '...' : $title;
            $keyboard[] = [['text' => $title, 'callback_data' => 'anime_' . $anime['id']]];
        }

        $pagination_row = [];
        if ($page > 1) {
            $prev_page_data = $list_type . '_' . ($page - 1) . ($search_query ? '_' . $search_query : '');
            $pagination_row[] = ['text' => '◀️ Prev', 'callback_data' => $prev_page_data];
        }
        if ($anime_list_data['pageInfo']['hasNextPage']) {
            $next_page_data = $list_type . '_' . ($page + 1) . ($search_query ? '_' . $search_query : '');
            $pagination_row[] = ['text' => 'Next ▶️', 'callback_data' => $next_page_data];
        }

        if (!empty($pagination_row)) {
            $keyboard[] = $pagination_row;
        }

        return $keyboard;
    }
}

