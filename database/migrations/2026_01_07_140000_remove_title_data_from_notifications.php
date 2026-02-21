<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove from admin_notifications
        Schema::table('admin_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('admin_notifications', 'title')) {
                $table->dropColumn('title');
            }
            if (Schema::hasColumn('admin_notifications', 'data')) {
                $table->dropColumn('data');
            }
        });

        // Remove from user_notifications
        Schema::table('user_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('user_notifications', 'title')) {
                $table->dropColumn('title');
            }
            if (Schema::hasColumn('user_notifications', 'data')) {
                $table->dropColumn('data');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table) {
            $table->string('title')->nullable();
            $table->json('data')->nullable();
        });

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->string('title')->nullable();
            $table->json('data')->nullable();
        });
    }
};

