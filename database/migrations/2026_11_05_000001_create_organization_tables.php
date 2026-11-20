<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the M1 tenancy tables.
 *
 * - `organizations` with soft-delete
 * - `organization_members` with a partial unique index for exactly one owner
 * - `organization_invitations` with hashed tokens and a 7-day expiry
 * - `audit_logs` as the append-only audit trail
 *
 * `down()` drops the tables in reverse dependency order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('slug', 80)->unique();
            $table->foreignUuid('owner_id')->constrained('users')->restrictOnDelete();
            $table->jsonb('settings')->nullable(false);
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
        });

        Schema::create('organization_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 16);
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();

            $table->unique(['organization_id', 'user_id']);
            $table->index(['user_id']);
        });

        // Exactly one owner per organization (partial unique index).
        DB::statement(
            "CREATE UNIQUE INDEX one_owner_per_organization
             ON organization_members (organization_id)
             WHERE role = 'owner'"
        );

        Schema::create('organization_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('email', 254);
            $table->string('role', 16);
            $table->string('token_hash', 64)->unique();
            $table->foreignUuid('invited_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('expires_at')->nullable(false);
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();

            $table->index(['organization_id', 'email']);
        });

        // One pending invitation per organization + email.
        DB::statement(
            'CREATE UNIQUE INDEX one_pending_invitation_per_email
             ON organization_invitations (organization_id, lower(email))
             WHERE accepted_at IS NULL'
        );

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 16);
            $table->string('action', 64);
            $table->string('resource_type', 64);
            $table->uuid('resource_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->jsonb('metadata')->nullable(false);
            $table->timestampTz('created_at')->nullable(false);
        });

        DB::statement(
            'CREATE INDEX audit_logs_organization_created ON audit_logs (organization_id, created_at DESC)'
        );

        DB::statement(
            'CREATE INDEX audit_logs_resource ON audit_logs (resource_type, resource_id)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS audit_logs_resource');
        DB::statement('DROP INDEX IF EXISTS audit_logs_organization_created');
        Schema::dropIfExists('audit_logs');

        DB::statement('DROP INDEX IF EXISTS one_pending_invitation_per_email');
        Schema::dropIfExists('organization_invitations');

        DB::statement('DROP INDEX IF EXISTS one_owner_per_organization');
        Schema::dropIfExists('organization_members');

        Schema::dropIfExists('organizations');
    }
};
