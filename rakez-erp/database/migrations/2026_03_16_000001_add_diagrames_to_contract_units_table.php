<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add diagrames column for unit diagrams (optional).
     */
    public function up(): void
    {
        Schema::table('contract_units', function (Blueprint $table) {
            $table->text('diagrames')->nullable()->after('description_ar')->comment('Unit diagrams (URLs or paths)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_units', function (Blueprint $table) {
            $table->dropColumn('diagrames');
        });
    }
};
