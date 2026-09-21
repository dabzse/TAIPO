#!/usr/bin/env php
<?php

/**
 * TAWOS SQL Sampler & Seed Extractor (TAIPO)
 *
 * Stream-alapon, minimális memóriahasználattal mintavételezi a valódi nyers
 * TAWOS.sql (vagy tömörített TAWOS.sql.zip) adatbázis-dumpot.
 * Kiszűri a kért számú valódi Issue-t (Story, Bug, Task), párosítja a valós
 * projektekkel és kommentekkel, majd frissíti a seed CSV fájlt és az adatbázist.
 *
 * Használat:
 *   php tools/tawos_sql_sampler.php [opciók]
 *
 * Opciók:
 *   --sql=<fájl>          Nyers TAWOS.sql vagy .zip fájl (alapértelmezett: backend/data/TAWOS.sql)
 *   --count=<szám>        Kívánt valódi rekordszám (alapértelmezett: 350)
 *   --types=<kvóták>      Típus kvóták (pl. "Story:60%,Bug:30%,Task:10%" vagy "Story:200,Bug:100,Task:50")
 *   --priorities=<kvóták> Prioritás kvóták (pl. "Critical:50,Major:250,Minor:50")
 *   --output=<fájl>       Kimeneti CSV (alapértelmezett: backend/data/tawos_seed.csv)
 *   --db                  A kinyert rekordok közvetlen betöltése a helyi adatbázisba
 *   --no-backup           Ne készítsen .bak másolatot a korábbi seed fájlról
 *   -h, --help            Segítség megjelenítése
 */

declare(strict_types=1);

class TawosSamplerException extends RuntimeException {}

/**
 * Helper to resolve and open file streams (.sql, .zip, .gz).
 */
class TawosStreamOpener
{
    /**
     * Resolves the stream handle for raw .sql, .zip, or .gz file.
     *
     * @return array{0: resource, 1: string, 2: bool} [handle, resolvedPath, isPipe]
     */
    public static function openStream(string $filePath): array
    {
        $resolved = self::resolveExistingFilePath($filePath);
        if ($resolved === null) {
            throw new TawosSamplerException("Nem található a megadott TAWOS SQL fájl: {$filePath}");
        }

        $lower = strtolower($resolved);
        if (str_ends_with($lower, '.zip')) {
            return self::openZipStream($resolved);
        }
        if (str_ends_with($lower, '.gz')) {
            return self::openGzStream($resolved);
        }

        $handle = @fopen($resolved, 'r');
        if ($handle === false) {
            throw new TawosSamplerException("Nem sikerült megnyitni a fájlt: {$resolved}");
        }

        return [$handle, $resolved, false];
    }

    public static function resolveExistingFilePath(string $filePath): ?string
    {
        $candidates = [
            $filePath,
            $filePath . '.zip',
            $filePath . '.gz',
            dirname(__DIR__) . '/' . ltrim($filePath, '/'),
            dirname(__DIR__) . '/' . ltrim($filePath, '/') . '.zip',
        ];

        foreach ($candidates as $c) {
            if (file_exists($c) && is_readable($c)) {
                return realpath($c) ?: $c;
            }
        }

        return null;
    }

    private static function openZipStream(string $path): array
    {
        $zipStream = "zip://{$path}#TAWOS.sql";
        $handle = @fopen($zipStream, 'r');
        if ($handle !== false) {
            return [$handle, $path, false];
        }

        $cmd = 'unzip -p ' . escapeshellarg($path);
        $pipe = @popen($cmd, 'r');
        if ($pipe !== false) {
            return [$pipe, $path, true];
        }

        throw new TawosSamplerException("A zip fájl megnyitása nem sikerült: {$path}");
    }

    private static function openGzStream(string $path): array
    {
        $handle = @gzopen($path, 'r');
        if ($handle !== false) {
            return [$handle, $path, false];
        }
        throw new TawosSamplerException("A gz fájl megnyitása nem sikerült: {$path}");
    }
}

/**
 * Helper to parse and calculate issue type and priority quotas.
 */
class TawosQuotaManager
{
    public static function initQuotas(?string $rawTypes, ?string $rawPriorities, int $target): array
    {
        $typeQuotas = ($rawTypes === null || trim($rawTypes) === '')
            ? self::getDefaultTypeQuotas($target)
            : self::parseQuotasString($rawTypes, $target);

        $priorityQuotas = ($rawPriorities !== null && trim($rawPriorities) !== '')
            ? self::parsePriorityQuotas($rawPriorities)
            : [];

        return [$typeQuotas, $priorityQuotas];
    }

    public static function getDefaultTypeQuotas(int $target): array
    {
        $storyCount = (int)round($target * 0.60);
        $bugCount = (int)round($target * 0.30);
        $taskCount = max(1, $target - $storyCount - $bugCount);

        return [
            'Story' => $storyCount,
            'Bug' => $bugCount,
            'Task' => $taskCount,
        ];
    }

    private static function parseQuotasString(string $rawTypes, int $target): array
    {
        $parsed = [];
        $percentSum = 0.0;
        $hasPercent = false;

        foreach (explode(',', $rawTypes) as $p) {
            $kv = explode(':', trim($p), 2);
            if (count($kv) !== 2) {
                continue;
            }
            $k = trim($kv[0]);
            $v = trim($kv[1]);
            if (str_ends_with($v, '%')) {
                $pct = (float)rtrim($v, '%');
                $parsed[$k] = ['type' => 'pct', 'val' => $pct];
                $percentSum += $pct;
                $hasPercent = true;
            } else {
                $parsed[$k] = ['type' => 'abs', 'val' => max(1, (int)$v)];
            }
        }

        return $hasPercent
            ? self::distributePercentageQuotas($parsed, $percentSum, $target)
            : self::distributeAbsoluteQuotas($parsed, $target);
    }

    private static function distributePercentageQuotas(array $parsed, float $percentSum, int $target): array
    {
        $quotas = [];
        $allocated = 0;
        $keys = array_keys($parsed);

        foreach ($keys as $idx => $k) {
            if ($idx === count($keys) - 1) {
                $quotas[$k] = max(1, $target - $allocated);
            } else {
                $pct = $parsed[$k]['val'] / ($percentSum ?: 100.0);
                $cnt = max(1, (int)round($pct * $target));
                $quotas[$k] = $cnt;
                $allocated += $cnt;
            }
        }

        return $quotas;
    }

    private static function distributeAbsoluteQuotas(array $parsed, int $target): array
    {
        $quotas = [];
        $sum = 0;

        foreach ($parsed as $k => $info) {
            $quotas[$k] = $info['val'];
            $sum += $info['val'];
        }

        if ($sum < $target && !empty($quotas)) {
            $keys = array_keys($quotas);
            $quotas[$keys[count($keys) - 1]] += ($target - $sum);
        }

        return $quotas;
    }

    private static function parsePriorityQuotas(string $rawPriorities): array
    {
        $quotas = [];
        foreach (explode(',', $rawPriorities) as $p) {
            $kv = explode(':', trim($p), 2);
            if (count($kv) === 2) {
                $quotas[trim($kv[0])] = max(1, (int)trim($kv[1]));
            }
        }
        return $quotas;
    }

    public static function normalizeType(string $raw): string
    {
        $l = strtolower($raw);
        if (str_contains($l, 'bug') || str_contains($l, 'defect') || str_contains($l, 'error') || str_contains($l, 'fault')) {
            return 'Bug';
        }
        if (str_contains($l, 'task') || str_contains($l, 'sub-task') || str_contains($l, 'chore') || str_contains($l, 'technical')) {
            return 'Task';
        }
        return 'Story';
    }

    public static function normalizePriority(string $raw): string
    {
        $l = strtolower($raw);
        if (str_contains($l, 'blocker') || str_contains($l, 'critical')) {
            return 'Critical';
        }
        if (str_contains($l, 'minor') || str_contains($l, 'trivial') || str_contains($l, 'low')) {
            return 'Minor';
        }
        return 'Major';
    }
}

/**
 * Helper to scan and extract MySQL tuples from buffer.
 */
class TawosSqlTupleParser
{
    public static function extractTupleFromIndex(string $buffer, int &$i, int $len): ?array
    {
        $i++; // skip '('
        $tuple = [];
        $currentVal = null;
        $inString = false;
        $strVal = '';
        $token = '';

        while ($i < $len) {
            $ch = $buffer[$i];
            if ($inString) {
                self::consumeStringChar($buffer, $i, $strVal, $inString, $currentVal);
            } else {
                if ($ch === "'") {
                    $inString = true;
                    $strVal = '';
                    $i++;
                } elseif ($ch === ',') {
                    $tuple[] = self::finalizeTokenValue($token, $currentVal);
                    $currentVal = null;
                    $token = '';
                    $i++;
                } elseif ($ch === ')') {
                    $tuple[] = self::finalizeTokenValue($token, $currentVal);
                    $i++;
                    return $tuple;
                } else {
                    $token .= $ch;
                    $i++;
                }
            }
        }

        return null;
    }

    private static function consumeStringChar(string $buffer, int &$i, string &$strVal, bool &$inString, ?string &$currentVal): void
    {
        $ch = $buffer[$i];
        if ($ch === '\\') {
            $next = $buffer[$i + 1] ?? '';
            self::appendEscapedChar($next, $strVal);
            $i += 2;
        } elseif ($ch === "'") {
            if (($buffer[$i + 1] ?? '') === "'") {
                $strVal .= "'";
                $i += 2;
            } else {
                $inString = false;
                $currentVal = $strVal;
                $i++;
            }
        } else {
            $strVal .= $ch;
            $i++;
        }
    }

    private static function appendEscapedChar(string $next, string &$strVal): void
    {
        if ($next === "'") {
            $strVal .= "'";
        } elseif ($next === '"') {
            $strVal .= '"';
        } elseif ($next === '\\') {
            $strVal .= '\\';
        } elseif ($next === 'n') {
            $strVal .= "\n";
        } elseif ($next === 'r') {
            $strVal .= "\r";
        } elseif ($next === 't') {
            $strVal .= "\t";
        } else {
            $strVal .= $next;
        }
    }

    private static function finalizeTokenValue(string $token, ?string $currentVal): ?string
    {
        if ($currentVal !== null) {
            return $currentVal;
        }
        $trimmed = trim($token);
        return strtoupper($trimmed) === 'NULL' ? null : $trimmed;
    }
}

/**
 * Handles CSV export and formatting for sampled TAWOS dataset.
 */
class TawosCsvExporter
{
    /**
     * @param array<int, array> $rows
     */
    public static function export(
        string $outputPath,
        array $rows,
        bool $makeBackup,
        ?callable $logger = null
    ): void {
        if ($makeBackup && file_exists($outputPath)) {
            copy($outputPath, $outputPath . '.bak');
            if ($logger !== null) {
                $logger("💾 Biztonsági mentés létrehozva: {$outputPath}.bak\n");
            }
        }

        $fp = fopen($outputPath, 'w');
        if ($fp === false) {
            throw new TawosSamplerException("Nem sikerült megnyitni írásra a célfájlt: {$outputPath}");
        }

        fputcsv($fp, [
            'issue_key',
            'title',
            'description_text',
            'type',
            'priority',
            'status',
            'resolution',
            'story_point',
            'comment_text',
            'project_name'
        ]);

        foreach ($rows as $r) {
            fputcsv($fp, [
                $r['issue_key'],
                $r['title'],
                $r['description_text'],
                $r['type'],
                $r['priority'],
                $r['status'],
                $r['resolution'],
                $r['story_point'] !== null ? (string)$r['story_point'] : '',
                $r['comment_text'] ?? '',
                $r['project_name']
            ]);
        }

        fclose($fp);
        if ($logger !== null) {
            $logger("✅ Valódi TAWOS seed CSV sikeresen kiírva: " . count($rows) . " rekord ({$outputPath})\n");
        }
    }

    /**
     * @param array<int, array> $rows
     */
    public static function fillMissingComments(array &$rows): void
    {
        $commentsByType = [];
        foreach ($rows as $r) {
            if (!empty($r['comment_text'])) {
                $commentsByType[$r['type']][] = $r['comment_text'];
            }
        }

        foreach ($rows as &$r) {
            if (empty($r['comment_text'])) {
                $pool = $commentsByType[$r['type']] ?? [];
                if (!empty($pool)) {
                    $r['comment_text'] = $pool[array_rand($pool)];
                }
            }
        }
    }
}

/**
 * Main orchestrator for streaming sampling and seed export.
 */
class TawosSqlSampler
{
    /** @var array<int, string> Project ID -> Project Name */
    private array $projects = [];

    /** @var array<string, array> Type -> array of issues */
    private array $sampledIssuesByType = [];

    /** @var array<int, array> Issue ID -> reference to issue array */
    private array $sampledIssuesById = [];

    /** @var array<string, int> Type -> quota needed */
    private array $typeQuotas = [];

    /** @var array<string, int> Priority -> quota needed */
    private array $priorityQuotas = [];

    private int $targetCount = 350;
    private string $outputPath;
    private bool $makeBackup = true;

    /** @var callable|null */
    private $logger = null;

    public function __construct(array $options = [], ?callable $logger = null)
    {
        $this->logger = $logger ?? function (string $msg): void {
            echo $msg;
        };

        $this->targetCount = isset($options['count']) ? max(1, (int)$options['count']) : 350;
        $this->outputPath = $options['output'] ?? (dirname(__DIR__) . '/backend/data/tawos_seed.csv');
        $this->makeBackup = !isset($options['no-backup']);

        [$this->typeQuotas, $this->priorityQuotas] = TawosQuotaManager::initQuotas(
            $options['types'] ?? null,
            $options['priorities'] ?? null,
            $this->targetCount
        );
    }

    private function log(string $msg): void
    {
        if ($this->logger !== null) {
            ($this->logger)($msg);
        }
    }

    /**
     * Execute sampling process.
     *
     * @return array<int, array> The sampled rows
     */
    public function run(string $sqlFilePath): array
    {
        $this->log("🔍 TAWOS SQL forrás megnyitása: {$sqlFilePath}...\n");
        [$handle, $resolvedPath, $isPipe] = TawosStreamOpener::openStream($sqlFilePath);

        $this->logInitialSummary($resolvedPath);

        $currentTable = null;
        $activeInsert = false;
        $buffer = '';
        $bytesRead = 0;
        $totalIssuesCollected = 0;
        $issuesPhaseDone = false;
        $commentsCollected = 0;

        $startTime = microtime(true);
        $lastReport = $startTime;

        try {
            while (!feof($handle)) {
                $line = fgets($handle, 65536);
                if ($line === false) {
                    break;
                }
                $bytesRead += strlen($line);

                $this->reportProgressIfNeeded($lastReport, $bytesRead, $totalIssuesCollected, $commentsCollected);

                if ((str_starts_with($line, 'INSERT INTO `') || str_starts_with($line, 'INSERT INTO ')) &&
                    preg_match('/INSERT INTO [`"]?(\w+)[`"]?/', $line, $m)
                ) {
                    $currentTable = $m[1];
                    $activeInsert = true;
                }

                if (!$this->isTableRelevant($activeInsert, $currentTable, $line)) {
                    continue;
                }

                if ($this->shouldTerminateEarly($issuesPhaseDone, $currentTable, $commentsCollected, $totalIssuesCollected)) {
                    break;
                }

                $buffer .= $line;
                $this->parseTuplesFromBuffer($buffer, $currentTable, $totalIssuesCollected, $issuesPhaseDone, $commentsCollected);

                if (str_ends_with(trim($line), ';')) {
                    $activeInsert = false;
                    $currentTable = null;
                    $buffer = '';
                }
            }
        } finally {
            $isPipe ? pclose($handle) : fclose($handle);
        }

        return $this->finalizeSampling($startTime, $commentsCollected);
    }

    private function logInitialSummary(string $resolvedPath): void
    {
        $fileSize = file_exists($resolvedPath) ? filesize($resolvedPath) : 0;
        $sizeStr = $fileSize > 0 ? sprintf("%.2f MB", $fileSize / (1024 * 1024)) : 'ismeretlen méret';
        $this->log("✅ Forrás elérve: {$resolvedPath} ({$sizeStr})\n");
        $this->log("📊 Kívánt mintaméret: {$this->targetCount} rekord\n");
        $typeList = implode(', ', array_map(fn($k, $v) => "{$k}: {$v} db", array_keys($this->typeQuotas), $this->typeQuotas));
        $this->log("🎯 Célértékek típusonként: {$typeList}\n\n");
    }

    private function reportProgressIfNeeded(float &$lastReport, int $bytesRead, int $issuesCount, int $commentsCount): void
    {
        $now = microtime(true);
        if ($now - $lastReport >= 3.0) {
            $mb = sprintf("%.1f MB", $bytesRead / (1024 * 1024));
            $this->log("   ... olvasás folyamatban ({$mb}) - gyűjtött Issue-k: {$issuesCount}/{$this->targetCount}, Kommentek: {$commentsCount}\r");
            $lastReport = $now;
        }
    }

    private function isTableRelevant(bool &$activeInsert, ?string &$currentTable, string $line): bool
    {
        if (!$activeInsert || !in_array($currentTable, ['Project', 'Issue', 'Comment'], true)) {
            if ($activeInsert && str_ends_with(trim($line), ';')) {
                $activeInsert = false;
                $currentTable = null;
            }
            return false;
        }
        return true;
    }

    private function shouldTerminateEarly(bool $issuesDone, ?string $table, int $commentsCollected, int $issuesCount): bool
    {
        if ($issuesDone && $table === 'Change_Log') {
            $this->log("\n⚡ Korai leállítás: Az Issue és Comment adatok sikeresen beolvasva, felesleges táblák átugorva!\n");
            return true;
        }
        if ($issuesDone && $commentsCollected >= $issuesCount) {
            $this->log("\n⚡ Minden kinyert feladathoz megtaláltuk a kommenteket, beolvasás kész!\n");
            return true;
        }
        return false;
    }

    private function finalizeSampling(float $startTime, int $commentsCollected): array
    {
        $elapsed = round(microtime(true) - $startTime, 2);
        $this->log("\n⏱ Feldolgozási idő: {$elapsed} másodperc\n");
        $this->log("📦 Összesen kinyert valódi Issue: " . count($this->sampledIssuesById) . " db\n");
        $this->log("💬 Hozzákapcsolt kommentek száma: {$commentsCollected} db\n\n");

        if (empty($this->sampledIssuesById)) {
            throw new TawosSamplerException("Nem sikerült érvényes rekordokat kinyerni az SQL fájlból!");
        }

        $rows = array_values($this->sampledIssuesById);
        TawosCsvExporter::fillMissingComments($rows);
        TawosCsvExporter::export($this->outputPath, $rows, $this->makeBackup, $this->logger);

        return $rows;
    }

    private function parseTuplesFromBuffer(
        string &$buffer,
        string $tableName,
        int &$totalIssuesCollected,
        bool &$issuesPhaseDone,
        int &$commentsCollected
    ): void {
        $len = strlen($buffer);
        $i = 0;

        while ($i < $len) {
            while ($i < $len && $buffer[$i] !== '(') {
                $i++;
            }
            if ($i >= $len) {
                break;
            }

            $tupleStart = $i;
            $parsedTuple = TawosSqlTupleParser::extractTupleFromIndex($buffer, $i, $len);
            if ($parsedTuple === null) {
                $buffer = substr($buffer, $tupleStart);
                return;
            }

            $this->dispatchTuple($tableName, $parsedTuple, $totalIssuesCollected, $issuesPhaseDone, $commentsCollected);
        }

        $buffer = substr($buffer, $i);
    }

    private function dispatchTuple(
        string $tableName,
        array $tuple,
        int &$totalIssuesCollected,
        bool &$issuesPhaseDone,
        int &$commentsCollected
    ): void {
        if ($tableName === 'Project') {
            $this->handleProjectTuple($tuple);
        } elseif ($tableName === 'Issue' && !$issuesPhaseDone) {
            $this->handleIssueTuple($tuple, $totalIssuesCollected, $issuesPhaseDone);
        } elseif ($tableName === 'Comment') {
            $this->handleCommentTuple($tuple, $commentsCollected);
        }
    }

    private function handleProjectTuple(array $t): void
    {
        if (count($t) >= 3 && is_numeric($t[0])) {
            $pid = (int)$t[0];
            $name = trim((string)($t[2] ?: ($t[1] ?: "Project-{$pid}")));
            $this->projects[$pid] = $name;
        }
    }

    private function handleIssueTuple(array $t, int &$totalCollected, bool &$phaseDone): void
    {
        if (count($t) < 29 || !is_numeric($t[0])) {
            return;
        }

        $id = (int)$t[0];
        $key = trim((string)($t[2] ?? ''));
        $title = trim((string)($t[4] ?? ''));
        if ($key === '' || $title === '') {
            return;
        }

        $type = TawosQuotaManager::normalizeType(trim((string)($t[8] ?? 'Story')));
        $priority = TawosQuotaManager::normalizePriority(trim((string)($t[9] ?? 'Major')));

        $needed = $this->typeQuotas[$type] ?? ($this->targetCount / 3);
        $currentCount = count($this->sampledIssuesByType[$type] ?? []);

        if ($currentCount >= $needed && $totalCollected >= $this->targetCount) {
            $phaseDone = true;
            return;
        }

        $record = $this->buildIssueRecord($t, $id, $key, $title, $type, $priority);

        $this->sampledIssuesByType[$type][] = $record;
        $this->sampledIssuesById[$id] = $record;
        $totalCollected++;

        if ($totalCollected >= $this->targetCount && $this->areAllQuotasSatisfied()) {
            $phaseDone = true;
        }
    }

    private function buildIssueRecord(array $t, int $id, string $key, string $title, string $type, string $priority): array
    {
        $desc = trim((string)($t[6] ?: ($t[5] ?: '')));
        if ($desc === '') {
            $desc = $title;
        }
        if (mb_strlen($desc) > 1000) {
            $desc = mb_substr($desc, 0, 997) . '...';
        }

        $sp = null;
        if (isset($t[16]) && is_numeric($t[16]) && (float)$t[16] > 0) {
            $sp = (float)$t[16];
        }

        $projectName = $this->resolveProjectName($t[28] ?? null, $key);

        return [
            'id' => $id,
            'issue_key' => $key,
            'title' => $title,
            'description_text' => $desc,
            'type' => $type,
            'priority' => $priority,
            'status' => trim((string)($t[10] ?? 'Closed')) ?: 'Closed',
            'resolution' => trim((string)($t[11] ?? 'Done')) ?: 'Done',
            'story_point' => $sp,
            'comment_text' => null,
            'project_name' => $projectName,
        ];
    }

    private function resolveProjectName(string|int|null $rawProjectId, string $key): string
    {
        $projectId = is_numeric($rawProjectId) ? (int)$rawProjectId : null;
        if ($projectId !== null && isset($this->projects[$projectId])) {
            return $this->projects[$projectId];
        }

        $parts = explode('-', $key);
        return !empty($parts[0]) ? $parts[0] : 'Project';
    }

    private function areAllQuotasSatisfied(): bool
    {
        foreach ($this->typeQuotas as $qType => $qCount) {
            if (count($this->sampledIssuesByType[$qType] ?? []) < $qCount) {
                return false;
            }
        }
        return true;
    }

    private function handleCommentTuple(array $t, int &$commentsCollected): void
    {
        if (count($t) < 7 || !is_numeric($t[6])) {
            return;
        }

        $issueId = (int)$t[6];
        if (!isset($this->sampledIssuesById[$issueId]) || $this->sampledIssuesById[$issueId]['comment_text'] !== null) {
            return;
        }

        $comment = trim((string)($t[2] ?: ($t[1] ?: '')));
        if ($comment !== '') {
            if (mb_strlen($comment) > 500) {
                $comment = mb_substr($comment, 0, 497) . '...';
            }
            $this->sampledIssuesById[$issueId]['comment_text'] = $comment;
            $commentsCollected++;
        }
    }
}

// Standalone CLI execution
if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $longOpts = [
        'sql:',
        'count:',
        'types:',
        'priorities:',
        'output:',
        'db',
        'no-backup',
        'help',
    ];
    $options = getopt('h', $longOpts);

    if (isset($options['h']) || isset($options['help'])) {
        echo <<<HELP
TAWOS SQL Sampler & Seed Extractor (TAIPO)
===========================================
Stream-alapú mintavételező a valódi nyers TAWOS.sql vagy TAWOS.sql.zip dumpból.

Használat:
  php tools/tawos_sql_sampler.php [opciók]

Opciók:
  --sql=<fájl>          TAWOS.sql vagy TAWOS.sql.zip útvonala (alapértelmezett: backend/data/TAWOS.sql)
  --count=<szám>        Kívánt valódi rekordszám (alapértelmezett: 350)
  --types=<kvóták>      Típus kvóták (pl. "Story:60%,Bug:30%,Task:10%" vagy "Story:200,Bug:100,Task:50")
  --priorities=<kvóták> Prioritás kvóták (pl. "Critical:50,Major:250,Minor:50")
  --output=<fájl>       Kimeneti CSV fájl (alapértelmezett: backend/data/tawos_seed.csv)
  --db                  A kinyert rekordok automatikus betöltése az adatbázisba
  --no-backup           Ne készítsen .bak másolatot a korábbi seedről
  -h, --help            Segítség megjelenítése

Példák:
  # 350 valódi rekord kinyerése a default helyről:
  php tools/tawos_sql_sampler.php --count=350

  # Kinyerés tömörített zip-ből adatbázisba írással:
  php tools/tawos_sql_sampler.php --sql=backend/data/TAWOS.sql.zip --count=500 --db

HELP;
        exit(0);
    }

    $sqlPath = $options['sql'] ?? (dirname(__DIR__) . '/backend/data/TAWOS.sql');

    try {
        $sampler = new TawosSqlSampler($options);
        $rows = $sampler->run($sqlPath);

        if (isset($options['db'])) {
            echo "📥 Adatbázisba töltés folyamatban...\n";
            $backendDir = dirname(__DIR__) . '/backend';
            require_once $backendDir . '/vendor/autoload.php';
            $dotEnv = $backendDir . '/.env';
            if (file_exists($dotEnv)) {
                $dotenv = Dotenv\Dotenv::createImmutable($backendDir);
                $dotenv->load();
            }
            $db = new \App\Database();
            $service = new \App\Service\TawosService($db->getPdo(), $db->getDbType());
            $csvPath = $options['output'] ?? ($backendDir . '/data/tawos_seed.csv');
            $inserted = $service->reseedFromCsv(realpath($csvPath) ?: $csvPath);
            echo "✅ Adatbázis sikeresen frissítve: {$inserted} rekord betöltve a tawos_issues táblába!\n";
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "❌ Hiba: " . $e->getMessage() . "\n");
        exit(1);
    }
}
