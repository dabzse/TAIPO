<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\SecurityException;

class SecurityService
{
    private const CIPHER = 'aes-256-cbc';
    private const PREFIX = 'ENC:AES-256-CBC:';

    public static function encryptApiKey(string $plainText, ?string $secretKey = null): string
    {
        $key = self::deriveKey($secretKey);
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($plainText, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new SecurityException('Encryption failed.');
        }

        return self::PREFIX . base64_encode($iv . $encrypted);
    }

    public static function decryptApiKey(string $encryptedData, ?string $secretKey = null): ?string
    {
        $data = trim($encryptedData);
        if (str_starts_with($data, self::PREFIX)) {
            $data = substr($data, strlen(self::PREFIX));
        }

        $decoded = base64_decode($data, true);
        if ($decoded === false || strlen($decoded) <= 16) {
            return null;
        }

        $iv = substr($decoded, 0, 16);
        $ciphertext = substr($decoded, 16);
        $key = self::deriveKey($secretKey);

        $decrypted = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : null;
    }

    public static function isEncrypted(string $value): bool
    {
        return str_starts_with(trim($value), self::PREFIX);
    }

    public static function maskApiKey(?string $key): string
    {
        if (!$key) {
            return '';
        }
        $key = trim($key);
        $len = strlen($key);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }
        return substr($key, 0, 6) . '...' . substr($key, -4);
    }

    public static function isValidGeminiKey(string $key): bool
    {
        $trimmed = trim($key);
        return !empty($trimmed) && str_starts_with($trimmed, 'AIza');
    }

    private static function deriveKey(?string $secret): string
    {
        $secretValue = $secret ?? ($_ENV['ENCRYPTION_KEY'] ?? ($_ENV['APP_KEY'] ?? 'TAIPO_EDU_AES_256_SECRET_SALT_2026'));
        return hash('sha256', $secretValue, true);
    }
}
