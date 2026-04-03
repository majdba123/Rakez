<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MySQL: extend ENUM. SQLite: Laravel enum becomes a CHECK — rebuild table with string column so new action types work in tests.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE sales_reservation_actions MODIFY COLUMN action_type ENUM('lead_acquisition', 'persuasion', 'closing', 'credit_client_contact') NOT NULL");

            return;
        }

        if ($driver === 'sqlite') {
            Schema::disableForeignKeyConstraints();
            Schema::dropIfExists('sales_reservation_actions');
            Schema::create('sales_reservation_actions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sales_reservation_id')->constrained('sales_reservations')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('action_type', 64);
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['sales_reservation_id', 'created_at'], 'idx_reservation');
            });
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE sales_reservation_actions MODIFY COLUMN action_type ENUM('lead_acquisition', 'persuasion', 'closing') NOT NULL");
        }
    }
};
