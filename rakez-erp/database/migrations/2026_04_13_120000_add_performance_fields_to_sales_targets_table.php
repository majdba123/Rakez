<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Performance targets: unit count + monetary value (not inventory assignment).
     */
    public function up(): void
    {
        Schema::table('sales_targets', function (Blueprint $table) {
            $table->unsignedInteger('must_sell_units_count')->nullable()->after('leader_notes');
            $table->decimal('assigned_target_value', 15, 2)->nullable()->after('must_sell_units_count');
        });

        $this->backfillPerformanceFields();
    }

    public function down(): void
    {
        Schema::table('sales_targets', function (Blueprint $table) {
            $table->dropColumn(['must_sell_units_count', 'assigned_target_value']);
        });
    }

    protected function backfillPerformanceFields(): void
    {
        DB::table('sales_targets')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $id = (int) $row->id;
                $contractId = (int) $row->contract_id;

                $pivotIds = DB::table('sales_target_units')
                    ->where('sales_target_id', $id)
                    ->pluck('contract_unit_id')
                    ->map(fn ($v) => (int) $v)
                    ->all();

                $unitIds = $pivotIds;
                if (! empty($row->contract_unit_id)) {
                    $unitIds[] = (int) $row->contract_unit_id;
                }
                $unitIds = array_values(array_unique(array_filter($unitIds)));

                $countFromUnits = count($unitIds);
                $mustSell = max(1, $countFromUnits);

                $sumPrices = 0.0;
                if ($unitIds !== []) {
                    $sumPrices = (float) DB::table('contract_units')
                        ->whereIn('id', $unitIds)
                        ->sum('price');
                }

                if ($sumPrices <= 0 && $contractId > 0) {
                    $avg = DB::table('contract_units')->where('contract_id', $contractId)->avg('price');
                    $sumPrices = (float) (($avg ?? 0) * $mustSell);
                }

                DB::table('sales_targets')->where('id', $id)->update([
                    'must_sell_units_count' => $mustSell,
                    'assigned_target_value' => round($sumPrices, 2),
                ]);
            }
        });
    }
};
