<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('path', 200)->unique();
            $table->char('sha256', 64);
            $table->string('mime_type', 50);
            $table->unsignedInteger('byte_size');
            $table->dateTime('created_at');
            $table->unique(['maintenance_request_id', 'sha256'], 'maintenance_photo_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_photos');
    }
};
