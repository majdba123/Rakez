<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('governance_audit_logs', function (Blueprint $table) {
            $table->index('actor_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('governance_audit_logs', function (Blueprint $table) {
            $table->dropIndex(['actor_id']);
            $table->dropIndex(['created_at']);
        });
    }
};
