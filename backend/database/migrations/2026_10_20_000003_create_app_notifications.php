<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', fn (Blueprint $table) => $table->unsignedBigInteger('revision')->default(0));
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 40);
            $table->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('maintenance_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('dedupe_key', 64)->unique();
            $table->json('data');
            $table->dateTime('read_at')->nullable();
            $table->dateTime('created_at');
            $table->index(['user_id', 'read_at', 'id'], 'notifications_inbox_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
        Schema::table('maintenance_requests', fn (Blueprint $table) => $table->dropColumn('revision'));
    }
};
