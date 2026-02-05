<?php

namespace App;

use App\Exception\GeminiApiException;
use App\Config;
use Exception;

class Utils
{
    public static function loadEnv($filePath = '.env')
    {
        if (!file_exists($filePath)) {
            return;
        }
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }

    public static function createSafeId($title)
    {
        $title = str_replace(
            ['á', 'é', 'í', 'ó', 'ö', 'ő', 'ú', 'ü', 'ű', ' '],
            ['a', 'e', 'i', 'o', 'o', 'o', 'u', 'u', 'u', '_'],
            strtolower($title)
        );
        return preg_replace('/[^a-z0-9_]/', '', $title);
    }

    public static function getWIPLimit($columnTitle)
    {
        if (preg_match('/WIP:(\d+)/i', $columnTitle, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    public static function formatCodeBlocks($markdown)
    {
        if (preg_match_all('/```(\w*)\n(.*?)```/s', $markdown, $matches)) {
            $output = '';
            foreach ($matches[2] as $index => $code) {
                $language = $matches[1][$index] ?: 'Code';

                $taskId = $_POST['task_id'] ?? '';
                $description = htmlspecialchars($_POST['description'] ?? '');

                $isUserLoggedIn = !empty($_POST['user_token']);

                $output .= '<div class="code-block-wrapper">';
                $output .= '<div class="code-language-header">';
                $output .= '<span>' . htmlspecialchars(ucfirst($language)) . '</span>';

                $output .= '<span class="header-actions">';

                if (!empty($taskId) && $isUserLoggedIn) {
                    $output .= '<button class="github-commit-button-inline" title="Commit to GitHub"
                                data-task-id="' . $taskId . '"
                                data-description="' . $description . '"
                                onclick="commitJavaCodeToGitHubInline(this)">
                                <svg height="16" aria-hidden="true" viewBox="0 0 16 16" version="1.1" width="16" style="fill: currentColor; vertical-align: middle;">
                                    <path fill-rule="evenodd" d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"></path>
                                </svg>
                            </button>';
                }

                $output .= '<button class="copy-icon" title="Copy Code" onclick="copyCodeBlock(this)">📋</button>';
                $output .= '</span>';

                $output .= '</div>';
                $output .= '<pre><code class="language-' . htmlspecialchars($language) . '">' . htmlspecialchars($code) . '</code></pre>';
                $output .= '</div>';
            }
            return $output;
        }
        return '<pre>' . htmlspecialchars($markdown) . '</pre>';
    }

    public static function callGeminiAPI($apiKey, $prompt)
    {
        $url = $_ENV['LLM_URL'] . '?key=' . $apiKey;

        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 4096,
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [Config::APP_JSON]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($response === false) {
            throw new GeminiApiException("cURL error: " . $curlError);
        }

        $result = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMessage = $result['error']['message'] ?? 'Unknown error';
            throw new GeminiApiException("API error ({$httpCode}): " . $errorMessage . " - Response: " . $response);
        }

        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $blockReason = $result['candidates'][0]['finishReason'] ?? 'unknown';
            throw new GeminiApiException("API response format error (or blocked). Reason: " . $blockReason . ". Response: " . substr($response, 0, 500));
        }

        return $result['candidates'][0]['content']['parts'][0]['text'];
    }
}
