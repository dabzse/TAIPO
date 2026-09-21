#!/usr/bin/env php
<?php

/**
 * TAIPO Setup & TAWOS Dataset Sampling Wizard
 *
 * Beállítja a helyi TAWOS mintavételezést (80, 500 vagy egyedi rekordméret a nyers SQL/ZIP-ből vagy CSV-ből),
 * és opcionálisan azonnal feltölti az adatbázis tawos_issues tábláját.
 *
 * Használat:
 *   php tools/setup.php [opciók]
 *   vagy a gyökérből: ./setup.sh [opciók]
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$backendDir = $projectRoot . '/backend';
$defaultCsv = $backendDir . '/data/tawos_seed.csv';
$seed350Csv = $backendDir . '/data/tawos_seed_350.csv';
$envFile = $backendDir . '/.env';

// Argument parsing
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
TAIPO Rendszer & TAWOS Beállító Varázsló (Setup)
=================================================
Használat:
  php tools/setup.php [opciók]
  ./setup.sh [opciók]

Opciók:
  --count=<szám>            Kívánt helyi seed rekordszám (pl. 80, 350, 500; alapértelmezett: 80)
  --preset=<80|350>         Beépített készlet kiválasztása (80: kompakt, 350: beépített kibővített készlet)
  --sql=<fájl>              Nyers TAWOS.sql vagy .zip forrásból valódi mintavételezés (alap: backend/data/TAWOS.sql)
  --types=<kvóták>          Típus eloszlás (pl. "Story:250,Bug:200,Task:50" vagy "Story:60%,Bug:40%")
  --priorities=<kvóták>     Prioritás eloszlás (pl. "Critical:50,Major:400,Minor:50")
  --source=<fájl>           Forrás CSV fájl (alapértelmezett: backend/data/tawos_seed.csv vagy tawos_seed_350.csv)
  --output=<fájl>           Kimeneti CSV fájl (alapértelmezett: backend/data/tawos_seed.csv)
  --db                      Generálás után azonnal betölti az adatokat az adatbázisba
  --no-backup               Ne készítsen .bak mentést a korábbi seedről
  -y, --yes                 Automatikus megerősítés figyelmeztetéseknél
  -i, --interactive         Interaktív varázsló mód (alapértelmezett, ha nincs argumentum)
  -h, --help                Segítség megjelenítése

Példák:
  # Interaktív varázsló indítása (választható 80, 350 rekord vagy SQL dump):
  ./setup.sh

  # Beépített 350 rekordos készlet (tawos_seed_350.csv) betöltése azonnal adatbázisba:
  php tools/setup.php --count=350 --db -y
  # vagy explicit forrással:
  php tools/setup.php --source=backend/data/tawos_seed_350.csv --count=350 --db -y

  # 350 valódi rekord kinyerése a nyers ~4GB TAWOS.sql dumpból:
  php tools/setup.php --count=350 --sql=backend/data/TAWOS.sql --db -y

HELP;
    exit(0);
}

$useColor = !isset($options['no-color']) && (function_exists('posix_isatty') ? posix_isatty(STDOUT) : true);
$divider = "────────────────────────────────────────────────────────\n";

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
    ];
    return ($colors[$c] ?? '') . $text . $colors['reset'];
};

// Input prompt helper
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

// Yes/No helper
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
echo $color("║          TAIPO RENDSZER ÉS TAWOS BEÁLLÍTÓ            ║", 'cyan') . "\n";
echo $color("║                 (SETUP WIZARD)                       ║", 'cyan') . "\n";
echo $color("╚══════════════════════════════════════════════════════╝", 'cyan') . "\n\n";

$hasSeed350 = file_exists($seed350Csv);

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

echo $color("1. LÉPÉS: Helyi TAWOS adatkészlet (Local Seed)", 'bold') . "\n";
echo $divider;

$interactiveChoice = null;
if ($sqlSource === null && !isset($options['source']) && !isset($options['count']) && $isInteractive) {
    echo $color("Válassz a helyi TAWOS adatkészlet lehetőségek közül:\n", 'cyan');
    echo "  [1] Alapértelmezett kompakt adatkészlet (80 rekord - tawos_seed.csv)\n";
    if ($hasSeed350) {
        echo "  [2] Beépített kibővített adatkészlet (350 rekord - tawos_seed_350.csv) " . $color("[KÉSZ, BEÉPÍTETT]", 'green') . "\n";
    }
    if ($hasDefaultSql) {
        echo "  [3] Valódi mintavételezés a talált TAWOS.sql/zip dumpból (streamelve)\n";
    } else {
        echo "  [3] Valódi mintavételezés nyers TAWOS.sql vagy .zip dumpból (kb. 4GB)\n";
    }
    echo "  [4] Egyedi méret / forrásfájl megadása\n\n";

    $interactiveChoice = promptUser("Választás (1-4)", $hasSeed350 ? "2" : "1");

    if ($interactiveChoice === '1') {
        $sourcePath = $defaultCsv;
        $targetCount = 80;
    } elseif ($interactiveChoice === '2' && $hasSeed350) {
        $sourcePath = $seed350Csv;
        $targetCount = 350;
    } elseif ($interactiveChoice === '3') {
        $suggestedPath = $defaultSqlPath;
        if ($hasDefaultSql && !file_exists($projectRoot . '/' . $defaultSqlPath)) {
            $suggestedPath = $defaultSqlZip;
        }
        $sqlSource = promptUser("TAWOS.sql vagy .zip fájl elérési útja", $suggestedPath);
    }
} elseif ($sqlSource === null && $isInteractive && !isset($options['source']) && $hasDefaultSql
    && askYesNo("Található valódi TAWOS.sql/zip forrás! Szeretnél a nyers dumpból valódi mintát venni?", false, $autoYes, $color)) {
    $suggestedPath = file_exists($projectRoot . '/' . $defaultSqlPath) ? $defaultSqlPath : $defaultSqlZip;
    $sqlSource = promptUser("TAWOS.sql vagy .zip fájl elérési útja", $suggestedPath);
}

if ($sqlSource !== null && trim($sqlSource) !== '') {
    // Mode A: Sample from raw TAWOS SQL dump
    $targetCount = isset($options['count']) ? (int)$options['count'] : null;
    if ($targetCount === null) {
        if ($isInteractive) {
            $inp = promptUser("Hány valódi TAWOS rekordot szeretnél kinyerni? (pl. 350 vagy 500)", "350");
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
} else {
    // Mode B: Generate from local CSV templates (multiplication / phase extension)
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
    }
    fclose($handle);

    echo "Forrás fájl:      " . $color(realpath($sourcePath) ?: $sourcePath, 'yellow') . "\n";
    echo "Elérhető minták:  " . $color(count($sourceRecords) . " db", 'bold') . "\n";
    echo "Típusok a mintában: " . implode(', ', array_map(fn($t, $c) => "$t ($c db)", array_keys($availableTypes), $availableTypes)) . "\n\n";

    # 2. Determine target count for local seed
    $targetCount = isset($options['count']) ? (int)$options['count'] : null;
    if ($targetCount === null) {
        if ($isInteractive) {
            $inp = promptUser("Hány helyi TAWOS rekordot szeretnél a seed fájlba generálni? (pl. 80 vagy 500)", "80");
            $targetCount = max(1, (int)$inp);
        } else {
            $targetCount = 80;
        }
    }

    # 3. Determine type quotas
    $typeQuotas = [];
    $rawTypes = $options['types'] ?? null;

    if ($rawTypes !== null) {
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
            echo "Add meg a kívánt darabszámokat a típusokhoz:\n";
            foreach (array_keys($availableTypes) as $tName) {
                $val = promptUser("  - {$tName} darabszáma", "");
                if ($val !== '' && is_numeric($val)) {
                    $typeQuotas[$tName] = (int)$val;
                }
            }
        }
    }

    # 4. Quota allocation logic
    if (!empty($typeQuotas)) {
        $quotaSum = array_sum($typeQuotas);
        if ($quotaSum > $targetCount) {
            echo "\n" . $color("⚠️  A kvóták összege ({$quotaSum}) túllépi a megadott összértéket ({$targetCount})!", 'yellow') . "\n";
            $acceptHigher = askYesNo("Használjuk a magasabb értéket ({$quotaSum} db)?", true, $autoYes, $color);
            if ($acceptHigher) {
                $targetCount = $quotaSum;
            } else {
                $scaleDown = askYesNo("Arányosan skálázzuk vissza az eredeti korlátra ({$targetCount} db)?", true, $autoYes, $color);
                if ($scaleDown) {
                    $allocated = 0;
                    $keys = array_keys($typeQuotas);
                    foreach ($keys as $idx => $k) {
                        if ($idx === count($keys) - 1) {
                            $typeQuotas[$k] = max(1, $targetCount - $allocated);
                        } else {
                            $scaled = max(1, (int)round(($typeQuotas[$k] / $quotaSum) * $targetCount));
                            $typeQuotas[$k] = $scaled;
                            $allocated += $scaled;
                        }
                    }
                } else {
                    echo $color("Művelet megszakítva.\n", 'yellow');
                    exit(0);
                }
            }
        } elseif ($quotaSum < $targetCount) {
            $diff = $targetCount - $quotaSum;
            $missingTypes = array_diff(array_keys($availableTypes), array_keys($typeQuotas));
            if (!empty($missingTypes)) {
                $perType = (int)floor($diff / count($missingTypes));
                $rem = $diff % count($missingTypes);
                foreach ($missingTypes as $idx => $mType) {
                    $typeQuotas[$mType] = $perType + ($idx === 0 ? $rem : 0);
                }
            } else {
                $keys = array_keys($typeQuotas);
                $typeQuotas[$keys[count($keys) - 1]] += $diff;
            }
        }
    } else {
        $totalSource = count($sourceRecords);
        $allocated = 0;
        $keys = array_keys($availableTypes);
        foreach ($keys as $idx => $tName) {
            if ($idx === count($keys) - 1) {
                $typeQuotas[$tName] = max(1, $targetCount - $allocated);
            } else {
                $cnt = max(1, (int)round(($availableTypes[$tName] / $totalSource) * $targetCount));
                $typeQuotas[$tName] = $cnt;
                $allocated += $cnt;
            }
        }
    }

    # 5. Generate rows
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
            if ($repeatIndex > 0) {
                $title .= " (Phase " . ($repeatIndex + 1) . ")";
            }

            $generatedRows[] = [
                'issue_key' => $issueKey,
                'title' => $title,
                'description_text' => $base['description_text'],
                'type' => $tName,
                'priority' => $base['priority'],
                'status' => $base['status'],
                'resolution' => $base['resolution'],
                'story_point' => $base['story_point'],
                'comment_text' => $base['comment_text'],
                'project_name' => $base['project_name'],
            ];
        }
    }
}

    # 6. Backup and write CSV
    if ($makeBackup && file_exists($outputPath)) {
        copy($outputPath, $outputPath . '.bak');
        echo "Biztonsági mentés létrehozva: " . $color($outputPath . '.bak', 'dim') . "\n";
    }

    $outHandle = fopen($outputPath, 'w');
    if ($outHandle !== false) {
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
            'project_name'
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
                $r['project_name']
            ]);
        }
        fclose($outHandle);
        echo "✅ " . $color("Helyi TAWOS seed CSV kiírva: ", 'green') . $color(count($generatedRows) . " rekord", 'bold') . " (" . $outputPath . ")\n\n";
    }
}

# 7. STEP 2: Database seeding
echo $color("2. LÉPÉS: Adatbázis szinkronizáció (tawos_issues tábla)", 'bold') . "\n";
echo $divider;

if (!$writeToDb && $isInteractive) {
    $writeToDb = askYesNo("Szeretnéd azonnal betölteni a friss rekordokat a helyi adatbázisba?", true);
}

if ($writeToDb) {
    echo $color("Adatbázis seedelés folyamatban...", 'bold') . "\n";
    $autoloadPath = $backendDir . '/vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;

        if (file_exists($envFile)) {
            $dotenv = Dotenv\Dotenv::createImmutable($backendDir);
            $dotenv->load();
        }

        try {
            $db = new App\Database();
            $tawosService = new App\Service\TawosService($db->getPdo(), $db->getDbType());
            $inserted = $tawosService->reseedFromCsv(realpath($outputPath) ?: $outputPath);

            echo "✅ " . $color("Sikeres adatbázis seedelés! {$inserted} rekord beillesztve a tawos_issues táblába.", 'green') . "\n";

            $stats = $tawosService->getStats();
            echo "   • Aktuális adatbázis méret: " . $color("{$stats['total']} db", 'bold') . "\n";
            $tList = array_map(fn($t) => "{$t['type']}: {$t['count']} db", $stats['types']);
            echo "   • Típusok az adatbázisban:  " . implode(', ', $tList) . "\n";
        } catch (Exception $e) {
            fwrite(STDERR, $color("Adatbázis hiba: " . $e->getMessage() . "\n", 'red'));
        }
    } else {
        echo $color("Megjegyzés: Composer autoload nem található, adatbázis seedelés kihagyva.\n", 'dim');
    }
} else {
    echo "Adatbázis közvetlen feltöltése kihagyva (a backend indításkor auto-seedeli, ha a tábla üres).\n";
}

echo "\n";
echo $color("══════════════════════════════════════════════════════", 'cyan') . "\n";
echo "✅ " . $color("A TAIPO Setup beállításai sikeresen érvénybe léptek!", 'green') . "\n";
echo $color("══════════════════════════════════════════════════════", 'cyan') . "\n\n";
