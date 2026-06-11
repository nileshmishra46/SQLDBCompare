<?php

// Command Line Test Runner
require_once __DIR__ . '/engine/SqlFileParser.php';
require_once __DIR__ . '/engine/SchemaComparator.php';
require_once __DIR__ . '/engine/ScriptGenerator.php';

$testsPassed = 0;
$testsFailed = 0;

function assertTest(string $name, bool $expression): void {
    global $testsPassed, $testsFailed;
    if ($expression) {
        $testsPassed++;
        echo "\033[32m[PASS]\033[0m $name\n";
    } else {
        $testsFailed++;
        echo "\033[31m[FAIL]\033[0m $name\n";
    }
}

echo "==================================================\n";
echo "Running SQL Schema Comparator Unit Tests\n";
echo "==================================================\n\n";

// ==========================================
// TEST 1: DDL Parsing
// ==========================================
$sampleSql = <<<SQL
CREATE TABLE [dbo].[Users] (
    [Id] INT IDENTITY(1,1) NOT NULL,
    [Username] NVARCHAR(50) NOT NULL,
    [Email] NVARCHAR(100) NULL,
    [CreatedAt] DATETIME DEFAULT (getdate()) NOT NULL,
    CONSTRAINT [PK_Users] PRIMARY KEY CLUSTERED ([Id] ASC)
);
GO

CREATE UNIQUE NONCLUSTERED INDEX [IX_Users_Email] ON [dbo].[Users] ([Email] ASC) WHERE [Email] IS NOT NULL;
GO

ALTER TABLE [dbo].[Users] ADD CONSTRAINT [DF_Users_Username] DEFAULT ('Guest') FOR [Username];
GO

CREATE TRIGGER [dbo].[TR_Users_Audit] ON [dbo].[Users] AFTER INSERT, UPDATE AS
BEGIN
    SET NOCOUNT ON;
END;
GO

CREATE VIEW [dbo].[v_ActiveUsers] AS
SELECT Id, Username FROM [dbo].[Users] WHERE Email IS NOT NULL;
GO

CREATE PROCEDURE [dbo].[sp_GetUser]
    @UserId INT
AS
BEGIN
    SELECT * FROM [dbo].[Users] WHERE Id = @UserId;
END;
GO

CREATE FUNCTION [dbo].[fn_GetUsername]
(
    @UserId INT
)
RETURNS NVARCHAR(50)
AS
BEGIN
    DECLARE @Username NVARCHAR(50);
    SELECT @Username = Username FROM [dbo].[Users] WHERE Id = @UserId;
    RETURN @Username;
END;
GO
SQL;

$parser = new SqlFileParser();
$parsed = $parser->parse($sampleSql);

assertTest("Parser extracts tables successfully", isset($parsed['tables']['dbo.users']));
assertTest("Parser extracts columns count", count($parsed['columns']) === 4);
assertTest("Parser checks column type and length", $parsed['columns']['dbo.users.username']['type'] === 'NVARCHAR' && $parsed['columns']['dbo.users.username']['length'] === 50);
assertTest("Parser checks column nullability", $parsed['columns']['dbo.users.email']['nullable'] === true && $parsed['columns']['dbo.users.username']['nullable'] === false);
assertTest("Parser extracts inline default constraint", isset($parsed['default_constraints']['dbo.df_users_createdat']) || isset($parsed['default_constraints']['dbo.df_users_username']));
assertTest("Parser extracts primary key", isset($parsed['primary_keys']['dbo.users.pk_users']) && $parsed['primary_keys']['dbo.users.pk_users']['columns'][0] === 'Id');
assertTest("Parser extracts index with filter", isset($parsed['indexes']['dbo.users.ix_users_email']) && $parsed['indexes']['dbo.users.ix_users_email']['is_unique'] === true && $parsed['indexes']['dbo.users.ix_users_email']['filter'] === '[Email] IS NOT NULL');
assertTest("Parser extracts trigger", isset($parsed['triggers']['dbo.tr_users_audit']) && count($parsed['triggers']['dbo.tr_users_audit']['events']) === 2);
assertTest("Parser extracts view successfully", isset($parsed['views']['dbo.v_activeusers']));
assertTest("Parser extracts procedure successfully", isset($parsed['procedures']['dbo.sp_getuser']));
assertTest("Parser extracts function successfully", isset($parsed['functions']['dbo.fn_getusername']));

// ==========================================
// TEST 2: Schema Comparison
// ==========================================
$sourceMock = [
    'tables' => [
        'dbo.users' => ['schema' => 'dbo', 'name' => 'Users'],
        'dbo.orders' => ['schema' => 'dbo', 'name' => 'Orders']
    ],
    'columns' => [
        'dbo.users.id' => ['schema' => 'dbo', 'table' => 'Users', 'name' => 'Id', 'type' => 'INT', 'length' => null, 'precision' => null, 'scale' => null, 'nullable' => false, 'column_default' => null],
        'dbo.users.name' => ['schema' => 'dbo', 'table' => 'Users', 'name' => 'Name', 'type' => 'NVARCHAR', 'length' => 100, 'precision' => null, 'scale' => null, 'nullable' => true, 'column_default' => null]
    ],
    'primary_keys' => [],
    'foreign_keys' => [],
    'unique_constraints' => [],
    'check_constraints' => [],
    'default_constraints' => [],
    'indexes' => [],
    'triggers' => [],
    'views' => [
        'dbo.v_activeusers' => ['schema' => 'dbo', 'name' => 'v_ActiveUsers', 'definition' => "CREATE VIEW [dbo].[v_ActiveUsers] AS SELECT Id, Username FROM [dbo].[Users] WHERE Email IS NOT NULL;"]
    ],
    'procedures' => [
        'dbo.sp_getuser' => ['schema' => 'dbo', 'name' => 'sp_GetUser', 'definition' => "CREATE PROCEDURE [dbo].[sp_GetUser] @UserId INT AS BEGIN SELECT * FROM [dbo].[Users] WHERE Id = @UserId; END;"]
    ],
    'functions' => [
        'dbo.fn_getusername' => ['schema' => 'dbo', 'name' => 'fn_GetUsername', 'definition' => "CREATE FUNCTION [dbo].[fn_GetUsername] (@UserId INT) RETURNS NVARCHAR(50) AS BEGIN RETURN NULL; END;"]
    ]
];

$targetMock = [
    'tables' => [
        'dbo.users' => ['schema' => 'dbo', 'name' => 'Users']
        // missing: dbo.orders
    ],
    'columns' => [
        'dbo.users.id' => ['schema' => 'dbo', 'table' => 'Users', 'name' => 'Id', 'type' => 'INT', 'length' => null, 'precision' => null, 'scale' => null, 'nullable' => false, 'column_default' => null],
        'dbo.users.name' => ['schema' => 'dbo', 'table' => 'Users', 'name' => 'Name', 'type' => 'VARCHAR', 'length' => 50, 'precision' => null, 'scale' => null, 'nullable' => false, 'column_default' => null]
        // missing in target: (none, but type mismatches and missing orders table column)
    ],
    'primary_keys' => [],
    'foreign_keys' => [],
    'unique_constraints' => [],
    'check_constraints' => [],
    'default_constraints' => [],
    'indexes' => [],
    'triggers' => [],
    'views' => [
        // missing: v_activeusers
    ],
    'procedures' => [
        // mismatched definition
        'dbo.sp_getuser' => ['schema' => 'dbo', 'name' => 'sp_GetUser', 'definition' => "CREATE PROCEDURE [dbo].[sp_GetUser] @UserId INT AS BEGIN SELECT Username FROM [dbo].[Users] WHERE Id = @UserId; END;"]
    ],
    'functions' => [
        // matches
        'dbo.fn_getusername' => ['schema' => 'dbo', 'name' => 'fn_GetUsername', 'definition' => "CREATE FUNCTION [dbo].[fn_GetUsername] (@UserId INT) RETURNS NVARCHAR(50) AS BEGIN RETURN NULL; END;"]
    ]
];

$comparator = new SchemaComparator(['show_source_extras' => true]);
$diff = $comparator->compare($sourceMock, $targetMock);

assertTest("Comparator detects missing table in target", isset($diff['missing_in_target']['tables']['dbo.orders']));
assertTest("Comparator detects mismatched column data type", isset($diff['mismatches']['columns']['dbo.users.name']));
assertTest("Comparator details type difference", $diff['mismatches']['columns']['dbo.users.name']['mismatches']['type']['src'] === 'NVARCHAR');
assertTest("Comparator detects missing view in target", isset($diff['missing_in_target']['views']['dbo.v_activeusers']));
assertTest("Comparator detects mismatched procedure definition", isset($diff['mismatches']['procedures']['dbo.sp_getuser']));
assertTest("Comparator matches identical functions", !isset($diff['mismatches']['functions']['dbo.fn_getusername']) && !isset($diff['missing_in_target']['functions']['dbo.fn_getusername']));

// ==========================================
// TEST 3: Script Generation
// ==========================================
$generator = new ScriptGenerator();

// Test Column ADD statement
$colToAdd = [
    'schema' => 'dbo',
    'table' => 'Users',
    'name' => 'Age',
    'type' => 'INT',
    'length' => null,
    'precision' => null,
    'scale' => null,
    'nullable' => true,
    'column_default' => null
];
$addColSql = $generator->generateAlterTableAddColumn($colToAdd);
assertTest("Script generator writes clean Alter Table Add Column statement", strpos($addColSql, "ALTER TABLE [dbo].[Users] ADD [Age] INT NULL;") !== false);

// Test Table CREATE statement
$allCols = [
    'dbo.orders.id' => ['schema' => 'dbo', 'table' => 'Orders', 'name' => 'Id', 'type' => 'INT', 'length' => null, 'precision' => null, 'scale' => null, 'nullable' => false, 'column_default' => null],
    'dbo.orders.price' => ['schema' => 'dbo', 'table' => 'Orders', 'name' => 'Price', 'type' => 'DECIMAL', 'length' => null, 'precision' => 10, 'scale' => 2, 'nullable' => false, 'column_default' => '((0))']
];
$tableInfo = ['schema' => 'dbo', 'name' => 'Orders'];
$createTblSql = $generator->generateCreateTable('dbo.orders', $tableInfo, $allCols);
assertTest("Script generator outputs CREATE TABLE block", strpos($createTblSql, "CREATE TABLE [dbo].[Orders]") !== false);
assertTest("Script generator maps DECIMAL parameters", strpos($createTblSql, "[Price] DECIMAL(10, 2)") !== false);
assertTest("Script generator maps column defaults", strpos($createTblSql, "DEFAULT ((0))") !== false);

// Test View, Procedure, Function statement generation
$viewToAdd = [
    'schema' => 'dbo',
    'name' => 'v_ActiveUsers',
    'definition' => "CREATE VIEW [dbo].[v_ActiveUsers] AS SELECT Id, Username FROM [dbo].[Users];"
];
$addViewSql = $generator->generateAddView($viewToAdd);
assertTest("Script generator writes clean Create View statement", strpos($addViewSql, "CREATE VIEW [dbo].[v_ActiveUsers]") !== false);

$procToDrop = [
    'schema' => 'dbo',
    'name' => 'sp_GetUser'
];
$dropProcSql = $generator->generateDropProcedure($procToDrop);
assertTest("Script generator writes clean Drop Procedure statement", strpos($dropProcSql, "DROP PROCEDURE [dbo].[sp_GetUser];") !== false);

$funcToDrop = [
    'schema' => 'dbo',
    'name' => 'fn_GetUsername'
];
$dropFuncSql = $generator->generateDropFunction($funcToDrop);
assertTest("Script generator writes clean Drop Function statement", strpos($dropFuncSql, "DROP FUNCTION [dbo].[fn_GetUsername];") !== false);

echo "\n==================================================\n";
echo "Test Run Summary: $testsPassed Passed, $testsFailed Failed\n";
echo "==================================================\n";

exit($testsFailed > 0 ? 1 : 0);
