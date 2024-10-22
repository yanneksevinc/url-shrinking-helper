<?php
function getRandomShortener($config, $usedShorteners) {
    $availableShorteners = [];
    foreach ($config as $index => $shortener) {
        if (!in_array($index, $usedShorteners)) {
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

    for ($i = 0; $i < $redirections; $i++) {
        $shortenerIndex = getRandomShortener($config, $usedShorteners);
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
    }

    return $shortenedUrl;
}

// Load configuration
$config = require 'config.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $urls = explode("\n", $_POST['urls']);
    $redirections = (int)$_POST['redirections'];
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
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>URL Shortener</title>
</head>
<body>
    <h1>URL Shortener</h1>
    <form method="post">
        <label for="urls">Enter URLs (one per line):</label><br>
        <textarea name="urls" id="urls" rows="10" cols="50"></textarea><br>
        <label for="redirections">Number of Redirections:</label>
        <input type="number" name="redirections" id="redirections" value="3" min="1"><br><br>
        <input type="submit" value="Shorten URLs">
    </form>

    <?php if (isset($results)): ?>
        <h2>Results</h2>
        <ul>
            <?php foreach ($results as $result): ?>
                <li>
                    Original URL: <?= htmlspecialchars($result['original']) ?><br>
                    <?php if (isset($result['error'])): ?>
                        Error: <?= htmlspecialchars($result['error']) ?>
                    <?php else: ?>
                        Shortened URL: <a href="<?= htmlspecialchars($result['shortened']) ?>" target="_blank"><?= htmlspecialchars($result['shortened']) ?></a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
