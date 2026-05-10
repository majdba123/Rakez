<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_media', function (Blueprint $table) {
            if (!Schema::hasColumn('project_media', 'kind')) {
                $table->string('kind')->nullable()->after('department');
            }
            if (!Schema::hasColumn('project_media', 'note')) {
                $table->text('note')->nullable()->after('kind');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_media', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('project_media', 'kind') ? 'kind' : null,
                Schema::hasColumn('project_media', 'note') ? 'note' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
