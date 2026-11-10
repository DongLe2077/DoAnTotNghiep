<?php

namespace Tests\Unit;

use App\Support\MariaDbSchemaColumns;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MariaDbSchemaColumnsTest extends TestCase
{
    private function column(string $type, string $extra = '', string $nullable = 'NO'): array
    {
        return ['COLUMN_NAME' => 'value', 'COLUMN_TYPE' => $type, 'IS_NULLABLE' => $nullable, 'EXTRA' => $extra];
    }

    public function test_mysql_and_mariadb_integer_and_timestamp_metadata_are_equivalent(): void
    {
        $this->assertSame(MariaDbSchemaColumns::normalize([$this->column('bigint unsigned', 'DEFAULT_GENERATED on update CURRENT_TIMESTAMP')]),
            MariaDbSchemaColumns::normalize([$this->column('bigint(20) unsigned', 'on update current_timestamp()')]));
    }

    public function test_json_longtext_requires_a_validity_constraint_for_the_same_column(): void
    {
        $this->assertSame('json', MariaDbSchemaColumns::normalize([$this->column('longtext')], ['value'], ['json_valid(`value`)'])[0]['COLUMN_TYPE']);
        $this->expectException(RuntimeException::class);
        MariaDbSchemaColumns::normalize([$this->column('longtext')], ['value'], ['json_valid(`another_column`)']);
    }

    public function test_money_precision_nullability_and_generated_columns_remain_distinct(): void
    {
        $normal = MariaDbSchemaColumns::normalize([$this->column('decimal(18,2)', 'STORED GENERATED')]);
        $this->assertNotSame($normal, MariaDbSchemaColumns::normalize([$this->column('decimal(18,4)', 'STORED GENERATED')]));
        $this->assertNotSame($normal, MariaDbSchemaColumns::normalize([$this->column('decimal(18,2)', 'STORED GENERATED', 'YES')]));
        $this->assertNotSame($normal, MariaDbSchemaColumns::normalize([$this->column('decimal(18,2)')]));
        $this->assertSame('longtext', MariaDbSchemaColumns::normalize([$this->column('longtext')])[0]['COLUMN_TYPE']);
    }

    public function test_a_weakened_json_check_is_not_treated_as_mysql_json(): void
    {
        $this->expectException(RuntimeException::class);
        MariaDbSchemaColumns::normalize([$this->column('longtext')], ['value'], ['json_valid(`value`) OR 1']);
    }
}
