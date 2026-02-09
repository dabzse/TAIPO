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
        $key = $_POST['key'] ?? null;
        $value = $_POST['value'] ?? null;

        if (!$key || $value === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing key or value']);
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
