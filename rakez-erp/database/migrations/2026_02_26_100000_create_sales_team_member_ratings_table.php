<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * تقييم مدير فريق المبيعات لأعضاء فريقه (1-5 نجوم).
     */
    public function up(): void
    {
        Schema::create('sales_team_member_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leader_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('member_id')->constrained('users')->onDelete('cascade');
            $table->unsignedTinyInteger('rating')->comment('1-5');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['leader_id', 'member_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_team_member_ratings');
    }
};
