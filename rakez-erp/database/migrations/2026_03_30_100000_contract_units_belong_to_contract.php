<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contract units are owned by the contract (CSV / inventory), not by second_party_data.
     */
    public function up(): void
    {
        Schema::table('contract_units', function (Blueprint $table) {
            $table->unsignedBigInteger('contract_id')->nullable()->after('id');
        });

        DB::table('contract_units')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $cid = DB::table('second_party_data')
                    ->where('id', $row->second_party_data_id)
                    ->value('contract_id');
                if ($cid) {
                    DB::table('contract_units')->where('id', $row->id)->update(['contract_id' => $cid]);
                }
            }
        });

        Schema::table('contract_units', function (Blueprint $table) {
            $table->dropForeign(['second_party_data_id']);
            $table->dropColumn('second_party_data_id');
        });

        Schema::table('contract_units', function (Blueprint $table) {
            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_units', function (Blueprint $table) {
            $table->dropForeign(['contract_id']);
            $table->unsignedBigInteger('second_party_data_id')->nullable()->after('id');
        });

        DB::table('contract_units')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $spdId = DB::table('second_party_data')
                    ->where('contract_id', $row->contract_id)
                    ->value('id');
                if ($spdId) {
                    DB::table('contract_units')->where('id', $row->id)->update(['second_party_data_id' => $spdId]);
                }
            }
        });

        Schema::table('contract_units', function (Blueprint $table) {
            $table->dropColumn('contract_id');
            $table->foreign('second_party_data_id')->references('id')->on('second_party_data')->cascadeOnDelete();
        });
    }
};
