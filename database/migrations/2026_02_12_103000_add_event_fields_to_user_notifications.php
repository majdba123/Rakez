<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('user_notifications', 'event_type')) {
                $table->string('event_type', 100)->nullable()->after('message');
                $table->index('event_type');
            }

            if (!Schema::hasColumn('user_notifications', 'context')) {
                $table->json('context')->nullable()->after('event_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('user_notifications', 'context')) {
                $table->dropColumn('context');
            }

            if (Schema::hasColumn('user_notifications', 'event_type')) {
                $table->dropIndex(['event_type']);
                $table->dropColumn('event_type');
            }
        });
    }
};
