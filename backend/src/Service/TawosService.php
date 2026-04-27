<?php

namespace App\Service;

use PDO;
use Exception;
use App\Config;

class TawosService
{
    private PDO $pdo;
    private string $prefix;
    private string $dbType;

    public function __construct(PDO $pdo, string $dbType = 'sqlite')
    {
        $this->pdo = $pdo;
        $this->prefix = Config::getTablePrefix();
        $this->dbType = $dbType;
    }

    private function randomFunc(): string
    {
        return ($this->dbType === 'mysql') ? 'RAND()' : 'RANDOM()';
    }

    /**
     * Check if the tawos_issues table has been seeded with data.
     */
    public function isSeeded(): bool
    {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$this->prefix}tawos_issues");
            return (int)$stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Seed the tawos_issues table from a CSV file.
     * Returns the number of records inserted.
     */
    public function seedFromCsv(string $csvPath): int
    {
        if (!file_exists($csvPath) || !is_readable($csvPath)) {
            error_log("TawosService: CSV file not found or not readable: {$csvPath}");
            return 0;
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return 0;
        }

        // Read and validate header
        $header = fgetcsv($handle);
        if (!$header || count($header) < 10) {
            fclose($handle);
            return 0;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->prefix}tawos_issues
            (issue_key, title, description_text, type, priority, status, resolution, story_point, comment_text, project_name)
            VALUES (:issue_key, :title, :description_text, :type, :priority, :status, :resolution, :story_point, :comment_text, :project_name)"
        );

        $count = 0;
        $this->pdo->beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 10) {
                    continue;
                }

                $stmt->execute([
                    ':issue_key' => $row[0],
                    ':title' => $row[1],
                    ':description_text' => $row[2],
                    ':type' => $row[3],
                    ':priority' => $row[4],
                    ':status' => $row[5],
                    ':resolution' => $row[6],
                    ':story_point' => is_numeric($row[7]) ? (float)$row[7] : null,
                    ':comment_text' => $row[8] ?: null,
                    ':project_name' => $row[9],
                ]);
                $count++;
            }
            $this->pdo->commit();
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("TawosService: Seed failed: " . $e->getMessage());
            $count = 0;
        }

        fclose($handle);
        return $count;
    }

    /**
     * Auto-seed on first boot if the table is empty.
     */
    public function autoSeed(): void
    {
        if ($this->isSeeded()) {
            return;
        }

        $csvPath = realpath(__DIR__ . '/../../data/tawos_seed.csv');
        if ($csvPath) {
            $inserted = $this->seedFromCsv($csvPath);
            if ($inserted > 0) {
                error_log("TawosService: Auto-seeded {$inserted} TAWOS records.");
            }
        }
    }

    /**
     * Get issues filtered by type (Story, Bug, Task).
     */
    public function getRelevantIssues(string $type, int $limit = 5): array
    {
        $rf = $this->randomFunc();
        $stmt = $this->pdo->prepare(
            "SELECT issue_key, title, description_text, type, priority, story_point, comment_text
             FROM {$this->prefix}tawos_issues
             WHERE type = :type
             ORDER BY {$rf}
             LIMIT :lim"
        );
        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a random real-world comment for tone calibration in PO prompts.
     */
    public function getRandomComment(?string $type = null): ?string
    {
        $rf = $this->randomFunc();
        $where = "comment_text IS NOT NULL AND comment_text != ''";
        $params = [];

        if ($type !== null) {
            $where .= " AND type = :type";
            $params[':type'] = $type;
        }

        $stmt = $this->pdo->prepare(
            "SELECT comment_text FROM {$this->prefix}tawos_issues
             WHERE {$where}
             ORDER BY {$rf}
             LIMIT 1"
        );
        $stmt->execute($params);
        return $stmt->fetchColumn() ?: null;
    }

    /**
     * Get a sample change request pattern from TAWOS data for CR prompt enrichment.
     */
    public function getSampleChangePattern(): ?array
    {
        $rf = $this->randomFunc();
        $stmt = $this->pdo->prepare(
            "SELECT title, description_text, type, priority
             FROM {$this->prefix}tawos_issues
             WHERE type = 'Story' AND description_text IS NOT NULL AND description_text != ''
             ORDER BY {$rf}
             LIMIT 1"
        );
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get dataset statistics.
     */
    public function getStats(): array
    {
        $total = $this->pdo->query("SELECT COUNT(*) FROM {$this->prefix}tawos_issues")->fetchColumn();

        $typeStmt = $this->pdo->query(
            "SELECT type, COUNT(*) as count FROM {$this->prefix}tawos_issues GROUP BY type ORDER BY count DESC"
        );
        $types = $typeStmt->fetchAll(PDO::FETCH_ASSOC);

        $projectStmt = $this->pdo->query(
            "SELECT DISTINCT project_name FROM {$this->prefix}tawos_issues ORDER BY project_name"
        );
        $projects = $projectStmt->fetchAll(PDO::FETCH_COLUMN);

        return [
            'total' => (int)$total,
            'types' => $types,
            'projects' => $projects,
        ];
    }

    /**
     * Get a random sample of TAWOS records (for debug/demo).
     */
    public function getSample(int $limit = 5): array
    {
        $rf = $this->randomFunc();
        $stmt = $this->pdo->prepare(
            "SELECT issue_key, title, type, priority, status, story_point, project_name
             FROM {$this->prefix}tawos_issues
             ORDER BY {$rf}
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
