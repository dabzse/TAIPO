<?php

namespace App;

class Config
{
    public static function getProjectName(): string
    {
        return $_ENV['PROJECT_NAME'];
    }
    public const APP_JSON = 'Content-Type: application/json';

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

    public static function getGeminiFullUrl(): string
    {
        $baseUrl = self::getGeminiBaseUrl();
        $model = self::getGeminiModel();
        $apiKey = self::getGeminiApiKey();

        return "{$baseUrl}/models/{$model}:generateContent?key={$apiKey}";
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

    public static function getGithubUserAgent(): string
    {
        return "User-Agent: " . $_ENV['GITHUB_USERAGENT'];
    }
}
