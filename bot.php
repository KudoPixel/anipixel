
<?php

// --- CONFIGURATION ---
// Put your Bot Token here.
define('BOT_TOKEN', '8112345924:AAFb7e-WrYKngyeaRzB4-Hl3hKukisR1giI');

// Put the full URL to your Mini App HTML file on Render.com here.
// IMPORTANT: Make sure this is the correct public URL.
define('WEB_APP_URL', 'https://anipixel.onrender.com/index.html');

define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('ANILIST_API_URL', 'https://graphql.anilist.co');

// --- MAIN LOGIC ---

// Get updates from Telegram
$update = json_decode(file_get_contents('php://input'), true);

if (isset($update['callback_query'])) {
    handleCallbackQuery($update['callback_query']);
} elseif (isset($update['message'])) {
    handleMessage($update['message']);
}

// --- HANDLER FUNCTIONS ---

/**
 * Handles incoming text messages from users.
 */
function handleMessage($message) {
    $chat_id = $message['chat']['id'];
    $text = $message['text'];

    // Extract command and potential arguments
    $parts = explode(' ', $text);
    $command = $parts[0];

    switch ($command) {
        case '/start':
            $reply = "Welcome, Anime Explorer! 🚀\n\nI can help you discover new anime. Try one of these commands:\n\n/trending - See what's hot right now\n/popular - Browse all-time favorites\n/romance - For the lovers\n\nOr just send me an anime name to search!";
            apiRequest('sendMessage', ['chat_id' => $chat_id, 'text' => $reply]);
            break;

        case '/trending':
        case '/popular':
        case '/romance':
            $list_type = ltrim($command, '/');
            sendAnimeList($chat_id, $list_type, 1);
            break;

        default:
            // If it's not a known command, treat it as a search query
            sendAnimeList($chat_id, 'search', 1, $text);
            break;
    }
}

/**
 * Handles button clicks (callback queries).
 */
function handleCallbackQuery($callback_query) {
    $chat_id = $callback_query['message']['chat']['id'];
    $message_id = $callback_query['message']['message_id'];
    $data = $callback_query['data'];

    // Answer the callback query immediately to remove the loading icon on the user's side.
    apiRequest('answerCallbackQuery', ['callback_query_id' => $callback_query['id']]);

    $parts = explode('_', $data);
    $type = $parts[0];

    if (count($parts) > 1 && is_numeric($parts[1])) {
        // This is a pagination or anime detail request
        $id_or_page = (int)$parts[1];

        if ($type === 'anime') {
            // User wants to see the detail card for an anime
            sendAnimeDetailCard($chat_id, $id_or_page);
        } else {
            // User is clicking a "Next" or "Prev" button for a list
            $search_query = count($parts) > 2 ? implode('_', array_slice($parts, 2)) : null;
            updateAnimeList($chat_id, $message_id, $type, $id_or_page, $search_query);
        }
    }
}


// --- ACTION FUNCTIONS ---

/**
 * Sends a list of anime as an interactive message.
 */
function sendAnimeList($chat_id, $list_type, $page, $search_query = null) {
    $anime_list = callAniList($list_type, $page, $search_query);

    if (empty($anime_list['media'])) {
        $reply = "Sorry, I couldn't find any anime for that search. Try a different name!";
        apiRequest('sendMessage', ['chat_id' => $chat_id, 'text' => $reply]);
        return;
    }

    $keyboard = buildAnimeListKeyboard($anime_list, $list_type, $page, $search_query);
    $message_text = "Here are the results for: *" . ucfirst($list_type) . ($search_query ? " - {$search_query}" : "") . "*\nPage: {$page}";

    apiRequest('sendMessage', [
        'chat_id' => $chat_id,
        'text' => $message_text,
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
    ]);
}

/**
 * Edits an existing message to show a new page of anime.
 */
function updateAnimeList($chat_id, $message_id, $list_type, $page, $search_query = null) {
    $anime_list = callAniList($list_type, $page, $search_query);
    $keyboard = buildAnimeListKeyboard($anime_list, $list_type, $page, $search_query);
    $message_text = "Here are the results for: *" . ucfirst($list_type) . ($search_query ? " - {$search_query}" : "") . "*\nPage: {$page}";
    
    apiRequest('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $message_text,
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
    ]);
}

/**
 * Sends a single, rich message with an anime poster and a "View in Mini App" button.
 */
function sendAnimeDetailCard($chat_id, $anime_id) {
    $anime_data = callAniList('detail', 1, null, $anime_id);

    if (empty($anime_data)) {
        apiRequest('sendMessage', ['chat_id' => $chat_id, 'text' => 'Could not fetch details for this anime.']);
        return;
    }
    
    $title = $anime_data['title']['english'] ?? $anime_data['title']['romaji'];
    $photo_url = $anime_data['coverImage']['extraLarge'];
    $genres = implode(', ', $anime_data['genres']);
    $score = $anime_data['averageScore'] ? $anime_data['averageScore'] . '%' : 'N/A';

    $caption = "🎬 *{$title}*\n\n*Genre:* {$genres}\n*Score:* {$score}";

    $keyboard = [
        [
            ['text' => '🚀 View in Mini App', 'web_app' => ['url' => WEB_APP_URL . '#anime-' . $anime_id]]
        ]
    ];

    apiRequest('sendPhoto', [
        'chat_id' => $chat_id,
        'photo' => $photo_url,
        'caption' => $caption,
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
    ]);
}


// --- HELPER FUNCTIONS ---

/**
 * Builds the inline keyboard with anime buttons and pagination.
 */
function buildAnimeListKeyboard($anime_list_data, $list_type, $page, $search_query = null) {
    $keyboard = [];
    $media = $anime_list_data['media'];
    
    // Create a button for each anime
    foreach ($media as $anime) {
        $title = $anime['title']['english'] ?? $anime['title']['romaji'];
        // Limit title length to avoid Telegram errors
        $title = mb_strlen($title) > 40 ? mb_substr($title, 0, 37) . '...' : $title;
        $keyboard[] = [['text' => $title, 'callback_data' => 'anime_' . $anime['id']]];
    }

    // Pagination buttons
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


/**
 * Fetches data from the AniList GraphQL API.
 */
function callAniList($type, $page = 1, $search = null, $id = null) {
    $list_fragment = 'fragment mediaFields on Media { id title { romaji english } }';
    $detail_fragment = 'fragment detailFields on Media { id title { romaji english } coverImage { extraLarge } genres averageScore }';

    $queries = [
        'trending' => "query (\$page: Int) { Page(page: \$page, perPage: 5) { pageInfo { hasNextPage } media(sort: TRENDING_DESC, type: ANIME, isAdult: false) { ...mediaFields } } } {$list_fragment}",
        'popular' => "query (\$page: Int) { Page(page: \$page, perPage: 5) { pageInfo { hasNextPage } media(sort: POPULARITY_DESC, type: ANIME, isAdult: false) { ...mediaFields } } } {$list_fragment}",
        'romance' => "query (\$page: Int) { Page(page: \$page, perPage: 5) { pageInfo { hasNextPage } media(sort: SCORE_DESC, type: ANIME, genre: \"Romance\", isAdult: false) { ...mediaFields } } } {$list_fragment}",
        'search' => "query (\$page: Int, \$search: String) { Page(page: \$page, perPage: 5) { pageInfo { hasNextPage } media(search: \$search, type: ANIME, isAdult: false) { ...mediaFields } } } {$list_fragment}",
        'detail' => "query (\$id: Int) { Media(id: \$id, type: ANIME) { ...detailFields } } {$detail_fragment}",
    ];

    $query = $queries[$type];
    $variables = ['page' => $page];
    if ($search) $variables['search'] = $search;
    if ($id) $variables['id'] = $id;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, ANILIST_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => $query, 'variables' => $variables]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    
    return $type === 'detail' ? $result['data']['Media'] : $result['data']['Page'];
}

/**
 * A simple wrapper for making requests to the Telegram Bot API.
 */
function apiRequest($method, $parameters) {
    $url = API_URL . $method;
    if (!empty($parameters)) {
        $url .= "?" . http_build_query($parameters);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

?>

