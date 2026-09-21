<?php

/**
 * Background runner for PO Activity simulation tick.
 * Invoked asynchronously from CLI to prevent blocking HTTP requests.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Utils;
use App\Database;
use App\Service\GeminiService;
use App\Service\HistoryService;
use App\Service\TaskService;
use App\Service\TaskAiService;
use App\Service\TawosService;
use App\Service\PoActivityService;

$projectName = $argv[1] ?? '';
$userId = (int)($argv[2] ?? 0);

if (empty($projectName) || $userId <= 0) {
    exit(0);
}

// Load environment variables
$envPath = realpath(__DIR__ . '/../');
if (file_exists($envPath . '/.env')) {
    try {
        $dotenv = Dotenv::createImmutable($envPath);
        $dotenv->safeLoad();
    } catch (Exception $e) {
        Utils::loadEnv($envPath . '/.env');
    }
}

try {
    $database = new Database();
    $pdo = $database->getPdo();

    $geminiService = new GeminiService($pdo);
    $historyService = new HistoryService($pdo);
    $taskService = new TaskService($pdo, $geminiService, $historyService);
    $taskAiService = new TaskAiService($pdo, $geminiService, $taskService, $historyService);

    $tawosService = null;
    try {
        $tawosService = new TawosService($pdo, $database->getDbType());
    } catch (Exception $e) {
        // Optional service
    }

    $poActivityService = new PoActivityService(
        $pdo,
        $geminiService,
        $taskAiService,
        $historyService,
        $database->getDbType(),
        $tawosService
    );

    $poActivityService->tick($projectName, $userId);
} catch (Exception $e) {
    error_log("sim_tick background error: " . $e->getMessage());
}
