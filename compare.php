<?php
session_start();

require_once __DIR__ . '/config/settings.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/engine/DbInspector.php';
require_once __DIR__ . '/engine/SqlFileParser.php';
require_once __DIR__ . '/engine/SchemaComparator.php';
require_once __DIR__ . '/engine/ScriptGenerator.php';

$settings = require __DIR__ . '/config/settings.php';
$outputDir = $settings['output_dir'] ?? (__DIR__ . '/output/fix_scripts/');

$mode = $_SESSION['compare_mode'] ?? null;
if (!$mode) {
    header('Location: index.php');
    exit;
}

try {
    $sourceSchema = [];
    $targetSchema = [];
    $sourceName = '';
    $targetName = '';
    $targetDbName = 'TargetDatabase';

    if ($mode === 'live') {
        $sourceParams = $_SESSION['source_db'] ?? null;
        $targetParams = $_SESSION['target_db'] ?? null;

        if (!$sourceParams || !$targetParams) {
            throw new Exception("Connection parameters are missing from session.");
        }

        // Connect to Source
        try {
            $srcConn = getConnection($sourceParams);
        } catch (PDOException $e) {
            throw new Exception("Failed to connect to Source Database: " . $e->getMessage());
        }

        // Connect to Target
        try {
            $tgtConn = getConnection($targetParams);
        } catch (PDOException $e) {
            throw new Exception("Failed to connect to Target Database: " . $e->getMessage());
        }

        // Inspect Source
        $srcInspector = new DbInspector($srcConn);
        $sourceSchema = $srcInspector->inspect();
        $sourceName = "Live DB: " . $sourceParams['host'] . " -> " . $sourceParams['dbname'];

        // Inspect Target
        $tgtInspector = new DbInspector($tgtConn);
        $targetSchema = $tgtInspector->inspect();
        $targetName = "Live DB: " . $targetParams['host'] . " -> " . $targetParams['dbname'];
        $targetDbName = $targetParams['dbname'];

    } elseif ($mode === 'upload') {
        $srcPath = $_SESSION['source_file'] ?? null;
        $tgtPath = $_SESSION['target_file'] ?? null;
        $sourceName = $_SESSION['source_name'] ?? 'Source SQL';
        $targetName = $_SESSION['target_name'] ?? 'Target SQL';

        if (!$srcPath || !$tgtPath || !file_exists($srcPath) || !file_exists($tgtPath)) {
            throw new Exception("Uploaded DDL files could not be located on the server.");
        }

        // Read files
        $srcContent = file_get_contents($srcPath);
        $tgtContent = file_get_contents($tgtPath);

        // Parser
        $parser = new SqlFileParser();
        $sourceSchema = $parser->parse($srcContent);
        $targetSchema = $parser->parse($tgtContent);

        // Extract database name from target file name if clean, otherwise default
        $targetDbName = pathinfo($targetName, PATHINFO_FILENAME);

        // Security cleanup: unlink temporary uploaded files immediately
        @unlink($srcPath);
        @unlink($tgtPath);
        unset($_SESSION['source_file']);
        unset($_SESSION['target_file']);
    } else {
        throw new Exception("Invalid comparison mode selected.");
    }

    // Run Comparison
    $comparator = new SchemaComparator($settings);
    $diff = $comparator->compare($sourceSchema, $targetSchema);

    // Generate Fix Script
    $generator = new ScriptGenerator();
    $fullScript = $generator->generateFullScript($diff, $sourceSchema, $targetSchema, $targetDbName);

    // Save fix script to output directory
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0750, true);
    }
    
    $scriptFilename = 'fix_schema_' . time() . '_' . uniqid() . '.sql';
    $scriptPath = $outputDir . $scriptFilename;
    
    if (file_put_contents($scriptPath, $fullScript) === false) {
        throw new Exception("Failed to write the generated remediation script to disk. Check folder permissions.");
    }

    // Save report data to session
    $_SESSION['run_info'] = [
        'mode'       => $mode,
        'source'     => $sourceName,
        'target'     => $targetName,
        'time'       => time(),
        'db_name'    => $targetDbName,
        'script_file'=> $scriptFilename
    ];
    $_SESSION['comparison_results'] = $diff;
    $_SESSION['source_schema']      = $sourceSchema;
    $_SESSION['target_schema']      = $targetSchema;

    header('Location: report.php');
    exit;

} catch (Exception $e) {
    $_SESSION['compare_error'] = $e->getMessage();
    if ($mode === 'live') {
        header('Location: connect.php');
    } else {
        header('Location: upload.php');
    }
    exit;
}
