<?php

namespace App\Service;

use PDO;

class SettingsService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getSetting(string $key): ?string
    {
        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE key = :key");
        $stmt->execute([':key' => $key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : null;
    }

    public function saveSetting(string $key, string $value): void
    {
        // Upsert (SQLite specific syntax for ON CONFLICT)
        $stmt = $this->pdo->prepare("
            INSERT INTO settings (key, value, updated_at)
            VALUES (:key, :value, CURRENT_TIMESTAMP)
            ON CONFLICT(key) DO UPDATE SET
            value = excluded.value,
            updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([':key' => $key, ':value' => $value]);
    }

    public function deleteSetting(string $key): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM settings WHERE key = :key");
        $stmt->execute([':key' => $key]);
    }
}
