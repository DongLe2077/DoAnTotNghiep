<?php

namespace App\Console\Commands;

use App\Support\MariaDbSchemaColumns;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;
use Throwable;

class VerifyRentalDatabase extends Command
{
    protected $signature = 'rental:verify-database {--database= : Explicit target database, defaults to rental_management}';

    protected $description = 'Verify the application schema and registered Eloquent mappings using read-only queries';

    public function handle(): int
    {
        try {
            $connection = DB::connection();
            $database = $connection->getDatabaseName();
            $snapshot = json_decode(file_get_contents(database_path('schema/rental_management.json')), true, flags: JSON_THROW_ON_ERROR);
            $models = config('rental.models');

            $isMariaDb = $connection->getDriverName() === 'mariadb';
            if ($database !== ($this->option('database') ?: $snapshot['database']) || ! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
                throw new RuntimeException('Use the explicitly selected MySQL or MariaDB database for this verification.');
            }

            $tables = $connection->select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME != 'migrations' ORDER BY TABLE_NAME", [$database]);
            $actualTables = array_column($tables, 'TABLE_NAME');
            $expectedTables = array_keys($models);
            if (in_array('scheduled_deletions', $actualTables, true)) {
                $actualTables = array_values(array_diff($actualTables, ['scheduled_deletions']));
            }
            $payosTables = [
                'invoice_payos_checkouts' => ['id', 'invoice_id', 'order_code', 'amount', 'invoice_version', 'status', 'link_id', 'checkout_url', 'expires_at', 'checked_at', 'created_at', 'updated_at'],
                'invoice_payos_transfers' => ['id', 'checkout_id', 'reference_key', 'reference', 'amount', 'paid_at', 'payment_id', 'review_reason', 'created_at'],
            ];
            $hasPayos = array_intersect($actualTables, array_keys($payosTables)) !== [];
            if ($hasPayos) {
                foreach ($payosTables as $table => $columns) {
                    if ($connection->getSchemaBuilder()->getColumnListing($table) !== $columns) {
                        throw new RuntimeException("Incomplete PayOS migration: {$table}");
                    }
                }
                $actualTables = array_values(array_diff($actualTables, array_keys($payosTables)));
            }
            // Information-schema collation may ignore underscores; compare sets in PHP order.
            sort($actualTables, SORT_STRING);
            sort($expectedTables, SORT_STRING);
            if ($actualTables !== $expectedTables) {
                throw new RuntimeException('The application tables do not match the registered models.');
            }

            $rows = [];
            $relationCount = 0;
            foreach ($snapshot['tables'] as $table) {
                if ($table['TABLE_TYPE'] !== 'BASE TABLE') {
                    continue;
                }

                $tableName = $table['TABLE_NAME'];
                $modelClass = $models[$tableName];
                $model = new $modelClass;
                $columns = array_column($table['columns'], 'COLUMN_NAME');
                if ($model->getTable() !== $tableName || $model->getKeyName() !== 'id') {
                    throw new RuntimeException("Incorrect table or primary key mapping: {$tableName}");
                }

                $liveColumns = $connection->select('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', [$database, $tableName]);
                $expectedColumns = array_map(fn (array $column) => array_intersect_key($column, array_flip(['COLUMN_NAME', 'COLUMN_TYPE', 'IS_NULLABLE', 'EXTRA'])), $table['columns']);
                $actualColumns = array_map(fn (object $column) => (array) $column, $liveColumns);
                if ($isMariaDb) {
                    $jsonColumns = array_column(array_filter($expectedColumns, fn (array $column): bool => $column['COLUMN_TYPE'] === 'json'), 'COLUMN_NAME');
                    $checks = array_column($connection->select('SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ?', [$database, $tableName]), 'CHECK_CLAUSE');
                    $actualColumns = MariaDbSchemaColumns::normalize($actualColumns, $jsonColumns, $checks);
                    $expectedColumns = MariaDbSchemaColumns::normalize($expectedColumns);
                }
                if ($actualColumns != $expectedColumns) {
                    throw new RuntimeException("Schema has changed since the snapshot: {$tableName}");
                }

                $liveKeys = $connection->select('SELECT CONSTRAINT_NAME, COLUMN_NAME, ORDINAL_POSITION, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION', [$database, $tableName]);
                $actualKeys = array_map(fn (object $key) => (array) $key, $liveKeys);
                $expectedKeys = $table['keys'];
                $keyOrder = fn (array $left, array $right): int => [$left['CONSTRAINT_NAME'], (int) $left['ORDINAL_POSITION']] <=> [$right['CONSTRAINT_NAME'], (int) $right['ORDINAL_POSITION']];
                usort($actualKeys, $keyOrder);
                usort($expectedKeys, $keyOrder);
                if ($actualKeys != $expectedKeys) {
                    throw new RuntimeException("Keys or foreign key scopes have changed: {$tableName}");
                }

                foreach ($model->getFillable() as $column) {
                    if (! in_array($column, $columns, true)) {
                        throw new RuntimeException("Unknown fillable column: {$tableName}.{$column}");
                    }
                }
                foreach ($table['columns'] as $column) {
                    if (preg_match('/(?:STORED|VIRTUAL) GENERATED/', $column['EXTRA']) && $model->isFillable($column['COLUMN_NAME'])) {
                        throw new RuntimeException("Generated column must be guarded: {$tableName}.{$column['COLUMN_NAME']}");
                    }
                }
                if ($model->usesTimestamps()) {
                    foreach ([$model->getCreatedAtColumn(), $model->getUpdatedAtColumn()] as $column) {
                        if ($column !== null && ! in_array($column, $columns, true)) {
                            throw new RuntimeException("Unknown timestamp column: {$tableName}.{$column}");
                        }
                    }
                }

                $relations = [];
                $belongsTo = [];
                foreach ((new ReflectionClass($model))->getMethods() as $method) {
                    $type = $method->getReturnType();
                    if ($method->getDeclaringClass()->getName() !== $model::class || ! $type instanceof ReflectionNamedType || ! is_subclass_of($type->getName(), Relation::class)) {
                        continue;
                    }
                    $relation = $model->{$method->getName()}();
                    $relation->getQuery()->limit(0)->get();
                    $relations[] = $method->getName();
                    if ($relation instanceof BelongsTo) {
                        $belongsTo[] = [$relation->getForeignKeyName(), $relation->getRelated()->getTable(), $relation->getOwnerKeyName()];
                    }
                }
                foreach ($table['keys'] as $key) {
                    // Each composite FK starts with a globally unique primary id.
                    // MySQL enforces the remaining occupancy/group scope columns.
                    if ($key['REFERENCED_TABLE_NAME'] !== null && $key['ORDINAL_POSITION'] === 1 && ! in_array([$key['COLUMN_NAME'], $key['REFERENCED_TABLE_NAME'], $key['REFERENCED_COLUMN_NAME']], $belongsTo, true)) {
                        throw new RuntimeException("Missing belongsTo relation: {$tableName}.{$key['CONSTRAINT_NAME']}");
                    }
                }

                $model->newQuery()->with($relations)->limit(1)->get()->each(fn ($record) => $record->attributesToArray());
                $relationCount += count($relations);
                $rows[] = [$tableName, class_basename($model), count($columns), count($relations), 'OK'];
            }

            $this->table(['Table', 'Model', 'Columns', 'Relations', 'Status'], $rows);
            $viewCount = count(array_filter($snapshot['tables'], fn (array $table) => $table['TABLE_TYPE'] === 'VIEW'));
            $tableCount = count($models);
            $this->info("Verified {$tableCount} application tables and {$relationCount} relations; {$viewCount} views are documented in the schema snapshot. No data was modified.");
            if ($hasPayos) {
                $this->info('Verified columns for both PayOS tables added after the schema snapshot.');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
