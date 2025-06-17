<?php

require_once 'config.php';

$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
if ($db->connect_errno) {
    exit("Database connection failed: {$db->connect_error}");
}

$db->query('SET NAMES utf8');
$db->query("SET SQL_MODE = ''");

const COLUMNS_TO_INDEX = [
    'price',
    'quantity',
    'priority',
    'model',
    'sku',
    'upc',
    'ean',
    'jan',
    'isbn',
    'mpn',
    'views',
    'date_available',
    'status',
    'sort_order',
    'date_added',
    'date_modified',
];

function addCompositeIndex(mysqli $db, string $table, array $columns): void
{
    $indexName = 'idx_' . implode('_', $columns);
    $cols = '`' . implode('`,`', $columns) . '`';
    $result = $db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$indexName}'");
    if ($result && $result->num_rows > 0) {
        echo "Composite index {$indexName} already exists on {$table}. Skipping." . PHP_EOL;
        return;
    }
    if ($db->query("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` ({$cols})")) {
        echo "Added composite index {$indexName} on {$table} (" . implode(', ', $columns) . ")" . PHP_EOL;
    } else {
        echo "Failed to add composite index {$indexName} on {$table}: {$db->error}" . PHP_EOL;
    }
}

function addIndex(mysqli $db, string $table, string $column): void
{
    $indexName = "idx_{$column}";
    if ($db->query("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$column}`)")) {
        echo "Added index {$indexName} on {$table} ({$column})" . PHP_EOL;
    } else {
        echo "Failed to add index {$indexName} on {$table} ({$column}): {$db->error}" . PHP_EOL;
    }
}

function getIndexes(mysqli $db, string $table): array
{
    $indexResult = $db->query("SHOW INDEX FROM `{$table}`");
    if (!$indexResult) {
        return [];
    }

    $indexes = [];
    while ($row = $indexResult->fetch_assoc()) {
        $indexes[$row['Key_name']][] = $row['Column_name'];
    }

    return $indexes;
}

$tablesResult = $db->query("SHOW TABLES");
if (!$tablesResult) {
    exit("Failed to fetch tables: {$db->error}");
}

while ($tableRow = $tablesResult->fetch_array()) {
    $tableName = $tableRow[0];
    $columnsResult = $db->query("SHOW COLUMNS FROM `{$tableName}`");
    if (!$columnsResult) {
        echo "Failed to fetch columns for table {$tableName}: {$db->error}" . PHP_EOL;
        continue;
    }

    $tableIndexes = getIndexes($db, $tableName);

    while ($column = $columnsResult->fetch_assoc()) {
        $columnName = $column['Field'];
        $columnKey = $column['Key'];

        foreach ($tableIndexes as $indexName => $indexColumns) {
            if ($indexColumns === [$columnName]) {
                continue 2;
            }
        }

        // Only consider columns ending with '_id' or in the predefined list
        if (substr($columnName, -3) !== '_id' && !in_array($columnName, COLUMNS_TO_INDEX, true)) {
            continue;
        }

        if ($columnKey === 'PRI' || $columnKey === 'UNI') {
            continue;
        }

        addIndex($db, $tableName, $columnName);
    }

    $columnsResult->close();
}

addCompositeIndex($db, DB_PREFIX . 'product', ['status', 'date_available']);
addCompositeIndex($db, DB_PREFIX . 'product', ['sort_order', 'date_added']);
addCompositeIndex($db, DB_PREFIX . 'product_discount', ['product_id', 'customer_group_id']);
addCompositeIndex($db, DB_PREFIX . 'product_discount', ['date_start', 'date_end']);
addCompositeIndex($db, DB_PREFIX . 'product_special', ['product_id', 'customer_group_id']);
addCompositeIndex($db, DB_PREFIX . 'product_special', ['date_start', 'date_end']);
addCompositeIndex($db, DB_PREFIX . 'review', ['product_id', 'status']);

$tablesResult->close();
$db->close();