<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_request_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('revision');
            $table->string('action', 20);
            $table->string('before_status', 20)->nullable();
            $table->string('after_status', 20);
            $table->text('before_resolution')->nullable();
            $table->text('after_resolution')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_username', 80)->nullable();
            $table->string('actor_role', 20)->nullable();
            $table->dateTime('created_at');
            $table->unique(['maintenance_request_id', 'revision'], 'maintenance_history_revision_unique');
        });
        DB::table('maintenance_requests')->orderBy('id')->chunkById(200, function ($reports) {
            foreach ($reports as $report) {
                DB::transaction(function () use ($report) {
                    $current = DB::table('maintenance_requests')->where('id', $report->id)->lockForUpdate()->first();
                    if ($current === null || DB::table('maintenance_request_histories')->where('maintenance_request_id', $report->id)->exists()) {
                        return;
                    }
                    DB::table('maintenance_request_histories')->insert([
                        'maintenance_request_id' => $current->id, 'revision' => $current->revision, 'action' => 'baseline',
                        'before_status' => null, 'after_status' => $current->status,
                        'before_resolution' => null, 'after_resolution' => $current->resolution,
                        'performed_by' => null, 'actor_username' => null, 'actor_role' => null,
                        'created_at' => now()->startOfSecond(),
                    ]);
                });
            }
        });
    }

    public function down(): void
    {
        if (DB::table('maintenance_request_histories')->exists()) {
            throw new RuntimeException('Cannot discard recorded maintenance history.');
        }
        Schema::dropIfExists('maintenance_request_histories');
    }
};
