<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\Deposit;
use App\Models\User;

class CommissionTestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating test commission data...');

        // Create a commission
        $commission = Commission::factory()->create([
            'final_selling_price' => 1000000,
            'commission_percentage' => 2.5,
            'status' => 'pending',
        ]);

        $this->command->info("✓ Commission created: ID {$commission->id}, Net Amount: {$commission->net_amount} SAR");

        // Create distributions
        $user1 = User::where('type', 'sales')->first() ?? User::factory()->create(['type' => 'sales']);
        $user2 = User::where('type', 'sales')->skip(1)->first() ?? User::factory()->create(['type' => 'sales']);
        $user3 = User::where('type', 'sales')->skip(2)->first() ?? User::factory()->create(['type' => 'sales']);

        $dist1 = CommissionDistribution::factory()->create([
            'commission_id' => $commission->id,
            'user_id' => $user1->id,
            'type' => 'lead_generation',
            'percentage' => 30,
            'status' => 'pending',
        ]);
        $dist1->calculateAmount();
        $dist1->save();

        $dist2 = CommissionDistribution::factory()->create([
            'commission_id' => $commission->id,
            'user_id' => $user2->id,
            'type' => 'persuasion',
            'percentage' => 25,
            'status' => 'pending',
        ]);
        $dist2->calculateAmount();
        $dist2->save();

        $dist3 = CommissionDistribution::factory()->create([
            'commission_id' => $commission->id,
            'user_id' => $user3->id,
            'type' => 'closing',
            'percentage' => 20,
            'status' => 'pending',
        ]);
        $dist3->calculateAmount();
        $dist3->save();

        $this->command->info("✓ Created 3 distributions (Lead: {$dist1->amount} SAR, Persuasion: {$dist2->amount} SAR, Closing: {$dist3->amount} SAR)");

        // Create deposits
        $deposit1 = Deposit::factory()->create([
            'amount' => 5000,
            'status' => 'received',
            'commission_source' => 'owner',
        ]);

        $deposit2 = Deposit::factory()->create([
            'amount' => 3000,
            'status' => 'confirmed',
            'commission_source' => 'buyer',
        ]);

        $deposit3 = Deposit::factory()->create([
            'amount' => 2000,
            'status' => 'pending',
            'commission_source' => 'owner',
        ]);

        $this->command->info("✓ Created 3 deposits: {$deposit1->amount} SAR (received), {$deposit2->amount} SAR (confirmed), {$deposit3->amount} SAR (pending)");

        // Create more commissions for analytics
        Commission::factory()->count(5)->create();
        $this->command->info("✓ Created 5 additional commissions for analytics");

        // Create more deposits
        Deposit::factory()->count(10)->create();
        $this->command->info("✓ Created 10 additional deposits");

        $this->command->info('');
        $this->command->info('Test data created successfully!');
        $this->command->info('');
        $this->command->info('Summary:');
        $this->command->info('- Total Commissions: ' . Commission::count());
        $this->command->info('- Total Distributions: ' . CommissionDistribution::count());
        $this->command->info('- Total Deposits: ' . Deposit::count());
        $this->command->info('- Total Commission Value: ' . Commission::sum('net_amount') . ' SAR');
        $this->command->info('- Total Deposits Value: ' . Deposit::sum('amount') . ' SAR');
    }
}
