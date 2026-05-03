<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_infos', function (Blueprint $table) {
            if (! Schema::hasColumn('contract_infos', 'first_party_bank_account_name')) {
                $table->string('first_party_bank_account_name')->nullable()->after('first_party_email');
            }
            if (! Schema::hasColumn('contract_infos', 'first_party_bank_name')) {
                $table->string('first_party_bank_name')->nullable()->after('first_party_bank_account_name');
            }
            if (! Schema::hasColumn('contract_infos', 'first_party_iban_number')) {
                $table->string('first_party_iban_number', 34)->nullable()->after('first_party_bank_name');
            }
            if (! Schema::hasColumn('contract_infos', 'second_party_bank_account_name')) {
                $table->string('second_party_bank_account_name')->nullable()->after('second_party_email');
            }
            if (! Schema::hasColumn('contract_infos', 'second_party_bank_name')) {
                $table->string('second_party_bank_name')->nullable()->after('second_party_bank_account_name');
            }
            if (! Schema::hasColumn('contract_infos', 'second_party_iban_number')) {
                $table->string('second_party_iban_number', 34)->nullable()->after('second_party_bank_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contract_infos', function (Blueprint $table) {
            $columns = [
                'first_party_bank_account_name',
                'first_party_bank_name',
                'first_party_iban_number',
                'second_party_bank_account_name',
                'second_party_bank_name',
                'second_party_iban_number',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('contract_infos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
