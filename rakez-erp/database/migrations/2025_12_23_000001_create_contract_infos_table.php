<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('contract_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->string('contract_number')->nullable();
            $table->string('first_party_name')->nullable();
            $table->string('first_party_cr_number')->nullable();
            $table->string('first_party_signatory')->nullable();
            $table->string('first_party_phone')->nullable();
            $table->string('first_party_email')->nullable();
            $table->dateTime('gregorian_date')->nullable();
            $table->string('hijri_date')->nullable();
            $table->string('contract_city')->nullable();
            $table->integer('agreement_duration_days')->nullable();
            $table->decimal('commission_percent', 8, 2)->nullable();
            $table->string('commission_from')->nullable();
            $table->string('agency_number')->nullable();
            $table->dateTime('agency_date')->nullable();
            $table->decimal('avg_property_value', 16, 2)->nullable();
            $table->dateTime('release_date')->nullable();
            $table->string('second_party_name')->nullable();
            $table->text('second_party_address')->nullable();
            $table->string('second_party_cr_number')->nullable();
            $table->string('second_party_signatory')->nullable();
            $table->string('second_party_id_number')->nullable();
                        $table->string('second_party_email')->nullable();

            $table->string('second_party_role')->nullable();
            $table->string('second_party_phone')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('contract_infos');
    }
};
