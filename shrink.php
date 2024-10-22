<?php
function getRandomShortener($config, $usedShorteners, $lastUsedIndex) {
    $availableShorteners = [];
    foreach ($config as $index => $shortener) {
        if (!in_array($index, $usedShorteners) && $index !== $lastUsedIndex) {
            $availableShorteners[] = $index;
        }
    }
    if (empty($availableShorteners)) {
        return null;
    }
    return $availableShorteners[array_rand($availableShorteners)];
}

function shortenUrl($url, $config, $redirections) {
    $shortenedUrl = $url;
    $usedShorteners = [];
    $shortenerCounts = array_fill(0, count($config), 0);
    $lastUsedIndex = null;

    for ($i = 0; $i < $redirections; $i++) {
        $shortenerIndex = getRandomShortener($config, $usedShorteners, $lastUsedIndex);
        if ($shortenerIndex === null) {
            throw new Exception("No more shorteners available.");
        }

        $shortener = $config[$shortenerIndex];
        $shortenerCounts[$shortenerIndex]++;

        if ($shortenerCounts[$shortenerIndex] > $shortener['limit']) {
            $usedShorteners[] = $shortenerIndex;
            $i--; // Retry with a different shortener
            continue;
        }

        $apiUrl = str_replace('{url}', urlencode($shortenedUrl), $shortener['api']);
        $response = file_get_contents($apiUrl);

        if ($response === false) {
            throw new Exception("Failed to shorten URL with API: " . $shortener['api']);
        }

        $shortenedUrl = $response;
        $lastUsedIndex = $shortenerIndex;
    }

    return $shortenedUrl;
}

// Load configuration
$config = require 'config.php';

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $urls = explode("\n", $_POST['urls']);
    $redirections = (int)$_POST['redirections'];
    $rawOutput = isset($_POST['raw_output']);
    $results = [];

    foreach ($urls as $url) {
        $url = trim($url);
        if (!empty($url)) {
            try {
                $finalShortenedUrl = shortenUrl($url, $config, $redirections);
                $results[] = [
                    'original' => $url,
                    'shortened' => $finalShortenedUrl,
                ];
            } catch (Exception $e) {
                $results[] = [
                    'original' => $url,
                    'error' => $e->getMessage(),
                ];
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}
?>
