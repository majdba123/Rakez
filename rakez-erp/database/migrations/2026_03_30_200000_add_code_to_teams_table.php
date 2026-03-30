<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('code', 32)->nullable()->unique()->after('id');
        });

        $rows = DB::table('teams')->orderBy('id')->get();
        foreach ($rows as $row) {
            $code = 'T' . str_pad((string) $row->id, 6, '0', STR_PAD_LEFT);
            DB::table('teams')->where('id', $row->id)->update(['code' => $code]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
