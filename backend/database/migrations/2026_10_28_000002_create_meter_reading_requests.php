<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meter_reading_requests', function (Blueprint $table) {
            $table->id();
            $key = $table->string('idempotency_key', 64);
            if (DB::getDriverName() === 'mysql') {
                $key->charset('ascii')->collation('ascii_bin');
            }
            $table->unique('idempotency_key');
            $table->foreignId('occupancy_id')->constrained()->restrictOnDelete();
            $table->foreignId('occupancy_service_id')->constrained()->restrictOnDelete();
            $table->foreignId('meter_reading_id')->nullable()->constrained()->nullOnDelete();
            // Retain the original identity after an allowed deletion of the last reading.
            $table->unsignedBigInteger('original_reading_id');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->json('request_data');
            $table->char('request_fingerprint', 64);
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        if (DB::table('meter_reading_requests')->exists()) {
            throw new RuntimeException('Cannot discard recorded meter request identities. Restore a reviewed backup instead.');
        }
        Schema::dropIfExists('meter_reading_requests');
    }
};
