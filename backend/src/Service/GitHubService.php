<?php

namespace App\Service;

use App\Config;
use App\Exception\GitHubConfigurationException;
use App\Exception\GitHubAuthenticationException;
use App\Exception\GitHubFileExistsException;
use App\Exception\GitHubApiException;

class GitHubService
{
    private ?string $token;
    private ?string $username;
    private ?string $repo;

    public function __construct(?string $token, ?string $username, ?string $repo)
    {
        $this->token = $token;
        $this->username = $username;
        $this->repo = $repo;
    }

    public function commitFile(string $filePath, string $content, string $message, string $branch = 'main'): array
    {
        if (empty($this->token)) {
            throw new GitHubAuthenticationException("To commit, you must log in with your GitHub token (PAT)! (Missing token)", 401);
        }

        if (empty($this->username) || empty($this->repo)) {
            throw new GitHubConfigurationException("Invalid GitHub configuration (GITHUB_REPO, GITHUB_USERNAME)", 500);
        }

        $url = "https://api.github.com/repos/{$this->username}/{$this->repo}/contents/{$filePath}";
        $encodedContent = base64_encode($content);

        $payload = [
            'message' => $message,
            'content' => $encodedContent,
            'branch' => $branch
        ];

        $response = $this->makeRequest($url, $payload, 'PUT');
        $result = json_decode($response['body'], true);
        $httpCode = $response['http_code'];

        if ($httpCode === 201) {
            return ['success' => true, 'filePath' => $filePath];
        } elseif ($httpCode === 422 && strpos($result['message'] ?? '', 'sha') !== false) {
            throw new GitHubFileExistsException("File already exists on GitHub: '{$filePath}'. Delete or rename the file to update.", 409);
        } else {
            throw new GitHubApiException("GitHub API error ({$httpCode}): " . ($result['message'] ?? 'Unknown error.'), $httpCode);
        }
    }

    private function makeRequest(string $url, array $data, string $method): array
    {
        if (function_exists('curl_init')) {
            return $this->makeCurlRequest($url, $data, $method);
        } else {
            return $this->makeFileGetContentsRequest($url, $data, $method);
        }
    }

    private function makeCurlRequest(string $url, array $data, string $method): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: token ' . $this->token,
            Config::APP_JSON,
            Config::getGithubUserAgent()
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            // curl_close moved to after check if needed, or rely on distinct checks
            // To be consistent with GeminiService, we throw here.
            throw new GitHubApiException("Request failed: " . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return ['body' => $response, 'http_code' => $httpCode];
    }

    private function makeFileGetContentsRequest(string $url, array $data, string $method): array
    {
        $options = [
            'http' => [
                'header'  => Config::APP_JSON . "\r\n" .
                    "Authorization: token " . $this->token . "\r\n" .
                    Config::getGithubUserAgent() . "\r\n",
                'method'  => $method,
                'content' => json_encode($data),
                'timeout' => 60,
                'ignore_errors' => true
            ]
        ];

        $context  = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            $safeErrorMessage = "Network request failed.";
            if (isset($error['message'])) {
                // Sanitize token
                $msg = $error['message'];
                $msg = str_replace($this->token, '***', $msg);
                $safeErrorMessage .= " Details: " . $msg;
            }
            throw new GitHubApiException($safeErrorMessage);
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
