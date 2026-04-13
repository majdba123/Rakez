<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Governance\GovernanceAccessService;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$u = User::where('email', 'erp-admin-visual@example.com')->first();
if (! $u) {
    fwrite(STDERR, "no user\n");
    exit(1);
}

$svc = app(GovernanceAccessService::class);
$panel = filament()->getPanel('admin');

echo 'canAccess: ' . ($svc->canAccessPanel($u, $panel) ? 'yes' : 'no') . PHP_EOL;
echo 'hasRole erp_admin: ' . ($u->hasRole('erp_admin') ? 'yes' : 'no') . PHP_EOL;
echo 'has admin.panel.access: ' . ($u->hasPermissionTo('admin.panel.access') ? 'yes' : 'no') . PHP_EOL;
