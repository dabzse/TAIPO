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
            throw new GitHubAuthenticationException("A commitoláshoz be kell jelentkezni GitHub token (PAT) megadásával! (Hiányzó token)", 401);
        }

        if (empty($this->username) || empty($this->repo)) {
            throw new GitHubConfigurationException("Hiányzó GitHub konfiguráció (GITHUB_REPO, GITHUB_USERNAME). Kérlek, állítsd be a szerveren!", 500);
        }

        $url = "https://api.github.com/repos/{$this->username}/{$this->repo}/contents/{$filePath}";
        $encodedContent = base64_encode($content);

        $payload = json_encode([
            'message' => $message,
            'content' => $encodedContent,
            'branch' => $branch
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: token ' . $this->token,
            Config::APP_JSON,
            'User-Agent: AI-Kanban-App'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $result = json_decode($response, true);

        if ($httpCode === 201) {
            return ['success' => true, 'filePath' => $filePath];
        } elseif ($httpCode === 422 && strpos($result['message'] ?? '', 'sha') !== false) {
            throw new GitHubFileExistsException("A fájl már létezik a GitHubon: '{$filePath}'. Töröld/nevezd át a frissítéshez.", 409);
        } else {
            throw new GitHubApiException("GitHub API hiba ({$httpCode}): " . ($result['message'] ?? 'Ismeretlen hiba.'), $httpCode);
        }
    }
}
