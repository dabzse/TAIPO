<?php

namespace App\Config;

class GeminiConfig
{
    public const GEMINI_API_KEY_HEADER = 'x-goog-api-key';

    public static function getGeminiBaseUrl(): string
    {
        return $_ENV['GEMINI_BASE_URL'] ?? $_ENV['GEMINI_FALLBACK_URL'];
    }

    public static function getGeminiModel(): string
    {
        return $_ENV['GEMINI_BASE_MODEL'] ?? $_ENV['GEMINI_FALLBACK_MODEL'];
    }

    public static function getGeminiApiKey(): string
    {
        return $_ENV['GEMINI_API_KEY'] ?? '';
    }

    public static function getGeminiApiKeyHeader(): string
    {
        return self::GEMINI_API_KEY_HEADER . ': ' . self::getGeminiApiKey();
    }

    public static function getGeminiFullUrl(): string
    {
        $baseUrl = self::getGeminiBaseUrl();
        $model = self::getGeminiModel();

        return "{$baseUrl}/models/{$model}:generateContent";
    }

    public static function getModelPromptCost(string $modelName): float
    {
        if ($modelName === self::getGeminiModel()) {
            return (float) ($_ENV['GEMINI_BASE_MODEL_PROMPT_COST_PER_MILLION'] ?? 0.075);
        }
        return (float) ($_ENV['GEMINI_FALLBACK_MODEL_PROMPT_COST_PER_MILLION'] ?? 0.075);
    }

    public static function getModelCandidateCost(string $modelName): float
    {
        if ($modelName === self::getGeminiModel()) {
            return (float) ($_ENV['GEMINI_BASE_MODEL_CANDIDATE_COST_PER_MILLION'] ?? 0.300);
        }
        return (float) ($_ENV['GEMINI_FALLBACK_MODEL_CANDIDATE_COST_PER_MILLION'] ?? 0.300);
    }

    public static function getGeminiTemperature(): float
    {
        return (float) ($_ENV['GEMINI_TEMPERATURE'] ?? 0.7);
    }

    public static function getGeminiTopK(): int
    {
        return (int) ($_ENV['GEMINI_TOP_K'] ?? 40);
    }

    public static function getGeminiTopP(): float
    {
        return (float) ($_ENV['GEMINI_TOP_P'] ?? 0.95);
    }

    public static function getGeminiMaxOutputTokens(): int
    {
        return (int) ($_ENV['GEMINI_MAX_OUTPUT_TOKENS'] ?? 4096);
    }
}
