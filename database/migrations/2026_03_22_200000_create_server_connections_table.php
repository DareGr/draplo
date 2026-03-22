<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->text('encrypted_api_key')->nullable();
            $table->string('server_id')->nullable();
            $table->string('server_ip', 45)->nullable();
            $table->string('coolify_url', 500)->nullable();
            $table->text('coolify_api_key')->nullable();
            $table->string('server_name', 255);
            $table->string('server_spec', 100)->nullable();
            $table->string('status', 50)->default('pending');
            $table->timestamp('last_health_check')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_connections');
    }
};
