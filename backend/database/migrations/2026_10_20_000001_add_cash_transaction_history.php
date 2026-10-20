<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('revision')->default(0);
            $table->softDeletes();
        });
        Schema::create('cash_transaction_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_transaction_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('revision');
            $table->string('action', 20);
            $table->json('before_data')->nullable();
            $table->json('after_data');
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('reverted_history_id')->nullable()->constrained('cash_transaction_histories')->restrictOnDelete();
            $table->dateTime('created_at');
            $table->unique(['cash_transaction_id', 'revision'], 'cash_history_revision_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transaction_histories');
        Schema::table('cash_transactions', fn (Blueprint $table) => $table->dropColumn(['revision', 'deleted_at']));
    }
};
