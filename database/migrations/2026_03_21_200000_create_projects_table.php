<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 100);
            $table->string('template_slug', 100)->nullable();
            $table->text('description')->nullable();
            $table->jsonb('wizard_data')->nullable();
            $table->jsonb('generation_output')->nullable();
            $table->string('skeleton_version', 20)->nullable();
            $table->string('input_hash', 64)->nullable();
            $table->string('github_repo_url', 500)->nullable();
            $table->string('github_repo_name', 200)->nullable();
            $table->string('coolify_app_id', 100)->nullable();
            $table->string('coolify_db_id', 100)->nullable();
            $table->string('deploy_url', 500)->nullable();
            $table->string('custom_domain', 255)->nullable();
            $table->string('status', 50)->default('draft');
            $table->timestamp('exported_at')->nullable();
            $table->timestamp('deployed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'idx_projects_user');
            $table->index('input_hash', 'idx_projects_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
