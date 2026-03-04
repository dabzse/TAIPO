<?php

namespace App\Controller;

use App\Service\SettingsService;
use App\Config;

class SettingsController
{
    private SettingsService $settingsService;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    public function handleGetSetting(string $key)
    {
        $value = $this->settingsService->getSetting($key);

        header(Config::APP_JSON);
        echo json_encode(['success' => true, 'key' => $key, 'value' => $value]);
    }

    public function handleSaveSetting()
    {
        $key = strip_tags(trim($_POST['key'] ?? ''));
        $value = trim($_POST['value'] ?? '');

        if (!$key || $value === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing key or value']);
            return;
        }

        if (!preg_match('/^\w+$/', $key)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid setting key']);
            return;
        }

        try {
            $this->settingsService->saveSetting($key, $value);
            header(Config::APP_JSON);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
