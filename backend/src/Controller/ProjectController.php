<?php

namespace App\Controller;

use App\Service\ProjectService;
use App\Service\TaskService;
use App\Config;
use Exception;
use App\Exception\ProjectNotFoundException;
use App\Exception\ProjectAlreadyExistsException;
use App\Prompts;

class ProjectController
{
    private ProjectService $projectService;
    private ?TaskService $taskService;

    public function __construct(ProjectService $projectService, ?TaskService $taskService = null)
    {
        $this->projectService = $projectService;
        $this->taskService = $taskService;
    }

    public function handleList()
    {
        try {
            $projects = $this->projectService->getAllProjects();
            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'projects' => $projects]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleCreate()
    {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Project name is required']);
            return;
        }

        try {
            $id = $this->projectService->createProject($name);
            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'id' => $id, 'name' => $name]);
        } catch (ProjectAlreadyExistsException $e) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleUpdate()
    {
        $id = $_POST['id'] ?? null;
        $name = trim($_POST['name'] ?? '');

        if (!$id || empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID and name are required']);
            return;
        }

        try {
            $this->projectService->renameProject((int)$id, $name);
            header(Config::APP_JSON);
            echo json_encode(['success' => true]);
        } catch (ProjectNotFoundException $e) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (ProjectAlreadyExistsException $e) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleDelete()
    {
        $id = $_POST['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID is required']);
            return;
        }

        try {
            $this->projectService->deleteProject((int)$id);
            header(Config::APP_JSON);
            echo json_encode(['success' => true]);
        } catch (ProjectNotFoundException $e) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleCreateFromSpec()
    {
        $spec = $_POST['spec'] ?? '';

        if (empty($spec)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Specification content is required.']);
            return;
        }

        if (!$this->taskService) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'TaskService not available.']);
            return;
        }

        try {
            // 1. Analyze Spec
            $result = $this->taskService->analyzeSpec($spec);
            $projectName = $result['name'];
            $tasks = $result['tasks'];

            if (empty($projectName)) {
                $projectName = "Project " . date('Y-m-d H:i:s');
            }

            // 2. Create Project
            try {
                $projectId = $this->projectService->createProject($projectName);
            } catch (ProjectAlreadyExistsException $e) {
                // If exists, maybe append timestamp? Or just use existing?
                // For now, let's append a random suffix
                $projectName .= " " . substr(md5(uniqid()), 0, 4);
                $projectId = $this->projectService->createProject($projectName);
            }

            // 3. Create Tasks
            $this->taskService->replaceProjectTasks($projectName, $tasks);

            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'projectName' => $projectName, 'projectId' => $projectId]);
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Error generating project from spec: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleGetDefaults()
    {
        $languages = Config::SUPPORTED_LANGUAGES;
        $prompts = [];
        foreach ($languages as $lang) {
            $prompts[$lang] = Prompts::getLanguagePrompt($lang);
        }

        header(Config::APP_JSON);
        echo json_encode(['success' => true, 'languages' => $languages, 'prompts' => $prompts]);
    }
}
