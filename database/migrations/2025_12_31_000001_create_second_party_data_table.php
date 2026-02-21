<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('second_party_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');

            // رابط اوراق العقار - Real Estate Papers URL
            $table->string('real_estate_papers_url')->nullable();

            // رابط مستندات المخطاطات والتجهيزات - Plans and Equipment Documents URL
            $table->string('plans_equipment_docs_url')->nullable();

            // رابط شعار المشروع - Project Logo URL
            $table->string('project_logo_url')->nullable();

            // رابط الاسعار والوحرات - Prices and Units URL
            $table->string('prices_units_url')->nullable();

            // رخصة التسويق - Marketing License URL
            $table->string('marketing_license_url')->nullable();

            // قسم معلن - Advertiser Section URL
            $table->string('advertiser_section_url')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('second_party_data');
    }
};

