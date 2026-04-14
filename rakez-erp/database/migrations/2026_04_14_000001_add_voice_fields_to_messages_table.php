<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('type', 20)->default('text')->after('sender_id');
            $table->string('voice_path')->nullable()->after('message');
            $table->unsignedSmallInteger('voice_duration_seconds')->nullable()->after('voice_path');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['type', 'voice_path', 'voice_duration_seconds']);
        });
    }
};
