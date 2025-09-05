#!/usr/bin/env php
<?php

/**
 * Coverage Parser für KPI-Tracker
 * Liest coverage.xml und zeigt Coverage pro Klasse in der Shell an.
 */
$coverageFile = __DIR__.'/../coverage.xml';

if (!file_exists($coverageFile)) {
    echo "❌ Coverage-Datei nicht gefunden. Führe zuerst 'make coverage' aus.\n";
    exit(1);
}

try {
    $xml = simplexml_load_file($coverageFile);

    if (!$xml) {
        throw new Exception('Kann Coverage-XML nicht parsen');
    }

    echo "📊 Code Coverage pro Klasse:\n";
    echo str_repeat('=', 80)."\n";
    printf("%-50s %8s %8s %8s\n", 'Klasse', 'Lines %', 'Methods %', 'Elements');
    echo str_repeat('=', 80)."\n";

    $classes = [];

    // Parse XML für alle Klassen
    foreach ($xml->xpath('//class') as $class) {
        $className = (string) $class['name'];

        // Nur App-Klassen anzeigen
        if (!str_starts_with($className, 'App\\')) {
            continue;
        }

        $metrics = $class->metrics[0];
        $linesCovered = (int) $metrics['coveredstatements'];
        $linesTotal = (int) $metrics['statements'];
        $methodsCovered = (int) $metrics['coveredmethods'];
        $methodsTotal = (int) $metrics['methods'];

        $linesPercent = $linesTotal > 0 ? round(($linesCovered / $linesTotal) * 100, 1) : 0;
        $methodsPercent = $methodsTotal > 0 ? round(($methodsCovered / $methodsTotal) * 100, 1) : 0;

        $classes[] = [
            'name' => $className,
            'lines_percent' => $linesPercent,
            'methods_percent' => $methodsPercent,
            'elements' => $linesTotal + $methodsTotal,
        ];
    }

    // Sortiere nach Lines-Coverage (absteigend)
    usort($classes, fn ($a, $b) => $b['lines_percent'] <=> $a['lines_percent']);

    // Zeige Top 25 Klassen
    foreach (array_slice($classes, 0, 25) as $class) {
        $shortName = mb_substr(mb_strrchr($class['name'], '\\'), 1) ?: $class['name'];
        printf("%-50s %7.1f%% %8.1f%% %8d\n",
            mb_strlen($shortName) > 49 ? mb_substr($shortName, 0, 46).'...' : $shortName,
            $class['lines_percent'],
            $class['methods_percent'],
            $class['elements']
        );
    }

    echo str_repeat('=', 80)."\n";

    // Gesamtstatistiken
    $totalClasses = count($classes);
    $avgLines = $totalClasses > 0 ? round(array_sum(array_column($classes, 'lines_percent')) / $totalClasses, 1) : 0;
    $avgMethods = $totalClasses > 0 ? round(array_sum(array_column($classes, 'methods_percent')) / $totalClasses, 1) : 0;

    echo "📈 Zusammenfassung:\n";
    echo "   Klassen analysiert: $totalClasses\n";
    echo "   Durchschnittliche Lines Coverage: {$avgLines}%\n";
    echo "   Durchschnittliche Methods Coverage: {$avgMethods}%\n";
    echo str_repeat('=', 80)."\n";

    // Top/Bottom 3
    echo "🏆 Beste Coverage:\n";
    foreach (array_slice($classes, 0, 3) as $i => $class) {
        $shortName = mb_substr(mb_strrchr($class['name'], '\\'), 1) ?: $class['name'];
        echo '   '.($i + 1).". $shortName: {$class['lines_percent']}%\n";
    }

    echo "\n⚠️  Niedrigste Coverage:\n";
    foreach (array_slice(array_reverse($classes), 0, 3) as $i => $class) {
        $shortName = mb_substr(mb_strrchr($class['name'], '\\'), 1) ?: $class['name'];
        echo '   '.($i + 1).". $shortName: {$class['lines_percent']}%\n";
    }
} catch (Exception $e) {
    echo '❌ Fehler beim Parsen der Coverage-Daten: '.$e->getMessage()."\n";
    exit(1);
}
