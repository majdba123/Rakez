<?php

namespace App\Console\Commands;

use App\Services\Governance\GovernanceTemporaryPermissionService;
use Illuminate\Console\Command;

class ExpireGovernanceTemporaryPermissionsCommand extends Command
{
    protected $signature = 'governance:expire-temporary-permissions';

    protected $description = 'Mark expired governance temporary permission rows as revoked';

    public function handle(GovernanceTemporaryPermissionService $service): int
    {
        $count = $service->expireDueRows();
        $this->info("Revoked {$count} expired temporary permission grant(s).");

        return self::SUCCESS;
    }
}
