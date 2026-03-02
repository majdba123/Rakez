<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * حقول تفاصيل الوحدة للواجهة: غرف، حمامات، مساحة خاصة، واجهة
     */
    public function up(): void
    {
        Schema::table('contract_units', function (Blueprint $table) {
            $table->unsignedTinyInteger('bedrooms')->nullable()->after('floor')->comment('عدد الغرف');
            $table->unsignedTinyInteger('bathrooms')->nullable()->after('bedrooms')->comment('عدد الحمامات');
            $table->decimal('private_area_m2', 10, 2)->nullable()->after('bathrooms')->comment('المساحة الخاصة / الشرفة');
            $table->decimal('total_area_m2', 10, 2)->nullable()->after('private_area_m2')->comment('إجمالي المساحة م²');
            $table->string('facade', 100)->nullable()->after('total_area_m2')->comment('الواجهة / الاتجاه');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_units', function (Blueprint $table) {
            $table->dropColumn(['bedrooms', 'bathrooms', 'private_area_m2', 'total_area_m2', 'facade']);
        });
    }
};
