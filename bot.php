
<?php

// ------ CONFIGURATION ------
// !!! IMPORTANT: Replace these with your actual bot token and web app URL !!!
define('BOT_TOKEN', '8112345924:AAFb7e-WrYKngyeaRzB4-Hl3hKukisR1giI');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('WEB_APP_URL', 'https://anipixel.onrender.com/index.html'); // The URL where your SPA is hosted

// ------ ANILIST API FUNCTION ------
function callAniListAPI($query, $variables) {
    $graphqlQuery = json_encode(['query' => $query, 'variables' => $variables]);

    $ch = curl_init('https://graphql.anilist.co');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $graphqlQuery);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// ------ TELEGRAM API FUNCTIONS ------
function sendMessage($chatId, $text, $keyboard = null) {
    $url = API_URL . 'sendMessage?chat_id=' . $chatId . '&text=' . urlencode($text) . '&parse_mode=HTML';
    if ($keyboard) {
        $url .= '&reply_markup=' . $keyboard;
    }
    file_get_contents($url);
}

// ------ BOT LOGIC ------

// Get update from Telegram
$update = json_decode(file_get_contents('php://input'), true);

if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = $message['text'];

    if (strpos($text, '/start') === 0) {
        $responseText = "<b>Welcome to Anime Explorer Bot!</b>\n\n";
        $responseText .= "You can discover anime using these commands:\n";
        $responseText .= "/trending - See what's trending now\n";
        $responseText .= "/popular - Browse all-time popular anime\n";
        $responseText .= "/romance - Top romance anime\n";
        $responseText .= "/search <code>One Punch Man</code> - Search for a specific anime";
        sendMessage($chatId, $responseText);

    } elseif (strpos($text, '/search') === 0) {
        $searchTerm = trim(substr($text, 7));
        if (empty($searchTerm)) {
            sendMessage($chatId, 'Please provide an anime name to search. e.g., <code>/search Attack on Titan</code>');
            exit;
        }

        $query = 'query ($search: String) { Page(page: 1, perPage: 5) { media(search: $search, type: ANIME, isAdult: false) { id title { english romaji } } } }';
        $variables = ['search' => $searchTerm];
        $result = callAniListAPI($query, $variables);
        
        $media = $result['data']['Page']['media'];

        if (empty($media)) {
            sendMessage($chatId, "Sorry, I couldn't find any results for '{$searchTerm}'.");
        } else {
            $keyboard = ['inline_keyboard' => []];
            foreach ($media as $anime) {
                $title = $anime['title']['english'] ?? $anime['title']['romaji'];
                $keyboard['inline_keyboard'][] = [['text' => $title, 'callback_data' => 'anime_' . $anime['id']]];
            }
            sendMessage($chatId, "Here are the top results for '<b>{$searchTerm}</b>':", json_encode($keyboard));
        }

    } else { // Handle popular, trending, etc.
        $commandMap = [
            '/trending' => ['sort' => 'TRENDING_DESC', 'title' => '🔥 Trending Anime'],
            '/popular' => ['sort' => 'POPULARITY_DESC', 'title' => '⭐ All-Time Popular'],
            '/romance' => ['sort' => 'SCORE_DESC', 'genre' => 'Romance', 'title' => '💖 Top Romance']
        ];
        
        if (array_key_exists($text, $commandMap)) {
            $params = $commandMap[$text];
            $query = 'query ($sort: [MediaSort], $genre: String) { Page(page: 1, perPage: 5) { media(sort: $sort, type: ANIME, isAdult: false, genre: $genre) { id title { english romaji } } } }';
            $variables = ['sort' => $params['sort']];
            if (isset($params['genre'])) {
                $variables['genre'] = $params['genre'];
            }

            $result = callAniListAPI($query, $variables);
            $media = $result['data']['Page']['media'];

            if (!empty($media)) {
                $keyboard = ['inline_keyboard' => []];
                foreach ($media as $anime) {
                    $title = $anime['title']['english'] ?? $anime['title']['romaji'];
                    $keyboard['inline_keyboard'][] = [['text' => $title, 'callback_data' => 'anime_' . $anime['id']]];
                }
                sendMessage($chatId, "Here are the <b>{$params['title']}</b>:", json_encode($keyboard));
            }
        }
    }

} elseif (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $data = $callbackQuery['data'];

    if (strpos($data, 'anime_') === 0) {
        $animeId = substr($data, 6);
        
        // Fetch anime title for the message
        $query = 'query ($id: Int) { Media(id: $id, type: ANIME) { title { english romaji } } }';
        $result = callAniListAPI($query, ['id' => (int)$animeId]);
        $animeTitle = $result['data']['Media']['title']['english'] ?? $result['data']['Media']['title']['romaji'];

        // Create the Mini App button
        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '🚀 Open Full Details!',
                        'web_app' => ['url' => WEB_APP_URL . '#anime-' . $animeId]
                    ]
                ]
            ]
        ];

        sendMessage($chatId, "You selected <b>{$animeTitle}</b>. Click below to see everything!", json_encode($keyboard));
    }
}
?>
