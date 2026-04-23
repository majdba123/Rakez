<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manager_employee_reviews', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->after('manager_id');
            $table->text('comment')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('manager_employee_reviews', function (Blueprint $table) {
            $table->dropColumn('rating');
            $table->text('comment')->nullable(false)->change();
        });
    }
};
