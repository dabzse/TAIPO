<?php

namespace App\Service;

use PDO;
use App\Config;

class SettingsService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getSetting(string $key): ?string
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("SELECT setting_value FROM {$prefix}settings WHERE setting_key = :key");
        $stmt->execute([':key' => $key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : null;
    }

    public function saveSetting(string $key, string $value): void
    {
        $prefix = Config::getTablePrefix();
        $dbType = $_ENV['DB_TYPE'] ?? 'sqlite';

        if ($dbType === 'mysql' || $dbType === 'mariadb') {
            $stmt = $this->pdo->prepare("
                INSERT INTO {$prefix}settings (setting_key, setting_value, updated_at)
                VALUES (:key, :value, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_at = CURRENT_TIMESTAMP
            ");
        } elseif ($dbType === 'pgsql') {
            $stmt = $this->pdo->prepare("
                INSERT INTO {$prefix}settings (setting_key, setting_value, updated_at)
                VALUES (:key, :value, CURRENT_TIMESTAMP)
                ON CONFLICT(setting_key) DO UPDATE SET
                setting_value = EXCLUDED.setting_value,
                updated_at = CURRENT_TIMESTAMP
            ");
        } else {
            // SQLite
            $stmt = $this->pdo->prepare("
                INSERT INTO {$prefix}settings (setting_key, setting_value, updated_at)
                VALUES (:key, :value, CURRENT_TIMESTAMP)
                ON CONFLICT(setting_key) DO UPDATE SET
                setting_value = excluded.setting_value,
                updated_at = CURRENT_TIMESTAMP
            ");
        }
        $stmt->execute([':key' => $key, ':value' => $value]);
    }

    public function deleteSetting(string $key): void
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("DELETE FROM {$prefix}settings WHERE setting_key = :key");
        $stmt->execute([':key' => $key]);
    }
}
