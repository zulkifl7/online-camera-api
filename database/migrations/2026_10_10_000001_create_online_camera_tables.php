<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->string('role')->default('staff');
            $table->json('permissions');
            $table->unsignedInteger('auth_version')->default(1);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('display_name');
            $table->string('username')->unique();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('password');
            $table->boolean('must_change_password')->default(true);
            $table->string('status')->default('active')->index();
            $table->foreignUuid('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->unsignedInteger('auth_version')->default(1);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->date('event_date')->nullable();
            $table->string('timezone')->nullable();
            $table->string('status')->default('active')->index();
            $table->boolean('uploads_enabled')->default(true);
            $table->uuid('common_guard')->nullable()->unique();
            $table->foreignUuid('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'type']);
            $table->unique(['client_id', 'name']);
        });

        Schema::create('camera_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('collection_id')->constrained('collections')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->text('token_encrypted');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('expires_at')->nullable();
            $table->foreignUuid('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignUuid('rotated_from_id')->nullable()->constrained('camera_links')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['collection_id', 'is_active']);
        });

        Schema::create('photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('collection_id')->constrained('collections')->cascadeOnDelete();
            $table->string('storage_key')->unique();
            $table->string('thumbnail_storage_key')->nullable();
            $table->string('preview_storage_key')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 80);
            $table->unsignedBigInteger('file_size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('upload_status')->default('pending')->index();
            $table->string('failure_reason')->nullable();
            $table->timestamp('client_hidden_at')->nullable()->index();
            $table->foreignUuid('client_hidden_by')->nullable()->constrained('clients')->nullOnDelete();
            $table->timestamp('permanently_deleted_at')->nullable()->index();
            $table->foreignUuid('permanently_deleted_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
            $table->index(['collection_id', 'created_at']);
        });

        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('camera_link_id')->constrained('camera_links')->cascadeOnDelete();
            $table->string('idempotency_key');
            $table->foreignUuid('photo_id')->nullable()->constrained('photos')->nullOnDelete();
            $table->timestamps();

            $table->unique(['camera_link_id', 'idempotency_key']);
        });

        Schema::create('download_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('mode');
            $table->string('status')->index();
            $table->json('photo_ids');
            $table->string('file_name')->nullable();
            $table->string('archive_storage_key')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('actor_type');
            $table->uuid('actor_id')->nullable();
            $table->string('action')->index();
            $table->string('target_type')->nullable();
            $table->uuid('target_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['target_type', 'target_id']);
            $table->index(['actor_type', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('download_jobs');
        Schema::dropIfExists('upload_sessions');
        Schema::dropIfExists('photos');
        Schema::dropIfExists('camera_links');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('admins');
    }
};
