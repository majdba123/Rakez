<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * If the old `sales_target_executive_directors` table exists, move to standalone `executive_director_lines` (line_type + value only).
     */
    public function up(): void
    {
        if (Schema::hasTable('executive_director_lines')) {
            if (Schema::hasTable('sales_target_executive_directors')) {
                Schema::drop('sales_target_executive_directors');
            }

            return;
        }

        if (! Schema::hasTable('sales_target_executive_directors')) {
            Schema::create('executive_director_lines', function (Blueprint $table) {
                $table->id();
                $table->string('line_type', 100);
                $table->decimal('value', 15, 2)->nullable();
                $table->string('status', 32)->default('pending');
                $table->timestamps();
            });

            return;
        }

        Schema::create('executive_director_lines', function (Blueprint $table) {
            $table->id();
            $table->string('line_type', 100);
            $table->decimal('value', 15, 2)->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamps();
        });

        $rows = DB::table('sales_target_executive_directors')->get();
        foreach ($rows as $row) {
            DB::table('executive_director_lines')->insert([
                'line_type' => $row->line_type,
                'value' => $row->value,
                'status' => (isset($row->status) && is_string($row->status) && $row->status !== '') ? $row->status : 'pending',
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop('sales_target_executive_directors');
    }

    public function down(): void
    {
        if (! Schema::hasTable('executive_director_lines')) {
            return;
        }
        if (Schema::hasTable('sales_target_executive_directors')) {
            return;
        }

        Schema::create('sales_target_executive_directors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_target_id')->constrained('sales_targets')->cascadeOnDelete();
            $table->string('line_type', 100);
            $table->decimal('value', 15, 2)->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamps();
        });
    }
};
