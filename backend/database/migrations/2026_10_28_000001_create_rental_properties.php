<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_properties', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('address', 500);
            $table->string('contact_name', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 254)->nullable();
            $table->string('bank_name', 150)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_account_name', 150)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('rental_properties')->exists()) {
            throw new RuntimeException('Cannot discard configured property information. Restore a reviewed backup instead.');
        }
        Schema::dropIfExists('rental_properties');
    }
};
