<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payos_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('order_code')->unique();
            $table->unsignedBigInteger('amount');
            $table->char('invoice_version', 64);
            $table->string('status', 16)->default('pending');
            $table->string('link_id', 64)->nullable()->unique();
            $table->string('checkout_url', 512)->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('checked_at')->nullable();
            $table->timestamps();
            $table->index(['invoice_id', 'id']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('invoice_payos_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_id')->constrained('invoice_payos_checkouts')->restrictOnDelete();
            $table->char('reference_key', 64)->unique();
            $table->string('reference', 128);
            $table->unsignedBigInteger('amount');
            $table->dateTime('paid_at');
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('review_reason', 240)->nullable();
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payos_transfers');
        Schema::dropIfExists('invoice_payos_checkouts');
    }
};
