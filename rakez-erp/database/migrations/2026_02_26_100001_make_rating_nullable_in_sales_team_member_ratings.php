<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * مدير المبيعات يمكنه التعليق على الموظف دون تقييم نجوم.
     */
    public function up(): void
    {
        Schema::table('sales_team_member_ratings', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable()->comment('1-5 أو null عند التعليق فقط')->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales_team_member_ratings', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable(false)->change();
        });
    }
};
