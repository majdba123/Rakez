<?php

namespace App\Services\Accounting;

use App\Models\ProjectReward;
use App\Models\ProjectRewardRecipient;
use App\Models\SalesReservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectRewardService
{
    private const AMOUNT_EPSILON = 0.02;

    public function __construct(
        private ProjectRewardPreviewService $previewService,
        private AccountingNotificationService $notificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function index(array $filters = []): mixed
    {
        $query = ProjectReward::query()
            ->with(['contract', 'salesReservation', 'recipients.user'])
            ->orderByDesc('id');

        if (!empty($filters['contract_id'])) {
            $query->where('contract_id', $filters['contract_id']);
        }

        if (!empty($filters['sales_reservation_id'])) {
            $query->where('sales_reservation_id', $filters['sales_reservation_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->paginate(max(1, min(100, $perPage)));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function generate(SalesReservation $reservation, User $user, array $options = []): ProjectReward
    {
        $preview = $this->previewService->preview($reservation, $options);
        $this->assertPreviewCanBePersisted($preview);

        $reward = DB::transaction(function () use ($reservation, $user, $options, $preview) {
            $existing = ProjectReward::query()
                ->where('sales_reservation_id', $reservation->id)
                ->latest('id')
                ->first();

            if ($existing instanceof ProjectReward) {
                if (in_array($existing->status, [ProjectReward::STATUS_APPROVED, ProjectReward::STATUS_PAID], true)) {
                    throw ValidationException::withMessages([
                        'project_reward' => 'This reservation already has an approved or paid project reward.',
                    ]);
                }

                if ($existing->status === ProjectReward::STATUS_PENDING) {
                    $existing->delete();
                }
            }

            $reward = ProjectReward::query()->create([
                'sales_reservation_id' => (int) $preview['reservation_id'],
                'contract_id' => (int) $preview['contract_id'],
                'project_reward_setting_id' => (int) $preview['project_reward_setting_id'],
                'calculation_mode' => $preview['calculation_mode'],
                'calculation_base_amount' => $preview['calculation_base_amount'],
                'reward_percentage' => $preview['reward_percentage'],
                'source' => $preview['source'],
                'base_amount' => $preview['base_amount'],
                'tax_enabled' => $preview['tax_enabled'],
                'vat_percentage' => $preview['vat_percentage'],
                'vat_amount' => $preview['vat_amount'],
                'total_amount' => $preview['total_amount'],
                'distribution_pool_amount' => $preview['distribution_pool_amount'],
                'status' => ProjectReward::STATUS_PENDING,
                'notes' => $options['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            foreach ($preview['recipients'] as $row) {
                ProjectRewardRecipient::query()->create([
                    'project_reward_id' => $reward->id,
                    'user_id' => (int) $row['user_id'],
                    'recipient_type' => $row['recipient_type'] ?? null,
                    'sales_reservation_id' => $row['sales_reservation_id'] ?? $reward->sales_reservation_id,
                    'sales_reservation_participant_id' => $row['sales_reservation_participant_id'] ?? null,
                    'source_scope' => $row['source_scope'] ?? null,
                    'source_type' => $row['source_type'] ?? null,
                    'percentage' => $row['percentage'] ?? null,
                    'amount' => $row['amount'],
                    'status' => ProjectRewardRecipient::STATUS_PENDING,
                ]);
            }

            return $reward->fresh($this->eagerLoads());
        });

        $this->notificationService->notifyRewardGenerated($reward);

        return $reward;
    }

    public function approve(ProjectReward $reward, User $user): ProjectReward
    {
        if (!$reward->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only pending project rewards can be approved.',
            ]);
        }

        return DB::transaction(function () use ($reward, $user) {
            $now = now();

            $reward->update([
                'status' => ProjectReward::STATUS_APPROVED,
                'approved_by' => $user->id,
                'approved_at' => $now,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            $reward->recipients()->update([
                'status' => ProjectRewardRecipient::STATUS_APPROVED,
                'approved_by' => $user->id,
                'approved_at' => $now,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            return $reward->fresh($this->eagerLoads());
        });
    }

    public function reject(ProjectReward $reward, User $user, ?string $reason = null): ProjectReward
    {
        if (!$reward->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only pending project rewards can be rejected.',
            ]);
        }

        return DB::transaction(function () use ($reward, $user, $reason) {
            $now = now();

            $reward->update([
                'status' => ProjectReward::STATUS_REJECTED,
                'rejected_by' => $user->id,
                'rejected_at' => $now,
                'rejection_reason' => $reason,
            ]);

            $reward->recipients()->update([
                'status' => ProjectRewardRecipient::STATUS_REJECTED,
                'rejected_by' => $user->id,
                'rejected_at' => $now,
                'rejection_reason' => $reason,
            ]);

            return $reward->fresh($this->eagerLoads());
        });
    }

    public function markAsPaid(ProjectReward $reward, User $user): ProjectReward
    {
        if (!$reward->isApproved()) {
            throw ValidationException::withMessages([
                'status' => 'Only approved project rewards can be marked as paid.',
            ]);
        }

        return DB::transaction(function () use ($reward, $user) {
            $now = now();

            $reward->update([
                'status' => ProjectReward::STATUS_PAID,
                'paid_by' => $user->id,
                'paid_at' => $now,
            ]);

            $reward->recipients()->update([
                'status' => ProjectRewardRecipient::STATUS_PAID,
                'paid_by' => $user->id,
                'paid_at' => $now,
            ]);

            return $reward->fresh($this->eagerLoads());
        });
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    protected function assertPreviewCanBePersisted(array $preview): void
    {
        $poolAmount = (float) ($preview['distribution_pool_amount'] ?? 0);
        if ($poolAmount <= 0) {
            throw ValidationException::withMessages([
                'distribution_pool_amount' => 'Reward distribution pool amount must be greater than 0.',
            ]);
        }

        $unresolvedTotal = round(array_sum(array_column($preview['unresolved'] ?? [], 'amount')), 2);
        if ($unresolvedTotal > self::AMOUNT_EPSILON) {
            throw ValidationException::withMessages([
                'unresolved' => 'Project reward has unresolved allocations. Resolve recipients before generation.',
            ]);
        }

        $recipientTotal = round(array_sum(array_column($preview['recipients'] ?? [], 'amount')), 2);
        if ($recipientTotal > $poolAmount + self::AMOUNT_EPSILON) {
            throw ValidationException::withMessages([
                'recipients' => 'Project reward recipient total exceeds the distribution pool amount.',
            ]);
        }

        if ($recipientTotal <= 0) {
            throw ValidationException::withMessages([
                'recipients' => 'Project reward must have at least one resolved recipient.',
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function eagerLoads(): array
    {
        return [
            'contract',
            'salesReservation',
            'recipients.user',
            'creator',
            'approver',
            'payer',
        ];
    }
}
