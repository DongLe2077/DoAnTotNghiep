<?php

namespace App\Support;

use RuntimeException;

final class MariaDbSchemaColumns
{
    /** @param array<int, array<string, mixed>> $columns */
    public static function normalize(array $columns, array $jsonColumns = [], array $checks = []): array
    {
        return array_map(function (array $column) use ($jsonColumns, $checks): array {
            $type = strtolower($column['COLUMN_TYPE']);
            $type = preg_replace('/\b(tinyint|smallint|mediumint|int|bigint)\(\d+\)/', '$1', $type);
            if (in_array($column['COLUMN_NAME'], $jsonColumns, true) && $type === 'longtext') {
                $validityCheck = static function (string $check) use ($column): bool {
                    $expression = strtolower(preg_replace('/[\s`]+/', '', $check));
                    while (str_starts_with($expression, '(') && str_ends_with($expression, ')')) {
                        $expression = substr($expression, 1, -1);
                    }

                    return $expression === 'json_valid('.strtolower($column['COLUMN_NAME']).')';
                };
                if (! array_filter($checks, $validityCheck)) {
                    throw new RuntimeException('MariaDB JSON validity constraint is missing: '.$column['COLUMN_NAME']);
                }
                $type = 'json';
            }
            $column['COLUMN_TYPE'] = $type;
            $extra = strtolower(str_replace('DEFAULT_GENERATED', '', $column['EXTRA']));
            $extra = preg_replace('/\bcurrent_timestamp\(\)/', 'current_timestamp', $extra);
            $column['EXTRA'] = trim(preg_replace('/\s+/', ' ', $extra));

            return $column;
        }, $columns);
    }
}
