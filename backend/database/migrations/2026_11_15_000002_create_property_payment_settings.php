<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('property_payment_settings', function (Blueprint $table) {
            $table->foreignId('property_id')->primary()->constrained('rental_properties')->restrictOnDelete();
            $table->text('credentials'); $table->boolean('enabled')->default(false); $table->timestamps();
        });
    }
    public function down(): void {
        if (\Illuminate\Support\Facades\DB::table('property_payment_settings')->exists()) throw new RuntimeException('Payment settings exist; reviewed backup required.');
        Schema::dropIfExists('property_payment_settings');
    }
};
