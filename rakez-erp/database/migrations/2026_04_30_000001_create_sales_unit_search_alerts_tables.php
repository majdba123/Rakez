<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_unit_search_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_staff_id')->constrained('users')->cascadeOnDelete();
            $table->string('client_name')->nullable();
            $table->string('client_mobile', 50);
            $table->string('client_email')->nullable();
            $table->boolean('client_sms_opt_in')->default(false);
            $table->timestamp('client_sms_opted_in_at')->nullable();
            $table->string('client_sms_locale', 20)->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->string('unit_type')->nullable();
            $table->string('floor', 50)->nullable();
            $table->decimal('min_price', 16, 2)->nullable();
            $table->decimal('max_price', 16, 2)->nullable();
            $table->decimal('min_area', 12, 2)->nullable();
            $table->decimal('max_area', 12, 2)->nullable();
            $table->unsignedTinyInteger('min_bedrooms')->nullable();
            $table->unsignedTinyInteger('max_bedrooms')->nullable();
            $table->string('query_text')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamp('last_system_notified_at')->nullable();
            $table->timestamp('last_sms_attempted_at')->nullable();
            $table->timestamp('last_sms_sent_at')->nullable();
            $table->text('last_sms_error')->nullable();
            $table->foreignId('last_matched_unit_id')->nullable()->constrained('contract_units')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->string('last_twilio_sid')->nullable();
            $table->text('last_delivery_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sales_staff_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->index(['city_id', 'district_id']);
            $table->index('project_id');
        });

        Schema::create('sales_unit_search_alert_deliveries', function (Blueprint $table) {
            $table->id();
            // Short FK name: auto name exceeds MySQL 64-char identifier limit.
            $table->unsignedBigInteger('sales_unit_search_alert_id');
            $table->foreign('sales_unit_search_alert_id', 'su_alert_del_salert_fk')
                ->references('id')
                ->on('sales_unit_search_alerts')
                ->cascadeOnDelete();
            $table->foreignId('contract_unit_id')->constrained('contract_units')->cascadeOnDelete();
            $table->foreignId('user_notification_id')->nullable()->constrained('user_notifications')->nullOnDelete();
            $table->string('client_mobile', 50);
            $table->string('delivery_channel', 40)->default('sms');
            $table->string('status', 30)->default('pending');
            $table->string('twilio_sid')->nullable();
            $table->text('skip_reason')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['sales_unit_search_alert_id', 'contract_unit_id', 'delivery_channel'],
                'su_search_alert_delivery_unique'
            );
            $table->index(['delivery_channel', 'status']);
            $table->index('contract_unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_unit_search_alert_deliveries');
        Schema::dropIfExists('sales_unit_search_alerts');
    }
};
