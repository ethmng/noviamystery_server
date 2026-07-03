<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }
    if (headers_sent()) {
        return;
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'Erreur serveur interne.',
        'code' => 'NM-5000',
    ], JSON_UNESCAPED_UNICODE);
});

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$configFile = __DIR__ . '/config.php';
if (is_readable($configFile)) {
    require_once $configFile;
} else {
    define('API_VERSION', '1.1.0');
    define('MAINTENANCE_MODE', false);
    define('MAINTENANCE_MESSAGE', 'Le serveur est en cours de maintenance. Réessayez plus tard.');
    define('PLAYER_NAME_MAX_LENGTH', 32);
    define('LEADERBOARD_MAX_ENTRIES', 20);
}

require_once __DIR__ . '/generator.php';

$route = getRequestRoute();
$segments = explode('/', trim($route, '/'));

if (count($segments) < 2 || $segments[0] !== 'mystery') {
    sendJson(['error' => 'Endpoint inconnu', 'code' => 'NM-4040'], 404);
}

switch ($segments[1]) {
    case 'status':
        handleStatus();
        break;
    case 'scenario':
        handleScenario();
        break;
    case 'solve':
        handleSolve();
        break;
    case 'leaderboard':
        handleLeaderboard();
        break;
    default:
        sendJson(['error' => 'Endpoint inconnu', 'code' => 'NM-4040'], 404);
        break;
}

function getRequestRoute(): string
{
    if (isset($_GET['route'])) {
        return trim((string) $_GET['route'], '/');
    }

    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = dirname($script);

    if ($base !== '/' && strpos($uri, $base) === 0) {
        $uri = substr($uri, strlen($base));
    }

    return trim($uri, '/');
}

function handleStatus(): void
{
    if (MAINTENANCE_MODE) {
        sendJson([
            'status' => 'maintenance',
            'message' => MAINTENANCE_MESSAGE,
            'version' => API_VERSION,
        ]);
    }

    sendJson([
        'status' => 'ok',
        'version' => API_VERSION,
        'date' => date('Y-m-d'),
    ]);
}

function handleScenario(): void
{
    if (MAINTENANCE_MODE) {
        sendJson([
            'status' => 'maintenance',
            'message' => MAINTENANCE_MESSAGE,
            'code' => 'NM-1003',
        ]);
    }

    $scenario = generateScenario();
    sendJson(buildPublicScenario($scenario));
}

function handleSolve(): void
{
    if (MAINTENANCE_MODE) {
        sendSolveResponse(false, 'Le serveur est en maintenance. Réessayez plus tard.');
    }

    $body = file_get_contents('php://input');
    $data = json_decode($body ?? '', true);

    if (!is_array($data)) {
        sendSolveResponse(false, 'Requête invalide. Veuillez réessayer.');
    }

    $required = ['player', 'suspect', 'weapon', 'room'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') {
            sendSolveResponse(false, "Le champ {$field} est requis.");
        }
    }

    $player = normalizePlayerName($data['player']);
    if ($player === null) {
        sendSolveResponse(false, 'Pseudo invalide (1 à 32 caractères).');
    }

    $scenarioDate = isset($data['scenario_date']) && is_string($data['scenario_date'])
        ? $data['scenario_date']
        : date('Y-m-d');

    $scenario = generateScenario($scenarioDate);
    $scenarioId = buildScenarioId(
        $scenarioDate,
        $scenario['culprit'],
        $scenario['room'],
        $scenario['weapon']
    );

    if (isset($data['scenario_id']) && is_string($data['scenario_id']) && $data['scenario_id'] !== $scenarioId) {
        sendSolveResponse(false, 'Scénario invalide ou expiré. Rechargez la partie.');
    }

    $isCorrect = ($data['suspect'] === $scenario['culprit'])
        && ($data['weapon'] === $scenario['weapon'])
        && ($data['room'] === $scenario['room']);

    if ($isCorrect) {
        $entry = [
            'player' => $player,
            'clues_found' => normalizeInt($data['clues_found'] ?? 0),
            'time_seconds' => normalizeInt($data['time_seconds'] ?? 0),
            'date' => $scenarioDate,
            'scenario_id' => $scenarioId,
        ];
        appendLeaderboardEntry($entry);
        sendSolveResponse(true, 'Bravo ! Vous avez trouvé le bon suspect, la bonne arme et la bonne pièce.');
    }

    sendSolveResponse(false, '', [
        'suspect' => $scenario['culprit'],
        'weapon' => $scenario['weapon'],
        'room' => $scenario['room'],
    ]);
}

function handleLeaderboard(): void
{
    if (MAINTENANCE_MODE) {
        sendJson([]);
    }

    sendJson(getLeaderboard());
}

function sendSolveResponse(bool $correct, string $explanation, ?array $solution = null): void
{
    $payload = [
        'correct' => $correct,
        'game_over' => true,
    ];

    if ($correct) {
        $payload['explanation'] = $explanation;
    } elseif ($solution !== null) {
        $payload['solution'] = $solution;
    } else {
        $payload['explanation'] = $explanation !== ''
            ? $explanation
            : 'Enquête terminée.';
    }

    sendJson($payload);
}

function normalizePlayerName(string $name): ?string
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    if (function_exists('mb_strlen')) {
        if (mb_strlen($name) > PLAYER_NAME_MAX_LENGTH) {
            return null;
        }
    } elseif (strlen($name) > PLAYER_NAME_MAX_LENGTH) {
        return null;
    }

    return $name;
}

function normalizeInt($value, int $default = 0): int
{
    if (is_int($value)) {
        return max(0, $value);
    }

    if (is_float($value)) {
        return max(0, (int) $value);
    }

    if (is_string($value) && is_numeric($value)) {
        return max(0, (int) $value);
    }

    return $default;
}

function sendJson($payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}