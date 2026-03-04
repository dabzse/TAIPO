<?php

namespace App\Service;

use PDO;
use Exception;

class RequirementService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function saveRequirement(string $projectName, string $content): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO requirements (project_name, content) VALUES (:project_name, :content)");
        $stmt->execute([
            ':project_name' => $projectName,
            ':content' => $content
        ]);
    }

    public function getRequirements(string $projectName): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM requirements WHERE project_name = :project_name ORDER BY created_at DESC");
        $stmt->execute([':project_name' => $projectName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
