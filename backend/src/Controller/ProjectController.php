<?php

namespace App\Controller;

use App\Service\ProjectService;
use App\Config;
use Exception;
use App\Exception\ProjectNotFoundException;
use App\Exception\ProjectAlreadyExistsException;
use App\Exception\GeminiApiException;
use App\Prompts;

class ProjectController
{
    private ProjectService $projectService;
 
    public function __construct(ProjectService $projectService)
    {
        $this->projectService = $projectService;
    }

    public function handleList()
    {
        try {
            $userId = $_SESSION['user_id'] ?? 0;
            $isInstructor = $_SESSION['is_instructor'] ?? false;
            $projects = $this->projectService->getAllProjects($userId, $isInstructor);
            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'projects' => $projects]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleCreate()
    {
        $name = strip_tags(trim($_POST['name'] ?? ''));
        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Project name is required']);
            return;
        }

        if (strlen($name) > Config::getMaxTitleLength()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Project name too long']);
            return;
        }

        try {
            $userId = $_SESSION['user_id'] ?? null;
            $teamId = filter_var($_POST['team_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
            $id = $this->projectService->createProject($name, $userId, $teamId);
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
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $name = strip_tags(trim($_POST['name'] ?? ''));

        if (!$id || empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID and name are required']);
            return;
        }

        if (strlen($name) > Config::getMaxTitleLength()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Project name too long']);
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
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

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
