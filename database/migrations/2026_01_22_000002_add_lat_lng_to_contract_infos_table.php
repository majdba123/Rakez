<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_infos', function (Blueprint $table) {
            if (!Schema::hasColumn('contract_infos', 'lat')) {
                $table->decimal('lat', 10, 7)->nullable()->after('contract_city');
            }
            if (!Schema::hasColumn('contract_infos', 'lng')) {
                $table->decimal('lng', 10, 7)->nullable()->after('lat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contract_infos', function (Blueprint $table) {
            if (Schema::hasColumn('contract_infos', 'lng')) {
                $table->dropColumn('lng');
            }
            if (Schema::hasColumn('contract_infos', 'lat')) {
                $table->dropColumn('lat');
            }
        });
    }
};


