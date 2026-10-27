<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait CreatesCashBookSchema
{
    use CreatesBillingSchema;

    private function createCashBookSchema(): void
    {
        $this->createBillingSchema();
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type', 20);
            $table->decimal('amount', 18, 2);
            $table->dateTime('occurred_at');
            $table->string('description', 255);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('recorded_at');
            $table->index(['occurred_at', 'transaction_type']);
        });
        (require database_path('migrations/2026_10_20_000001_add_cash_transaction_history.php'))->up();
        (require database_path('migrations/2026_10_20_000004_create_cash_creation_requests.php'))->up();
    }
}
