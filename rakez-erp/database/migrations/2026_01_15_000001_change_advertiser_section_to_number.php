<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change advertiser_section_url from URL string to number
 * تغيير قسم المعلن من رابط إلى رقم
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('second_party_data', function (Blueprint $table) {
            // Change from string (URL) to string that stores number (like "125712612")
            $table->string('advertiser_section_url', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('second_party_data', function (Blueprint $table) {
            $table->string('advertiser_section_url', 500)->nullable()->change();
        });
    }
};

