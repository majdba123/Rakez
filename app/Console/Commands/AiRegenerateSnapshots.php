<?php

namespace App\Console\Commands;

use App\Services\AI\CatalogService;
use App\Services\AI\CapabilityResolver;
use Illuminate\Console\Command;

class AiRegenerateSnapshots extends Command
{
    protected $signature = 'ai:regenerate-snapshots {--role= : Specific role to regenerate (default: all)}';

    protected $description = 'Regenerate golden test snapshots from the current catalog state';

    public function handle(): int
    {
        $catalog = new CatalogService(new CapabilityResolver());
        $targetRole = $this->option('role');

        $roles = $targetRole ? [$targetRole] : array_keys($catalog->roleMap());
        $snapshotDir = base_path('tests/Golden/snapshots');

        if (! is_dir($snapshotDir)) {
            mkdir($snapshotDir, 0755, true);
        }

        foreach ($roles as $role) {
            $sections = $catalog->sectionsForRole($role);
            $permissions = $catalog->permissionsForRole($role);
            $allSections = $catalog->sectionKeys();

            $deniedSections = array_values(array_diff($allSections, array_keys($sections)));

            $snapshot = [
                'role' => $role,
                'allowed_sections' => array_keys($sections),
                'denied_sections' => $deniedSections,
                'permissions_count' => count($permissions),
                'generated_at' => now()->toIso8601String(),
            ];

            $path = "{$snapshotDir}/{$role}.json";
            file_put_contents($path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("تم تحديث snapshot: {$role} ({$path})");
        }

        $this->info('تم تجديد جميع الـ snapshots بنجاح.');

        return self::SUCCESS;
    }
}
