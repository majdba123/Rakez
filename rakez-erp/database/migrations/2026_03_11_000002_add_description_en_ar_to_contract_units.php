<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add description_en and description_ar for bilingual unit descriptions.
     */
    public function up(): void
    {
        Schema::table('contract_units', function (Blueprint $table) {
            $table->text('description_en')->nullable()->after('facade')->comment('Description (English)');
            $table->text('description_ar')->nullable()->after('description_en')->comment('الوصف (عربي)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_units', function (Blueprint $table) {
            $table->dropColumn(['description_en', 'description_ar']);
        });
    }
};
