<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safe_write_proposal_commits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action_key', 120);
            $table->string('idempotency_key', 128);
            $table->string('commit_token', 64)->unique();
            $table->string('proposal_fingerprint', 64);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'action_key', 'idempotency_key'], 'sw_prop_commits_uid_act_idem_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safe_write_proposal_commits');
    }
};
