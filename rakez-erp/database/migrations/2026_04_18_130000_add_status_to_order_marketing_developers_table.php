<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_marketing_developers', function (Blueprint $table) {
            $table->string('status', 32)->default('pending')->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('order_marketing_developers', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
