<?php

namespace AniPixel;

use Exception;

/**
 * File: src/AniListAPI.php
 * Description: AniPixel Bot - AniList API Wrapper
 * This class handles all GraphQL requests to the AniList API.
 */
class AniListAPI {
    private const API_URL = 'https://graphql.anilist.co';

    private $queries = [];

    public function __construct() {
        $list_fragment = 'fragment mediaFields on Media { id title { romaji english } }';
        $detail_fragment = 'fragment detailFields on Media { id type title { romaji english } coverImage { extraLarge } genres averageScore }';

        $this->queries = [
            'trending' => "query (\$page: Int) { Page(page: \$page, perPage: 5) { pageInfo { hasNextPage } media(sort: TRENDING_DESC, type: ANIME, isAdult: false) { ...mediaFields } } } {$list_fragment}",
            'popular' => "query (\$page: Int) { Page(page: \$page, perPage: 5) { pageInfo { hasNextPage } media(sort: POPULARITY_DESC, type: ANIME, isAdult: false) { ...mediaFields } } } {$list_fragment}",
            'romance' => "query (\$page: Int) { Page(page: \$page, perPage: 5) { pageInfo { hasNextPage } media(sort: SCORE_DESC, type: ANIME, genre: \"Romance\", isAdult: false) { ...mediaFields } } } {$list_fragment}",
            'search' => "query (\$page: Int, \$search: String) { Page(page: \$page, perPage: 5) { pageInfo { hasNextPage } media(search: \$search, type: ANIME, isAdult: false) { ...mediaFields } } } {$list_fragment}",
            'detail_anime' => "query (\$id: Int) { Media(id: \$id, type: ANIME) { ...detailFields } } {$detail_fragment}",
            'detail_manga' => "query (\$id: Int) { Media(id: \$id, type: MANGA) { ...detailFields } } {$detail_fragment}",
        ];
    }
    
    /**
     * Fetches data from the AniList API.
     * @param string $type
     * @param array $variables
     * @return array|null
     * @throws Exception
     */
    public function fetch(string $type, array $variables = []): ?array {
        if (!isset($this->queries[$type])) {
            throw new Exception("Invalid AniList query type: {$type}");
        }

        $query = $this->queries[$type];
        $payload = json_encode(['query' => $query, 'variables' => $variables]);

        Logger::logInfo("AniList Request: {$type} | Vars: " . json_encode($variables));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('cURL Error fetching AniList: ' . $error);
        }
        curl_close($ch);

        $result = json_decode($response, true);

        if (isset($result['errors'])) {
            Logger::logError("AniList API Error: " . json_encode($result['errors']));
            return null;
        }

        // Based on the query type, returns the correct part of the data
        if (strpos($type, 'detail') === 0) {
            return $result['data']['Media'] ?? null;
        }
        return $result['data']['Page'] ?? null;
    }
}

