<?php

class DbInspector {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Inspects the database and returns the normalized schema array.
     */
    public function inspect(): array {
        return [
            'tables'              => $this->inspectTables(),
            'columns'             => $this->inspectColumns(),
            'primary_keys'        => $this->inspectPrimaryKeys(),
            'foreign_keys'        => $this->inspectForeignKeys(),
            'unique_constraints'  => $this->inspectUniqueConstraints(),
            'check_constraints'   => $this->inspectCheckConstraints(),
            'default_constraints' => $this->inspectDefaultConstraints(),
            'indexes'             => $this->inspectIndexes(),
            'triggers'            => $this->inspectTriggers(),
        ];
    }

    private function inspectTables(): array {
        $sql = "SELECT TABLE_SCHEMA, TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_TYPE = 'BASE TABLE'
                ORDER BY TABLE_SCHEMA, TABLE_NAME";
        
        $stmt = $this->db->query($sql);
        $tables = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = strtolower($row['TABLE_SCHEMA'] . '.' . $row['TABLE_NAME']);
            $tables[$key] = [
                'schema' => $row['TABLE_SCHEMA'],
                'name'   => $row['TABLE_NAME']
            ];
        }
        return $tables;
    }

    private function inspectColumns(): array {
        $sql = "SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, DATA_TYPE,
                       CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE,
                       IS_NULLABLE, COLUMN_DEFAULT
                FROM INFORMATION_SCHEMA.COLUMNS
                ORDER BY TABLE_SCHEMA, TABLE_NAME, ORDINAL_POSITION";
        
        $stmt = $this->db->query($sql);
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tableKey = strtolower($row['TABLE_SCHEMA'] . '.' . $row['TABLE_NAME']);
            $colKey = strtolower($tableKey . '.' . $row['COLUMN_NAME']);
            
            $columns[$colKey] = [
                'schema'         => $row['TABLE_SCHEMA'],
                'table'          => $row['TABLE_NAME'],
                'name'           => $row['COLUMN_NAME'],
                'type'           => strtoupper($row['DATA_TYPE']),
                'length'         => $row['CHARACTER_MAXIMUM_LENGTH'] !== null ? (int)$row['CHARACTER_MAXIMUM_LENGTH'] : null,
                'precision'      => $row['NUMERIC_PRECISION'] !== null ? (int)$row['NUMERIC_PRECISION'] : null,
                'scale'          => $row['NUMERIC_SCALE'] !== null ? (int)$row['NUMERIC_SCALE'] : null,
                'nullable'       => strtolower($row['IS_NULLABLE']) === 'yes',
                'column_default' => $row['COLUMN_DEFAULT']
            ];
        }
        return $columns;
    }

    private function inspectPrimaryKeys(): array {
        $sql = "SELECT kcu.TABLE_SCHEMA, kcu.TABLE_NAME, kcu.COLUMN_NAME,
                       tc.CONSTRAINT_NAME, kcu.ORDINAL_POSITION
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
                  ON kcu.CONSTRAINT_NAME = tc.CONSTRAINT_NAME AND kcu.TABLE_SCHEMA = tc.TABLE_SCHEMA
                WHERE tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
                ORDER BY kcu.TABLE_SCHEMA, kcu.TABLE_NAME, tc.CONSTRAINT_NAME, kcu.ORDINAL_POSITION";
        
        $stmt = $this->db->query($sql);
        $pkeys = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = strtolower($row['TABLE_SCHEMA'] . '.' . $row['TABLE_NAME'] . '.' . $row['CONSTRAINT_NAME']);
            if (!isset($pkeys[$key])) {
                $pkeys[$key] = [
                    'schema'  => $row['TABLE_SCHEMA'],
                    'table'   => $row['TABLE_NAME'],
                    'name'    => $row['CONSTRAINT_NAME'],
                    'columns' => []
                ];
            }
            $pkeys[$key]['columns'][] = $row['COLUMN_NAME'];
        }
        return $pkeys;
    }

    private function inspectForeignKeys(): array {
        $sql = "SELECT fk.name AS FK_NAME,
                       OBJECT_SCHEMA_NAME(fk.parent_object_id) AS SCHEMA_NAME,
                       OBJECT_NAME(fk.parent_object_id) AS TABLE_NAME,
                       COL_NAME(fkc.parent_object_id, fkc.parent_column_id) AS COLUMN_NAME,
                       OBJECT_SCHEMA_NAME(fk.referenced_object_id) AS REF_SCHEMA,
                       OBJECT_NAME(fk.referenced_object_id) AS REF_TABLE,
                       COL_NAME(fkc.referenced_object_id, fkc.referenced_column_id) AS REF_COLUMN,
                       fk.delete_referential_action_desc,
                       fk.update_referential_action_desc,
                       fkc.constraint_column_id
                FROM sys.foreign_keys fk
                JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
                ORDER BY SCHEMA_NAME, TABLE_NAME, FK_NAME, fkc.constraint_column_id";
        
        $stmt = $this->db->query($sql);
        $fkeys = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $schema = $row['SCHEMA_NAME'] ?? 'dbo';
            $key = strtolower($schema . '.' . $row['FK_NAME']);
            
            if (!isset($fkeys[$key])) {
                $fkeys[$key] = [
                    'schema'      => $schema,
                    'table'       => $row['TABLE_NAME'],
                    'name'        => $row['FK_NAME'],
                    'columns'     => [],
                    'ref_schema'  => $row['REF_SCHEMA'] ?? 'dbo',
                    'ref_table'   => $row['REF_TABLE'],
                    'ref_columns' => [],
                    'on_delete'   => $row['delete_referential_action_desc'],
                    'on_update'   => $row['update_referential_action_desc']
                ];
            }
            $fkeys[$key]['columns'][] = $row['COLUMN_NAME'];
            $fkeys[$key]['ref_columns'][] = $row['REF_COLUMN'];
        }
        return $fkeys;
    }

    private function inspectUniqueConstraints(): array {
        $sql = "SELECT tc.TABLE_SCHEMA, tc.TABLE_NAME, tc.CONSTRAINT_NAME, kcu.COLUMN_NAME, kcu.ORDINAL_POSITION
                FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
                JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                  ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME AND tc.TABLE_SCHEMA = kcu.TABLE_SCHEMA
                WHERE tc.CONSTRAINT_TYPE = 'UNIQUE'
                ORDER BY tc.TABLE_SCHEMA, tc.TABLE_NAME, tc.CONSTRAINT_NAME, kcu.ORDINAL_POSITION";
        
        $stmt = $this->db->query($sql);
        $uniques = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = strtolower($row['TABLE_SCHEMA'] . '.' . $row['CONSTRAINT_NAME']);
            if (!isset($uniques[$key])) {
                $uniques[$key] = [
                    'schema'  => $row['TABLE_SCHEMA'],
                    'table'   => $row['TABLE_NAME'],
                    'name'    => $row['CONSTRAINT_NAME'],
                    'columns' => []
                ];
            }
            $uniques[$key]['columns'][] = $row['COLUMN_NAME'];
        }
        return $uniques;
    }

    private function inspectCheckConstraints(): array {
        $sql = "SELECT cc.name AS CONSTRAINT_NAME,
                       OBJECT_SCHEMA_NAME(cc.parent_object_id) AS SCHEMA_NAME,
                       OBJECT_NAME(cc.parent_object_id) AS TABLE_NAME,
                       cc.definition
                FROM sys.check_constraints cc
                ORDER BY SCHEMA_NAME, CONSTRAINT_NAME";
        
        $stmt = $this->db->query($sql);
        $checks = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $schema = $row['SCHEMA_NAME'] ?? 'dbo';
            $key = strtolower($schema . '.' . $row['CONSTRAINT_NAME']);
            $checks[$key] = [
                'schema'     => $schema,
                'table'      => $row['TABLE_NAME'],
                'name'       => $row['CONSTRAINT_NAME'],
                'definition' => $row['definition']
            ];
        }
        return $checks;
    }

    private function inspectDefaultConstraints(): array {
        $sql = "SELECT dc.name AS CONSTRAINT_NAME,
                       OBJECT_SCHEMA_NAME(dc.parent_object_id) AS SCHEMA_NAME,
                       OBJECT_NAME(dc.parent_object_id) AS TABLE_NAME,
                       COL_NAME(dc.parent_object_id, dc.parent_column_id) AS COLUMN_NAME,
                       dc.definition
                FROM sys.default_constraints dc
                ORDER BY SCHEMA_NAME, CONSTRAINT_NAME";
        
        $stmt = $this->db->query($sql);
        $defaults = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $schema = $row['SCHEMA_NAME'] ?? 'dbo';
            $key = strtolower($schema . '.' . $row['CONSTRAINT_NAME']);
            $defaults[$key] = [
                'schema'     => $schema,
                'table'      => $row['TABLE_NAME'],
                'name'       => $row['CONSTRAINT_NAME'],
                'column'     => $row['COLUMN_NAME'],
                'definition' => $row['definition']
            ];
        }
        return $defaults;
    }

    private function inspectIndexes(): array {
        // We retrieve indexes, excluding PKs which are handled separately
        $sql = "SELECT
                    i.name AS INDEX_NAME,
                    OBJECT_SCHEMA_NAME(i.object_id) AS SCHEMA_NAME,
                    OBJECT_NAME(i.object_id) AS TABLE_NAME,
                    i.type_desc AS INDEX_TYPE,
                    i.is_unique,
                    i.is_disabled,
                    STRING_AGG(c.name, ', ') WITHIN GROUP (ORDER BY ic.key_ordinal) AS COLUMNS,
                    STRING_AGG(
                        CASE ic.is_included_column WHEN 1 THEN c.name ELSE NULL END, ', '
                    ) AS INCLUDED_COLUMNS,
                    i.filter_definition
                FROM sys.indexes i
                JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
                JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
                WHERE i.is_primary_key = 0 AND i.type > 0 AND i.name IS NOT NULL
                GROUP BY i.name, i.object_id, i.type_desc, i.is_unique, i.is_disabled, i.filter_definition";
        
        $stmt = $this->db->query($sql);
        $indexes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $schema = $row['SCHEMA_NAME'] ?? 'dbo';
            $key = strtolower($schema . '.' . $row['TABLE_NAME'] . '.' . $row['INDEX_NAME']);
            
            $columns = array_map('trim', explode(',', $row['COLUMNS']));
            $incColumns = !empty($row['INCLUDED_COLUMNS']) 
                ? array_map('trim', explode(',', $row['INCLUDED_COLUMNS'])) 
                : [];

            $indexes[$key] = [
                'schema'           => $schema,
                'table'            => $row['TABLE_NAME'],
                'name'             => $row['INDEX_NAME'],
                'type'             => $row['INDEX_TYPE'],
                'is_unique'        => (bool)$row['is_unique'],
                'is_disabled'      => (bool)$row['is_disabled'],
                'columns'          => $columns,
                'included_columns' => $incColumns,
                'filter'           => $row['filter_definition']
            ];
        }
        return $indexes;
    }

    private function inspectTriggers(): array {
        $sql = "SELECT t.name AS TRIGGER_NAME,
                       OBJECT_SCHEMA_NAME(t.parent_id) AS SCHEMA_NAME,
                       OBJECT_NAME(t.parent_id) AS TABLE_NAME,
                       t.is_disabled,
                       STRING_AGG(te.type_desc, ', ') AS EVENTS,
                       OBJECT_DEFINITION(t.object_id) AS TRIGGER_DEFINITION
                FROM sys.triggers t
                JOIN sys.trigger_events te ON t.object_id = te.object_id
                WHERE t.parent_class = 1 AND t.name IS NOT NULL
                GROUP BY t.name, t.parent_id, t.is_disabled, t.object_id";
        
        $stmt = $this->db->query($sql);
        $triggers = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $schema = $row['SCHEMA_NAME'] ?? 'dbo';
            $key = strtolower($schema . '.' . $row['TRIGGER_NAME']);
            $triggers[$key] = [
                'schema'     => $schema,
                'table'      => $row['TABLE_NAME'],
                'name'       => $row['TRIGGER_NAME'],
                'disabled'   => (bool)$row['is_disabled'],
                'events'     => array_map('trim', explode(',', $row['EVENTS'])),
                'definition' => $row['TRIGGER_DEFINITION']
            ];
        }
        return $triggers;
    }
}
