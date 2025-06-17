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

function indexExists(mysqli $db, string $table, string $indexName): bool
{
    $result = $db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$indexName}'");
    return $result && $result->num_rows > 0;
}

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
    if (indexExists($db, $table, $indexName)) {
        echo "Index {$indexName} already exists on {$table}. Skipping." . PHP_EOL;
        return;
    }

    if ($db->query("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$column}`)")) {
        echo "Added index {$indexName} on {$table} ({$column})" . PHP_EOL;
    } else {
        echo "Failed to add index {$indexName} on {$table} ({$column}): {$db->error}" . PHP_EOL;
    }
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

    if ($tableName === DB_PREFIX . 'product') {
        addCompositeIndex($db, $tableName, ['status', 'date_available']);
        addCompositeIndex($db, $tableName, ['sort_order', 'date_added']);
    } elseif ($tableName === DB_PREFIX . 'product_discount' || $tableName === DB_PREFIX . 'product_special') {
        addCompositeIndex($db, $tableName, ['product_id', 'customer_group_id']);
        addCompositeIndex($db, $tableName, ['date_start', 'date_end']);
    } elseif ($tableName === DB_PREFIX . 'product_to_category') {
        addCompositeIndex($db, $tableName, ['category_id', 'product_id']);
    } elseif ($tableName === DB_PREFIX . 'product_filter') {
        addCompositeIndex($db, $tableName, ['product_id', 'filter_id']);
    } elseif ($tableName === DB_PREFIX . 'product_to_store') {
        addCompositeIndex($db, $tableName, ['store_id', 'product_id']);
    } elseif ($tableName === DB_PREFIX . 'product_description') {
        addCompositeIndex($db, $tableName, ['product_id', 'language_id']);
    } elseif ($tableName === DB_PREFIX . 'category_path') {
        addCompositeIndex($db, $tableName, ['category_id', 'path_id']);
    } elseif ($tableName === DB_PREFIX . 'review') {
        addCompositeIndex($db, $tableName, ['product_id', 'status']);
    }

    while ($column = $columnsResult->fetch_assoc()) {
        $columnName = $column['Field'];
        $columnKey = $column['Key'];

        // Only consider columns ending with '_id' or in the predefined list
        if (substr($columnName, -3) !== '_id' && !in_array($columnName, COLUMNS_TO_INDEX, true)) {
            continue;
        }

        // Skip columns already indexed (primary, unique, or regular index)
        if ($columnKey !== '') {
            continue;
        }

        addIndex($db, $tableName, $columnName);
    }

    $columnsResult->close();
}

$tablesResult->close();
$db->close();