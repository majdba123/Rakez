<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_realtime_sessions', function (Blueprint $table) {
            $table->string('bridge_owner_token', 80)->nullable()->after('provider_session_id')->index();
            $table->unsignedBigInteger('bridge_owner_pid')->nullable()->after('bridge_owner_token');
            $table->timestamp('bridge_started_at')->nullable()->after('bridge_owner_pid');
            $table->timestamp('bridge_heartbeat_at')->nullable()->after('bridge_started_at');
        });

        Schema::table('ai_realtime_session_events', function (Blueprint $table) {
            $table->unique(
                ['session_id', 'direction', 'transport_event_id'],
                'ai_rt_events_session_direction_transport_event_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('ai_realtime_session_events', function (Blueprint $table) {
            $table->dropUnique('ai_rt_events_session_direction_transport_event_unique');
        });

        Schema::table('ai_realtime_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'bridge_owner_token',
                'bridge_owner_pid',
                'bridge_started_at',
                'bridge_heartbeat_at',
            ]);
        });
    }
};
