<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occupancy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_stay_id')->constrained('tenant_stays')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 16);
            $table->string('rejection_reason', 500)->nullable();
            $table->unsignedBigInteger('pending_occupancy_id')->nullable()
                ->storedAs("CASE WHEN status = 'pending' THEN occupancy_id ELSE NULL END")->unique();
            $table->timestamps();
            $table->index(['occupancy_id', 'status']);
            $table->index(['requested_by', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_requests');
    }
};
