<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill total_area_m2 as area + private_area_m2 for existing contract_units.
     * From now on, ContractUnit model keeps total_area_m2 in sync on save.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('
                UPDATE contract_units
                SET total_area_m2 = COALESCE(CAST(area AS DECIMAL(12,2)), 0) + COALESCE(private_area_m2, 0)
                WHERE deleted_at IS NULL
            ');
        } else {
            DB::table('contract_units')
                ->whereNull('deleted_at')
                ->get()
                ->each(function ($row) {
                    $area = is_numeric($row->area) ? (float) $row->area : 0;
                    $private = isset($row->private_area_m2) ? (float) $row->private_area_m2 : 0;
                    DB::table('contract_units')
                        ->where('id', $row->id)
                        ->update(['total_area_m2' => $area + $private]);
                });
        }
    }

    /**
     * Reverse the migration (no-op; we don't revert computed values).
     */
    public function down(): void
    {
        // no-op
    }
};
