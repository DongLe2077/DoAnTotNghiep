<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('rental_properties')->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->unique()->constrained('rooms')->nullOnDelete();
            $table->string('title', 120);
            $table->string('area', 150);
            $table->decimal('area_m2', 7, 2)->nullable();
            $table->text('description');
            $table->string('amenities', 500)->nullable();
            $table->string('fee_notes', 500)->nullable();
            $table->boolean('sharing')->default(false);
            $table->boolean('published')->default(false);
            $table->string('photo_mime', 32)->nullable();
            $table->mediumText('photo_base64')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'id']);
            $table->index(['published', 'id']);
        });

        Schema::create('room_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('rental_properties')->restrictOnDelete();
            $table->foreignId('listing_id')->constrained('room_listings')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 12);
            $table->string('status', 18);
            $table->string('open_key', 60)->nullable()->unique();
            $table->unsignedSmallInteger('people');
            $table->string('contact_name', 150);
            $table->string('contact_phone', 20);
            $table->string('note', 500)->nullable();
            $table->string('owner_note', 500)->nullable();
            $table->dateTime('preferred_at');
            $table->dateTime('appointment_at')->nullable();
            $table->foreignId('occupancy_id')->nullable()->constrained('occupancies')->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->boolean('membership_approved_at_move_in')->default(false);
            $table->dateTime('moved_in_at')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'kind', 'status', 'id']);
            $table->index(['user_id', 'id']);
        });

        Schema::create('room_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('room_requests')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 18);
            $table->dateTime('appointment_at')->nullable();
            $table->dateTime('occurred_at');
            $table->index(['request_id', 'id']);
        });
    }

    public function down(): void
    {
        if (DB::table('room_requests')->exists() || DB::table('room_listings')->exists()) {
            throw new RuntimeException('Marketplace contains data. Restore a reviewed backup instead.');
        }

        Schema::dropIfExists('room_request_events');
        Schema::dropIfExists('room_requests');
        Schema::dropIfExists('room_listings');
    }
};
