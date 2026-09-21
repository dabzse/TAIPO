#!/usr/bin/env php
<?php

/**
 * TAWOS Labels Inspector Tool
 * Lekérdezi és megjeleníti a TAWOS adathalmazban (CSV vagy adatbázis) található címkéket és kategóriákat.
 *
 * Használat:
 *   php tools/tawos_list_labels.php [opciók]
 *
 * Opciók:
 *   --csv=<fájl>          Egyedi CSV fájl elérési útja (alapértelmezett: backend/data/tawos_seed.csv)
 *   --db                  Adatok lekérése a konfigurált adatbázisból (tawos_issues tábla)
 *   --dimension=<dim>     Szűrés adott dimenzióra: type, priority, status, resolution, project, all (alapértelmezett: all)
 *   --json                JSON formátumú kimenet
 *   --no-color            Színek kikapcsolása a terminálban
 *   -h, --help            Segítség megjelenítése
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$backendDir = $projectRoot . '/backend';
$defaultCsv = $backendDir . '/data/tawos_seed.csv';

// Command line arguments parsing
$options = getopt('h', ['csv:', 'source:', 'db', 'dimension:', 'json', 'no-color', 'help']);

if (isset($options['h']) || isset($options['help'])) {
    echo <<<HELP
TAWOS Címke Lekérdező Eszköz (TAIPO)
=====================================
Használat:
  php tools/tawos_list_labels.php [opciók]

Opciók:
  --source=<elérési_út>,
  --csv=<elérési_út>   CSV fájl elemzése (alapértelmezett: backend/data/tawos_seed.csv)
  --db                 A TAIPO adatbázisból (tawos_issues tábla) kéri le az adatokat
  --dimension=<név>    Csak a megadott dimenzió listázása:
                         type        - Issue típusok (Story, Bug, Task, stb.)
                         priority    - Prioritások (Critical, Major, Minor, stb.)
                         status      - Státuszok (Closed, Open, In Progress, stb.)
                         resolution  - Megoldások (Done, Fixed, Unresolved, stb.)
                         project     - Projektek / Modulok
                         all         - Minden dimenzió (alapértelmezett)
  --json               Strukturált JSON kimenet
  --no-color           ANSI színek kikapcsolása
  -h, --help           Segítség megjelenítése

Példák:
  php tools/tawos_list_labels.php
  php tools/tawos_list_labels.php --source=backend/data/tawos_seed_350.csv
  php tools/tawos_list_labels.php --db
  php tools/tawos_list_labels.php --dimension=type
  php tools/tawos_list_labels.php --json

HELP;
    exit(0);
}

$useDb = isset($options['db']);
$csvPath = $options['source'] ?? ($options['csv'] ?? $defaultCsv);
$dimension = strtolower((string)($options['dimension'] ?? 'all'));
$isJson = isset($options['json']);
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
        'bg_blue' => "\033[44m",
    ];
    return ($colors[$c] ?? '') . $text . $colors['reset'];
};

$data = [
    'source' => '',
    'total' => 0,
    'types' => [],
    'priorities' => [],
    'statuses' => [],
    'resolutions' => [],
    'projects' => [],
];

if ($useDb) {
    // Read from Database
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
        $detailed = $tawosService->getDetailedStats();

        $data['source'] = 'Database (' . ($_ENV['DB_TYPE'] ?? 'sqlite') . ', tábla: ' . App\Config::getTablePrefix() . 'tawos_issues)';
        $data['total'] = $detailed['total'];

        foreach ($detailed['types'] as $row) {
            $data['types'][$row['type']] = (int)$row['count'];
        }
        foreach ($detailed['priorities'] as $row) {
            $data['priorities'][$row['priority']] = (int)$row['count'];
        }
        foreach ($detailed['statuses'] as $row) {
            $data['statuses'][$row['status']] = (int)$row['count'];
        }
        foreach ($detailed['resolutions'] as $row) {
            $data['resolutions'][$row['resolution'] ?: 'None'] = (int)$row['count'];
        }
        foreach ($detailed['project_counts'] as $row) {
            $data['projects'][$row['project_name']] = (int)$row['count'];
        }
    } catch (Exception $e) {
        fwrite(STDERR, $color("Adatbázis hiba: " . $e->getMessage() . "\n", 'red'));
        exit(1);
    }
} else {
    // Read from CSV
    if (!file_exists($csvPath) || !is_readable($csvPath)) {
        fwrite(STDERR, $color("Hiba: CSV fájl nem található vagy nem olvasható: {$csvPath}\n", 'red'));
        exit(1);
    }

    $handle = fopen($csvPath, 'r');
    if ($handle === false) {
        fwrite(STDERR, $color("Hiba: Nem sikerült megnyitni a CSV fájlt: {$csvPath}\n", 'red'));
        exit(1);
    }

    $header = fgetcsv($handle);
    if (!$header || count($header) < 10) {
        fclose($handle);
        fwrite(STDERR, $color("Hiba: Érvénytelen TAWOS CSV fejléc formátum!\n", 'red'));
        exit(1);
    }

    // Expected columns:
    // 0: issue_key, 1: title, 2: description_text, 3: type, 4: priority,
    // 5: status, 6: resolution, 7: story_point, 8: comment_text, 9: project_name
    $data['source'] = "CSV fájl (" . realpath($csvPath) . ")";

    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < 10) {
            continue;
        }
        $data['total']++;
        $t = trim($row[3]) ?: 'Unknown';
        $p = trim($row[4]) ?: 'Unknown';
        $s = trim($row[5]) ?: 'Unknown';
        $r = trim($row[6]) ?: 'None';
        $proj = trim($row[9]) ?: 'Unknown';

        $data['types'][$t] = ($data['types'][$t] ?? 0) + 1;
        $data['priorities'][$p] = ($data['priorities'][$p] ?? 0) + 1;
        $data['statuses'][$s] = ($data['statuses'][$s] ?? 0) + 1;
        $data['resolutions'][$r] = ($data['resolutions'][$r] ?? 0) + 1;
        $data['projects'][$proj] = ($data['projects'][$proj] ?? 0) + 1;
    }
    fclose($handle);
}

// Sort in descending order
arsort($data['types']);
arsort($data['priorities']);
arsort($data['statuses']);
arsort($data['resolutions']);
arsort($data['projects']);

// JSON Output
if ($isJson) {
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}

// Table printer helper
$printSection = function (string $title, string $icon, array $items, int $total) use ($color) {
    echo "\n" . $color("{$icon} {$title}", 'bold') . " " . $color("(Összes egyedi: " . count($items) . ")", 'dim') . "\n";
    echo str_repeat("─", 50) . "\n";
    printf("  %-25s %8s %12s\n", "Címke / Érték", "Darab", "Arány (%)");
    echo str_repeat("─", 50) . "\n";

    if (empty($items)) {
        echo "  " . $color("(Nincs adat)", 'dim') . "\n";
        return;
    }

    foreach ($items as $name => $count) {
        $pct = ($total > 0) ? ($count / $total) * 100 : 0.0;
        $barLength = (int)round(($pct / 100) * 15);
        $bar = str_repeat("█", $barLength) . str_repeat("░", 15 - $barLength);

        printf(
            "  %-25s %8d %8.1f%% %s\n",
            $name,
            $count,
            $pct,
            $color($bar, 'cyan')
        );
    }
    echo str_repeat("─", 50) . "\n";
};

// Pretty CLI Output
echo "\n" . $color("╔══════════════════════════════════════════════════════╗", 'cyan') . "\n";
echo $color("║            TAWOS ADATHALMAZ CÍMKE STATISZTIKA        ║", 'cyan') . "\n";
echo $color("╚══════════════════════════════════════════════════════╝", 'cyan') . "\n";
echo "Forrás:          " . $color($data['source'], 'yellow') . "\n";
echo "Összes bejegyzés:" . $color(" {$data['total']} db", 'bold') . "\n";

if ($dimension === 'all' || $dimension === 'type') {
    $printSection("Issue Típusok (Type)", "🏷️", $data['types'], $data['total']);
}
if ($dimension === 'all' || $dimension === 'priority') {
    $printSection("Prioritások (Priority)", "⚡", $data['priorities'], $data['total']);
}
if ($dimension === 'all' || $dimension === 'status') {
    $printSection("Státuszok (Status)", "🔄", $data['statuses'], $data['total']);
}
if ($dimension === 'all' || $dimension === 'resolution') {
    $printSection("Megoldások (Resolution)", "🎯", $data['resolutions'], $data['total']);
}
if ($dimension === 'all' || $dimension === 'project') {
    $printSection("Projektek / Modulok (Project)", "📁", $data['projects'], $data['total']);
}

echo "\n";
