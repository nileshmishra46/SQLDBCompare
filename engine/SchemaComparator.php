<?php

class SchemaComparator {
    private array $settings;

    public function __construct(array $settings = []) {
        $this->settings = array_merge([
            'comparison_ignore_case' => true,
            'show_source_extras'     => true
        ], $settings);
    }

    /**
     * Compares source and target schemas.
     */
    public function compare(array $source, array $target): array {
        $diff = [
            'missing_in_target' => [
                'tables'              => [],
                'columns'             => [],
                'primary_keys'        => [],
                'foreign_keys'        => [],
                'unique_constraints'  => [],
                'check_constraints'   => [],
                'default_constraints' => [],
                'indexes'             => [],
                'triggers'            => [],
                'views'               => [],
                'procedures'          => [],
                'functions'           => [],
            ],
            'missing_in_source' => [
                'tables'              => [],
                'columns'             => [],
                'primary_keys'        => [],
                'foreign_keys'        => [],
                'unique_constraints'  => [],
                'check_constraints'   => [],
                'default_constraints' => [],
                'indexes'             => [],
                'triggers'            => [],
                'views'               => [],
                'procedures'          => [],
                'functions'           => [],
            ],
            'mismatches' => [
                'columns'             => [],
                'primary_keys'        => [],
                'foreign_keys'        => [],
                'unique_constraints'  => [],
                'check_constraints'   => [],
                'default_constraints' => [],
                'indexes'             => [],
                'triggers'            => [],
                'views'               => [],
                'procedures'          => [],
                'functions'           => [],
            ]
        ];

        // 1. Compare Tables
        $this->compareTables($source['tables'], $target['tables'], $diff);

        // 2. Compare Columns
        $this->compareColumns($source['columns'], $target['columns'], $source['tables'], $target['tables'], $diff);

        // 3. Compare Primary Keys
        $this->comparePrimaryKeys($source['primary_keys'], $target['primary_keys'], $source['tables'], $target['tables'], $diff);

        // 4. Compare Foreign Keys
        $this->compareForeignKeys($source['foreign_keys'], $target['foreign_keys'], $source['tables'], $target['tables'], $diff);

        // 5. Compare Unique Constraints
        $this->compareUniqueConstraints($source['unique_constraints'], $target['unique_constraints'], $source['tables'], $target['tables'], $diff);

        // 6. Compare Check Constraints
        $this->compareCheckConstraints($source['check_constraints'], $target['check_constraints'], $source['tables'], $target['tables'], $diff);

        // 7. Compare Default Constraints
        $this->compareDefaultConstraints($source['default_constraints'], $target['default_constraints'], $source['tables'], $target['tables'], $diff);

        // 8. Compare Indexes
        $this->compareIndexes($source['indexes'], $target['indexes'], $source['tables'], $target['tables'], $diff);

        // 9. Compare Triggers
        $this->compareTriggers($source['triggers'], $target['triggers'], $source['tables'], $target['tables'], $diff);

        // 10. Compare Views
        $this->compareViews($source['views'] ?? [], $target['views'] ?? [], $diff);

        // 11. Compare Procedures
        $this->compareProcedures($source['procedures'] ?? [], $target['procedures'] ?? [], $diff);

        // 12. Compare Functions
        $this->compareFunctions($source['functions'] ?? [], $target['functions'] ?? [], $diff);

        return $diff;
    }

    private function equalNames(string $a, string $b): bool {
        if ($this->settings['comparison_ignore_case']) {
            return strcasecmp(trim($a), trim($b)) === 0;
        }
        return trim($a) === trim($b);
    }

    private function normalizeExpression(?string $expr): ?string {
        if ($expr === null) {
            return null;
        }
        // Recursively strip outer parentheses, e.g. ((0)) -> 0, (getdate()) -> getdate()
        $expr = trim($expr);
        while (strlen($expr) > 2 && $expr[0] === '(' && substr($expr, -1) === ')') {
            // Ensure they are a matching pair by matching depth
            $depth = 0;
            $matched = true;
            $len = strlen($expr);
            for ($i = 0; $i < $len - 1; $i++) {
                if ($expr[$i] === '(') $depth++;
                elseif ($expr[$i] === ')') $depth--;
                if ($depth === 0 && $i > 0) {
                    $matched = false;
                    break;
                }
            }
            if ($matched && $depth === 1 && $expr[$len - 1] === ')') {
                $expr = trim(substr($expr, 1, -1));
            } else {
                break;
            }
        }
        // Strip square brackets to avoid naming style mismatch: [dbo].[table] vs dbo.table
        $expr = str_replace(['[', ']'], '', $expr);
        // Normalize multiple spaces into single space
        return preg_replace('/\s+/', ' ', strtolower($expr));
    }

    private function compareTables(array $src, array $tgt, array &$diff): void {
        foreach ($src as $key => $table) {
            if (!isset($tgt[$key])) {
                $diff['missing_in_target']['tables'][$key] = $table;
            }
        }

        if ($this->settings['show_source_extras']) {
            foreach ($tgt as $key => $table) {
                if (!isset($src[$key])) {
                    $diff['missing_in_source']['tables'][$key] = $table;
                }
            }
        }
    }

    private function compareColumns(array $src, array $tgt, array $srcTables, array $tgtTables, array &$diff): void {
        foreach ($src as $key => $col) {
            $tableKey = strtolower($col['schema'] . '.' . $col['table']);
            // Skip columns of tables that are missing entirely in target
            if (isset($diff['missing_in_target']['tables'][$tableKey])) {
                continue;
            }

            if (!isset($tgt[$key])) {
                $diff['missing_in_target']['columns'][$key] = $col;
                continue;
            }

            $tgtCol = $tgt[$key];
            $mismatches = [];

            if (!$this->equalNames($col['type'], $tgtCol['type'])) {
                $mismatches['type'] = ['src' => $col['type'], 'tgt' => $tgtCol['type']];
            }

            if ($col['length'] !== $tgtCol['length']) {
                $mismatches['length'] = ['src' => $col['length'], 'tgt' => $tgtCol['length']];
            }

            if ($col['precision'] !== $tgtCol['precision']) {
                $mismatches['precision'] = ['src' => $col['precision'], 'tgt' => $tgtCol['precision']];
            }

            if ($col['scale'] !== $tgtCol['scale']) {
                $mismatches['scale'] = ['src' => $col['scale'], 'tgt' => $tgtCol['scale']];
            }

            if ($col['nullable'] !== $tgtCol['nullable']) {
                $mismatches['nullable'] = ['src' => $col['nullable'], 'tgt' => $tgtCol['nullable']];
            }

            $srcDef = $this->normalizeExpression($col['column_default']);
            $tgtDef = $this->normalizeExpression($tgtCol['column_default']);
            if ($srcDef !== $tgtDef) {
                $mismatches['default'] = ['src' => $col['column_default'], 'tgt' => $tgtCol['column_default']];
            }

            if (!empty($mismatches)) {
                $diff['mismatches']['columns'][$key] = [
                    'source'     => $col,
                    'target'     => $tgtCol,
                    'mismatches' => $mismatches
                ];
            }
        }

        if ($this->settings['show_source_extras']) {
            foreach ($tgt as $key => $col) {
                $tableKey = strtolower($col['schema'] . '.' . $col['table']);
                if (isset($diff['missing_in_source']['tables'][$tableKey])) {
                    continue;
                }
                if (!isset($src[$key])) {
                    $diff['missing_in_source']['columns'][$key] = $col;
                }
            }
        }
    }

    private function comparePrimaryKeys(array $src, array $tgt, array $srcTables, array $tgtTables, array &$diff): void {
        // Find PK by matching tables since table names are unique, even if constraint names differ.
        // We will map PKs by table name first.
        $srcPkByTable = [];
        foreach ($src as $key => $pk) {
            $tableKey = strtolower($pk['schema'] . '.' . $pk['table']);
            $srcPkByTable[$tableKey] = $pk;
        }

        $tgtPkByTable = [];
        foreach ($tgt as $key => $pk) {
            $tableKey = strtolower($pk['schema'] . '.' . $pk['table']);
            $tgtPkByTable[$tableKey] = $pk;
        }

        foreach ($srcPkByTable as $tableKey => $pk) {
            if (isset($diff['missing_in_target']['tables'][$tableKey])) {
                continue;
            }

            if (!isset($tgtPkByTable[$tableKey])) {
                $diff['missing_in_target']['primary_keys'][$tableKey] = $pk;
                continue;
            }

            $tgtPk = $tgtPkByTable[$tableKey];
            // Normalize column lists for comparison (ignoring case of column names if configured)
            $srcCols = $pk['columns'];
            $tgtCols = $tgtPk['columns'];
            
            $mismatch = false;
            if (count($srcCols) !== count($tgtCols)) {
                $mismatch = true;
            } else {
                foreach ($srcCols as $i => $col) {
                    if (!$this->equalNames($col, $tgtCols[$i])) {
                        $mismatch = true;
                        break;
                    }
                }
            }

            if ($mismatch) {
                $diff['mismatches']['primary_keys'][$tableKey] = [
                    'source' => $pk,
                    'target' => $tgtPk
                ];
            }
        }

        if ($this->settings['show_source_extras']) {
            foreach ($tgtPkByTable as $tableKey => $pk) {
                if (isset($diff['missing_in_source']['tables'][$tableKey])) {
                    continue;
                }
                if (!isset($srcPkByTable[$tableKey])) {
                    $diff['missing_in_source']['primary_keys'][$tableKey] = $pk;
                }
            }
        }
    }

    private function compareForeignKeys(array $src, array $tgt, array $srcTables, array $tgtTables, array &$diff): void {
        foreach ($src as $key => $fk) {
            $tableKey = strtolower($fk['schema'] . '.' . $fk['table']);
            if (isset($diff['missing_in_target']['tables'][$tableKey])) {
                continue;
            }

            if (!isset($tgt[$key])) {
                $diff['missing_in_target']['foreign_keys'][$key] = $fk;
                continue;
            }

            $tgtFk = $tgt[$key];
            $mismatch = false;

            // Check columns
            if (count($fk['columns']) !== count($tgtFk['columns']) || count($fk['ref_columns']) !== count($tgtFk['ref_columns'])) {
                $mismatch = true;
            } else {
                foreach ($fk['columns'] as $i => $col) {
                    if (!$this->equalNames($col, $tgtFk['columns'][$i])) {
                        $mismatch = true;
                        break;
                    }
                }
                foreach ($fk['ref_columns'] as $i => $col) {
                    if (!$this->equalNames($col, $tgtFk['ref_columns'][$i])) {
                        $mismatch = true;
                        break;
                    }
                }
            }

            // Check referenced table
            if (!$this->equalNames($fk['ref_schema'], $tgtFk['ref_schema']) || !$this->equalNames($fk['ref_table'], $tgtFk['ref_table'])) {
                $mismatch = true;
            }

            // Check actions
            // Normalize actions (replace underscores with spaces)
            $srcDel = str_replace('_', ' ', $fk['on_delete']);
            $tgtDel = str_replace('_', ' ', $tgtFk['on_delete']);
            $srcUpd = str_replace('_', ' ', $fk['on_update']);
            $tgtUpd = str_replace('_', ' ', $tgtFk['on_update']);

            if (strcasecmp($srcDel, $tgtDel) !== 0 || strcasecmp($srcUpd, $tgtUpd) !== 0) {
                $mismatch = true;
            }

            if ($mismatch) {
                $diff['mismatches']['foreign_keys'][$key] = [
                    'source' => $fk,
                    'target' => $tgtFk
                ];
            }
        }

        if ($this->settings['show_source_extras']) {
            foreach ($tgt as $key => $fk) {
                $tableKey = strtolower($fk['schema'] . '.' . $fk['table']);
                if (isset($diff['missing_in_source']['tables'][$tableKey])) {
                    continue;
                }
                if (!isset($src[$key])) {
                    $diff['missing_in_source']['foreign_keys'][$key] = $fk;
                }
            }
        }
    }

    private function compareUniqueConstraints(array $src, array $tgt, array $srcTables, array $tgtTables, array &$diff): void {
        foreach ($src as $key => $uc) {
            $tableKey = strtolower($uc['schema'] . '.' . $uc['table']);
            if (isset($diff['missing_in_target']['tables'][$tableKey])) {
                continue;
            }

            if (!isset($tgt[$key])) {
                $diff['missing_in_target']['unique_constraints'][$key] = $uc;
                continue;
            }

            $tgtUc = $tgt[$key];
            $mismatch = false;

            if (count($uc['columns']) !== count($tgtUc['columns'])) {
                $mismatch = true;
            } else {
                foreach ($uc['columns'] as $i => $col) {
                    if (!$this->equalNames($col, $tgtUc['columns'][$i])) {
                        $mismatch = true;
                        break;
                    }
                }
            }

            if ($mismatch) {
                $diff['mismatches']['unique_constraints'][$key] = [
                    'source' => $uc,
                    'target' => $tgtUc
                ];
            }
        }

        if ($this->settings['show_source_extras']) {
            foreach ($tgt as $key => $uc) {
                $tableKey = strtolower($uc['schema'] . '.' . $uc['table']);
                if (isset($diff['missing_in_source']['tables'][$tableKey])) {
                    continue;
                }
                if (!isset($src[$key])) {
                    $diff['missing_in_source']['unique_constraints'][$key] = $uc;
                }
            }
        }
    }

    private function compareCheckConstraints(array $src, array $tgt, array $srcTables, array $tgtTables, array &$diff): void {
        foreach ($src as $key => $cc) {
            $tableKey = strtolower($cc['schema'] . '.' . $cc['table']);
            if (isset($diff['missing_in_target']['tables'][$tableKey])) {
                continue;
            }

            if (!isset($tgt[$key])) {
                $diff['missing_in_target']['check_constraints'][$key] = $cc;
                continue;
            }

            $tgtCc = $tgt[$key];
            $srcDef = $this->normalizeExpression($cc['definition']);
            $tgtDef = $this->normalizeExpression($tgtCc['definition']);

            if ($srcDef !== $tgtDef) {
                $diff['mismatches']['check_constraints'][$key] = [
                    'source' => $cc,
                    'target' => $tgtCc
                ];
            }
        }

        if ($this->settings['show_source_extras']) {
            foreach ($tgt as $key => $cc) {
                $tableKey = strtolower($cc['schema'] . '.' . $cc['table']);
                if (isset($diff['missing_in_source']['tables'][$tableKey])) {
                    continue;
                }
                if (!isset($src[$key])) {
                    $diff['missing_in_source']['check_constraints'][$key] = $cc;
                }
            }
        }
    }

    private function compareDefaultConstraints(array $src, array $tgt, array $srcTables, array $tgtTables, array &$diff): void {
        foreach ($src as $key => $dc) {
            $tableKey = strtolower($dc['schema'] . '.' . $dc['table']);
            if (isset($diff['missing_in_target']['tables'][$tableKey])) {
                continue;
            }

            if (!isset($tgt[$key])) {
                // Check if it's already identified as a column default mismatch to avoid double alerting
                $colKey = strtolower($dc['schema'] . '.' . $dc['table'] . '.' . $dc['column']);
                if (!isset($diff['missing_in_target']['columns'][$colKey])) {
                    $diff['missing_in_target']['default_constraints'][$key] = $dc;
                }
                continue;
            }

            $tgtDc = $tgt[$key];
            $srcDef = $this->normalizeExpression($dc['definition']);
            $tgtDef = $this->normalizeExpression($tgtDc['definition']);

            if ($srcDef !== $tgtDef || !$this->equalNames($dc['column'], $tgtDc['column'])) {
                $diff['mismatches']['default_constraints'][$key] = [
                    'source' => $dc,
                    'target' => $tgtDc
                ];
            }
        }

        if ($this->settings['show_source_extras']) {
            foreach ($tgt as $key => $dc) {
                $tableKey = strtolower($dc['schema'] . '.' . $dc['table']);
                if (isset($diff['missing_in_source']['tables'][$tableKey])) {
                    continue;
                }
                if (!isset($src[$key])) {
                    $diff['missing_in_source']['default_constraints'][$key] = $dc;
                }
            }
        }
    }

    private function compareIndexes(array $src, array $tgt, array $srcTables, array $tgtTables, array &$diff): void {
        foreach ($src as $key => $idx) {
            $tableKey = strtolower($idx['schema'] . '.' . $idx['table']);
            if (isset($diff['missing_in_target']['tables'][$tableKey])) {
                continue;
            }

            if (!isset($tgt[$key])) {
                $diff['missing_in_target']['indexes'][$key] = $idx;
                continue;
            }

            $tgtIdx = $tgt[$key];
            $mismatch = false;

            // Check columns list
            if (count($idx['columns']) !== count($tgtIdx['columns'])) {
                $mismatch = true;
            } else {
                foreach ($idx['columns'] as $i => $col) {
                    if (!$this->equalNames($col, $tgtIdx['columns'][$i])) {
                        $mismatch = true;
                        break;
                    }
                }
            }

            // Check included columns
            if (count($idx['included_columns']) !== count($tgtIdx['included_columns'])) {
                $mismatch = true;
            } else {
                foreach ($idx['included_columns'] as $i => $col) {
                    if (!$this->equalNames($col, $tgtIdx['included_columns'][$i])) {
                        $mismatch = true;
                        break;
                    }
                }
            }

            // Check other flags
            if ($idx['is_unique'] !== $tgtIdx['is_unique'] || strcasecmp($idx['type'], $tgtIdx['type']) !== 0) {
                $mismatch = true;
            }

            // Check filter
            $srcFilter = $this->normalizeExpression($idx['filter']);
            $tgtFilter = $this->normalizeExpression($tgtIdx['filter']);
            if ($srcFilter !== $tgtFilter) {
                $mismatch = true;
            }

            if ($mismatch) {
                $diff['mismatches']['indexes'][$key] = [
                    'source' => $idx,
                    'target' => $tgtIdx
                ];
            }
        }

        if ($this->settings['show_source_extras']) {
            foreach ($tgt as $key => $idx) {
                $tableKey = strtolower($idx['schema'] . '.' . $idx['table']);
                if (isset($diff['missing_in_source']['tables'][$tableKey])) {
                    continue;
                }
                if (!isset($src[$key])) {
                    $diff['missing_in_source']['indexes'][$key] = $idx;
                }
            }
        }
    }

    private function compareTriggers(array $src, array $tgt, array $srcTables, array $tgtTables, array &$diff): void {
        foreach ($src as $key => $trg) {
            $tableKey = strtolower($trg['schema'] . '.' . $trg['table']);
            if (isset($diff['missing_in_target']['tables'][$tableKey])) {
                continue;
            }

            if (!isset($tgt[$key])) {
                $diff['missing_in_target']['triggers'][$key] = $trg;
                continue;
            }

            $tgtTrg = $tgt[$key];
            $mismatch = false;

            // Check disabled
            if ($trg['disabled'] !== $tgtTrg['disabled']) {
                $mismatch = true;
            }

            // Check definition (normalize whitespace, comments, casing)
            $srcBody = $this->normalizeTriggerDefinition($trg['definition']);
            $tgtBody = $this->normalizeTriggerDefinition($tgtTrg['definition']);

            if ($srcBody !== $tgtBody) {
                $mismatch = true;
            }

            if ($mismatch) {
                $diff['mismatches']['triggers'][$key] = [
                    'source' => $trg,
                    'target' => $tgtTrg
                ];
            }
        }

        if ($this->settings['show_source_extras']) {
            foreach ($tgt as $key => $trg) {
                $tableKey = strtolower($trg['schema'] . '.' . $trg['table']);
                if (isset($diff['missing_in_source']['tables'][$tableKey])) {
                    continue;
                }
                if (!isset($src[$key])) {
                    $diff['missing_in_source']['triggers'][$key] = $trg;
                }
            }
        }
    }

    private function normalizeTriggerDefinition(?string $def): string {
        if ($def === null) {
            return '';
        }
        
        // Strip block comments and standard formatting differences
        $def = preg_replace('/(?:\/\*(?:[^*]|\*(?!\/))*\*\/)/s', '', $def);
        
        // Split by lines and remove single-line comments
        $lines = explode("\n", $def);
        foreach ($lines as &$line) {
            if (($pos = strpos($line, '--')) !== false) {
                $line = substr($line, 0, $pos);
            }
        }
        $def = implode(" ", $lines);
        
        // Replace all whitespace sequences with a single space
        $def = preg_replace('/\s+/', ' ', $def);
        
        // Strip bracket formatting for tables/columns
        $def = str_replace(['[', ']'], '', $def);
        
        return strtolower(trim($def));
    }

    private function compareViews(array $src, array $tgt, array &$diff): void {
        foreach ($src as $key => $view) {
            if (!isset($tgt[$key])) {
                $diff['missing_in_target']['views'][$key] = $view;
                continue;
            }

            $tgtView = $tgt[$key];
            $srcBody = $this->normalizeTriggerDefinition($view['definition']);
            $tgtBody = $this->normalizeTriggerDefinition($tgtView['definition']);

            if ($srcBody !== $tgtBody) {
                $diff['mismatches']['views'][$key] = [
                    'source' => $view,
                    'target' => $tgtView
                ];
            }
        }

        if ($this->settings['show_source_extras']) {
            foreach ($tgt as $key => $view) {
                if (!isset($src[$key])) {
                    $diff['missing_in_source']['views'][$key] = $view;
                }
            }
        }
    }

    private function compareProcedures(array $src, array $tgt, array &$diff): void {
        foreach ($src as $key => $proc) {
            if (!isset($tgt[$key])) {
                $diff['missing_in_target']['procedures'][$key] = $proc;
                continue;
            }

            $tgtProc = $tgt[$key];
            $srcBody = $this->normalizeTriggerDefinition($proc['definition']);
            $tgtBody = $this->normalizeTriggerDefinition($tgtProc['definition']);

            if ($srcBody !== $tgtBody) {
                $diff['mismatches']['procedures'][$key] = [
                    'source' => $proc,
                    'target' => $tgtProc
                ];
            }
        }

        if ($this->settings['show_source_extras']) {
            foreach ($tgt as $key => $proc) {
                if (!isset($src[$key])) {
                    $diff['missing_in_source']['procedures'][$key] = $proc;
                }
            }
        }
    }

    private function compareFunctions(array $src, array $tgt, array &$diff): void {
        foreach ($src as $key => $func) {
            if (!isset($tgt[$key])) {
                $diff['missing_in_target']['functions'][$key] = $func;
                continue;
            }

            $tgtFunc = $tgt[$key];
            $srcBody = $this->normalizeTriggerDefinition($func['definition']);
            $tgtBody = $this->normalizeTriggerDefinition($tgtFunc['definition']);

            if ($srcBody !== $tgtBody) {
                $diff['mismatches']['functions'][$key] = [
                    'source' => $func,
                    'target' => $tgtFunc
                ];
            }
        }

        if ($this->settings['show_source_extras']) {
            foreach ($tgt as $key => $func) {
                if (!isset($src[$key])) {
                    $diff['missing_in_source']['functions'][$key] = $func;
                }
            }
        }
    }
}
