<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When set, this media is considered "after editing" (final) and shown in marketing
     * project media_links. Editing workflow can set this when the asset is finalized.
     */
    public function up(): void
    {
        Schema::table('project_media', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('department');
        });

        \DB::table('project_media')->whereNull('approved_at')->update([
            'approved_at' => \DB::raw('updated_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('project_media', function (Blueprint $table) {
            $table->dropColumn('approved_at');
        });
    }
};
