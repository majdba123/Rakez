<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('second_party_data', function (Blueprint $table) {
            if (!Schema::hasColumn('second_party_data', 'advertiser_section_expiry_date')) {
                $table->date('advertiser_section_expiry_date')
                    ->nullable()
                    ->after('advertiser_section_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('second_party_data', function (Blueprint $table) {
            if (Schema::hasColumn('second_party_data', 'advertiser_section_expiry_date')) {
                $table->dropColumn('advertiser_section_expiry_date');
            }
        });
    }
};
