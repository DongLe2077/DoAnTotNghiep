<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_deletions', function (Blueprint $table) {
            $table->id();
            $table->string('record_type', 32);
            $table->unsignedBigInteger('record_id');
            $table->string('label');
            $table->json('records');
            $table->dateTime('purge_at')->index();
            $table->text('problem')->nullable();
            $table->json('photo_paths')->nullable();
            $table->unique(['record_type', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_deletions');
    }
};
