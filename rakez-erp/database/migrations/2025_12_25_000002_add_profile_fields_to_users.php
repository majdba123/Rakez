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
        Schema::table('users', function (Blueprint $table) {
            $table->string('team')->nullable()->after('type');
            $table->string('identity_number')->nullable()->unique()->after('team');
            $table->date('birthday')->nullable();
            $table->date('date_of_works')->nullable()->after('birthday');
            $table->string('contract_type')->nullable()->after('date_of_works');
            $table->string('iban')->nullable()->after('contract_type');
            $table->decimal('salary', 15, 2)->nullable()->after('iban');
            $table->string('marital_status')->nullable()->after('salary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'marital_status')) $table->dropColumn('marital_status');
            if (Schema::hasColumn('users', 'salary')) $table->dropColumn('salary');
            if (Schema::hasColumn('users', 'iban')) $table->dropColumn('iban');
            if (Schema::hasColumn('users', 'contract_type')) $table->dropColumn('contract_type');
            if (Schema::hasColumn('users', 'date_of_works')) $table->dropColumn('date_of_works');
            if (Schema::hasColumn('users', 'birthday')) $table->dropColumn('birthday');
            if (Schema::hasColumn('users', 'identity_number')) $table->dropColumn('identity_number');
            if (Schema::hasColumn('users', 'team')) $table->dropColumn('team');
        });
    }
};
