<?php

namespace App;

class Config
{
    public const PROJECT_NAME = 'AIKanban';
    public const APP_JSON = 'Content-Type: application/json';

    public static function getGeminiBaseUrl(): string
    {
        return $_ENV['GEMINI_BASE_URL'] ?? 'https://generativelanguage.googleapis.com/v1beta';
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
}
