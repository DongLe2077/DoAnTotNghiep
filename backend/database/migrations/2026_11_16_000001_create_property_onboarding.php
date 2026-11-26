<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A login can now exist before it is associated with a rental profile.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $hasLegacyCheck = DB::table('information_schema.table_constraints')
                ->where('constraint_schema', DB::connection()->getDatabaseName())
                ->where('table_name', 'users')->where('constraint_name', 'ck_users_profile')
                ->where('constraint_type', 'CHECK')->exists();
            if ($hasLegacyCheck) {
                $serverVersion = DB::selectOne('SELECT VERSION() AS version')->version;
                $isMariaDB = str_contains(strtolower($serverVersion), 'mariadb');
                DB::statement($isMariaDB
                    ? 'ALTER TABLE users DROP CONSTRAINT ck_users_profile'
                    : 'ALTER TABLE users DROP CHECK ck_users_profile');
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('full_name', 150)->nullable();
            $table->string('contact_phone', 20)->nullable();
        });

        Schema::table('rental_properties', function (Blueprint $table) {
            $table->string('photo_path')->nullable();
        });

        Schema::create('property_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('rental_properties')->restrictOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->char('code_suffix', 4);
            $table->unsignedSmallInteger('max_approvals');
            $table->unsignedSmallInteger('approvals')->default(0);
            $table->dateTime('expires_at');
            $table->dateTime('revoked_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['property_id', 'id']);
        });

        Schema::create('property_join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('rental_properties')->restrictOnDelete();
            $table->foreignId('invitation_id')->constrained('property_invitations')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            // Null for completed requests so decisions remain in history.
            $table->string('pending_key', 45)->nullable()->unique();
            $table->string('status', 16);
            $table->dateTime('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['property_id', 'status', 'id']);
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        if (DB::table('property_join_requests')->exists() || DB::table('property_invitations')->exists()
            || DB::table('users')->whereNotNull('full_name')->exists()
            || DB::table('rental_properties')->whereNotNull('photo_path')->exists()) {
            throw new RuntimeException('Onboarding has stored accounts or invitations. Restore a reviewed backup.');
        }

        Schema::dropIfExists('property_join_requests');
        Schema::dropIfExists('property_invitations');
        Schema::table('rental_properties', fn (Blueprint $table) => $table->dropColumn('photo_path'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['full_name', 'contact_phone']));
    }
};
