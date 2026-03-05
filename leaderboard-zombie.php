<?php
/**
 * Zombie Run Global Leaderboard API
 *
 * GET: Returns top 10 scores
 * POST: Submits a new score (requires name, score, distance, kills, headshots)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dataFile = __DIR__ . '/leaderboard-zombie-data.json';

// Initialize data file if it doesn't exist
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode(['scores' => []]));
}

// Read current data
function readData($file) {
    $content = file_get_contents($file);
    return json_decode($content, true) ?: ['scores' => []];
}

// Write data
function writeData($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Sanitize input
function sanitize($input, $maxLength = 20) {
    $clean = strip_tags(trim($input));
    $clean = preg_replace('/[^\w\s\-]/', '', $clean);
    return substr($clean, 0, $maxLength);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Return top 10 scores
    $data = readData($dataFile);
    $scores = $data['scores'];

    // Sort by distance descending (primary), then kills (secondary)
    usort($scores, function($a, $b) {
        if ($b['distance'] !== $a['distance']) {
            return $b['distance'] - $a['distance'];
        }
        return $b['kills'] - $a['kills'];
    });

    // Return top 10
    $top10 = array_slice($scores, 0, 10);

    echo json_encode([
        'success' => true,
        'scores' => $top10
    ]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Submit a new score
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['name']) || !isset($input['distance'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit();
    }

    $name = sanitize($input['name']);
    $distance = intval($input['distance']);
    $kills = intval($input['kills'] ?? 0);
    $headshots = intval($input['headshots'] ?? 0);
    $gold = intval($input['gold'] ?? 0);

    if (empty($name)) {
        $name = 'Anonymous';
    }

    if ($distance < 0 || $distance > 100000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid distance']);
        exit();
    }

    $data = readData($dataFile);

    // Add new score
    $data['scores'][] = [
        'name' => $name,
        'distance' => $distance,
        'kills' => $kills,
        'headshots' => $headshots,
        'gold' => $gold,
        'date' => date('Y-m-d H:i:s')
    ];

    // Sort and keep top 100 (to prevent file from growing too large)
    usort($data['scores'], function($a, $b) {
        if ($b['distance'] !== $a['distance']) {
            return $b['distance'] - $a['distance'];
        }
        return $b['kills'] - $a['kills'];
    });
    $data['scores'] = array_slice($data['scores'], 0, 100);

    writeData($dataFile, $data);

    // Find rank of submitted score
    $rank = 1;
    foreach ($data['scores'] as $entry) {
        if ($entry['distance'] === $distance && $entry['name'] === $name) {
            break;
        }
        $rank++;
    }

    echo json_encode([
        'success' => true,
        'rank' => $rank,
        'message' => $rank <= 10 ? 'You made the top 10!' : 'Score submitted!'
    ]);

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>
