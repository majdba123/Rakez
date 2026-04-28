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
            if (! Schema::hasColumn('executive_director_line_user', 'value_target')) {
                $table->decimal('value_target', 14, 2)->default(0)->after('user_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('executive_director_line_user')) {
            return;
        }

        Schema::table('executive_director_line_user', function (Blueprint $table) {
            if (Schema::hasColumn('executive_director_line_user', 'value_target')) {
                $table->dropColumn('value_target');
            }
        });
    }
};
