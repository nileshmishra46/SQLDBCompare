<?php

class SqlFileParser {
    
    /**
     * Parses the SQL content of a DDL file and normalizes it.
     */
    public function parse(string $sqlContent): array {
        $sqlContent = $this->stripComments($sqlContent);
        
        // Normalize line endings and split by GO boundaries
        $batches = preg_split('/^\s*GO\s*$/im', $sqlContent);
        
        $schema = [
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
        ];
        
        foreach ($batches as $batch) {
            $batch = trim($batch);
            if (empty($batch)) {
                continue;
            }
            
            // Clean up semicolons at the end of statements if present
            $statements = $this->splitStatements($batch);
            
            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if (empty($stmt)) {
                    continue;
                }
                
                $this->parseStatement($stmt, $schema);
            }
        }
        
        return $schema;
    }
    
    /**
     * Strips single-line (--) and multi-line (/* * /) comments.
     */
    private function stripComments(string $sql): string {
        // Strip block comments
        $sql = preg_replace('/Matching regex block comments.../s', '', $sql); // Will write actual regex below
        // Let's use standard regex for comments:
        // Multi-line: /\/\*(.*?)\*\//s
        // Single-line: /--.*/
        $sql = preg_replace('/(?:\/\*(?:[^*]|\*(?!\/))*\*\/)/s', '', $sql);
        $lines = explode("\n", $sql);
        foreach ($lines as &$line) {
            // Remove single-line comments but protect URL-like or string patterns if simple
            if (($pos = strpos($line, '--')) !== false) {
                $line = substr($line, 0, $pos);
            }
        }
        return implode("\n", $lines);
    }
    
    /**
     * Splits statements on semicolon boundaries, unless they are within parenthesis or AS blocks.
     * (We'll do a simple split, but ignore semicolons inside BEGIN...END blocks for triggers if needed)
     */
    private function splitStatements(string $batch): array {
        // If it starts with CREATE TRIGGER/VIEW/PROCEDURE/FUNCTION, do not split it because it contains internal semicolons
        if (preg_match('/^\s*CREATE\s+(?:TRIGGER|VIEW|PROCEDURE|PROC|FUNCTION)\b/i', $batch)) {
            return [$batch];
        }
        
        // Otherwise, split by semicolons outside parenthesis
        $statements = [];
        $current = '';
        $depth = 0;
        $len = strlen($batch);
        for ($i = 0; $i < $len; $i++) {
            $char = $batch[$i];
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }
            
            if ($char === ';' && $depth === 0) {
                $statements[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }
        if (trim($current) !== '') {
            $statements[] = trim($current);
        }
        return $statements;
    }
    
    private function parseStatement(string $stmt, array &$schema): void {
        // Check statement type by keyword
        if (preg_match('/^\s*CREATE\s+TABLE/i', $stmt)) {
            $this->parseCreateTable($stmt, $schema);
        } elseif (preg_match('/^\s*CREATE\s+(?:UNIQUE\s+)?(?:(?:CLUSTERED|NONCLUSTERED)\s+)?INDEX/i', $stmt)) {
            $this->parseCreateIndex($stmt, $schema);
        } elseif (preg_match('/^\s*ALTER\s+TABLE\s+\S+\s+ADD\s+CONSTRAINT/i', $stmt)) {
            $this->parseAlterTableAddConstraint($stmt, $schema);
        } elseif (preg_match('/^\s*CREATE\s+TRIGGER/i', $stmt)) {
            $this->parseCreateTrigger($stmt, $schema);
        } elseif (preg_match('/^\s*CREATE\s+VIEW/i', $stmt)) {
            $this->parseCreateView($stmt, $schema);
        } elseif (preg_match('/^\s*CREATE\s+(?:PROCEDURE|PROC)\b/i', $stmt)) {
            $this->parseCreateProcedure($stmt, $schema);
        } elseif (preg_match('/^\s*CREATE\s+FUNCTION/i', $stmt)) {
            $this->parseCreateFunction($stmt, $schema);
        }
    }
    
    private function cleanName(string $name): string {
        $name = trim(str_replace(['[', ']'], '', $name));
        return preg_replace('/\s+(?:ASC|DESC)\b/i', '', $name);
    }
    
    private function parseCreateTable(string $stmt, array &$schema): void {
        // Extract table name
        if (!preg_match('/CREATE\s+TABLE\s+(?:(\[?\w+\]?)\.)?(\[?\w+\]?)/i', $stmt, $matches)) {
            return;
        }
        
        $tableSchema = !empty($matches[1]) ? $this->cleanName($matches[1]) : 'dbo';
        $tableName = $this->cleanName($matches[2]);
        $tableKey = strtolower($tableSchema . '.' . $tableName);
        
        $schema['tables'][$tableKey] = [
            'schema' => $tableSchema,
            'name'   => $tableName
        ];
        
        // Extract outer body content
        $body = $this->extractOuterParentheses($stmt);
        if ($body === null) {
            return;
        }
        
        $parts = $this->splitByCommaOutsideParentheses($body);
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }
            
            // Check if it's a table-level constraint
            if (preg_match('/^(?:CONSTRAINT\s+(\[?\w+\]?)\s+)?(PRIMARY\s+KEY|FOREIGN\s+KEY|UNIQUE|CHECK|DEFAULT)\b/i', $part, $cMatches)) {
                $cName = !empty($cMatches[1]) ? $this->cleanName($cMatches[1]) : 'DF_' . uniqid();
                $cType = strtoupper($cMatches[2]);
                
                $this->parseInlineTableConstraint($part, $cName, $cType, $tableSchema, $tableName, $schema);
            } else {
                // Otherwise it is a column definition
                $this->parseColumnDefinition($part, $tableSchema, $tableName, $schema);
            }
        }
    }
    
    private function parseInlineTableConstraint(string $part, string $cName, string $cType, string $tableSchema, string $tableName, array &$schema): void {
        $key = strtolower($tableSchema . '.' . $cName);
        
        if ($cType === 'PRIMARY KEY') {
            if (preg_match('/PRIMARY\s+KEY\s*(?:CLUSTERED|NONCLUSTERED)?\s*\((.*?)\)/i', $part, $m)) {
                $cols = array_map([$this, 'cleanName'], explode(',', $m[1]));
                // Standard PK key uses table path for safety matching
                $pkKey = strtolower($tableSchema . '.' . $tableName . '.' . $cName);
                $schema['primary_keys'][$pkKey] = [
                    'schema'  => $tableSchema,
                    'table'   => $tableName,
                    'name'    => $cName,
                    'columns' => $cols
                ];
            }
        } elseif ($cType === 'FOREIGN KEY') {
            if (preg_match('/FOREIGN\s+KEY\s*\((.*?)\)\s*REFERENCES\s+(?:(\[?\w+\]?)\.)?(\[?\w+\]?)\s*\((.*?)\)(?:\s+ON\s+DELETE\s+(CASCADE|SET\s+NULL|SET\s+DEFAULT|NO\s+ACTION))?(?:\s+ON\s+UPDATE\s+(CASCADE|SET\s+NULL|SET\s+DEFAULT|NO\s+ACTION))?/i', $part, $m)) {
                $cols = array_map([$this, 'cleanName'], explode(',', $m[1]));
                $refSch = !empty($m[2]) ? $this->cleanName($m[2]) : 'dbo';
                $refTab = $this->cleanName($m[3]);
                $refCols = array_map([$this, 'cleanName'], explode(',', $m[4]));
                $onDelete = !empty($m[5]) ? strtoupper($m[5]) : 'NO_ACTION';
                $onUpdate = !empty($m[6]) ? strtoupper($m[6]) : 'NO_ACTION';
                
                $schema['foreign_keys'][$key] = [
                    'schema'      => $tableSchema,
                    'table'       => $tableName,
                    'name'        => $cName,
                    'columns'     => $cols,
                    'ref_schema'  => $refSch,
                    'ref_table'   => $refTab,
                    'ref_columns' => $refCols,
                    'on_delete'   => $onDelete,
                    'on_update'   => $onUpdate
                ];
            }
        } elseif ($cType === 'UNIQUE') {
            if (preg_match('/UNIQUE\s*(?:CLUSTERED|NONCLUSTERED)?\s*\((.*?)\)/i', $part, $m)) {
                $cols = array_map([$this, 'cleanName'], explode(',', $m[1]));
                $schema['unique_constraints'][$key] = [
                    'schema'  => $tableSchema,
                    'table'   => $tableName,
                    'name'    => $cName,
                    'columns' => $cols
                ];
            }
        } elseif ($cType === 'CHECK') {
            // Find everything between the outer parentheses of CHECK (...)
            $chkBody = $this->extractOuterParentheses($part);
            if ($chkBody) {
                $schema['check_constraints'][$key] = [
                    'schema'     => $tableSchema,
                    'table'      => $tableName,
                    'name'       => $cName,
                    'definition' => '(' . $chkBody . ')'
                ];
            }
        }
    }
    
    private function parseColumnDefinition(string $part, string $tableSchema, string $tableName, array &$schema): void {
        // e.g. [Id] INT IDENTITY(1,1) NOT NULL
        // Split by whitespace, but protect types like VARCHAR(50) or DECIMAL(18, 2)
        // Find first word as column name
        if (!preg_match('/^(\[?\w+\]?)\s+(\w+)(?:\((.*?)\))?/i', $part, $matches)) {
            return;
        }
        
        $colName = $this->cleanName($matches[1]);
        $dataType = strtoupper($matches[2]);
        $paramStr = !empty($matches[3]) ? trim($matches[3]) : null;
        
        $length = null;
        $precision = null;
        $scale = null;
        
        if ($paramStr !== null) {
            $params = explode(',', $paramStr);
            if (count($params) === 2) {
                $precision = (int)trim($params[0]);
                $scale = (int)trim($params[1]);
            } elseif (count($params) === 1) {
                $val = trim($params[0]);
                if (strcasecmp($val, 'max') === 0) {
                    $length = -1;
                } else {
                    $length = (int)$val;
                }
            }
        }
        
        // Nullable check
        $nullable = true;
        if (preg_match('/\bNOT\s+NULL\b/i', $part)) {
            $nullable = false;
        } elseif (preg_match('/\bNULL\b/i', $part)) {
            $nullable = true;
        }
        
        // Inline default constraint
        $defaultDef = null;
        if (preg_match('/\bDEFAULT\b\s+(.*)/i', $part, $defaultMatches)) {
            // Read until the end of the line, or before another keyword (like NOT NULL if it happened to be placed after DEFAULT)
            $defRaw = trim($defaultMatches[1]);
            
            // To isolate the default expression, search for brackets or parenthesis
            // Standard DEFAULT expression is usually bracketed or parenthesized like ((0)) or (getdate())
            // If the default expression is followed by comma/newline/etc, we handle it
            // We can match parenthesized expression
            $depth = 0;
            $expr = '';
            for ($i = 0; $i < strlen($defRaw); $i++) {
                $char = $defRaw[$i];
                if ($char === '(') {
                    $depth++;
                } elseif ($char === ')') {
                    $depth--;
                }
                $expr .= $char;
                if ($depth === 0 && $char === ')') {
                    break;
                }
                // Break if we hit key columns constraints
                if ($depth === 0 && preg_match('/^\s*(?:NOT\s+NULL|NULL|IDENTITY|PRIMARY|CONSTRAINT)/i', substr($defRaw, $i))) {
                    $expr = substr($expr, 0, -$i);
                    break;
                }
            }
            $defaultDef = trim($expr);
            if (empty($defaultDef)) {
                // If not parenthesized, capture the first token
                $tokens = preg_split('/\s+/', $defRaw);
                $defaultDef = $tokens[0] ?? null;
            }
        }
        
        $colKey = strtolower($tableSchema . '.' . $tableName . '.' . $colName);
        $schema['columns'][$colKey] = [
            'schema'         => $tableSchema,
            'table'          => $tableName,
            'name'           => $colName,
            'type'           => $dataType,
            'length'         => $length,
            'precision'      => $precision,
            'scale'          => $scale,
            'nullable'       => $nullable,
            'column_default' => $defaultDef
        ];
        
        // If an inline default was found, we add it to default_constraints as well
        // for uniformity in comparison (so we can generate ALTER TABLE ADD CONSTRAINT DEFAULT)
        if ($defaultDef !== null) {
            $dfName = 'DF_' . $tableName . '_' . $colName;
            $dfKey = strtolower($tableSchema . '.' . $dfName);
            $schema['default_constraints'][$dfKey] = [
                'schema'     => $tableSchema,
                'table'      => $tableName,
                'name'       => $dfName,
                'column'     => $colName,
                'definition' => $defaultDef
            ];
        }
    }
    
    private function parseCreateIndex(string $stmt, array &$schema): void {
        $regex = '/CREATE\s+(UNIQUE\s+)?(?:(CLUSTERED|NONCLUSTERED)\s+)?INDEX\s+(\[?\w+\]?)\s+ON\s+(?:(\[?\w+\]?)\.)?(\[?\w+\]?)\s*\((.*?)\)(?:\s*INCLUDE\s*\((.*?)\))?(?:\s*WHERE\s+(.*))?/is';
        if (!preg_match($regex, $stmt, $matches)) {
            return;
        }
        
        $isUnique = !empty($matches[1]);
        $type = !empty($matches[2]) ? strtoupper(trim($matches[2])) : 'NONCLUSTERED';
        $idxName = $this->cleanName($matches[3]);
        $tblSchema = !empty($matches[4]) ? $this->cleanName($matches[4]) : 'dbo';
        $tblName = $this->cleanName($matches[5]);
        
        $cols = array_map([$this, 'cleanName'], explode(',', $matches[6]));
        
        $incCols = [];
        if (!empty($matches[7])) {
            $incCols = array_map([$this, 'cleanName'], explode(',', $matches[7]));
        }
        
        $filter = !empty($matches[8]) ? trim($matches[8]) : null;
        
        $key = strtolower($tblSchema . '.' . $tblName . '.' . $idxName);
        $schema['indexes'][$key] = [
            'schema'           => $tblSchema,
            'table'            => $tblName,
            'name'             => $idxName,
            'type'             => $type,
            'is_unique'        => $isUnique,
            'is_disabled'      => false,
            'columns'          => $cols,
            'included_columns' => $incCols,
            'filter'           => $filter
        ];
    }
    
    private function parseAlterTableAddConstraint(string $stmt, array &$schema): void {
        // Match ALTER TABLE ... ADD CONSTRAINT ...
        $regex = '/ALTER\s+TABLE\s+(?:(\[?\w+\]?)\.)?(\[?\w+\]?)\s+ADD\s+CONSTRAINT\s+(\[?\w+\]?)\s+(PRIMARY\s+KEY|FOREIGN\s+KEY|UNIQUE|CHECK|DEFAULT)\b/is';
        if (!preg_match($regex, $stmt, $matches)) {
            return;
        }
        
        $tblSchema = !empty($matches[1]) ? $this->cleanName($matches[1]) : 'dbo';
        $tblName = $this->cleanName($matches[2]);
        $cName = $this->cleanName($matches[3]);
        $cType = strtoupper($matches[4]);
        
        // Find the remaining content of the constraint definition
        // (after the CONSTRAINT [name] [TYPE])
        $pos = stripos($stmt, $cType);
        $constraintContent = substr($stmt, $pos);
        
        $key = strtolower($tblSchema . '.' . $cName);
        
        if ($cType === 'PRIMARY KEY') {
            if (preg_match('/PRIMARY\s+KEY\s*(?:CLUSTERED|NONCLUSTERED)?\s*\((.*?)\)/is', $constraintContent, $m)) {
                $cols = array_map([$this, 'cleanName'], explode(',', $m[1]));
                // PK is unique per table, map by table for match checks
                $pkKey = strtolower($tblSchema . '.' . $tblName . '.' . $cName);
                $schema['primary_keys'][$pkKey] = [
                    'schema'  => $tblSchema,
                    'table'   => $tblName,
                    'name'    => $cName,
                    'columns' => $cols
                ];
            }
        } elseif ($cType === 'FOREIGN KEY') {
            $fkRegex = '/FOREIGN\s+KEY\s*\((.*?)\)\s*REFERENCES\s+(?:(\[?\w+\]?)\.)?(\[?\w+\]?)\s*\((.*?)\)(?:\s+ON\s+DELETE\s+(CASCADE|SET\s+NULL|SET\s+DEFAULT|NO\s+ACTION))?(?:\s+ON\s+UPDATE\s+(CASCADE|SET\s+NULL|SET\s+DEFAULT|NO\s+ACTION))?/is';
            if (preg_match($fkRegex, $constraintContent, $m)) {
                $cols = array_map([$this, 'cleanName'], explode(',', $m[1]));
                $refSch = !empty($m[2]) ? $this->cleanName($m[2]) : 'dbo';
                $refTab = $this->cleanName($m[3]);
                $refCols = array_map([$this, 'cleanName'], explode(',', $m[4]));
                $onDelete = !empty($m[5]) ? strtoupper($m[5]) : 'NO_ACTION';
                $onUpdate = !empty($m[6]) ? strtoupper($m[6]) : 'NO_ACTION';
                
                $schema['foreign_keys'][$key] = [
                    'schema'      => $tblSchema,
                    'table'       => $tblName,
                    'name'        => $cName,
                    'columns'     => $cols,
                    'ref_schema'  => $refSch,
                    'ref_table'   => $refTab,
                    'ref_columns' => $refCols,
                    'on_delete'   => $onDelete,
                    'on_update'   => $onUpdate
                ];
            }
        } elseif ($cType === 'UNIQUE') {
            if (preg_match('/UNIQUE\s*(?:CLUSTERED|NONCLUSTERED)?\s*\((.*?)\)/is', $constraintContent, $m)) {
                $cols = array_map([$this, 'cleanName'], explode(',', $m[1]));
                $schema['unique_constraints'][$key] = [
                    'schema'  => $tblSchema,
                    'table'   => $tblName,
                    'name'    => $cName,
                    'columns' => $cols
                ];
            }
        } elseif ($cType === 'CHECK') {
            $chkBody = $this->extractOuterParentheses($constraintContent);
            if ($chkBody) {
                $schema['check_constraints'][$key] = [
                    'schema'     => $tblSchema,
                    'table'      => $tblName,
                    'name'       => $cName,
                    'definition' => '(' . $chkBody . ')'
                ];
            }
        } elseif ($cType === 'DEFAULT') {
            if (preg_match('/DEFAULT\s+(.*?)\s+FOR\s+(\[?\w+\]?)/is', $constraintContent, $m)) {
                $defExpr = trim($m[1]);
                $col = $this->cleanName($m[2]);
                $schema['default_constraints'][$key] = [
                    'schema'     => $tblSchema,
                    'table'      => $tblName,
                    'name'       => $cName,
                    'column'     => $col,
                    'definition' => $defExpr
                ];
            }
        }
    }
    
    private function parseCreateTrigger(string $stmt, array &$schema): void {
        // CREATE TRIGGER [schema].[trigger] ON [schema].[table] FOR|AFTER|INSTEAD OF [actions] AS [body]
        $regex = '/CREATE\s+TRIGGER\s+(?:(\[?\w+\]?)\.)?(\[?\w+\]?)\s+ON\s+(?:(\[?\w+\]?)\.)?(\[?\w+\]?)\s+(?:FOR|AFTER|INSTEAD\s+OF)\s+(.*?)\s+AS/is';
        if (!preg_match($regex, $stmt, $matches)) {
            return;
        }
        
        $trgSchema = !empty($matches[1]) ? $this->cleanName($matches[1]) : 'dbo';
        $trgName = $this->cleanName($matches[2]);
        $tblSchema = !empty($matches[3]) ? $this->cleanName($matches[3]) : 'dbo';
        $tblName = $this->cleanName($matches[4]);
        
        $eventsRaw = array_map('trim', explode(',', $matches[5]));
        $events = [];
        foreach ($eventsRaw as $evt) {
            $evt = strtoupper($evt);
            if ($evt === 'INSERT') $events[] = 'INSERT';
            elseif ($evt === 'UPDATE') $events[] = 'UPDATE';
            elseif ($evt === 'DELETE') $events[] = 'DELETE';
        }
        
        $key = strtolower($trgSchema . '.' . $trgName);
        $schema['triggers'][$key] = [
            'schema'     => $trgSchema,
            'table'      => $tblName,
            'name'       => $trgName,
            'disabled'   => false,
            'events'     => $events,
            'definition' => $stmt
        ];
    }
    
    private function extractOuterParentheses(string $str): ?string {
        $start = strpos($str, '(');
        if ($start === false) {
            return null;
        }
        
        $depth = 0;
        $len = strlen($str);
        for ($i = $start; $i < $len; $i++) {
            $char = $str[$i];
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return substr($str, $start + 1, $i - $start - 1);
                }
            }
        }
        return null;
    }
    
    private function splitByCommaOutsideParentheses(string $str): array {
        $parts = [];
        $current = '';
        $depth = 0;
        $len = strlen($str);
        for ($i = $i = 0; $i < $len; $i++) {
            $char = $str[$i];
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }
            
            if ($char === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }
        if (trim($current) !== '') {
            $parts[] = trim($current);
        }
        return $parts;
    }

    private function parseCreateView(string $stmt, array &$schema): void {
        if (preg_match('/CREATE\s+VIEW\s+(?:(\[?\w+\]?)\.)?(\[?\w+\]?)/i', $stmt, $matches)) {
            $vSchema = !empty($matches[1]) ? $this->cleanName($matches[1]) : 'dbo';
            $vName = $this->cleanName($matches[2]);
            $key = strtolower($vSchema . '.' . $vName);
            $schema['views'][$key] = [
                'schema'     => $vSchema,
                'name'       => $vName,
                'definition' => $stmt
            ];
        }
    }

    private function parseCreateProcedure(string $stmt, array &$schema): void {
        if (preg_match('/CREATE\s+(?:PROCEDURE|PROC)\s+(?:(\[?\w+\]?)\.)?(\[?\w+\]?)/i', $stmt, $matches)) {
            $pSchema = !empty($matches[1]) ? $this->cleanName($matches[1]) : 'dbo';
            $pName = $this->cleanName($matches[2]);
            $key = strtolower($pSchema . '.' . $pName);
            $schema['procedures'][$key] = [
                'schema'     => $pSchema,
                'name'       => $pName,
                'definition' => $stmt
            ];
        }
    }

    private function parseCreateFunction(string $stmt, array &$schema): void {
        if (preg_match('/CREATE\s+FUNCTION\s+(?:(\[?\w+\]?)\.)?(\[?\w+\]?)/i', $stmt, $matches)) {
            $fSchema = !empty($matches[1]) ? $this->cleanName($matches[1]) : 'dbo';
            $fName = $this->cleanName($matches[2]);
            $key = strtolower($fSchema . '.' . $fName);
            $schema['functions'][$key] = [
                'schema'     => $fSchema,
                'name'       => $fName,
                'definition' => $stmt
            ];
        }
    }
}
