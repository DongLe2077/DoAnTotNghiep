<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_deletion_receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('job_id')->primary();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->char('confirmation', 64);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_deletion_receipts');
    }
};
