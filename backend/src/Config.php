<?php

namespace App;

class Config
{
    public static function getTablePrefix(): string
    {
        return $_ENV['TABLE_PREFIX'] ?? '';
    }

    public static function getProjectName(): string
    {
        return $_ENV['PROJECT_NAME'];
    }
    public const APP_JSON = 'Content-Type: application/json';
    public const ERROR_TASK_NOT_FOUND = "Task not found.";

    public const SUPPORTED_LANGUAGES = [
        'Python',
        'PHP',
        'Rust',
        'C++',
        'C#',
        'Dart',
        'Java',
        'Go',
        'TypeScript',
        'JavaScript'
    ];

    public static function getGithubUserAgent(): string
    {
        return "User-Agent: " . $_ENV['GITHUB_USERAGENT'];
    }

    public static function getMaxTitleLength(): int
    {
        return (int) ($_ENV['MAX_TITLE_LENGTH'] ?? 42);
    }

    public static function getMaxDescriptionLength(): int
    {
        return (int) ($_ENV['MAX_DESCRIPTION_LENGTH'] ?? 512);
    }

    public static function getMaxQueryLength(): int
    {
        return (int) ($_ENV['MAX_QUERY_LENGTH'] ?? 1320);
    }

    public static function getMinUsernameLength(): int
    {
        return (int) ($_ENV['MIN_USERNAME_LENGTH'] ?? 4);
    }

    public static function getMinPasswordLength(): int
    {
        return (int) ($_ENV['MIN_PASSWORD_LENGTH'] ?? 8);
    }

    public static function isRegistrationEnabled(): bool
    {
        return ($_ENV['REGISTRATION_ENABLED'] ?? 'true') === 'true';
    }

    public static function isOffline(): bool
    {
        $rootDir = realpath(__DIR__ . '/../../');
        return is_dir($rootDir . '/__OFFLINE') || file_exists($rootDir . '/.offline');
    }

    public static function getDatabaseConfig(): array
    {
        return [
            'sqlite_file' => $_ENV['SQLITE_FILE_NAME'] ?? null,
            'type' => $_ENV['DB_TYPE'] ?? 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'dbname' => $_ENV['DB_NAME'] ?? 'taipo',
            'user' => $_ENV['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASS'] ?? '',
            'port' => $_ENV['DB_PORT'] ?? '3306',
        ];
    }
}
