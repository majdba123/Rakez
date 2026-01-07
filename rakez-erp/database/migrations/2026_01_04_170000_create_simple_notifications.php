<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin Notifications - إشعارات المدراء
        // user_id = ID of admin who receives the notification
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Admin user
            $table->string('title')->nullable();
            $table->text('message');
            $table->enum('status', ['pending', 'read'])->default('pending');
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // User Notifications - إشعارات المستخدمين
        // user_id = NULL means PUBLIC notification (for everyone)
        // user_id = specific user ID means private notification
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade'); // NULL = public
            $table->string('title')->nullable();
            $table->text('message');
            $table->enum('status', ['pending', 'read'])->default('pending');
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('admin_notifications');
    }
};

