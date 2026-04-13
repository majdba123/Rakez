<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_realtime_session_events', function (Blueprint $table) {
            $table->string('direction', 40)->default('internal')->after('sequence');
            $table->string('transport_event_type', 120)->nullable()->after('event_type');
            $table->string('transport_event_id', 191)->nullable()->after('transport_event_type');
            $table->string('error_code', 80)->nullable()->after('transport_event_id');
            $table->timestamp('processed_at')->nullable()->after('payload');

            $table->index(['session_id', 'direction', 'processed_at'], 'ai_rt_events_session_direction_processed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_realtime_session_events', function (Blueprint $table) {
            $table->dropIndex('ai_rt_events_session_direction_processed_idx');
            $table->dropColumn([
                'direction',
                'transport_event_type',
                'transport_event_id',
                'error_code',
                'processed_at',
            ]);
        });
    }
};
