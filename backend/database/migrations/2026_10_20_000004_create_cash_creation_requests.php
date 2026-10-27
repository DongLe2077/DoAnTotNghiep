<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_creation_requests', function (Blueprint $table) {
            $table->id();
            $key = $table->string('idempotency_key', 64);
            if (DB::getDriverName() === 'mysql') {
                $key->charset('ascii')->collation('ascii_bin');
            }
            $table->unique('idempotency_key', 'cash_creation_key_unique');
            $table->foreignId('cash_transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->json('request_data');
            $table->char('request_fingerprint', 64);
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_creation_requests');
    }
};
