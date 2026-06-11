<?php
session_start();

$settings = require __DIR__ . '/config/settings.php';
$outputDir = $settings['output_dir'] ?? (__DIR__ . '/output/fix_scripts/');

// Handle download request
if (isset($_GET['download'])) {
    $filename = basename($_GET['download']); // Security: prevent directory traversal
    $path = $outputDir . $filename;
    if (file_exists($path) && is_file($path)) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="remediation_script.sql"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    } else {
        $downloadError = "Remediation script could not be downloaded.";
    }
}

$diff = $_SESSION['comparison_results'] ?? null;
$runInfo = $_SESSION['run_info'] ?? null;
$sourceSchema = $_SESSION['source_schema'] ?? null;
$targetSchema = $_SESSION['target_schema'] ?? null;

if (!$diff || !$runInfo) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config/layout.php';
require_once __DIR__ . '/engine/ScriptGenerator.php';

$generator = new ScriptGenerator();

// Calculate totals
$missingInTargetCount = 0;
foreach ($diff['missing_in_target'] as $cat => $items) {
    $missingInTargetCount += count($items);
}

$mismatchCount = 0;
foreach ($diff['mismatches'] as $cat => $items) {
    $mismatchCount += count($items);
}

$missingInSourceCount = 0;
foreach ($diff['missing_in_source'] as $cat => $items) {
    $missingInSourceCount += count($items);
}

$totalDiffs = $missingInTargetCount + $mismatchCount + $missingInSourceCount;

// Calculate total objects in source
$sourceObjectCount = count($sourceSchema['tables']) +
                     count($sourceSchema['columns']) +
                     count($sourceSchema['primary_keys']) +
                     count($sourceSchema['foreign_keys']) +
                     count($sourceSchema['unique_constraints']) +
                     count($sourceSchema['check_constraints']) +
                     count($sourceSchema['default_constraints']) +
                     count($sourceSchema['indexes']) +
                     count($sourceSchema['triggers']) +
                     count($sourceSchema['views'] ?? []) +
                     count($sourceSchema['procedures'] ?? []) +
                     count($sourceSchema['functions'] ?? []);

// Percentage match (rough estimate)
$matchPercent = 100;
if ($sourceObjectCount > 0) {
    $matchedObjects = max(0, $sourceObjectCount - ($missingInTargetCount + $mismatchCount));
    $matchPercent = round(($matchedObjects / $sourceObjectCount) * 100);
}

renderHeader('Comparison Report');
?>

<div class="row g-4 animate-fade-in">
    <!-- Top Action Row -->
    <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
        <div>
            <h2 class="fw-bold mb-1">Schema Comparison Report</h2>
            <p class="text-secondary small mb-0">
                Source: <strong><?php echo htmlspecialchars($runInfo['source']); ?></strong> &nbsp;|&nbsp; 
                Target: <strong><?php echo htmlspecialchars($runInfo['target']); ?></strong>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-outline-secondary border-color d-inline-flex align-items-center gap-1" style="color: var(--text-secondary);">
                <i class="bi bi-arrow-left-right"></i> Compare New
            </a>
            <a href="report.php?download=<?php echo urlencode($runInfo['script_file']); ?>" class="btn btn-primary d-inline-flex align-items-center gap-2">
                <i class="bi bi-download"></i> Download Full SQL Fix
            </a>
        </div>
    </div>

    <?php if (isset($downloadError)): ?>
        <div class="col-12">
            <div class="alert alert-danger border-0 mb-0"><?php echo htmlspecialchars($downloadError); ?></div>
        </div>
    <?php endif; ?>

    <!-- Summary Widgets Row -->
    <div class="col-lg-3 col-md-6">
        <div class="card glass-card border-0 p-4 h-100">
            <div class="card-body d-flex flex-column align-items-center text-center">
                <div class="mb-2 text-muted small fw-semibold uppercase">Schema Congruence</div>
                <div class="display-4 fw-bold <?php echo $matchPercent === 100 ? 'text-success' : ($matchPercent > 80 ? 'text-warning' : 'text-danger'); ?>">
                    <?php echo $matchPercent; ?>%
                </div>
                <div class="text-secondary small mt-2">Similarity matching score</div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="card glass-card border-0 p-4 h-100" style="border-left: 4px solid var(--danger-color) !important;">
            <div class="card-body d-flex flex-column align-items-center text-center">
                <div class="mb-2 text-muted small fw-semibold">Missing in Target</div>
                <div class="display-4 fw-bold text-danger"><?php echo $missingInTargetCount; ?></div>
                <div class="text-secondary small mt-2">Objects to create</div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="card glass-card border-0 p-4 h-100" style="border-left: 4px solid var(--warning-color) !important;">
            <div class="card-body d-flex flex-column align-items-center text-center">
                <div class="mb-2 text-muted small fw-semibold">Mismatched Definitions</div>
                <div class="display-4 fw-bold text-warning"><?php echo $mismatchCount; ?></div>
                <div class="text-secondary small mt-2">Objects to alter or recreate</div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="card glass-card border-0 p-4 h-100" style="border-left: 4px solid var(--info-color) !important;">
            <div class="card-body d-flex flex-column align-items-center text-center">
                <div class="mb-2 text-muted small fw-semibold">Target-Only Extras</div>
                <div class="display-4 fw-bold text-info"><?php echo $missingInSourceCount; ?></div>
                <div class="text-secondary small mt-2">Objects to review or drop</div>
            </div>
        </div>
    </div>

    <!-- Main Report Section -->
    <div class="col-12 mt-5">
        <div class="card glass-card border-0 p-4">
            <div class="card-body">
                
                <!-- Search and Categories Nav -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 border-bottom border-color pb-3">
                    <ul class="nav nav-pills" id="reportTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tables-tab" data-bs-toggle="tab" data-bs-target="#tables-pane" type="button" role="tab">
                                <i class="bi bi-table me-1"></i> Tables 
                                <span class="badge bg-secondary ms-1 small"><?php echo count($diff['missing_in_target']['tables']) + count($diff['missing_in_source']['tables']); ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="columns-tab" data-bs-toggle="tab" data-bs-target="#columns-pane" type="button" role="tab">
                                <i class="bi bi-layout-three-columns me-1"></i> Columns 
                                <span class="badge bg-secondary ms-1 small"><?php echo count($diff['missing_in_target']['columns']) + count($diff['mismatches']['columns']) + count($diff['missing_in_source']['columns']); ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="constraints-tab" data-bs-toggle="tab" data-bs-target="#constraints-pane" type="button" role="tab">
                                <i class="bi bi-shield-lock me-1"></i> Constraints 
                                <span class="badge bg-secondary ms-1 small">
                                    <?php 
                                    $cCount = count($diff['missing_in_target']['primary_keys']) + count($diff['mismatches']['primary_keys']) + count($diff['missing_in_source']['primary_keys']) +
                                              count($diff['missing_in_target']['foreign_keys']) + count($diff['mismatches']['foreign_keys']) + count($diff['missing_in_source']['foreign_keys']) +
                                              count($diff['missing_in_target']['unique_constraints']) + count($diff['mismatches']['unique_constraints']) + count($diff['missing_in_source']['unique_constraints']) +
                                              count($diff['missing_in_target']['check_constraints']) + count($diff['mismatches']['check_constraints']) + count($diff['missing_in_source']['check_constraints']) +
                                              count($diff['missing_in_target']['default_constraints']) + count($diff['mismatches']['default_constraints']) + count($diff['missing_in_source']['default_constraints']);
                                    echo $cCount;
                                    ?>
                                </span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="indexes-tab" data-bs-toggle="tab" data-bs-target="#indexes-pane" type="button" role="tab">
                                <i class="bi bi-list-ol me-1"></i> Indexes 
                                <span class="badge bg-secondary ms-1 small"><?php echo count($diff['missing_in_target']['indexes']) + count($diff['mismatches']['indexes']) + count($diff['missing_in_source']['indexes']); ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="triggers-tab" data-bs-toggle="tab" data-bs-target="#triggers-pane" type="button" role="tab">
                                <i class="bi bi-lightning me-1"></i> Triggers 
                                <span class="badge bg-secondary ms-1 small"><?php echo count($diff['missing_in_target']['triggers']) + count($diff['mismatches']['triggers']) + count($diff['missing_in_source']['triggers']); ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="views-tab" data-bs-toggle="tab" data-bs-target="#views-pane" type="button" role="tab">
                                <i class="bi bi-eye me-1"></i> Views 
                                <span class="badge bg-secondary ms-1 small"><?php echo count($diff['missing_in_target']['views'] ?? []) + count($diff['mismatches']['views'] ?? []) + count($diff['missing_in_source']['views'] ?? []); ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="procedures-tab" data-bs-toggle="tab" data-bs-target="#procedures-pane" type="button" role="tab">
                                <i class="bi bi-terminal me-1"></i> Procs 
                                <span class="badge bg-secondary ms-1 small"><?php echo count($diff['missing_in_target']['procedures'] ?? []) + count($diff['mismatches']['procedures'] ?? []) + count($diff['missing_in_source']['procedures'] ?? []); ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="functions-tab" data-bs-toggle="tab" data-bs-target="#functions-pane" type="button" role="tab">
                                <i class="bi bi-braces me-1"></i> Functions 
                                <span class="badge bg-secondary ms-1 small"><?php echo count($diff['missing_in_target']['functions'] ?? []) + count($diff['mismatches']['functions'] ?? []) + count($diff['missing_in_source']['functions'] ?? []); ?></span>
                            </button>
                        </li>
                    </ul>
                    
                    <div class="d-flex align-items-center gap-2">
                        <div class="input-group input-group-sm" style="max-width: 250px;">
                            <span class="input-group-text border-color bg-transparent"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="tableFilter" class="form-control" placeholder="Search objects...">
                        </div>
                    </div>
                </div>

                <!-- Tab Panes -->
                <div class="tab-content" id="reportTabsContent">
                    
                    <!-- TABLES TAB -->
                    <div class="tab-pane fade show active" id="tables-pane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="tables-table">
                                <thead class="table-light text-secondary small">
                                    <tr>
                                        <th>Table Name</th>
                                        <th>Schema</th>
                                        <th>Status</th>
                                        <th class="text-end">Remediation DDL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $hasTables = false;
                                    // Missing in target
                                    foreach ($diff['missing_in_target']['tables'] as $key => $table):
                                        $hasTables = true;
                                        $snippetId = 'snippet_tbl_add_' . md5($key);
                                        $ddl = $generator->generateCreateTable($key, $table, $sourceSchema['columns']);
                                        ?>
                                        <tr class="diff-row-missing-target">
                                            <td class="fw-bold"><?php echo htmlspecialchars($table['name']); ?></td>
                                            <td><code class="text-secondary"><?php echo htmlspecialchars($table['schema']); ?></code></td>
                                            <td><span class="badge badge-missing-target"><i class="bi bi-plus-circle me-1"></i> Missing in Target</span></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $snippetId; ?>">View DDL</button>
                                            </td>
                                        </tr>
                                        <tr class="collapse-row bg-light border-0">
                                            <td colspan="4" class="p-0 border-0">
                                                <div class="collapse p-3" id="<?php echo $snippetId; ?>">
                                                    <pre class="code-block m-0"><button class="copy-btn btn btn-sm">Copy</button><code id="code_<?php echo $snippetId; ?>"><?php echo htmlspecialchars($ddl); ?></code></pre>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php
                                    // Missing in source (target extras)
                                    foreach ($diff['missing_in_source']['tables'] as $key => $table):
                                        $hasTables = true;
                                        $snippetId = 'snippet_tbl_del_' . md5($key);
                                        $ddl = "DROP TABLE [{$table['schema']}].[{$table['name']}];\nGO";
                                        ?>
                                        <tr class="diff-row-missing-source">
                                            <td class="fw-bold"><?php echo htmlspecialchars($table['name']); ?></td>
                                            <td><code class="text-secondary"><?php echo htmlspecialchars($table['schema']); ?></code></td>
                                            <td><span class="badge badge-missing-source"><i class="bi bi-info-circle me-1"></i> Extra in Target</span></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $snippetId; ?>">View DDL</button>
                                            </td>
                                        </tr>
                                        <tr class="collapse-row bg-light border-0">
                                            <td colspan="4" class="p-0 border-0">
                                                <div class="collapse p-3" id="<?php echo $snippetId; ?>">
                                                    <pre class="code-block m-0"><button class="copy-btn btn btn-sm">Copy</button><code id="code_<?php echo $snippetId; ?>"><?php echo htmlspecialchars($ddl); ?></code></pre>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (!$hasTables): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted small">No differences found in tables. All schemas match!</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- COLUMNS TAB -->
                    <div class="tab-pane fade" id="columns-pane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="columns-table">
                                <thead class="table-light text-secondary small">
                                    <tr>
                                        <th>Column Name</th>
                                        <th>Table</th>
                                        <th>Status / Properties</th>
                                        <th class="text-end">Remediation DDL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $hasCols = false;
                                    // Missing in target
                                    foreach ($diff['missing_in_target']['columns'] as $key => $col):
                                        $hasCols = true;
                                        $snippetId = 'snippet_col_add_' . md5($key);
                                        $ddl = $generator->generateAlterTableAddColumn($col);
                                        ?>
                                        <tr class="diff-row-missing-target">
                                            <td class="fw-bold"><?php echo htmlspecialchars($col['name']); ?></td>
                                            <td><?php echo htmlspecialchars($col['schema'] . '.' . $col['table']); ?></td>
                                            <td>
                                                <span class="badge badge-missing-target mb-1"><i class="bi bi-plus-circle"></i> Missing Column</span><br>
                                                <code class="text-secondary small"><?php echo htmlspecialchars($col['type'] . ($col['length'] ? "({$col['length']})" : "") . ($col['nullable'] ? " NULL" : " NOT NULL")); ?></code>
                                            </td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $snippetId; ?>">View DDL</button>
                                            </td>
                                        </tr>
                                        <tr class="collapse-row bg-light border-0">
                                            <td colspan="4" class="p-0 border-0">
                                                <div class="collapse p-3" id="<?php echo $snippetId; ?>">
                                                    <pre class="code-block m-0"><button class="copy-btn btn btn-sm">Copy</button><code id="code_<?php echo $snippetId; ?>"><?php echo htmlspecialchars($ddl); ?></code></pre>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php
                                    // Mismatches
                                    foreach ($diff['mismatches']['columns'] as $key => $item):
                                        $hasCols = true;
                                        $src = $item['source'];
                                        $tgt = $item['target'];
                                        $snippetId = 'snippet_col_mod_' . md5($key);
                                        $ddl = $generator->generateAlterTableModifyColumn($src, $tgt);
                                        ?>
                                        <tr class="diff-row-mismatch">
                                            <td class="fw-bold"><?php echo htmlspecialchars($src['name']); ?></td>
                                            <td><?php echo htmlspecialchars($src['schema'] . '.' . $src['table']); ?></td>
                                            <td>
                                                <span class="badge badge-mismatch mb-2"><i class="bi bi-exclamation-triangle"></i> Type Mismatch</span>
                                                <div class="small">
                                                    <span class="text-success fw-bold">Source:</span> <code><?php echo htmlspecialchars($src['type'] . ($src['length'] ? "({$src['length']})" : "") . ($src['nullable'] ? " NULL" : " NOT NULL")); ?></code><br>
                                                    <span class="text-danger fw-bold">Target:</span> <code><?php echo htmlspecialchars($tgt['type'] . ($tgt['length'] ? "({$tgt['length']})" : "") . ($tgt['nullable'] ? " NULL" : " NOT NULL")); ?></code>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $snippetId; ?>">View DDL</button>
                                            </td>
                                        </tr>
                                        <tr class="collapse-row bg-light border-0">
                                            <td colspan="4" class="p-0 border-0">
                                                <div class="collapse p-3" id="<?php echo $snippetId; ?>">
                                                    <pre class="code-block m-0"><button class="copy-btn btn btn-sm">Copy</button><code id="code_<?php echo $snippetId; ?>"><?php echo htmlspecialchars($ddl); ?></code></pre>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php
                                    // Missing in source
                                    foreach ($diff['missing_in_source']['columns'] as $key => $col):
                                        $hasCols = true;
                                        $snippetId = 'snippet_col_del_' . md5($key);
                                        $ddl = "ALTER TABLE [{$col['schema']}].[{$col['table']}] DROP COLUMN [{$col['name']}];\nGO";
                                        ?>
                                        <tr class="diff-row-missing-source">
                                            <td class="fw-bold"><?php echo htmlspecialchars($col['name']); ?></td>
                                            <td><?php echo htmlspecialchars($col['schema'] . '.' . $col['table']); ?></td>
                                            <td><span class="badge badge-missing-source"><i class="bi bi-info-circle"></i> Extra in Target</span></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $snippetId; ?>">View DDL</button>
                                            </td>
                                        </tr>
                                        <tr class="collapse-row bg-light border-0">
                                            <td colspan="4" class="p-0 border-0">
                                                <div class="collapse p-3" id="<?php echo $snippetId; ?>">
                                                    <pre class="code-block m-0"><button class="copy-btn btn btn-sm">Copy</button><code id="code_<?php echo $snippetId; ?>"><?php echo htmlspecialchars($ddl); ?></code></pre>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (!$hasCols): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted small">No differences found in columns. All tables share exact columns!</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- CONSTRAINTS TAB -->
                    <div class="tab-pane fade" id="constraints-pane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="constraints-table">
                                <thead class="table-light text-secondary small">
                                    <tr>
                                        <th>Constraint Name</th>
                                        <th>Table</th>
                                        <th>Type / Definition</th>
                                        <th class="text-end">Remediation DDL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $hasCons = false;

                                    // Helper function to render a constraint row
                                    $renderConstraint = function(string $typeLabel, array $con, string $statusClass, string $badgeClass, string $statusLabel, string $ddl) use (&$hasCons, $generator) {
                                        $hasCons = true;
                                        $name = $con['name'] ?? 'Unnamed Constraint';
                                        $snippetId = 'snippet_con_' . md5($name . serialize($con));
                                        ?>
                                        <tr class="<?php echo $statusClass; ?>">
                                            <td class="fw-bold"><?php echo htmlspecialchars($name); ?></td>
                                            <td><?php echo htmlspecialchars($con['schema'] . '.' . $con['table']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $badgeClass; ?> mb-1"><?php echo $statusLabel; ?></span> &bull; 
                                                <strong class="small"><?php echo $typeLabel; ?></strong><br>
                                                <?php if (isset($con['columns'])): ?>
                                                    <code class="text-secondary small">Columns: <?php echo implode(', ', $con['columns']); ?></code>
                                                <?php endif; ?>
                                                <?php if (isset($con['definition'])): ?>
                                                    <code class="text-secondary small"><?php echo htmlspecialchars($con['definition']); ?></code>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $snippetId; ?>">View DDL</button>
                                            </td>
                                        </tr>
                                        <tr class="collapse-row bg-light border-0">
                                            <td colspan="4" class="p-0 border-0">
                                                <div class="collapse p-3" id="<?php echo $snippetId; ?>">
                                                    <pre class="code-block m-0"><button class="copy-btn btn btn-sm">Copy</button><code id="code_<?php echo $snippetId; ?>"><?php echo htmlspecialchars($ddl); ?></code></pre>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
                                    };

                                    // --- 1. Primary Keys ---
                                    foreach ($diff['missing_in_target']['primary_keys'] as $pk) {
                                        $renderConstraint('PRIMARY KEY', $pk, 'diff-row-missing-target', 'badge-missing-target', 'Missing PK', $generator->generateAddPrimaryKey($pk));
                                    }
                                    foreach ($diff['mismatches']['primary_keys'] as $item) {
                                        $ddl = $generator->generateDropPrimaryKey($item['target']) . "\n" . $generator->generateAddPrimaryKey($item['source']);
                                        $renderConstraint('PRIMARY KEY', $item['source'], 'diff-row-mismatch', 'badge-mismatch', 'Mismatch PK', $ddl);
                                    }
                                    foreach ($diff['missing_in_source']['primary_keys'] as $pk) {
                                        $renderConstraint('PRIMARY KEY', $pk, 'diff-row-missing-source', 'badge-missing-source', 'Extra PK', $generator->generateDropPrimaryKey($pk));
                                    }

                                    // --- 2. Foreign Keys ---
                                    foreach ($diff['missing_in_target']['foreign_keys'] as $fk) {
                                        $renderConstraint('FOREIGN KEY', $fk, 'diff-row-missing-target', 'badge-missing-target', 'Missing FK', $generator->generateAddForeignKey($fk));
                                    }
                                    foreach ($diff['mismatches']['foreign_keys'] as $item) {
                                        $ddl = $generator->generateDropForeignKey($item['target']) . "\n" . $generator->generateAddForeignKey($item['source']);
                                        $renderConstraint('FOREIGN KEY', $item['source'], 'diff-row-mismatch', 'badge-mismatch', 'Mismatch FK', $ddl);
                                    }
                                    foreach ($diff['missing_in_source']['foreign_keys'] as $fk) {
                                        $renderConstraint('FOREIGN KEY', $fk, 'diff-row-missing-source', 'badge-missing-source', 'Extra FK', $generator->generateDropForeignKey($fk));
                                    }

                                    // --- 3. Unique Constraints ---
                                    foreach ($diff['missing_in_target']['unique_constraints'] as $uc) {
                                        $renderConstraint('UNIQUE CONSTRAINT', $uc, 'diff-row-missing-target', 'badge-missing-target', 'Missing Unique', $generator->generateAddUniqueConstraint($uc));
                                    }
                                    foreach ($diff['mismatches']['unique_constraints'] as $item) {
                                        $ddl = $generator->generateDropUniqueConstraint($item['target']) . "\n" . $generator->generateAddUniqueConstraint($item['source']);
                                        $renderConstraint('UNIQUE CONSTRAINT', $item['source'], 'diff-row-mismatch', 'badge-mismatch', 'Mismatch Unique', $ddl);
                                    }
                                    foreach ($diff['missing_in_source']['unique_constraints'] as $uc) {
                                        $renderConstraint('UNIQUE CONSTRAINT', $uc, 'diff-row-missing-source', 'badge-missing-source', 'Extra Unique', $generator->generateDropUniqueConstraint($uc));
                                    }

                                    // --- 4. Check Constraints ---
                                    foreach ($diff['missing_in_target']['check_constraints'] as $cc) {
                                        $renderConstraint('CHECK CONSTRAINT', $cc, 'diff-row-missing-target', 'badge-missing-target', 'Missing Check', $generator->generateAddCheckConstraint($cc));
                                    }
                                    foreach ($diff['mismatches']['check_constraints'] as $item) {
                                        $ddl = $generator->generateDropCheckConstraint($item['target']) . "\n" . $generator->generateAddCheckConstraint($item['source']);
                                        $renderConstraint('CHECK CONSTRAINT', $item['source'], 'diff-row-mismatch', 'badge-mismatch', 'Mismatch Check', $ddl);
                                    }
                                    foreach ($diff['missing_in_source']['check_constraints'] as $cc) {
                                        $renderConstraint('CHECK CONSTRAINT', $cc, 'diff-row-missing-source', 'badge-missing-source', 'Extra Check', $generator->generateDropCheckConstraint($cc));
                                    }

                                    // --- 5. Default Constraints ---
                                    foreach ($diff['missing_in_target']['default_constraints'] as $dc) {
                                        $renderConstraint('DEFAULT CONSTRAINT', $dc, 'diff-row-missing-target', 'badge-missing-target', 'Missing Default', $generator->generateAddDefaultConstraint($dc));
                                    }
                                    foreach ($diff['mismatches']['default_constraints'] as $item) {
                                        $ddl = $generator->generateDropDefaultConstraint($item['target']) . "\n" . $generator->generateAddDefaultConstraint($item['source']);
                                        $renderConstraint('DEFAULT CONSTRAINT', $item['source'], 'diff-row-mismatch', 'badge-mismatch', 'Mismatch Default', $ddl);
                                    }
                                    foreach ($diff['missing_in_source']['default_constraints'] as $dc) {
                                        $renderConstraint('DEFAULT CONSTRAINT', $dc, 'diff-row-missing-source', 'badge-missing-source', 'Extra Default', $generator->generateDropDefaultConstraint($dc));
                                    }

                                    if (!$hasCons): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted small">No differences found in constraints (PKs, FKs, Unique, Check, Defaults). All constraints match!</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- INDEXES TAB -->
                    <div class="tab-pane fade" id="indexes-pane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="indexes-table">
                                <thead class="table-light text-secondary small">
                                    <tr>
                                        <th>Index Name</th>
                                        <th>Table</th>
                                        <th>Details</th>
                                        <th class="text-end">Remediation DDL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $hasIdx = false;
                                    // Missing in target
                                    foreach ($diff['missing_in_target']['indexes'] as $key => $idx):
                                        $hasIdx = true;
                                        $snippetId = 'snippet_idx_add_' . md5($key);
                                        $ddl = $generator->generateAddIndex($idx);
                                        ?>
                                        <tr class="diff-row-missing-target">
                                            <td class="fw-bold"><?php echo htmlspecialchars($idx['name']); ?></td>
                                            <td><?php echo htmlspecialchars($idx['schema'] . '.' . $idx['table']); ?></td>
                                            <td>
                                                <span class="badge badge-missing-target mb-1"><i class="bi bi-plus-circle"></i> Missing Index</span> &bull; 
                                                <strong class="small"><?php echo htmlspecialchars($idx['type'] . ($idx['is_unique'] ? ' (Unique)' : '')); ?></strong><br>
                                                <code class="text-secondary small">Columns: <?php echo implode(', ', $idx['columns']); ?></code>
                                                <?php if (!empty($idx['included_columns'])): ?>
                                                    <br><code class="text-secondary small">Included: <?php echo implode(', ', $idx['included_columns']); ?></code>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $snippetId; ?>">View DDL</button>
                                            </td>
                                        </tr>
                                        <tr class="collapse-row bg-light border-0">
                                            <td colspan="4" class="p-0 border-0">
                                                <div class="collapse p-3" id="<?php echo $snippetId; ?>">
                                                    <pre class="code-block m-0"><button class="copy-btn btn btn-sm">Copy</button><code id="code_<?php echo $snippetId; ?>"><?php echo htmlspecialchars($ddl); ?></code></pre>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php
                                    // Mismatches
                                    foreach ($diff['mismatches']['indexes'] as $key => $item):
                                        $hasIdx = true;
                                        $src = $item['source'];
                                        $tgt = $item['target'];
                                        $snippetId = 'snippet_idx_mod_' . md5($key);
                                        $ddl = $generator->generateDropIndex($tgt) . "\n" . $generator->generateAddIndex($src);
                                        ?>
                                        <tr class="diff-row-mismatch">
                                            <td class="fw-bold"><?php echo htmlspecialchars($src['name']); ?></td>
                                            <td><?php echo htmlspecialchars($src['schema'] . '.' . $src['table']); ?></td>
                                            <td>
                                                <span class="badge badge-mismatch mb-2"><i class="bi bi-exclamation-triangle"></i> Definition Mismatch</span>
                                                <div class="small">
                                                    <span class="text-success fw-bold">Source:</span> <code><?php echo $src['type'] . ($src['is_unique'] ? ' (Unique)' : '') . " (" . implode(', ', $src['columns']) . ")"; ?></code><br>
                                                    <span class="text-danger fw-bold">Target:</span> <code><?php echo $tgt['type'] . ($tgt['is_unique'] ? ' (Unique)' : '') . " (" . implode(', ', $tgt['columns']) . ")"; ?></code>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $snippetId; ?>">View DDL</button>
                                            </td>
                                        </tr>
                                        <tr class="collapse-row bg-light border-0">
                                            <td colspan="4" class="p-0 border-0">
                                                <div class="collapse p-3" id="<?php echo $snippetId; ?>">
                                                    <pre class="code-block m-0"><button class="copy-btn btn btn-sm">Copy</button><code id="code_<?php echo $snippetId; ?>"><?php echo htmlspecialchars($ddl); ?></code></pre>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php
                                    // Missing in source
                                    foreach ($diff['missing_in_source']['indexes'] as $key => $idx):
                                        $hasIdx = true;
                                        $snippetId = 'snippet_idx_del_' . md5($key);
                                        $ddl = $generator->generateDropIndex($idx);
                                        ?>
                                        <tr class="diff-row-missing-source">
                                            <td class="fw-bold"><?php echo htmlspecialchars($idx['name']); ?></td>
                                            <td><?php echo htmlspecialchars($idx['schema'] . '.' . $idx['table']); ?></td>
                                            <td><span class="badge badge-missing-source"><i class="bi bi-info-circle"></i> Extra in Target</span></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $snippetId; ?>">View DDL</button>
                                            </td>
                                        </tr>
                                        <tr class="collapse-row bg-light border-0">
                                            <td colspan="4" class="p-0 border-0">
                                                <div class="collapse p-3" id="<?php echo $snippetId; ?>">
                                                    <pre class="code-block m-0"><button class="copy-btn btn btn-sm">Copy</button><code id="code_<?php echo $snippetId; ?>"><?php echo htmlspecialchars($ddl); ?></code></pre>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (!$hasIdx): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted small">No differences found in indexes. All non-PK indexes match!</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TRIGGERS TAB -->
                    <div class="tab-pane fade" id="triggers-pane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="triggers-table">
                                <thead class="table-light text-secondary small">
                                    <tr>
                                        <th>Trigger Name</th>
                                        <th>Table</th>
                                        <th>Actions</th>
                                        <th class="text-end">Remediation DDL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $hasTrg = false;
                                    // Missing in target
                                    foreach ($diff['missing_in_target']['triggers'] as $key => $trg):
                                        $hasTrg = true;
                                        $snippetId = 'snippet_trg_add_' . md5($key);
                                        $ddl = $generator->generateAddTrigger($trg);
                                        ?>
                                        <tr class="diff-row-missing-target">
                                            <td class="fw-bold"><?php echo htmlspecialchars($trg['name']); ?></td>
                                            <td><?php echo htmlspecialchars($trg['schema'] . '.' . $trg['table']); ?></td>
                                            <td>
                                                <span class="badge badge-missing-target mb-1"><i class="bi bi-plus-circle"></i> Missing Trigger</span><br>
                                                <code class="text-secondary small">Events: <?php echo implode(', ', $trg['events']); ?></code>
                                            </td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $snippetId; ?>">View DDL</button>
                                            </td>
                                        </tr>
                                        <tr class="collapse-row bg-light border-0">
                                            <td colspan="4" class="p-0 border-0">
                                                <div class="collapse p-3" id="<?php echo $snippetId; ?>">
                                                    <pre class="code-block m-0"><button class="copy-btn btn btn-sm">Copy</button><code id="code_<?php echo $snippetId; ?>"><?php echo htmlspecialchars($ddl); ?></code></pre>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php
                                    // Mismatches
                                    foreach ($diff['mismatches']['triggers'] as $key => $item):
                                        $hasTrg = true;
                                        $src = $item['source'];
                                        $tgt = $item['target'];
                                        $snippetId = 'snippet_trg_mod_' . md5($key);
                                        $ddl = $generator->generateDropTrigger($tgt) . "\nGO\n" . $generator->generateAddTrigger($src);
                                        ?>
                                        <tr class="diff-row-mismatch">
                                            <td class="fw-bold"><?php echo htmlspecialchars($src['name']); ?></td>
                                            <td><?php echo htmlspecialchars($src['schema'] . '.' . $src['table']); ?></td>
                                            <td>
                                                <span class="badge badge-mismatch mb-1"><i class="bi bi-exclamation-triangle"></i> Content Mismatch</span><br>
                                                <span class="text-muted small">Trigger bodies differ in spacing, definitions or actions.</span>
                                            </td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $snippetId; ?>">View DDL</button>
                                            </td>
                                        </tr>
                                        <tr class="collapse-row bg-light border-0">
                                            <td colspan="4" class="p-0 border-0">
                                                <div class="collapse p-3" id="<?php echo $snippetId; ?>">
                                                    <pre class="code-block m-0"><button class="copy-btn btn btn-sm">Copy</button><code id="code_<?php echo $snippetId; ?>"><?php echo htmlspecialchars($ddl); ?></code></pre>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php
                                    // Missing in source
                                    foreach ($diff['missing_in_source']['triggers'] as $key => $trg):
                                        $hasTrg = true;
                                        $snippetId = 'snippet_trg_del_' . md5($key);
                                        $ddl = $generator->generateDropTrigger($trg);
                                        ?>
                                        <tr class="diff-row-missing-source">
                                            <td class="fw-bold"><?php echo htmlspecialchars($trg['name']); ?></td>
                                            <td><?php echo htmlspecialchars($trg['schema'] . '.' . $trg['table']); ?></td>
                                            <td><span class="badge badge-missing-source"><i class="bi bi-info-circle"></i> Extra in Target</span></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $snippetId; ?>">View DDL</button>
                                            </td>
                                        </tr>
                                        <tr class="collapse-row bg-light border-0">
                                            <td colspan="4" class="p-0 border-0">
                                                <div class="collapse p-3" id="<?php echo $snippetId; ?>">
                                                    <pre class="code-block m-0"><button class="copy-btn btn btn-sm">Copy</button><code id="code_<?php echo $snippetId; ?>"><?php echo htmlspecialchars($ddl); ?></code></pre>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (!$hasTrg): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted small">No differences found in triggers. All triggers match!</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- VIEWS TAB -->
                    <div class="tab-pane fade" id="views-pane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="views-table">
                                <thead class="table-light text-secondary small">
                                    <tr>
                                        <th>View Name</th>
                                        <th>Schema</th>
                                        <th>Status</th>
                                        <th class="text-end">Remediation DDL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $hasView = false;
                                    foreach ($diff['missing_in_target']['views'] ?? [] as $key => $view):
                                        $hasView = true;
                                        $snippetId = 'snippet_view_add_' . md5($key);
                                        $ddl = $generator->generateAddView($view);
                                        ?>
                                        <tr class="diff-row-missing-target">
                                            <td class="fw-bold"><?php echo htmlspecialchars($view['name']); ?></td>
                                            <td><code class="text-secondary"><?php echo htmlspecialchars($view['schema']); ?></code></td>
                                            <td><span class="badge badge-missing-target"><i class="bi bi-plus-circle"></i> Missing View</span></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $snippetId; ?>">View DDL</button>
                                            </td>
                                        </tr>
                                        <tr class="collapse-row bg-light border-0">
                                            <td colspan="4" class="p-0 border-0">
                                                <div class="collapse p-3" id="<?php echo $snippetId; ?>">
                                                    <pre class="code-block m-0"><button class="copy-btn btn btn-sm">Copy</button><code id="code_<?php echo $snippetId; ?>"><?php echo htmlspecialchars($ddl); ?></code></pre>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php
                                    foreach ($diff['mismatches']['views'] ?? [] as $key => $item):
                                        $hasView = true;
                                        $src = $item['source'];
                                        $tgt = $item['target'];
                                        $snippetId = 'snippet_view_mod_' . md5($key);
                                        $ddl = $generator->generateDropView($tgt) . "\nGO\n" . $generator->generateAddView($src);
                                        ?>
                                        <tr class="diff-row-mismatch">
                                            <td class="fw-bold"><?php echo htmlspecialchars($src['name']); ?></td>
                                            <td><code class="text-secondary"><?php echo htmlspecialchars($src['schema']); ?></code></td>
                                            <td><span class="badge badge-mismatch"><i class="bi bi-exclamation-triangle"></i> Mismatched View</span></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $snippetId; ?>">View DDL</button>
                                            </td>
                                        </tr>
                                        <tr class="collapse-row bg-light border-0">
                                            <td colspan="4" class="p-0 border-0">
                                                <div class="collapse p-3" id="<?php echo $snippetId; ?>">
                                                    <pre class="code-block m-0"><button class="copy-btn btn btn-sm">Copy</button><code id="code_<?php echo $snippetId; ?>"><?php echo htmlspecialchars($ddl); ?></code></pre>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php
                                    foreach ($diff['missing_in_source']['views'] ?? [] as $key => $view):
                                        $hasView = true;
                                        $snippetId = 'snippet_view_del_' . md5($key);
                                        $ddl = $generator->generateDropView($view);
                                        ?>
                                        <tr class="diff-row-missing-source">
                                            <td class="fw-bold"><?php echo htmlspecialchars($view['name']); ?></td>
                                            <td><code class="text-secondary"><?php echo htmlspecialchars($view['schema']); ?></code></td>
                                            <td><span class="badge badge-missing-source"><i class="bi bi-info-circle"></i> Extra in Target</span></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $snippetId; ?>">View DDL</button>
                                            </td>
                                        </tr>
                                        <tr class="collapse-row bg-light border-0">
                                            <td colspan="4" class="p-0 border-0">
                                                <div class="collapse p-3" id="<?php echo $snippetId; ?>">
                                                    <pre class="code-block m-0"><button class="copy-btn btn btn-sm">Copy</button><code id="code_<?php echo $snippetId; ?>"><?php echo htmlspecialchars($ddl); ?></code></pre>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (!$hasView): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted small">No differences found in views. All views match!</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- PROCEDURES TAB -->
                    <div class="tab-pane fade" id="procedures-pane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="procedures-table">
                                <thead class="table-light text-secondary small">
                                    <tr>
                                        <th>Proc Name</th>
                                        <th>Schema</th>
                                        <th>Status</th>
                                        <th class="text-end">Remediation DDL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $hasProc = false;
                                    foreach ($diff['missing_in_target']['procedures'] ?? [] as $key => $proc):
                                        $hasProc = true;
                                        $snippetId = 'snippet_proc_add_' . md5($key);
                                        $ddl = $generator->generateAddProcedure($proc);
                                        ?>
                                        <tr class="diff-row-missing-target">
                                            <td class="fw-bold"><?php echo htmlspecialchars($proc['name']); ?></td>
                                            <td><code class="text-secondary"><?php echo htmlspecialchars($proc['schema']); ?></code></td>
                                            <td><span class="badge badge-missing-target"><i class="bi bi-plus-circle"></i> Missing Proc</span></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $snippetId; ?>">View DDL</button>
                                            </td>
                                        </tr>
                                        <tr class="collapse-row bg-light border-0">
                                            <td colspan="4" class="p-0 border-0">
                                                <div class="collapse p-3" id="<?php echo $snippetId; ?>">
                                                    <pre class="code-block m-0"><button class="copy-btn btn btn-sm">Copy</button><code id="code_<?php echo $snippetId; ?>"><?php echo htmlspecialchars($ddl); ?></code></pre>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php
                                    foreach ($diff['mismatches']['procedures'] ?? [] as $key => $item):
                                        $hasProc = true;
                                        $src = $item['source'];
                                        $tgt = $item['target'];
                                        $snippetId = 'snippet_proc_mod_' . md5($key);
                                        $ddl = $generator->generateDropProcedure($tgt) . "\nGO\n" . $generator->generateAddProcedure($src);
                                        ?>
                                        <tr class="diff-row-mismatch">
                                            <td class="fw-bold"><?php echo htmlspecialchars($src['name']); ?></td>
                                            <td><code class="text-secondary"><?php echo htmlspecialchars($src['schema']); ?></code></td>
                                            <td><span class="badge badge-mismatch"><i class="bi bi-exclamation-triangle"></i> Mismatched Proc</span></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $snippetId; ?>">View DDL</button>
                                            </td>
                                        </tr>
                                        <tr class="collapse-row bg-light border-0">
                                            <td colspan="4" class="p-0 border-0">
                                                <div class="collapse p-3" id="<?php echo $snippetId; ?>">
                                                    <pre class="code-block m-0"><button class="copy-btn btn btn-sm">Copy</button><code id="code_<?php echo $snippetId; ?>"><?php echo htmlspecialchars($ddl); ?></code></pre>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php
                                    foreach ($diff['missing_in_source']['procedures'] ?? [] as $key => $proc):
                                        $hasProc = true;
                                        $snippetId = 'snippet_proc_del_' . md5($key);
                                        $ddl = $generator->generateDropProcedure($proc);
                                        ?>
                                        <tr class="diff-row-missing-source">
                                            <td class="fw-bold"><?php echo htmlspecialchars($proc['name']); ?></td>
                                            <td><code class="text-secondary"><?php echo htmlspecialchars($proc['schema']); ?></code></td>
                                            <td><span class="badge badge-missing-source"><i class="bi bi-info-circle"></i> Extra in Target</span></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $snippetId; ?>">View DDL</button>
                                            </td>
                                        </tr>
                                        <tr class="collapse-row bg-light border-0">
                                            <td colspan="4" class="p-0 border-0">
                                                <div class="collapse p-3" id="<?php echo $snippetId; ?>">
                                                    <pre class="code-block m-0"><button class="copy-btn btn btn-sm">Copy</button><code id="code_<?php echo $snippetId; ?>"><?php echo htmlspecialchars($ddl); ?></code></pre>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (!$hasProc): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted small">No differences found in stored procedures. All procs match!</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- FUNCTIONS TAB -->
                    <div class="tab-pane fade" id="functions-pane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="functions-table">
                                <thead class="table-light text-secondary small">
                                    <tr>
                                        <th>Function Name</th>
                                        <th>Schema</th>
                                        <th>Status</th>
                                        <th class="text-end">Remediation DDL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $hasFunc = false;
                                    foreach ($diff['missing_in_target']['functions'] ?? [] as $key => $func):
                                        $hasFunc = true;
                                        $snippetId = 'snippet_func_add_' . md5($key);
                                        $ddl = $generator->generateAddFunction($func);
                                        ?>
                                        <tr class="diff-row-missing-target">
                                            <td class="fw-bold"><?php echo htmlspecialchars($func['name']); ?></td>
                                            <td><code class="text-secondary"><?php echo htmlspecialchars($func['schema']); ?></code></td>
                                            <td><span class="badge badge-missing-target"><i class="bi bi-plus-circle"></i> Missing Function</span></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $snippetId; ?>">View DDL</button>
                                            </td>
                                        </tr>
                                        <tr class="collapse-row bg-light border-0">
                                            <td colspan="4" class="p-0 border-0">
                                                <div class="collapse p-3" id="<?php echo $snippetId; ?>">
                                                    <pre class="code-block m-0"><button class="copy-btn btn btn-sm">Copy</button><code id="code_<?php echo $snippetId; ?>"><?php echo htmlspecialchars($ddl); ?></code></pre>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php
                                    foreach ($diff['mismatches']['functions'] ?? [] as $key => $item):
                                        $hasFunc = true;
                                        $src = $item['source'];
                                        $tgt = $item['target'];
                                        $snippetId = 'snippet_func_mod_' . md5($key);
                                        $ddl = $generator->generateDropFunction($tgt) . "\nGO\n" . $generator->generateAddFunction($src);
                                        ?>
                                        <tr class="diff-row-mismatch">
                                            <td class="fw-bold"><?php echo htmlspecialchars($src['name']); ?></td>
                                            <td><code class="text-secondary"><?php echo htmlspecialchars($src['schema']); ?></code></td>
                                            <td><span class="badge badge-mismatch"><i class="bi bi-exclamation-triangle"></i> Mismatched Function</span></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $snippetId; ?>">View DDL</button>
                                            </td>
                                        </tr>
                                        <tr class="collapse-row bg-light border-0">
                                            <td colspan="4" class="p-0 border-0">
                                                <div class="collapse p-3" id="<?php echo $snippetId; ?>">
                                                    <pre class="code-block m-0"><button class="copy-btn btn btn-sm">Copy</button><code id="code_<?php echo $snippetId; ?>"><?php echo htmlspecialchars($ddl); ?></code></pre>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php
                                    foreach ($diff['missing_in_source']['functions'] ?? [] as $key => $func):
                                        $hasFunc = true;
                                        $snippetId = 'snippet_func_del_' . md5($key);
                                        $ddl = $generator->generateDropFunction($func);
                                        ?>
                                        <tr class="diff-row-missing-source">
                                            <td class="fw-bold"><?php echo htmlspecialchars($func['name']); ?></td>
                                            <td><code class="text-secondary"><?php echo htmlspecialchars($func['schema']); ?></code></td>
                                            <td><span class="badge badge-missing-source"><i class="bi bi-info-circle"></i> Extra in Target</span></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $snippetId; ?>">View DDL</button>
                                            </td>
                                        </tr>
                                        <tr class="collapse-row bg-light border-0">
                                            <td colspan="4" class="p-0 border-0">
                                                <div class="collapse p-3" id="<?php echo $snippetId; ?>">
                                                    <pre class="code-block m-0"><button class="copy-btn btn btn-sm">Copy</button><code id="code_<?php echo $snippetId; ?>"><?php echo htmlspecialchars($ddl); ?></code></pre>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (!$hasFunc): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted small">No differences found in user-defined functions. All functions match!</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Dynamic JS filter for all tab tables
    const filterInput = document.getElementById('tableFilter');
    filterInput.addEventListener('keyup', () => {
        const query = filterInput.value.toLowerCase().trim();
        const activePane = document.querySelector('.tab-pane.active');
        const rows = activePane.querySelectorAll('tbody tr:not(.collapse-row)');
        
        rows.forEach(row => {
            const cells = row.getElementsByTagName('td');
            let match = false;
            
            // Search name (cell 0) or schema/table (cell 1)
            if (cells[0] && cells[0].textContent.toLowerCase().includes(query)) match = true;
            if (cells[1] && cells[1].textContent.toLowerCase().includes(query)) match = true;
            
            if (match) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
                // Also close corresponding open DDL block if search hides it
                const nextRow = row.nextElementSibling;
                if (nextRow && nextRow.classList.contains('collapse-row')) {
                    const collapseDiv = nextRow.querySelector('.collapse');
                    if (collapseDiv && collapseDiv.classList.contains('show')) {
                        // Use bootstrap programmatic collapse hide or just hide
                        collapseDiv.classList.remove('show');
                    }
                }
            }
        });
    });

    // Reset input on tab change
    const tabs = document.querySelectorAll('button[data-bs-toggle="tab"]');
    tabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', () => {
            filterInput.value = '';
            const allRows = document.querySelectorAll('tbody tr');
            allRows.forEach(r => r.style.display = '');
        });
    });
});
</script>

<?php
renderFooter();
?>
