#!/usr/bin/env php
<?php

/**
 * TAWOS Seed Manager Tool (TAIPO)
 * Generálja, konfigurálja és seedeli a TAWOS adathalmazt a CSV fájlba és az adatbázisba.
 *
 * Használat:
 *   php tools/tawos_seed_manager.php [opciók]
 *
 * Opciók:
 *   --count=<szám>        Seedelendő TAWOS rekordok száma (pl. 500)
 *   --types=<kvóták>      Címke fókusz / kvóta típusonként (pl. "Story:300,Bug:150,Task:100")
 *   --priorities=<kvóták> Címke fókusz prioritásonként (pl. "Critical:50,Major:400,Minor:50")
 *   --source=<fájl>       Forrás CSV fájl (alapértelmezett: backend/data/tawos_seed.csv)
 *   --output=<fájl>       Kimeneti CSV fájl (alapértelmezett: backend/data/tawos_seed.csv)
 *   --db                  A generált rekordok közvetlen betöltése a TAIPO adatbázisba
 *   --no-backup           Biztonsági mentés (.bak) kihagyása
 *   -y, --yes             Automatikus megerősítés (túllépés esetén elfogadja a magasabb értéket)
 *   -i, --interactive     Lépésről-lépésre interaktív varázsló mód
 *   -h, --help            Segítség megjelenítése
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$backendDir = $projectRoot . '/backend';
$defaultCsv = $backendDir . '/data/tawos_seed.csv';
$seed350Csv = $backendDir . '/data/tawos_seed_350.csv';
$hasSeed350 = file_exists($seed350Csv);

// Command line argument parsing
$longOpts = [
    'count:',
    'preset:',
    'types:',
    'priorities:',
    'sql:',
    'sql-source:',
    'source:',
    'output:',
    'db',
    'no-backup',
    'yes',
    'interactive',
    'no-color',
    'help'
];
$options = getopt('hyi', $longOpts);

if (isset($options['h']) || isset($options['help'])) {
    echo <<<HELP
TAWOS Seed Kezelő és Generáló Eszköz (TAIPO)
=============================================
Használat:
  php tools/tawos_seed_manager.php [opciók]

Opciók:
  --count=<szám>         Kívánt összrekordszám (pl. 80, 350, 500, alapértelmezett: 80)
  --preset=<80|350>      Beépített készlet kiválasztása (80: kompakt, 350: beépített kibővített)
  --sql=<fájl>           Nyers TAWOS.sql vagy .zip forrásból valódi mintavételezés (alap: backend/data/TAWOS.sql)
  --types=<kvóták>       Konkrét eloszlás vagy fókusz issue típusonként:
                           Példa: --types="Story:300,Bug:150,Task:100"
                           vagy súlyozás: --types="Story:60%,Bug:30%,Task:10%"
  --priorities=<kvóták>  Prioritás eloszlás:
                           Példa: --priorities="Critical:50,Major:400,Minor:50"
  --source=<fájl>        Forrás adatkészlet CSV (alapértelmezett: backend/data/tawos_seed.csv vagy tawos_seed_350.csv)
  --output=<fájl>        Kimeneti CSV fájl (alapértelmezett: backend/data/tawos_seed.csv)
  --db                   Generálás után automatikusan beírja a TAIPO adatbázisba is
  --no-backup            Ne hozzon létre .bak másolatot a korábbi seed fájlról
  -y, --yes              Automatikus megerősítés figyelmeztetéseknél (nem kérdez Y/N)
  -i, --interactive      Interaktív bekérdező mód
  -h, --help             Segítség megjelenítése

Példák:
  # Beépített 350 rekordos készlet (tawos_seed_350.csv) betöltése azonnal adatbázisba:
  php tools/tawos_seed_manager.php --source=backend/data/tawos_seed_350.csv --count=350 --db
  # vagy egyszerűen:
  php tools/tawos_seed_manager.php --count=350 --db

  # 350 valódi rekord kinyerése nyers TAWOS.sql-ből adatbázisba írással:
  php tools/tawos_seed_manager.php --count=350 --sql=backend/data/TAWOS.sql --db

  # 500 elem generálása az alapértelmezett arányokkal meglévő mintákból:
  php tools/tawos_seed_manager.php --count=500

  # Fókuszálás hibákra (Bug) és sztorikra (Story), adatbázisba írással:
  php tools/tawos_seed_manager.php --count=500 --types="Story:250,Bug:200,Task:50" --db

  # Túllépés teszt (300+250 = 550 > 500) figyelmeztető kérdéssel (Y/N):
  php tools/tawos_seed_manager.php --count=500 --types="Story:300,Bug:250"

Megjegyzés:
  Átfogó rendszerbeállításhoz és interaktív konfiguráláshoz
  használd a setup varázslót:
    ./setup.sh  vagy  php tools/setup.php

HELP;
    exit(0);
}

$useColor = !isset($options['no-color']) && (function_exists('posix_isatty') ? posix_isatty(STDOUT) : true);

// Color helper
$color = function (string $text, string $c) use ($useColor): string {
    if (!$useColor) {
        return $text;
    }
    $colors = [
        'reset' => "\033[0m",
        'bold' => "\033[1m",
        'dim' => "\033[2m",
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'magenta' => "\033[35m",
        'cyan' => "\033[36m",
        'white' => "\033[37m",
        'bg_yellow' => "\033[43m;30m",
        'bg_red' => "\033[41m;37m",
    ];
    return ($colors[$c] ?? '') . $text . $colors['reset'];
};

// Prompt helper for CLI inputs
function promptUser(string $question, string $default = ''): string
{
    $promptText = $question . ($default !== '' ? " [{$default}]: " : ": ");
    echo $promptText;
    $line = fgets(STDIN);
    if ($line === false) {
        return $default;
    }
    $val = trim($line);
    return $val === '' ? $default : $val;
}

// Ask Yes/No helper
function askYesNo(string $question, bool $default = true, bool $autoYes = false, ?callable $colorFn = null): bool
{
    if ($autoYes) {
        return true;
    }
    $suffix = $default ? "(Y/n)" : "(y/N)";
    $qText = $question . " " . $suffix . ": ";
    if ($colorFn) {
        echo $colorFn($qText, 'bold');
    } else {
        echo $qText;
    }

    $line = fgets(STDIN);
    $ans = ($line !== false) ? strtolower(trim($line)) : '';
    return ($ans === '') ? $default : in_array($ans, ['y', 'yes', 'i', 'igen', '1']);
}

// Banner
echo "\n";
echo $color("╔══════════════════════════════════════════════════════╗", 'cyan') . "\n";
echo $color("║            TAWOS SEED KEZELŐ ÉS GENERÁLÓ             ║", 'cyan') . "\n";
echo $color("╚══════════════════════════════════════════════════════╝", 'cyan') . "\n";

// Preset / Auto-selection logic
if (isset($options['preset'])) {
    if ((string)$options['preset'] === '350' && $hasSeed350) {
        $options['source'] = $options['source'] ?? $seed350Csv;
        $options['count'] = $options['count'] ?? 350;
    } elseif ((string)$options['preset'] === '80') {
        $options['source'] = $options['source'] ?? $defaultCsv;
        $options['count'] = $options['count'] ?? 80;
    }
} elseif (!isset($options['source']) && !isset($options['sql']) && !isset($options['sql-source'])) {
    if (isset($options['count']) && (int)$options['count'] === 350 && $hasSeed350) {
        $options['source'] = $seed350Csv;
    }
}

$isInteractive = isset($options['i']) || isset($options['interactive']) || (count($argv) === 1);
$autoYes = isset($options['y']) || isset($options['yes']);
$sourcePath = $options['source'] ?? $defaultCsv;
$outputPath = $options['output'] ?? $defaultCsv;
$writeToDb = isset($options['db']);
$makeBackup = !isset($options['no-backup']);

$sqlSource = $options['sql'] ?? ($options['sql-source'] ?? null);
$defaultSqlPath = 'backend/data/TAWOS.sql';
$defaultSqlZip = 'backend/data/TAWOS.sql.zip';
$hasDefaultSql = file_exists($projectRoot . '/' . $defaultSqlPath) || file_exists($projectRoot . '/' . $defaultSqlZip);

if ($sqlSource === null && $isInteractive) {
    echo $color("💡 Választhatsz valódi TAWOS.sql / .zip nyers adatbázis-dumpból való mintavételezést is,\n", 'cyan');
    echo $color("   amely valódi, egyedi agilis feladatokat és kommenteket emel be ismétlések nélkül.\n\n", 'cyan');
    $promptMsg = $hasDefaultSql
        ? "Található valódi TAWOS.sql/zip forrás! Szeretnél a nyers dumpból valódi mintát venni?"
        : "Szeretnél nyers TAWOS.sql (vagy .zip) adatbázisból valódi mintát venni?";
    if (askYesNo($promptMsg, $hasDefaultSql, $autoYes, $color)) {
        $suggestedPath = $defaultSqlPath;
        if ($hasDefaultSql && !file_exists($projectRoot . '/' . $defaultSqlPath)) {
            $suggestedPath = $defaultSqlZip;
        }
        $sqlSource = promptUser("TAWOS.sql vagy .zip fájl elérési útja", $suggestedPath);
    }
}

if ($sqlSource !== null && trim($sqlSource) !== '') {
    $targetCount = isset($options['count']) ? (int)$options['count'] : null;
    if ($targetCount === null) {
        if ($isInteractive) {
            $inp = promptUser("Hány valódi TAWOS rekordot szeretnél kinyerni?", "350");
            $targetCount = max(1, (int)$inp);
        } else {
            $targetCount = 350;
        }
    }

    require_once __DIR__ . '/tawos_sql_sampler.php';
    $sampler = new TawosSqlSampler([
        'count' => $targetCount,
        'types' => $options['types'] ?? null,
        'priorities' => $options['priorities'] ?? null,
        'output' => $outputPath,
        'no-backup' => !$makeBackup,
    ], function (string $msg): void {
        echo $msg;
    });

    try {
        $generatedRows = $sampler->run($sqlSource);
    } catch (Exception $e) {
        fwrite(STDERR, $color("Hiba az SQL mintavételezés során: " . $e->getMessage() . "\n", 'red'));
        exit(1);
    }

    if ($writeToDb) {
        echo $color("Adatok betöltése az adatbázisba...\n", 'bold');
        $autoloadPath = $backendDir . '/vendor/autoload.php';
        if (file_exists($autoloadPath)) {
            require_once $autoloadPath;
            $envFile = $backendDir . '/.env';
            if (file_exists($envFile)) {
                $dotenv = Dotenv\Dotenv::createImmutable($backendDir);
                $dotenv->load();
            }
            try {
                $db = new App\Database();
                $tawosService = new App\Service\TawosService($db->getPdo(), $db->getDbType());
                $inserted = $tawosService->reseedFromCsv(realpath($outputPath) ?: $outputPath);
                echo "✅ " . $color("Sikeres adatbázis seedelés! {$inserted} rekord beillesztve a tawos_issues táblába.\n", 'green');
            } catch (Exception $e) {
                fwrite(STDERR, $color("Adatbázis hiba: " . $e->getMessage() . "\n", 'red'));
            }
        }
    }

    echo "\n" . $color("Kész!", 'green') . "\n";
    exit(0);
}

# 1. Read and analyze source templates
if (!file_exists($sourcePath) || !is_readable($sourcePath)) {
    fwrite(STDERR, $color("Hiba: Forrás CSV fájl nem található: {$sourcePath}\n", 'red'));
    exit(1);
}

$handle = fopen($sourcePath, 'r');
$header = fgetcsv($handle);
if (!$header || count($header) < 10) {
    fclose($handle);
    fwrite(STDERR, $color("Hiba: Érvénytelen forrás CSV formátum!\n", 'red'));
    exit(1);
}

$sourceRecords = [];
$templatesByType = [];
$availableTypes = [];
$availablePriorities = [];

while (($row = fgetcsv($handle)) !== false) {
    if (count($row) < 10) {
        continue;
    }
    $record = [
        'issue_key' => $row[0],
        'title' => $row[1],
        'description_text' => $row[2],
        'type' => trim($row[3]) ?: 'Story',
        'priority' => trim($row[4]) ?: 'Major',
        'status' => trim($row[5]) ?: 'Closed',
        'resolution' => trim($row[6]) ?: 'Done',
        'story_point' => is_numeric($row[7]) ? (float)$row[7] : null,
        'comment_text' => $row[8] ?: null,
        'project_name' => trim($row[9]) ?: 'MainProject',
    ];
    $sourceRecords[] = $record;
    $templatesByType[$record['type']][] = $record;
    $availableTypes[$record['type']] = ($availableTypes[$record['type']] ?? 0) + 1;
    $availablePriorities[$record['priority']] = ($availablePriorities[$record['priority']] ?? 0) + 1;
}
fclose($handle);

echo "Forrás fájl:      " . $color(realpath($sourcePath) ?: $sourcePath, 'yellow') . "\n";
echo "Elérhető minták:  " . $color(count($sourceRecords) . " db", 'bold') . "\n";
echo "Típusok a mintában: " . implode(', ', array_map(fn($t, $c) => "$t ($c db)", array_keys($availableTypes), $availableTypes)) . "\n\n";

# 2. Determine target count
$targetCount = isset($options['count']) ? (int)$options['count'] : null;
if ($targetCount === null) {
    if ($isInteractive) {
        $inp = promptUser("Hány TAWOS rekordot szeretnél generálni a seed-be?", "80");
        $targetCount = max(1, (int)$inp);
    } else {
        $targetCount = 80;
    }
}

# 3. Determine type quotas / focus
$typeQuotas = [];
$rawTypes = $options['types'] ?? null;

if ($rawTypes !== null) {
    // Parse format: "Story:300,Bug:150,Task:100" or "Story:60%,Bug:40%"
    $parts = explode(',', (string)$rawTypes);
    foreach ($parts as $part) {
        $kv = explode(':', trim($part), 2);
        if (count($kv) === 2) {
            $tName = trim($kv[0]);
            $tVal = trim($kv[1]);
            if (str_ends_with($tVal, '%')) {
                $pct = (float)rtrim($tVal, '%');
                $typeQuotas[$tName] = (int)round(($pct / 100) * $targetCount);
            } else {
                $typeQuotas[$tName] = (int)$tVal;
            }
        }
    }
} elseif ($isInteractive) {
    $specifyTypes = askYesNo("Szeretnél egyedi címkekvótákat (pl. hány Story, Bug, Task legyen) megadni?", false);
    if ($specifyTypes) {
        echo "Add meg a kívánt darabszámokat a típusokhoz (hagyd üresen az automatikus eloszláshoz):\n";
        foreach (array_keys($availableTypes) as $tName) {
            $val = promptUser("  - {$tName} darabszáma", "");
            if ($val !== '' && is_numeric($val)) {
                $typeQuotas[$tName] = (int)$val;
            }
        }
    }
}

# 4. Validate quota sum vs target count (EXCEEDS CHECK & WARNING)
if (!empty($typeQuotas)) {
    $quotaSum = array_sum($typeQuotas);

    if ($quotaSum > $targetCount) {
        // Exceeds warning
        echo "\n";
        echo $color("───────────────────────────────────────────────────────────────────", 'yellow') . "\n";
        echo $color("⚠️  FIGYELMEZTETÉS: A megadott címkekvóták összege túllépi a beállított értéket!", 'yellow') . "\n";
        echo "   • Beállított kívánt összérték: " . $color("{$targetCount} db", 'bold') . "\n";
        echo "   • Címkék (kvóták) összege:     " . $color("{$quotaSum} db", 'bold') . " (" . implode(', ', array_map(fn($k, $v) => "$k: $v", array_keys($typeQuotas), $typeQuotas)) . ")\n";
        echo "   • Különbözet (túllépés):        " . $color("+" . ($quotaSum - $targetCount) . " db", 'red') . "\n";
        echo $color("───────────────────────────────────────────────────────────────────", 'yellow') . "\n\n";

        $warningPrompt = "Túllépi a beállított értéket ({$targetCount}). Mindenképpen a magasabb értékkel ({$quotaSum}) seed-eljek?";
        $acceptHigher = askYesNo($warningPrompt, true, $autoYes, $color);

        if ($acceptHigher) {
            echo $color("-> Jóváhagyva: A magasabb értékkel ({$quotaSum} db) haladunk tovább.\n\n", 'green');
            $targetCount = $quotaSum;
        } else {
            // User answered 'N'
            echo "\nMit szeretnél tenni?\n";
            $scaleDown = askYesNo("Arányosan skálázzam vissza a megadott címkéket az eredeti korlátra ({$targetCount} db)?", true, $autoYes, $color);
            if ($scaleDown) {
                $newQuotas = [];
                $allocated = 0;
                $keys = array_keys($typeQuotas);
                foreach ($keys as $idx => $k) {
                    if ($idx === count($keys) - 1) {
                        $newQuotas[$k] = max(1, $targetCount - $allocated);
                    } else {
                        $scaled = (int)round(($typeQuotas[$k] / $quotaSum) * $targetCount);
                        $scaled = max(1, $scaled);
                        $newQuotas[$k] = $scaled;
                        $allocated += $scaled;
                    }
                }
                $typeQuotas = $newQuotas;
                echo $color("-> Visszaskálázva az eredeti méretre ({$targetCount} db): " . implode(', ', array_map(fn($k, $v) => "$k: $v", array_keys($typeQuotas), $typeQuotas)) . "\n\n", 'green');
            } else {
                echo $color("Művelet megszakítva a felhasználó kérésére. Nem történt módosítás.\n", 'yellow');
                exit(0);
            }
        }
    } elseif ($quotaSum < $targetCount) {
        // Quota sum is less than target count -> distribute remainder among existing/remaining types
        $diff = $targetCount - $quotaSum;
        $missingTypes = array_diff(array_keys($availableTypes), array_keys($typeQuotas));
        if (!empty($missingTypes)) {
            // Distribute among types that had no quota specified
            $perType = (int)floor($diff / count($missingTypes));
            $rem = $diff % count($missingTypes);
            foreach ($missingTypes as $idx => $mType) {
                $add = $perType + ($idx === 0 ? $rem : 0);
                $typeQuotas[$mType] = $add;
            }
        } else {
            // Distribute proportionally among specified types
            $extraAllocated = 0;
            $keys = array_keys($typeQuotas);
            foreach ($keys as $idx => $k) {
                if ($idx === count($keys) - 1) {
                    $typeQuotas[$k] += ($diff - $extraAllocated);
                } else {
                    $add = (int)round(($typeQuotas[$k] / $quotaSum) * $diff);
                    $typeQuotas[$k] += $add;
                    $extraAllocated += $add;
                }
            }
        }
    }
} else {
    // Default distribution based on source proportions
    $totalSource = count($sourceRecords);
    $allocated = 0;
    $keys = array_keys($availableTypes);
    foreach ($keys as $idx => $tName) {
        if ($idx === count($keys) - 1) {
            $typeQuotas[$tName] = max(1, $targetCount - $allocated);
        } else {
            $cnt = (int)round(($availableTypes[$tName] / $totalSource) * $targetCount);
            $cnt = max(1, $cnt);
            $typeQuotas[$tName] = $cnt;
            $allocated += $cnt;
        }
    }
}

# 5. Generate / Sample records
echo $color("Generálás folyamatban...", 'bold') . "\n";
echo "Végleges rekordszám: " . $color("{$targetCount} db", 'bold') . "\n";
echo "Tervezett címke eloszlás:\n";
foreach ($typeQuotas as $tName => $tCount) {
    $pct = ($targetCount > 0) ? ($tCount / $targetCount) * 100 : 0;
    printf("  • %-15s: %5d db (%5.1f%%)\n", $tName, $tCount, $pct);
}
echo "\n";

$generatedRows = [];
$issueSeq = 101;

if ($rawTypes === null && $targetCount === count($sourceRecords)) {
    $generatedRows = $sourceRecords;
} else {
    foreach ($typeQuotas as $tName => $countNeeded) {
        $pool = $templatesByType[$tName] ?? $sourceRecords;
        $poolCount = count($pool);

        for ($i = 0; $i < $countNeeded; $i++) {
            $base = $pool[$i % $poolCount];
            $repeatIndex = (int)floor($i / $poolCount);

            $issueKey = sprintf("PROJ-%d", $issueSeq++);
            $title = $base['title'];
            $desc = $base['description_text'];
            $comment = $base['comment_text'];

            if ($repeatIndex > 0) {
                // Slight variation for duplicated templates
                $title = $title . " (Phase " . ($repeatIndex + 1) . ")";
            }

            $generatedRows[] = [
                'issue_key' => $issueKey,
                'title' => $title,
                'description_text' => $desc,
                'type' => $tName,
                'priority' => $base['priority'],
                'status' => $base['status'],
                'resolution' => $base['resolution'],
                'story_point' => $base['story_point'],
                'comment_text' => $comment,
                'project_name' => $base['project_name'],
            ];
        }
    }
}

# 6. Backup existing file
if ($makeBackup && file_exists($outputPath)) {
    $bakFile = $outputPath . '.bak';
    copy($outputPath, $bakFile);
    echo "Biztonsági mentés létrehozva: " . $color($bakFile, 'dim') . "\n";
}

# 7. Write to CSV file
$outHandle = fopen($outputPath, 'w');
if ($outHandle === false) {
    fwrite(STDERR, $color("Hiba: Nem lehet írni a kimeneti fájlba: {$outputPath}\n", 'red'));
    exit(1);
}

// Header
fputcsv($outHandle, [
    'issue_key',
    'title',
    'description_text',
    'type',
    'priority',
    'status',
    'resolution',
    'story_point',
    'comment_text',
    'project_name',
]);

foreach ($generatedRows as $r) {
    fputcsv($outHandle, [
        $r['issue_key'],
        $r['title'],
        $r['description_text'],
        $r['type'],
        $r['priority'],
        $r['status'],
        $r['resolution'],
        $r['story_point'] !== null ? (string)$r['story_point'] : '',
        $r['comment_text'] ?? '',
        $r['project_name'],
    ]);
}
fclose($outHandle);

echo "✅ " . $color("Új TAWOS seed CSV sikeresen kiírva (" . count($generatedRows) . " rekord): ", 'green') . $color($outputPath, 'bold') . "\n\n";

# 8. Database seeding
if (!$writeToDb && $isInteractive) {
    $writeToDb = askYesNo("Szeretnéd azonnal betölteni az új rekordokat a TAIPO adatbázisba (tawos_issues tábla)?", true);
}

if ($writeToDb) {
    echo $color("Adatbázis seedelés folyamatban...", 'bold') . "\n";
    $autoloadPath = $backendDir . '/vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        fwrite(STDERR, $color("Hiba: Composer autoload nem található: {$autoloadPath}\n", 'red'));
        exit(1);
    }
    require_once $autoloadPath;

    if (file_exists($backendDir . '/.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable($backendDir);
        $dotenv->load();
    }

    try {
        $db = new App\Database();
        $tawosService = new App\Service\TawosService($db->getPdo(), $db->getDbType());

        $inserted = $tawosService->reseedFromCsv(realpath($outputPath) ?: $outputPath);

        echo "✅ " . $color("Sikeres adatbázis seedelés! {$inserted} rekord beillesztve a tawos_issues táblába.", 'green') . "\n\n";

        // Show fresh stats
        $stats = $tawosService->getStats();
        echo "Adatbázis aktuális állapota:\n";
        echo "  • Összes rekord: " . $color("{$stats['total']} db", 'bold') . "\n";
        echo "  • Típusok: ";
        $tList = array_map(fn($t) => "{$t['type']}: {$t['count']} db", $stats['types']);
        echo implode(', ', $tList) . "\n";
    } catch (Exception $e) {
        fwrite(STDERR, $color("Adatbázis hiba történt a seedelés közben: " . $e->getMessage() . "\n", 'red'));
        exit(1);
    }
}

echo "\n" . $color("Kész! A TAWOS konfiguráció és seedelés sikeresen befejeződött.", 'green') . "\n";
