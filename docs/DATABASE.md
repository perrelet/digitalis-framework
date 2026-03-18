# Digitalis Framework: Database

Custom database tables, schema versioning, and migrations under the `Digitalis\DB` namespace.

---

## Table of Contents

- [Overview](#overview)
- [Defining a Table](#defining-a-table)
  - [Columns](#columns)
  - [Indexes](#indexes)
- [Defining a Schema](#defining-a-schema)
- [Writing Migrations](#writing-migrations)
  - [Schema_Context](#schema_context)
- [Running Migrations](#running-migrations)
  - [Table_Registry](#table_registry)
  - [Migration_Runner](#migration_runner)
- [App Integration](#app-integration)
- [Logging](#logging)
  - [Option_Migration_Logger](#option_migration_logger)
- [DB_Context](#db_context)
- [Quick Reference](#quick-reference)

---

## Overview

The `Digitalis\DB` system provides structured custom database table management:

- **Tables** declare their columns, indexes, and scope (site vs. network).
- **Schemas** group tables and declare a target version with an ordered list of migrations.
- **Migrations** are versioned classes that run `up()` once and are never re-run.
- **Migration_Runner** checks the installed version against the schema target, acquires a lock, and runs only the needed migrations in order.

All classes live in the `Digitalis\DB` namespace.

```
include/db/
├── table.abstract.php
├── column.final.php
├── index.final.php
├── schema.abstract.php
├── migration.abstract.php
├── schema-context.final.php
├── migration-runner.final.php
├── migration-logger.class.php
├── option-migration-logger.migration-logger.php
├── table-registry.final.php
└── db-context.final.php
```

---

## Defining a Table

Extend `Digitalis\DB\Table` and override the three static properties and two static methods.

```php
namespace Digitalis\DB;

class Log_Table extends Table {

    protected static $slug      = 'my-log';
    protected static $base_name = 'my_plugin_log';
    protected static $scope     = self::SCOPE_SITE; // or SCOPE_NETWORK

    public static function get_columns () : array {
        return [
            new Column('id',         'BIGINT(20) UNSIGNED', nullable: false, auto_increment: true),
            new Column('level',      'VARCHAR(20)',          nullable: false),
            new Column('message',    'TEXT',                 nullable: false),
            new Column('created_at', 'DATETIME',             nullable: false, default: 'CURRENT_TIMESTAMP'),
        ];
    }

    public static function get_indexes () : array {
        return [
            Index::primary(['id']),
            Index::index('level', ['level']),
        ];
    }

}
```

| Property | Description |
|---|---|
| `$slug` | Unique identifier for the table within `Table_Registry`. |
| `$base_name` | Table name without the wpdb prefix (e.g. `my_plugin_log` → `wp_my_plugin_log`). |
| `$scope` | `SCOPE_SITE` uses `$wpdb->prefix`; `SCOPE_NETWORK` uses `$wpdb->base_prefix`. |

### Columns

`Column` is a value object describing one database column.

```php
new Column(
    name:           'status',
    type_sql:       'VARCHAR(50)',
    nullable:       false,         // default false
    default:        'pending',     // default null = no DEFAULT clause
    auto_increment: false          // default false
)
```

Supported default value types:
- `int` / `float` — rendered as bare numeric literals
- `bool` — rendered as `1` / `0`
- `'CURRENT_TIMESTAMP'` — rendered unquoted
- Any other string — single-quoted with internal quotes escaped

### Indexes

`Index` has three static factories:

```php
Index::primary(['id'])                        // PRIMARY KEY (`id`)
Index::unique('slug_unique', ['slug'])        // UNIQUE KEY `slug_unique` (`slug`)
Index::index('status_idx', ['status', 'id']) // KEY `status_idx` (`status`, `id`)
```

---

## Defining a Schema

A `Schema` groups a set of tables under a slug and version, and maps integer version numbers to migration classes.

```php
namespace Digitalis\DB;

class My_Plugin_Schema extends Schema {

    protected static $slug    = 'my-plugin';
    protected static $version = 2;

    public static function get_tables () : array {
        return [
            Log_Table::class,
        ];
    }

    public static function get_migrations () : array {
        return [
            1 => Create_Log_Table_Migration::class,
            2 => Add_Context_Column_Migration::class,
        ];
    }

}
```

- `$slug` — used as the key for the `digitalis_schema_{slug}` option that stores the installed version.
- `$version` — the target version. `Migration_Runner` runs all migrations with keys `> installed` and `<= version`.
- Migration keys **must be integers**. They are `ksort`-ed before running.

---

## Writing Migrations

Each migration is a class extending `Digitalis\DB\Migration` that implements `up()`.

```php
namespace Digitalis\DB;

class Create_Log_Table_Migration extends Migration {

    public function up (Schema_Context $ctx) : void {

        $sql = $ctx->create_table_sql(Log_Table::class);
        $ctx->query($sql);

    }

}

class Add_Context_Column_Migration extends Migration {

    public function up (Schema_Context $ctx) : void {

        $table = $ctx->get_table_name(Log_Table::class);

        if (!$ctx->column_exists($table, 'context')) {
            $ctx->query("ALTER TABLE `{$table}` ADD COLUMN `context` TEXT NULL AFTER `message`");
        }

    }

}
```

Migrations only have an `up()` — there is no `down()`. To revert, write a new migration.

### Schema_Context

`Schema_Context` is passed to every migration. It wraps `wpdb` with safe helpers.

| Method | Description |
|---|---|
| `query(string $sql)` | Run a SQL statement. Throws `RuntimeException` on failure. |
| `get_var(string $sql)` | Return a single value from a query. |
| `get_table_name(string $table_class)` | Resolve a registered table class to its full name (with prefix). |
| `create_table_sql(string $table_class)` | Generate the `CREATE TABLE` SQL for a registered table. |
| `drop_table_sql(string $table_class)` | Generate the `DROP TABLE IF EXISTS` SQL. |
| `drop_table(string $table_class)` | Execute drop immediately. |
| `table_exists(string $table_name)` | Check by raw table name. |
| `registered_table_exists(string $table_class)` | Check by table class. |
| `column_exists(string $table_name, string $column)` | Check if a column exists. |
| `index_exists(string $table_name, string $index_name)` | Check if an index exists. |
| `get_charset_collate()` | Return `$wpdb->get_charset_collate()`. |

Always guard additive migrations with `column_exists()` / `table_exists()` to make them idempotent.

---

## Running Migrations

### Table_Registry

`Table_Registry` holds all table class references. It is passed to `Migration_Runner` and to `Schema_Context`.

```php
$tables = new Table_Registry([
    Log_Table::class,
    // ... other tables
]);

// Or register individually:
$tables->register(Log_Table::class);
```

`Migration_Runner` auto-registers a schema's tables when `migrate_module()` is called, so manual registration is only needed if you need the registry before migration runs.

| Method | Description |
|---|---|
| `register(string $table_class)` | Add a table class. Throws if slug is already registered. |
| `has(string $table_class)` | Check if a table class is registered. |
| `resolve_name(wpdb $wpdb, string $table_class)` | Get the full table name for a registered class. |
| `get_all()` | All registered table classes as a flat array. |
| `get_all_by_slug()` | `['slug' => TableClass::class]` map. |

### Migration_Runner

```php
$tables = new Table_Registry();
$logger = new Option_Migration_Logger();

$runner = new Migration_Runner($tables, $logger);
$runner->migrate_module(My_Plugin_Schema::class);
```

`Migration_Runner` constructor:

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$tables` | `Table_Registry` | required | Registry instance. |
| `$logger` | `?Migration_Logger` | `new Migration_Logger()` | Logger (no-op by default). |
| `$wpdb` | `?wpdb` | `DB_Context::get_wpdb()` | Inject a custom `wpdb` if needed. |

**`migrate_module(string $schema_class)`**

1. Registers the schema's tables into the registry.
2. Reads the installed version from `digitalis_schema_{slug}`.
3. If already at target version, returns immediately.
4. Acquires a lock (`digitalis_schema_lock_{slug}`) with a 120-second TTL to prevent concurrent migrations.
5. Runs each migration in version order, updating the stored version after each one.
6. Releases the lock in a `finally` block.
7. Re-throws any exception after logging.

**`uninstall_module(string $schema_class, bool $drop_tables = false, bool $clear_logger = false)`**

Clears the installed version option. Optionally drops all tables and clears the logger.

---

## App Integration

`App::ensure_schema()` is the intended hook point for running migrations. It is called during `boot()` before any context-specific loading occurs.

Override it in your plugin's `App` subclass:

```php
class My_Plugin extends App {

    public function ensure_schema () : void {

        $tables = new \Digitalis\DB\Table_Registry();
        $logger = new \Digitalis\DB\Option_Migration_Logger('my_plugin_migrations_log');

        (new \Digitalis\DB\Migration_Runner($tables, $logger))
            ->migrate_module(My_Plugin_Schema::class);

    }

}
```

The default `ensure_schema()` in `App` is a no-op stub.

---

## Logging

`Migration_Logger` is the base class — a silent no-op with three methods:

```php
$logger->info('message', ['context' => 'value']);
$logger->warning('message', []);
$logger->error('message', ['error' => $e->getMessage()]);
```

Extend it for custom logging, or use the built-in `Option_Migration_Logger`.

### Option_Migration_Logger

Persists log entries to a WordPress option as a JSON array, capped at a maximum number of entries.

```php
$logger = new Option_Migration_Logger(
    option_key:  'my_plugin_migrations_log', // default: 'digitalis_migrations_log'
    max_entries: 200                          // default: 200
);

// Read stored entries
$entries = $logger->get_entries();
// Each entry: ['ts' => '2026-...', 'level' => 'info', 'message' => '...', 'context' => [...]]

// Clear all entries
$logger->clear();
```

The option is stored with `autoload: false`.

---

## DB_Context

`DB_Context::get_wpdb()` retrieves and filters the global `$wpdb` instance. Used internally but also available for direct use.

```php
$wpdb = \Digitalis\DB\DB_Context::get_wpdb();
```

**Filter: `Digitalis/DB/WPDB`**

Swap the `wpdb` instance used by the entire DB system — for example to use a secondary database connection:

```php
add_filter('Digitalis/DB/WPDB', function ($wpdb) {
    return $my_custom_wpdb; // Must be a wpdb instance
});
```

If the filter returns anything other than a `wpdb` instance a `RuntimeException` is thrown.

---

## Quick Reference

```php
use Digitalis\DB\{
    Table, Column, Index,
    Schema, Migration, Schema_Context,
    Migration_Runner, Table_Registry,
    Migration_Logger, Option_Migration_Logger,
    DB_Context
};

// Define a table
class My_Table extends Table {
    protected static $slug      = 'my-table';
    protected static $base_name = 'my_plugin_table';
    protected static $scope     = self::SCOPE_SITE;
    public static function get_columns () : array { return [
        new Column('id',   'BIGINT(20) UNSIGNED', auto_increment: true),
        new Column('data', 'TEXT', nullable: true),
    ]; }
    public static function get_indexes () : array { return [
        Index::primary(['id']),
    ]; }
}

// Define a schema
class My_Schema extends Schema {
    protected static $slug    = 'my-plugin';
    protected static $version = 1;
    public static function get_tables ()      : array { return [My_Table::class]; }
    public static function get_migrations ()  : array { return [1 => My_V1_Migration::class]; }
}

// Write a migration
class My_V1_Migration extends Migration {
    public function up (Schema_Context $ctx) : void {
        $ctx->query($ctx->create_table_sql(My_Table::class));
    }
}

// Run migrations (typically in App::ensure_schema())
(new Migration_Runner(new Table_Registry(), new Option_Migration_Logger()))
    ->migrate_module(My_Schema::class);

// Get table name anywhere
$name = My_Table::get_name_from_context(); // e.g. 'wp_my_plugin_table'
```
