<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporting_baseline', function (Blueprint $table) {
            $table->dateTime('started_at');
        });
        Schema::create('room_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('rental_properties');
            $table->unsignedBigInteger('room_id');
            $table->string('status', 20);
            $table->dateTime('recorded_at');
            $table->index(['property_id', 'room_id', 'recorded_at']);
        });
        Schema::create('monthly_report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('rental_properties');
            $table->string('month', 7);
            $table->string('digest', 64);
            $table->json('totals');
            $table->dateTime('captured_at');
            $table->unique(['property_id', 'month', 'digest']);
        });
        Schema::create('live_revisions', function (Blueprint $table) {
            $table->string('channel', 100)->primary();
            $table->unsignedBigInteger('revision');
        });
        $start = now()->toDateTimeString();
        DB::table('reporting_baseline')->insert(['started_at' => $start]);
        DB::table('rooms')->whereNotNull('property_id')->orderBy('id')->chunkById(200, function ($rooms) use ($start) {
            DB::table('room_status_events')->insert($rooms->map(fn ($room) => [
                'property_id' => $room->property_id, 'room_id' => $room->id,
                'status' => $room->operational_status, 'recorded_at' => $start,
            ])->all());
        });
    }

    public function down(): void
    {
        foreach (['live_revisions', 'monthly_report_snapshots', 'room_status_events', 'reporting_baseline'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
