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

// Handle form submission
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
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Shortener - Results</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #121212;
            color: #ffffff;
        }
        .form-control, .form-control:focus {
            background-color: #1e1e1e;
            color: #ffffff;
            border-color: #333333;
        }
        .btn-primary {
            background-color: #343a40;
            border-color: #343a40;
        }
        .btn-primary:hover {
            background-color: #23272b;
            border-color: #1d2124;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4">URL Shortener - Results</h1>
        <?php if (isset($results)): ?>
            <?php if ($rawOutput): ?>
                <h2 class="mb-3">Raw Output</h2>
                <pre class="bg-dark text-white p-3 rounded">
                    <?php foreach ($results as $result): ?>
                        <?= htmlspecialchars($result['shortened']) . "\n" ?>
                    <?php endforeach; ?>
                </pre>
            <?php else: ?>
                <h2 class="mb-3">Results</h2>
                <ul class="list-group">
                    <?php foreach ($results as $result): ?>
                        <li class="list-group-item bg-dark text-white">
                            Original URL: <?= htmlspecialchars($result['original']) ?><br>
                            <?php if (isset($result['error'])): ?>
                                Error: <?= htmlspecialchars($result['error']) ?>
                            <?php else: ?>
                                Shortened URL: <a href="<?= htmlspecialchars($result['shortened']) ?>" target="_blank" class="text-primary"><?= htmlspecialchars($result['shortened']) ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
