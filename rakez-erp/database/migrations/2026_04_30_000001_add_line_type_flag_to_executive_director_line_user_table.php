<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('executive_director_line_user')) {
            return;
        }

        Schema::table('executive_director_line_user', function (Blueprint $table) {
            if (! Schema::hasColumn('executive_director_line_user', 'line_type_flag')) {
                $table->string('line_type_flag', 100)->nullable()->after('value_target');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('executive_director_line_user')) {
            return;
        }

        Schema::table('executive_director_line_user', function (Blueprint $table) {
            if (Schema::hasColumn('executive_director_line_user', 'line_type_flag')) {
                $table->dropColumn('line_type_flag');
            }
        });
    }
};
