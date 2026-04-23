<?php

use App\Models\SecondPartyData;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->boolean('is_complete_second')
                ->default(false)
                ->after('is_closed')
                ->comment('True when all second-party document fields are filled');
        });

        SecondPartyData::query()->each(function (SecondPartyData $row) {
            $row->syncIsCompleteSecondOnContract();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('is_complete_second');
        });
    }
};
