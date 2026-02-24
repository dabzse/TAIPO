<?php

namespace App\Service;

use App\Config;
use App\Exception\GeminiApiException;

class GeminiService
{
    public function __construct()
    {
        $apiKey = Config::getGeminiApiKey();

        if (empty($apiKey) || strpos($apiKey, 'AIza') !== 0) {
            throw new GeminiApiException("Gemini API key is not set or invalid.");
        }
    }

    public function askTaipo(string $prompt): string
    {
        $url = Config::getGeminiFullUrl();

        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => Config::getGeminiTemperature(),
                'topK' => Config::getGeminiTopK(),
                'topP' => Config::getGeminiTopP(),
                'maxOutputTokens' => Config::getGeminiMaxOutputTokens(),
            ]
        ];

        $response = $this->makeRequest($url, $data);
        $body = $response['body'];
        $httpCode = $response['http_code'];

        $result = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $snippet = trim(substr(strip_tags($body), 0, 100));
            throw new GeminiApiException("Invalid JSON response. HTTP: {$httpCode}. Context: {$snippet}", $httpCode ?: 500);
        }

        if (isset($result['error'])) {
            $errorMessage = $result['error']['message'] ?? 'Unknown error';
            $errorCode = (int)($result['error']['code'] ?? $httpCode);
            $errorStatus = $result['error']['status'] ?? 'UNKNOWN';

            $contextualMessage = $this->getContextualMessage($errorCode, $errorStatus);
            $finalMessage = $contextualMessage ? "{$contextualMessage} (API: {$errorMessage})" : "API error [{$errorStatus}]: {$errorMessage}";

            throw new GeminiApiException($finalMessage, $errorCode ?: 500);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $contextualMessage = $this->getContextualMessage($httpCode, '');
            $finalMessage = $contextualMessage ? $contextualMessage : "API request failed with HTTP Code: {$httpCode}";
            throw new GeminiApiException($finalMessage, $httpCode);
        }

        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $blockReason = $result['candidates'][0]['finishReason'] ?? 'unknown';
            throw new GeminiApiException("API response blocked or invalid format. Reason: " . $blockReason, 502);
        }

        return $result['candidates'][0]['content']['parts'][0]['text'];
    }

    private function getContextualMessage(int $httpCode, string $errorStatus): ?string
    {
        $message = null;

        if ($httpCode === 400 || $errorStatus === 'INVALID_ARGUMENT') {
            $message = "Bad Request: Data format issue or invalid API key.";
        } elseif ($httpCode === 403 || $errorStatus === 'PERMISSION_DENIED') {
            $message = "API Key is forbidden or lacks necessary permissions.";
        } elseif ($httpCode === 429 || $errorStatus === 'RESOURCE_EXHAUSTED') {
            $message = "Rate limit exceeded or quota exhausted. Please try again later.";
        } elseif ($httpCode === 500 || $httpCode === 502) {
            $message = "Gemini API is currently encountering an internal error.";
        }

        return $message;
    }

    private function makeRequest(string $url, array $data): array
    {
        if (function_exists('curl_init')) {
            return $this->makeCurlRequest($url, $data);
        } else {
            return $this->makeFileGetContentsRequest($url, $data);
        }
    }

    private function makeCurlRequest(string $url, array $data): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            Config::APP_JSON,
            Config::getGeminiApiKeyHeader()
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        // SSL verification to match production standards
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            throw new GeminiApiException("Request failed: " . $error); // Curl errors usually don't contain the URL/Key
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return ['body' => $response, 'http_code' => $httpCode];
    }

    private function makeFileGetContentsRequest(string $url, array $data): array
    {
        $options = [
            'http' => [
                'header'  => Config::APP_JSON . "\r\n" .
                    Config::getGeminiApiKeyHeader() . "\r\n",
                'method'  => 'POST',
                'content' => json_encode($data),
                'timeout' => 60,
                'ignore_errors' => true // to fetch error body
            ],
            "ssl" => [
                "verify_peer" => true,
                "verify_peer_name" => true,
            ]
        ];

        $context  = stream_context_create($options);

        // Suppress warnings to handle errors manually and avoid exposing URL in standard error output
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            $safeErrorMessage = "Network request failed.";
            if (isset($error['message'])) {
                $msg = $error['message'];
                // Sanitize key (both old URL query param style and header presence)
                $msg = preg_replace('/key=[^&\s]+/', 'key=***', $msg);
                if (Config::getGeminiApiKey() !== '') {
                    $msg = str_replace(Config::getGeminiApiKey(), '***', $msg);
                }
                $safeErrorMessage .= " Details: " . $msg;
            }
            throw new GeminiApiException($safeErrorMessage);
        }

        $httpCode = 0;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('#HTTP/[\d\.]+\s+(\d+)#', $header, $matches)) {
                    $httpCode = intval($matches[1]);
                    break;
                }
            }
        }

        return ['body' => $response, 'http_code' => $httpCode];
    }
}
