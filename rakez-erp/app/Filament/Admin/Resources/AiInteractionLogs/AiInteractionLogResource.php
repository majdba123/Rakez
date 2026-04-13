<?php

namespace App\Filament\Admin\Resources\AiInteractionLogs;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\AiInteractionLogs\Pages\ListAiInteractionLogs;
use App\Models\AiInteractionLog;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AiInteractionLogResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = AiInteractionLog::class;

    protected static string $viewPermission = 'ai.calls.view';

    protected static ?string $slug = 'ai-interaction-logs';

    protected static ?string $navigationLabel = 'AI Interaction Logs';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string | \UnitEnum | null $navigationGroup = 'AI & Knowledge';

    protected static ?int $navigationSort = 11;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user')->latest('created_at'))
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.name')->label('User')->placeholder('-'),
                TextColumn::make('section')->badge()->placeholder('-'),
                TextColumn::make('request_type')->badge()->placeholder('-'),
                TextColumn::make('model')->placeholder('-'),
                TextColumn::make('total_tokens')->numeric(),
                TextColumn::make('latency_ms')->label('Latency (ms)')->numeric(decimalPlaces: 2),
                IconColumn::make('had_error')->label('Error')->boolean(),
                TextColumn::make('created_at')->dateTime(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiInteractionLogs::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('AI & Knowledge', static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('AI & Knowledge', static::$viewPermission);
    }
}
