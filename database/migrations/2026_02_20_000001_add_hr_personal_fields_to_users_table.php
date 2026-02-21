<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds birthday_hijri (تاريخ الميلاد هجري) and gender (الجنس) for HR employee profile per spec.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('birthday_hijri', 50)->nullable()->after('birthday');
            $table->string('gender', 20)->nullable()->after('nationality');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['birthday_hijri', 'gender']);
        });
    }
};
