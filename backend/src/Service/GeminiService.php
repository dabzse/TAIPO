<?php

namespace App\Service;

use App\Config;
use App\Configuration\GeminiConfig;
use App\Exception\GeminiApiException;
use App\Service\SecurityService;
use PDO;

class GeminiService
{
    private ?PDO $pdo;
    private ?int $currentUserId = null;
    private ?int $currentTeamId = null;
    private bool $isConfigured = false;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
        $apiKey = GeminiConfig::getGeminiApiKey();
        $this->isConfigured = SecurityService::isValidGeminiKey($apiKey);
    }

    public function setContext(?int $userId, ?int $teamId = null): void
    {
        $this->currentUserId = $userId;
        $this->currentTeamId = $teamId;
    }

    /**
     * Resolves the active Gemini API key according to hierarchy:
     * ?hallgató -> ?csapat -> :egyetemi
     *
     * @return array{key: string, source: string}
     */
    public function resolveApiKeyDetails(): array
    {
        $studentKey = $this->resolveStudentKey();
        if ($studentKey !== null) {
            return $studentKey;
        }

        $teamKey = $this->resolveTeamKey();
        if ($teamKey !== null) {
            return $teamKey;
        }

        return $this->resolveUniversityKey();
    }

    private function resolveStudentKey(): ?array
    {
        // 1. Session memory key (from .apikeyusb, .apikey, or manual paste)
        $sessionKey = $_SESSION['user_gemini_api_key'] ?? ($_SERVER['HTTP_X_USER_GEMINI_API_KEY'] ?? null);
        if ($sessionKey && SecurityService::isValidGeminiKey((string)$sessionKey)) {
            $source = $_SESSION['user_gemini_api_key_source'] ?? 'student_session';
            return ['key' => trim((string)$sessionKey), 'source' => $source === 'usb' ? 'student_usb' : 'student_session'];
        }

        // 2. Database saved key (AES-256 encrypted)
        $userId = $this->currentUserId ?? ($_SESSION['user_id'] ?? null);
        if ($userId && $this->pdo) {
            $key = $this->fetchEncryptedKeyFromTable('users', (int)$userId);
            if ($key !== null) {
                return ['key' => $key, 'source' => 'student_database'];
            }
        }

        return null;
    }

    private function resolveTeamKey(): ?array
    {
        $teamSessionKey = $_SESSION['team_gemini_api_key'] ?? null;
        if ($teamSessionKey && SecurityService::isValidGeminiKey((string)$teamSessionKey)) {
            return ['key' => trim((string)$teamSessionKey), 'source' => 'team_session'];
        }

        $teamId = $this->currentTeamId ?? null;
        if ($teamId && $this->pdo) {
            $key = $this->fetchEncryptedKeyFromTable('teams', (int)$teamId);
            if ($key !== null) {
                return ['key' => $key, 'source' => 'team_database'];
            }
        }

        return null;
    }

    private function resolveUniversityKey(): array
    {
        $envKey = GeminiConfig::getGeminiApiKey();
        if ($envKey && SecurityService::isValidGeminiKey($envKey)) {
            return ['key' => $envKey, 'source' => 'university'];
        }

        return ['key' => '', 'source' => 'none'];
    }

    private function fetchEncryptedKeyFromTable(string $table, int $id): ?string
    {
        try {
            $prefix = Config::getTablePrefix();
            $stmt = $this->pdo->prepare("SELECT api_key_encrypted FROM {$prefix}{$table} WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $encrypted = $stmt->fetchColumn();
            if ($encrypted && is_string($encrypted)) {
                $decrypted = SecurityService::decryptApiKey($encrypted);
                if ($decrypted && SecurityService::isValidGeminiKey($decrypted)) {
                    return $decrypted;
                }
            }
        } catch (\Exception $e) {
            error_log("Failed to resolve {$table} API key: " . $e->getMessage());
        }
        return null;
    }

    public function resolveApiKey(): string
    {
        return $this->resolveApiKeyDetails()['key'];
    }

    public function askTaipo(string $prompt, bool $isFallback = false): string
    {
        $apiKey = $this->resolveApiKey();
        if (!SecurityService::isValidGeminiKey($apiKey)) {
            throw new GeminiApiException("Gemini API key is not set or invalid.");
        }

        $baseUrl = $isFallback ? ($_ENV['GEMINI_FALLBACK_URL'] ?? '') : GeminiConfig::getGeminiBaseUrl();
        $model = $isFallback ? ($_ENV['GEMINI_FALLBACK_MODEL'] ?? '') : GeminiConfig::getGeminiModel();

        $url = "{$baseUrl}/models/{$model}:generateContent";
        $data = $this->buildRequestPayload($prompt);

        try {
            $response = $this->makeRequest($url, $data, $apiKey);
            return $this->processResponse($response, $model, $isFallback);
        } catch (GeminiApiException $e) {
            $code = $e->getCode();
            // If we are not already in fallback mode and receive a critical error (overloaded), try again with fallback
            if (!$isFallback && in_array($code, [429, 503, 500, 502])) {
                error_log("Gemini API error ({$code}). Retrying with fallback model...");
                return $this->askTaipo($prompt, true);
            }
            throw $e;
        }
    }

    private function buildRequestPayload(string $prompt): array
    {
        return [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => GeminiConfig::getGeminiTemperature(),
                'topK' => GeminiConfig::getGeminiTopK(),
                'topP' => GeminiConfig::getGeminiTopP(),
                'maxOutputTokens' => GeminiConfig::getGeminiMaxOutputTokens(),
            ]
        ];
    }

    private function processResponse(array $response, string $model, bool $isFallback): string
    {
        $body = $response['body'];
        $httpCode = $response['http_code'];

        $result = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $snippet = trim(substr(strip_tags($body), 0, 100));
            throw new GeminiApiException("Invalid JSON response. HTTP: {$httpCode}. Context: {$snippet}", $httpCode ?: 500);
        }

        $this->handleApiErrors($result, $httpCode);
        $this->logUsage($result, $model);

        $text = $result['candidates'][0]['content']['parts'][0]['text'];

        if ($isFallback) {
            $text = "> **Note:** The main model is overloaded, so this response was generated by the fallback model.\n\n" . $text;
        }

        return $text;
    }

    private function handleApiErrors(array $result, int $httpCode): void
    {
        if (isset($result['error'])) {
            $errorMessage = $result['error']['message'] ?? 'Unknown error';
            $errorCode = (int)($result['error']['code'] ?? $httpCode);
            $errorStatus = $result['error']['status'] ?? 'UNKNOWN';

            $contextualMessage = $this->getContextualMessage($errorCode, $errorStatus);
            $finalMessage = $contextualMessage ? "{$contextualMessage} (API: {$errorMessage})" : "API error [{$errorStatus}]: {$errorMessage}";

            throw new GeminiApiException($finalMessage, $errorCode ?: 500);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $contextualMessage = $this->getContextualMessage($httpCode, '');
            $finalMessage = $contextualMessage ? $contextualMessage : "API request failed with HTTP Code: {$httpCode}";
            throw new GeminiApiException($finalMessage, $httpCode);
        }

        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $blockReason = $result['candidates'][0]['finishReason'] ?? 'unknown';
            throw new GeminiApiException("API response blocked or invalid format. Reason: " . $blockReason, 502);
        }
    }

    private function logUsage(array $result, string $modelName): void
    {
        $usageMetadata = $result['usageMetadata'] ?? null;
        if ($usageMetadata && $this->pdo) {
            $promptTokens = $usageMetadata['promptTokenCount'] ?? 0;
            $candidateTokens = $usageMetadata['candidatesTokenCount'] ?? 0;
            $totalTokens = $usageMetadata['totalTokenCount'] ?? 0;

            try {
                $prefix = Config::getTablePrefix();
                $stmt = $this->pdo->prepare("INSERT INTO {$prefix}api_usage (endpoint, prompt_tokens, candidate_tokens, total_tokens, user_id, team_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$modelName, $promptTokens, $candidateTokens, $totalTokens, $this->currentUserId, $this->currentTeamId]);
            } catch (\Exception $e) {
                error_log("Failed to log API usage: " . $e->getMessage());
            }
        }
    }

    public function getAggregatedApiUsage(bool $isInstructor = false, ?int $userId = null, array $teamIds = []): array
    {
        $aggregatedUsage = [];
        if (!$this->pdo) {
            return $aggregatedUsage;
        }

        try {
            $prefix = Config::getTablePrefix();
            $query = "SELECT endpoint as model, SUM(prompt_tokens) as prompt_tokens, SUM(candidate_tokens) as candidate_tokens, SUM(total_tokens) as total_tokens FROM {$prefix}api_usage";
            $params = [];

            if (!$isInstructor) {
                $conditions = [];
                if ($userId !== null) {
                    $conditions[] = "user_id = :user_id";
                    $params[':user_id'] = $userId;
                }
                if (!empty($teamIds)) {
                    $teamPlaceholders = implode(',', array_map(function ($i) {
                        return ":team_id_$i";
                    }, array_keys($teamIds)));
                    $conditions[] = "team_id IN ($teamPlaceholders)";
                    foreach ($teamIds as $i => $id) {
                        $params[":team_id_$i"] = $id;
                    }
                }

                if (!empty($conditions)) {
                    $query .= " WHERE " . implode(" OR ", $conditions);
                } else {
                    return $aggregatedUsage;
                }
            }

            $query .= " GROUP BY endpoint";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($results) {
                $aggregatedUsage = array_map(function ($row) {
                    return [
                        'model' => $row['model'],
                        'prompt_tokens' => (int) $row['prompt_tokens'],
                        'candidate_tokens' => (int) $row['candidate_tokens'],
                        'total_tokens' => (int) $row['total_tokens']
                    ];
                }, $results);
            }
        } catch (\Exception $e) {
            error_log("Failed to get aggregated API usage: " . $e->getMessage());
        }

        return $aggregatedUsage;
    }

    private function getContextualMessage(int $httpCode, string $errorStatus): ?string
    {
        $message = null;

        if ($httpCode === 400 || $errorStatus === 'INVALID_ARGUMENT') {
            $message = "Bad Request: Data format issue or invalid API key.";
        } elseif ($httpCode === 403 || $errorStatus === 'PERMISSION_DENIED') {
            $message = "API Key is forbidden or lacks necessary permissions.";
        } elseif ($httpCode === 429 || $errorStatus === 'RESOURCE_EXHAUSTED') {
            $message = "Rate limit exceeded or quota exhausted. Please try again later.";
        } elseif ($httpCode === 500 || $httpCode === 502) {
            $message = "Gemini API is currently encountering an internal error.";
        }

        return $message;
    }

    private function makeRequest(string $url, array $data, ?string $apiKey = null): array
    {
        if (function_exists('curl_init')) {
            return $this->makeCurlRequest($url, $data, $apiKey);
        } else {
            return $this->makeFileGetContentsRequest($url, $data, $apiKey);
        }
    }

    private function makeCurlRequest(string $url, array $data, ?string $apiKey = null): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            Config::APP_JSON,
            GeminiConfig::getGeminiApiKeyHeader($apiKey)
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        // SSL verification to match production standards
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            throw new GeminiApiException("Request failed: " . $error); // Curl errors usually don't contain the URL/Key
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return ['body' => $response, 'http_code' => $httpCode];
    }

    private function makeFileGetContentsRequest(string $url, array $data, ?string $apiKey = null): array
    {
        $options = [
            'http' => [
                'header'  => Config::APP_JSON . "\r\n" .
                    GeminiConfig::getGeminiApiKeyHeader($apiKey) . "\r\n",
                'method'  => 'POST',
                'content' => json_encode($data),
                'timeout' => 60,
                'ignore_errors' => true // to fetch error body
            ],
            "ssl" => [
                "verify_peer" => true,
                "verify_peer_name" => true,
            ]
        ];

        $context  = stream_context_create($options);

        // Suppress warnings to handle errors manually and avoid exposing URL in standard error output
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            $safeErrorMessage = "Network request failed.";
            if (isset($error['message'])) {
                $safeErrorMessage .= " Details: " . $this->sanitizeErrorMessage($error['message'], $apiKey);
            }
            throw new GeminiApiException($safeErrorMessage);
        }

        $httpCode = 0;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('#HTTP/[\d\.]+\s+(\d+)#', $header, $matches)) {
                    $httpCode = intval($matches[1]);
                    break;
                }
            }
        }

        return ['body' => $response, 'http_code' => $httpCode];
    }

    private function sanitizeErrorMessage(string $msg, ?string $apiKey): string
    {
        $sanitized = preg_replace('/key=[^&\s]+/', 'key=***', $msg);
        if ($apiKey !== null && $apiKey !== '') {
            $sanitized = str_replace($apiKey, '***', $sanitized);
        }
        $envKey = GeminiConfig::getGeminiApiKey();
        if ($envKey !== '') {
            $sanitized = str_replace($envKey, '***', $sanitized);
        }
        return $sanitized;
    }
}
