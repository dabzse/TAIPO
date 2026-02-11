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
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 4096,
            ]
        ];

        $response = $this->makeRequest($url, $data);
        $result = json_decode($response, true);

        if (isset($result['error'])) {
            $errorMessage = $result['error']['message'] ?? 'Unknown error';
            // The HTTP code is not directly available here from makeRequest,
            // but the error message from Gemini API usually indicates the problem.
            throw new GeminiApiException("API error: " . $errorMessage);
        }

        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $blockReason = $result['candidates'][0]['finishReason'] ?? 'unknown';
            throw new GeminiApiException("API response blocked or invalid format. Reason: " . $blockReason, 502);
        }

        return $result['candidates'][0]['content']['parts'][0]['text'];
    }

    private function makeRequest(string $url, array $data): string
    {
        if (function_exists('curl_init')) {
            return $this->makeCurlRequest($url, $data);
        } else {
            return $this->makeFileGetContentsRequest($url, $data);
        }
    }

    private function makeCurlRequest(string $url, array $data): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [Config::APP_JSON]);
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

        if ($httpCode !== 200) {
            // For non-200, we still return response so calling method can parse error JSON
            // unless it's a complete failure?
            // Actually, `askTaipo` expects json, so lets return it.
            // But if it's 404 or 500 HTML...
        }

        return $response;
    }

    private function makeFileGetContentsRequest(string $url, array $data): string
    {
        $options = [
            'http' => [
                'header'  => Config::APP_JSON . "\r\n",
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
                // Sanitize key
                $msg = preg_replace('/key=[^&\s]+/', 'key=***', $msg);
                $safeErrorMessage .= " Details: " . $msg;
            }
            throw new GeminiApiException($safeErrorMessage);
        }

        return $response;
    }
}
