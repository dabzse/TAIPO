<?php

namespace App\Controller;

use App\Service\RequirementService;
use App\Config;

class RequirementController
{
    private RequirementService $requirementService;

    public function __construct(RequirementService $requirementService)
    {
        $this->requirementService = $requirementService;
    }

    public function handleSaveRequirement()
    {
        $projectName = strip_tags(trim($_POST['project_name'] ?? ''));
        $content = trim($_POST['content'] ?? '');

        if (!$projectName || !$content) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Project name and content are required.']);
            return;
        }

        try {
            $this->requirementService->saveRequirement($projectName, $content);
            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'message' => 'Requirement saved successfully.']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleGetRequirements()
    {
        $projectName = strip_tags(trim($_GET['project_name'] ?? ''));

        if (!$projectName) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Project name is required.']);
            return;
        }

        try {
            $requirements = $this->requirementService->getRequirements($projectName);
            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'data' => $requirements]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
