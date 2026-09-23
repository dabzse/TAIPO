#!/usr/bin/env php
<?php

/**
 * Batch Student Account Importer Tool (TAIPO)
 *
 * This tool allows instructors to batch-create student user accounts
 * with a standardized initial password and flags them to change their
 * password upon first login (must_change_password = 1).
 *
 * Usage:
 *   php tools/add_students.php [options]
 *
 * Options:
 *   --password=<pass>     Custom initial password (default: ChangeMe123!)
 *   --file=<path>         Optional text file with one username per line
 *   --help, -h            Show help message
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$backendDir = $projectRoot . '/backend';

// 1. Array of student usernames (Instructors can populate this list directly)
$students = [
    'dabzse',
    'zklstp',  # should fail in production: alredy exists (not at the moment)
    'nyilas',
    'mihaly',
    'coder',  # should fail in production: too short
    'tester',
    'linter',
    'admin00',  # should fail in production: too long
    '0admin'  # should fail in production: starts with number
];

// 2. Default initial password
$defaultPassword = 'ChangeMe123!';

// Parse CLI options
$options = getopt('h', ['password:', 'file:', 'help']);
if (isset($options['h']) || isset($options['help'])) {
    echo <<<HELP
Batch Student Account Importer (TAIPO)
=====================================
Usage:
  php tools/add_students.php [options]

Options:
  --password=<secret>   Set initial default password (default: ChangeMe123!)
  --file=<filepath>     Load usernames from a file (one username per line)
  -h, --help            Show this help screen

HELP;
    exit(0);
}

if (!empty($options['password'])) {
    $defaultPassword = (string)$options['password'];
}

if (!empty($options['file'])) {
    $filePath = (string)$options['file'];
    if (!file_exists($filePath) || !is_readable($filePath)) {
        fwrite(STDERR, "Error: Username file not found or not readable: {$filePath}\n");
        exit(1);
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        $students = array_values(array_filter(array_map('trim', $lines)));
    }
}

// 3. Bootstrap Backend Environment & Database
$autoloadPath = $backendDir . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    fwrite(STDERR, "Error: Composer autoload not found: {$autoloadPath}\n");
    exit(1);
}
require_once $autoloadPath;

if (file_exists($backendDir . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($backendDir);
    $dotenv->safeLoad();
}

$database = new App\Database();
$pdo = $database->getPdo();
$prefix = App\Config::getTablePrefix();

$minUserLen = App\Config::getMinUsernameLength();
$minPassLen = App\Config::getMinPasswordLength();

if (strlen($defaultPassword) < $minPassLen) {
    fwrite(STDERR, "Error: Initial password must be at least {$minPassLen} characters long.\n");
    exit(1);
}

echo "=== TAIPO: Batch Student Account Creation ===\n";
echo "Initial Default Password : {$defaultPassword}\n";
echo "Total Students in Queue  : " . count($students) . "\n\n";

$createdCount = 0;
$skippedCount = 0;
$errorCount = 0;

// Pre-hash default password
$passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);

// Prepared statements
$checkStmt = $pdo->prepare("SELECT id FROM {$prefix}users WHERE username = :username");
$insertStmt = $pdo->prepare("INSERT INTO {$prefix}users (username, password_hash, is_instructor, must_change_password) VALUES (:username, :hash, 0, 1)");

// 4. While loop to process all students from the queue
$studentQueue = $students;
$index = 0;

while (!empty($studentQueue)) {
    $index++;
    $username = trim((string)array_shift($studentQueue));

    if ($username === '') {
        continue;
    }

    // Validation
    if (strlen($username) < $minUserLen || strlen($username) > 16 || !preg_match('/^\w+$/', $username)) {
        echo sprintf("[%02d] ❌ Invalid username '%s' (Must be %d-16 chars, letters/numbers/underscore)\n", $index, $username, $minUserLen);
        $errorCount++;
        continue;
    }

    try {
        $checkStmt->execute([':username' => $username]);
        if ($checkStmt->fetch()) {
            echo sprintf("[%02d] ⚠️  Skipped '%s': User already exists.\n", $index, $username);
            $skippedCount++;
            continue;
        }

        $insertStmt->execute([
            ':username' => $username,
            ':hash' => $passwordHash
        ]);

        echo sprintf("[%02d] ✅ Created '%s' (must_change_password = 1)\n", $index, $username);
        $createdCount++;
    } catch (Exception $e) {
        echo sprintf("[%02d] ❌ Failed to create '%s': %s\n", $index, $username, $e->getMessage());
        $errorCount++;
    }
}

echo "\n";
echo "=============================================\n";
echo "Summary:\n";
echo "  Total Processed : " . ($createdCount + $skippedCount + $errorCount) . "\n";
echo "  Created         : {$createdCount}\n";
echo "  Skipped/Existing: {$skippedCount}\n";
echo "  Errors          : {$errorCount}\n";
echo "=============================================\n";
