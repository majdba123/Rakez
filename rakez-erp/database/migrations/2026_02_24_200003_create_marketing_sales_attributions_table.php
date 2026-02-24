<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_sales_attributions', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20)->index();
            $table->string('campaign_id')->nullable()->index();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('sales_reservations')->nullOnDelete();
            $table->decimal('marketing_spend', 14, 4)->default(0);
            $table->decimal('revenue', 14, 4)->default(0);
            $table->string('attribution_model', 20)->default('last_touch');
            $table->timestamps();

            $table->index(['platform', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_sales_attributions');
    }
};
