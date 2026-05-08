<?php

namespace App\Controller;

use App\Config;
use App\Configuration\GeminiConfig;
use App\Service\GeminiService;
use App\Service\ProjectService;
use App\Service\TawosService;
use App\Service\TeamService;
use Exception;

class DashboardController
{
    private GeminiService $geminiService;
    private ProjectService $projectService;
    private TawosService $tawosService;
    private TeamService $teamService;

    public function __construct(
        GeminiService $geminiService,
        ProjectService $projectService,
        TawosService $tawosService,
        TeamService $teamService
    ) {
        $this->geminiService = $geminiService;
        $this->projectService = $projectService;
        $this->tawosService = $tawosService;
        $this->teamService = $teamService;
    }

    public function handleGetApiUsage(): void
    {
        header(Config::APP_JSON);
        try {
            $userId = $_SESSION['user_id'];
            $isInstructor = $_SESSION['is_instructor'] ?? false;
            $teamIds = [];
            if (!$isInstructor) {
                $userTeams = $this->teamService->listUserTeams($userId);
                $teamIds = array_column($userTeams, 'id');
            }

            $usageData = $this->geminiService->getAggregatedApiUsage($isInstructor, $userId, $teamIds);
            $costConfig = [];
            foreach ($usageData as $usageItem) {
                $model = $usageItem['model'];
                $costConfig[$model] = [
                    'promptCostPerMillion' => GeminiConfig::getModelPromptCost($model),
                    'candidateCostPerMillion' => GeminiConfig::getModelCandidateCost($model)
                ];
            }
            echo json_encode(['success' => true, 'data' => $usageData, 'config' => $costConfig]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleTawosAction(string $action): void
    {
        header(Config::APP_JSON);
        try {
            if ($action === 'get_tawos_stats') {
                echo json_encode(['success' => true, 'data' => $this->tawosService->getStats()]);
            } elseif ($action === 'get_tawos_sample') {
                $limit = (int)($_GET['limit'] ?? 5);
                echo json_encode(['success' => true, 'data' => $this->tawosService->getSample(min($limit, 20))]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleDashboardAction(): void
    {
        if (!($_SESSION['is_instructor'] ?? false)) {
            header(Config::APP_JSON, true, 403);
            echo json_encode(['success' => false, 'error' => 'Forbidden. Instructor role required.']);
            return;
        }

        header(Config::APP_JSON);
        try {
            $config = $this->getDashboardConfig();
            $tawos = $this->tawosService->getStats();
            $userId = $_SESSION['user_id'] ?? 0;
            $projects = $this->projectService->getAllProjects($userId, true);
            $this->attachProjectMetrics($projects);

            echo json_encode([
                'success' => true,
                'config' => $config,
                'tawos' => $tawos,
                'projects' => $projects,
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function getDashboardConfig(): array
    {
        $sensitiveKeys = ['GEMINI_API_KEY', 'GITHUB_TOKEN', 'GITHUB_CLIENT_SECRET'];
        $fullyHiddenKeys = ['DB_NAME', 'DB_USER', 'DB_PASS'];

        $config = [
            'Project' => [
                'PROJECT_NAME' => $_ENV['PROJECT_NAME'] ?? '',
                'MAX_TITLE_LENGTH' => $_ENV['MAX_TITLE_LENGTH'] ?? '42',
                'MAX_DESCRIPTION_LENGTH' => $_ENV['MAX_DESCRIPTION_LENGTH'] ?? '512',
                'MAX_QUERY_LENGTH' => $_ENV['MAX_QUERY_LENGTH'] ?? '1320',
            ],
            'Gemini API' => [
                'GEMINI_API_KEY' => $_ENV['GEMINI_API_KEY'] ?? '',
                'GEMINI_BASE_MODEL' => $_ENV['GEMINI_BASE_MODEL'] ?? '',
                'GEMINI_FALLBACK_MODEL' => $_ENV['GEMINI_FALLBACK_MODEL'] ?? '',
                'GEMINI_BASE_URL' => $_ENV['GEMINI_BASE_URL'] ?? '',
                'GEMINI_FALLBACK_URL' => $_ENV['GEMINI_FALLBACK_URL'] ?? '',
                'GEMINI_TEMPERATURE' => $_ENV['GEMINI_TEMPERATURE'] ?? '0.7',
                'GEMINI_TOP_K' => $_ENV['GEMINI_TOP_K'] ?? '40',
                'GEMINI_TOP_P' => $_ENV['GEMINI_TOP_P'] ?? '0.95',
                'GEMINI_MAX_OUTPUT_TOKENS' => $_ENV['GEMINI_MAX_OUTPUT_TOKENS'] ?? '4096',
            ],
            'Gemini Costs' => [
                'GEMINI_BASE_MODEL_PROMPT_COST_PER_MILLION' => $_ENV['GEMINI_BASE_MODEL_PROMPT_COST_PER_MILLION'] ?? '',
                'GEMINI_BASE_MODEL_CANDIDATE_COST_PER_MILLION' => $_ENV['GEMINI_BASE_MODEL_CANDIDATE_COST_PER_MILLION'] ?? '',
                'GEMINI_FALLBACK_MODEL_PROMPT_COST_PER_MILLION' => $_ENV['GEMINI_FALLBACK_MODEL_PROMPT_COST_PER_MILLION'] ?? '',
                'GEMINI_FALLBACK_MODEL_CANDIDATE_COST_PER_MILLION' => $_ENV['GEMINI_FALLBACK_MODEL_CANDIDATE_COST_PER_MILLION'] ?? '',
            ],
            'PO Simulation' => [
                'SIM_TIMEZONE' => $_ENV['SIM_TIMEZONE'] ?? 'UTC',
                'SIM_MIN_ACTIVE_HOUR' => $_ENV['SIM_MIN_ACTIVE_HOUR'] ?? '8',
                'SIM_MAX_ACTIVE_HOUR' => $_ENV['SIM_MAX_ACTIVE_HOUR'] ?? '16',
                'SIM_MIN_FEEDBACK_SEC' => $_ENV['SIM_MIN_FEEDBACK_SEC'] ?? '7200',
                'SIM_MAX_FEEDBACK_SEC' => $_ENV['SIM_MAX_FEEDBACK_SEC'] ?? '10800',
                'SIM_MIN_CR_SEC' => $_ENV['SIM_MIN_CR_SEC'] ?? '86400',
                'SIM_MAX_CR_SEC' => $_ENV['SIM_MAX_CR_SEC'] ?? '259200',
            ],
            'Users' => [
                'MIN_USERNAME_LENGTH' => $_ENV['MIN_USERNAME_LENGTH'] ?? '6',
                'MIN_PASSWORD_LENGTH' => $_ENV['MIN_PASSWORD_LENGTH'] ?? '8',
                'REGISTRATION_ENABLED' => $_ENV['REGISTRATION_ENABLED'] ?? 'true',
            ],
            'GitHub' => [
                'GITHUB_USERNAME' => $_ENV['GITHUB_USERNAME'] ?? '',
                'GITHUB_REPO' => $_ENV['GITHUB_REPO'] ?? '',
                'GITHUB_TOKEN' => $_ENV['GITHUB_TOKEN'] ?? '',
                'GITHUB_USERAGENT' => $_ENV['GITHUB_USERAGENT'] ?? '',
            ],
            'Database' => [
                'DB_TYPE' => $_ENV['DB_TYPE'] ?? 'sqlite',
                'SQLITE_FILE_NAME' => $_ENV['SQLITE_FILE_NAME'] ?? '',
                'DB_HOST' => $_ENV['DB_HOST'] ?? '',
                'DB_NAME' => $_ENV['DB_NAME'] ?? '',
                'DB_USER' => $_ENV['DB_USER'] ?? '',
                'DB_PASS' => $_ENV['DB_PASS'] ?? '',
                'TABLE_PREFIX' => $_ENV['TABLE_PREFIX'] ?? '',
            ],
            'Network' => [
                'ALLOWED_ORIGINS' => $_ENV['ALLOWED_ORIGINS'] ?? '',
                'SESSION_COOKIE_SECURE_FLAG' => $_ENV['FORCE_HTTPS'] ?? 'false',
                'ENFORCE_HTTPS_REDIRECT' => Config::isOffline() ? 'false' : 'true',
            ],
        ];

        foreach ($config as &$items) {
            foreach ($items as $key => &$value) {
                if (in_array($key, $fullyHiddenKeys)) {
                    $value = empty($value) ? '' : '••••••••';
                } elseif (in_array($key, $sensitiveKeys) && strlen($value) > 4) {
                    $value = str_repeat('•', min(strlen($value) - 4, 20)) . substr($value, -4);
                }
            }
        }
        return $config;
    }

    private function attachProjectMetrics(array &$projects): void
    {
        $metrics = $this->projectService->getProjectMetrics();
        foreach ($projects as &$project) {
            $name = $project['name'];
            $m = $metrics[$name] ?? ['total_tasks' => 0, 'done_tasks' => 0, 'last_wip_update' => null];

            $stalled = false;
            if ($m['total_tasks'] > 0 && $m['done_tasks'] < $m['total_tasks'] && $m['last_wip_update']) {
                $lastUpdate = new \DateTime($m['last_wip_update']);
                if ((new \DateTime())->diff($lastUpdate)->days >= 3) {
                    $stalled = true;
                }
            }

            $project['metrics'] = [
                'total_tasks' => (int)$m['total_tasks'],
                'done_tasks' => (int)$m['done_tasks'],
                'completion_rate' => $m['total_tasks'] > 0 ? round(($m['done_tasks'] / $m['total_tasks']) * 100) : 0,
                'stalled' => $stalled
            ];
        }
    }
}
