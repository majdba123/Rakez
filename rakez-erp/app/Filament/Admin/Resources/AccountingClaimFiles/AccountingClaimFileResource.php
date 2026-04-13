<?php

namespace App\Filament\Admin\Resources\AccountingClaimFiles;

use App\Filament\Admin\Resources\AccountingClaimFiles\Pages\ListAccountingClaimFiles;
use App\Filament\Admin\Resources\AccountingClaimFiles\Pages\ViewAccountingClaimFile;
use App\Filament\Admin\Resources\ClaimFiles\ClaimFileResource;

class AccountingClaimFileResource extends ClaimFileResource
{
    protected static ?string $slug = 'accounting-claim-files';

    protected static ?string $navigationLabel = 'Claim Files';

    protected static string | \UnitEnum | null $navigationGroup = 'Accounting & Finance';

    protected static ?int $navigationSort = 8;

    protected static function governanceGroupForClaimFiles(): string
    {
        return 'Accounting & Finance';
    }

    protected static function claimFilesViewPermission(): string
    {
        return 'accounting.claim_files.view';
    }

    protected static function claimFilesManagePermission(): string
    {
        return 'accounting.claim_files.manage';
    }

    protected static function claimFilePdfGeneratedAuditEvent(): string
    {
        return 'governance.accounting.claim_file.pdf_generated';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountingClaimFiles::route('/'),
            'view' => ViewAccountingClaimFile::route('/{record}'),
        ];
    }
}
