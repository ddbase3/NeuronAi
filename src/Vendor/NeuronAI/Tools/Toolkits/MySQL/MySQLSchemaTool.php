<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Tools\Toolkits\MySQL;

use NeuronAi\Vendor\NeuronAI\Tools\Tool;
use PDO;
use function array_merge;
use function array_values;
use function count;
use function implode;
use function in_array;
use function str_contains;
use function str_repeat;
use function strtolower;
/**
 * @method static static make(PDO $pdo, ?array $tables = null)
 */
class MySQLSchemaTool extends Tool
{
    public function __construct(protected PDO $pdo, protected ?array $tables = null)
    {
        parent::__construct('analyze_mysql_database_schema', 'Retrieves MySQL database schema information including tables, columns, relationships, and indexes.
            Use this tool first to understand the database structure before writing any SQL queries.
            Essential for generating accurate queries with proper table/column names, JOIN conditions,
            and performance optimization. DO NOT call this tool if you already have database schema information in the context.');
    }
    public function __invoke(): string
    {
        return $this->formatForLLM(['tables' => $this->getTables(), 'relationships' => $this->getRelationships(), 'indexes' => $this->getIndexes(), 'constraints' => $this->getConstraints()]);
    }
    protected function formatForLLM(array $structure): string
    {
        $output = "# MySQL Database Schema Analysis\n\n";
        $output .= "This database contains " . count($structure['tables']) . " tables with the following structure:\n\n";
        // Tables overview
        $output .= "## Tables Overview\n";
        foreach ($structure['tables'] as $table) {
            $pkColumns = empty($table['primary_key']) ? 'None' : implode(', ', $table['primary_key']);
            $output .= "- **{$table['name']}**: {$table['estimated_rows']} rows, Primary Key: {$pkColumns}";
            if ($table['comment']) {
                $output .= " - {$table['comment']}";
            }
            $output .= "\n";
        }
        $output .= "\n";
        // Detailed table structures
        $output .= "## Detailed Table Structures\n\n";
        foreach ($structure['tables'] as $table) {
            $output .= "### Table: `{$table['name']}`\n";
            if ($table['comment']) {
                $output .= "**Description**: {$table['comment']}\n";
            }
            $output .= "**Estimated Rows**: {$table['estimated_rows']}\n\n";
            $output .= "**Columns**:\n";
            foreach ($table['columns'] as $column) {
                $nullable = $column['nullable'] ? 'NULL' : 'NOT NULL';
                $autoInc = $column['auto_increment'] ? ' AUTO_INCREMENT' : '';
                $default = $column['default'] !== null ? " DEFAULT '{$column['default']}'" : '';
                $output .= "- `{$column['name']}` {$column['full_type']} {$nullable}{$default}{$autoInc}";
                if ($column['comment']) {
                    $output .= " - {$column['comment']}";
                }
                $output .= "\n";
            }
            if (!empty($table['primary_key'])) {
                $output .= "\n**Primary Key**: " . implode(', ', $table['primary_key']) . "\n";
            }
            if (!empty($table['unique_keys'])) {
                $output .= "**Unique Keys**: " . implode(', ', $table['unique_keys']) . "\n";
            }
            $output .= "\n";
        }
        // Relationships
        if (!empty($structure['relationships'])) {
            $output .= "## Foreign Key Relationships\n\n";
            $output .= "Understanding these relationships is crucial for JOIN operations:\n\n";
            foreach ($structure['relationships'] as $rel) {
                $output .= "- `{$rel['source_table']}.{$rel['source_column']}` → `{$rel['target_table']}.{$rel['target_column']}`";
                $output .= " (ON DELETE {$rel['DELETE_RULE']}, ON UPDATE {$rel['UPDATE_RULE']})\n";
            }
            $output .= "\n";
        }
        // Indexes for query optimization
        if (!empty($structure['indexes'])) {
            $output .= "## Available Indexes (for Query Optimization)\n\n";
            $output .= "These indexes can significantly improve query performance:\n\n";
            foreach ($structure['indexes'] as $index) {
                $unique = $index['unique'] ? 'UNIQUE ' : '';
                $columns = implode(', ', $index['columns']);
                $output .= "- {$unique}INDEX `{$index['name']}` on `{$index['table']}` ({$columns})\n";
            }
            $output .= "\n";
        }
        // Query generation guidelines
        $output .= "## MySQL Query Generation Guidelines\n\n";
        $output .= "**Best Practices for this database**:\n";
        $output .= "1. Always use table aliases for better readability\n";
        $output .= "2. Prefer indexed columns in WHERE clauses for better performance\n";
        $output .= "3. Use appropriate JOINs based on the foreign key relationships listed above\n";
        $output .= "4. Consider the estimated row counts when writing queries - larger tables may need LIMIT clauses\n";
        $output .= "5. Pay attention to nullable columns when using comparison operators\n\n";
        // Common patterns
        $output .= "**Common Query Patterns**:\n";
        $this->addCommonPatterns($output, $structure['tables']);
        return $output;
    }
    protected function getTables(): array
    {
        $whereClause = "WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_TYPE = 'BASE TABLE'";
        $params = [];
        // Add table filtering if specific tables are requested
        if ($this->tables !== null && $this->tables !== []) {
            $placeholders = str_repeat('?,', count($this->tables) - 1) . '?';
            $whereClause .= " AND t.TABLE_NAME IN ({$placeholders})";
            $params = $this->tables;
        }
        $stmt = $this->pdo->prepare("\n            SELECT\n                t.TABLE_NAME,\n                t.ENGINE,\n                t.TABLE_ROWS,\n                t.TABLE_COMMENT,\n                c.COLUMN_NAME,\n                c.ORDINAL_POSITION,\n                c.COLUMN_DEFAULT,\n                c.IS_NULLABLE,\n                c.DATA_TYPE,\n                c.CHARACTER_MAXIMUM_LENGTH,\n                c.NUMERIC_PRECISION,\n                c.NUMERIC_SCALE,\n                c.COLUMN_TYPE,\n                c.COLUMN_KEY,\n                c.EXTRA,\n                c.COLUMN_COMMENT\n            FROM INFORMATION_SCHEMA.TABLES t\n            LEFT JOIN INFORMATION_SCHEMA.COLUMNS c ON t.TABLE_NAME = c.TABLE_NAME\n                AND t.TABLE_SCHEMA = c.TABLE_SCHEMA\n            {$whereClause}\n            ORDER BY t.TABLE_NAME, c.ORDINAL_POSITION\n        ");
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $tables = [];
        foreach ($results as $row) {
            $tableName = $row['TABLE_NAME'];
            if (!isset($tables[$tableName])) {
                $tables[$tableName] = ['name' => $tableName, 'engine' => $row['ENGINE'], 'estimated_rows' => $row['TABLE_ROWS'], 'comment' => $row['TABLE_COMMENT'], 'columns' => [], 'primary_key' => [], 'unique_keys' => [], 'indexes' => []];
            }
            if ($row['COLUMN_NAME']) {
                $column = ['name' => $row['COLUMN_NAME'], 'type' => $row['DATA_TYPE'], 'full_type' => $row['COLUMN_TYPE'], 'nullable' => $row['IS_NULLABLE'] === 'YES', 'default' => $row['COLUMN_DEFAULT'], 'auto_increment' => str_contains((string) $row['EXTRA'], 'auto_increment'), 'comment' => $row['COLUMN_COMMENT']];
                // Add length/precision info for better LLM understanding
                if ($row['CHARACTER_MAXIMUM_LENGTH']) {
                    $column['max_length'] = $row['CHARACTER_MAXIMUM_LENGTH'];
                }
                if ($row['NUMERIC_PRECISION']) {
                    $column['precision'] = $row['NUMERIC_PRECISION'];
                    $column['scale'] = $row['NUMERIC_SCALE'];
                }
                $tables[$tableName]['columns'][] = $column;
                // Track key information
                if ($row['COLUMN_KEY'] === 'PRI') {
                    $tables[$tableName]['primary_key'][] = $row['COLUMN_NAME'];
                } elseif ($row['COLUMN_KEY'] === 'UNI') {
                    $tables[$tableName]['unique_keys'][] = $row['COLUMN_NAME'];
                } elseif ($row['COLUMN_KEY'] === 'MUL') {
                    $tables[$tableName]['indexes'][] = $row['COLUMN_NAME'];
                }
            }
        }
        return $tables;
    }
    protected function getRelationships(): array
    {
        $whereClause = "WHERE kcu.TABLE_SCHEMA = DATABASE() AND kcu.REFERENCED_TABLE_NAME IS NOT NULL";
        $params = [];
        // Add table filtering if specific tables are requested
        if ($this->tables !== null && $this->tables !== []) {
            $placeholders = str_repeat('?,', count($this->tables) - 1) . '?';
            $whereClause .= " AND (kcu.TABLE_NAME IN ({$placeholders}) OR kcu.REFERENCED_TABLE_NAME IN ({$placeholders}))";
            $params = array_merge($this->tables, $this->tables);
        }
        $stmt = $this->pdo->prepare("\n            SELECT DISTINCT\n                kcu.CONSTRAINT_NAME,\n                kcu.TABLE_NAME as source_table,\n                kcu.COLUMN_NAME as source_column,\n                kcu.REFERENCED_TABLE_NAME as target_table,\n                kcu.REFERENCED_COLUMN_NAME as target_column,\n                rc.UPDATE_RULE,\n                rc.DELETE_RULE\n            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu\n            JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc\n                ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME\n                AND kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA\n            {$whereClause}\n            ORDER BY kcu.TABLE_NAME, kcu.ORDINAL_POSITION\n        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    protected function getIndexes(): array
    {
        $stmt = $this->pdo->prepare("\n            SELECT\n                TABLE_NAME,\n                INDEX_NAME,\n                COLUMN_NAME,\n                SEQ_IN_INDEX,\n                NON_UNIQUE,\n                INDEX_TYPE,\n                CARDINALITY\n            FROM INFORMATION_SCHEMA.STATISTICS\n            WHERE TABLE_SCHEMA = DATABASE()\n                AND INDEX_NAME != 'PRIMARY'\n            ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX\n        ");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $indexes = [];
        foreach ($results as $row) {
            $key = $row['TABLE_NAME'] . '.' . $row['INDEX_NAME'];
            if (!isset($indexes[$key])) {
                $indexes[$key] = ['table' => $row['TABLE_NAME'], 'name' => $row['INDEX_NAME'], 'unique' => $row['NON_UNIQUE'] == 0, 'type' => $row['INDEX_TYPE'], 'columns' => []];
            }
            $indexes[$key]['columns'][] = $row['COLUMN_NAME'];
        }
        return array_values($indexes);
    }
    protected function getConstraints(): array
    {
        $whereClause = "WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE IN ('UNIQUE')";
        $params = [];
        // Add table filtering if specific tables are requested
        if ($this->tables !== null && $this->tables !== []) {
            $placeholders = str_repeat('?,', count($this->tables) - 1) . '?';
            $whereClause .= " AND TABLE_NAME IN ({$placeholders})";
            $params = $this->tables;
        }
        $stmt = $this->pdo->prepare("\n            SELECT\n                CONSTRAINT_NAME,\n                TABLE_NAME,\n                CONSTRAINT_TYPE\n            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS\n            {$whereClause}\n        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    protected function addCommonPatterns(string &$output, array $tables): void
    {
        // Find tables with timestamps for temporal queries
        foreach ($tables as $table) {
            foreach ($table['columns'] as $column) {
                if (in_array($column['type'], ['timestamp', 'datetime', 'date']) && (str_contains(strtolower((string) $column['name']), 'created') || str_contains(strtolower((string) $column['name']), 'updated'))) {
                    $output .= "- For temporal queries on `{$table['name']}`, use `{$column['name']}` column\n";
                    break;
                }
            }
        }
        // Find potential text search columns
        foreach ($tables as $table) {
            foreach ($table['columns'] as $column) {
                if (in_array($column['type'], ['varchar', 'text', 'longtext']) && (str_contains(strtolower((string) $column['name']), 'name') || str_contains(strtolower((string) $column['name']), 'title') || str_contains(strtolower((string) $column['name']), 'description'))) {
                    $output .= "- For text searches on `{$table['name']}`, consider using `{$column['name']}` with LIKE or FULLTEXT\n";
                    break;
                }
            }
        }
        $output .= "\n";
    }
}
