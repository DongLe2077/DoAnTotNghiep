<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('body');
            $table->string('audience', 10);
            $table->json('room_ids');
            $table->json('room_snapshot');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $key = $table->string('idempotency_key', 64);
            if (DB::getDriverName() === 'mysql') {
                $key->charset('ascii')->collation('ascii_bin');
            }
            $table->unique('idempotency_key');
            $table->char('request_fingerprint', 64);
            $table->dateTime('created_at');
        });
        if (DB::getDriverName() === 'sqlite') {
            // Native ADD COLUMN retains inline UNIQUE constraints in the existing demo fixture.
            DB::statement('ALTER TABLE app_notifications ADD COLUMN announcement_id INTEGER REFERENCES announcements(id) ON DELETE RESTRICT');
        } else {
            Schema::table('app_notifications', fn (Blueprint $table) => $table->foreignId('announcement_id')->nullable()->constrained()->restrictOnDelete());
        }
        Schema::table('app_notifications', fn (Blueprint $table) => $table->unique(['announcement_id', 'user_id'], 'announcement_recipient_unique'));
    }

    public function down(): void
    {
        if (DB::table('announcements')->exists()) {
            throw new RuntimeException('Cannot discard sent announcements and read receipts.');
        }
        Schema::table('app_notifications', fn (Blueprint $table) => $table->dropUnique('announcement_recipient_unique'));
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE app_notifications DROP COLUMN announcement_id');
        } else {
            Schema::table('app_notifications', fn (Blueprint $table) => $table->dropConstrainedForeignId('announcement_id'));
        }
        Schema::dropIfExists('announcements');
    }
};
