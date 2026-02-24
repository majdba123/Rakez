<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads_platform_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20)->index();
            $table->string('account_id');
            $table->string('account_name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['platform', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_platform_accounts');
    }
};
