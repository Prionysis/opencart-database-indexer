# OpenCart Indexer

This PHP script automatically adds indexes to your OpenCart database tables to improve query performance. It creates single-column indexes on columns ending with _id and some common fields, plus composite indexes on specific table-column sets.


## How to use
    
1. Place the script in your project root directory.
2. Make sure your `config.php` is correctly set up to connect to your OpenCart database.
3. Run the script from the command line using `php indexer.php`

## Note
Make sure to back up your database before running this script, as it will modify the schema by adding indexes.