<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('campaign_platform', 20)->nullable()->index()->after('source');
            $table->string('campaign_id')->nullable()->after('campaign_platform');
            $table->string('campaign_type', 50)->nullable()->after('campaign_id');
            $table->unsignedTinyInteger('lead_score')->nullable()->after('campaign_type');
            $table->string('utm_source')->nullable()->after('lead_score');
            $table->string('utm_medium')->nullable()->after('utm_source');
            $table->string('utm_campaign')->nullable()->after('utm_medium');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'campaign_platform',
                'campaign_id',
                'campaign_type',
                'lead_score',
                'utm_source',
                'utm_medium',
                'utm_campaign',
            ]);
        });
    }
};
