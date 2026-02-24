<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('last_ai_call_id')->nullable()->after('assigned_to');
            $table->unsignedSmallInteger('ai_call_count')->default(0)->after('last_ai_call_id');
            $table->string('ai_qualification_status')->nullable()->after('ai_call_count');
            $table->text('ai_call_notes')->nullable()->after('ai_qualification_status');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['last_ai_call_id', 'ai_call_count', 'ai_qualification_status', 'ai_call_notes']);
        });
    }
};
