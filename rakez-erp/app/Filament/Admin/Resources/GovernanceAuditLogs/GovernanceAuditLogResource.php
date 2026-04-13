<?php

namespace App\Filament\Admin\Resources\GovernanceAuditLogs;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Resources\GovernanceAuditLogs\Pages\ListGovernanceAuditLogs;
use App\Filament\Admin\Resources\GovernanceAuditLogs\Pages\ViewGovernanceAuditLog;
use App\Models\GovernanceAuditLog;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GovernanceAuditLogResource extends Resource
{
    use HasGovernanceAuthorization;

    protected static ?string $model = GovernanceAuditLog::class;

    protected static ?string $slug = 'governance-audit';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Governance Audit';

    protected static string | \UnitEnum | null $navigationGroup = 'Governance Observability';

    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('actor')->latest('created_at'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Recorded At')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('event')
                    ->badge()
                    ->searchable(),
                TextColumn::make('actor.name')
                    ->label('Actor')
                    ->default('System')
                    ->searchable(),
                TextColumn::make('subject_type_label')
                    ->label('Subject')
                    ->state(fn (GovernanceAuditLog $record): string => static::subjectTypeLabel($record)),
                TextColumn::make('subject_id')
                    ->label('Subject ID')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('Event Type')
                    ->options(fn (): array => GovernanceAuditLog::query()
                        ->distinct()
                        ->pluck('event', 'event')
                        ->sort()
                        ->all()),
                SelectFilter::make('subject_type')
                    ->label('Subject Type')
                    ->options(fn (): array => GovernanceAuditLog::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->pluck('subject_type')
                        ->sort()
                        ->mapWithKeys(fn (string $fqcn): array => [$fqcn => class_basename($fqcn)])
                        ->all()),
                SelectFilter::make('event_category')
                    ->label('Category')
                    ->options([
                        'mutation' => 'Mutations (create/update/delete)',
                        'grant' => 'Grants (permission/role sync)',
                        'oversight' => 'Business Oversight (credit/projects)',
                        'access' => 'Access events',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $category = $data['value'] ?? null;

                        if (! $category) {
                            return $query;
                        }

                        return match ($category) {
                            'mutation' => $query->where(fn (Builder $q) => $q
                                ->where('event', 'like', '%.created')
                                ->orWhere('event', 'like', '%.updated')
                                ->orWhere('event', 'like', '%.deleted')
                                ->orWhere('event', 'like', '%.restored')),
                            'grant' => $query->where(fn (Builder $q) => $q
                                ->where('event', 'like', '%permission%')
                                ->orWhere('event', 'like', '%role%')
                                ->orWhere('event', 'like', '%grant%')
                                ->orWhere('event', 'like', '%sync%')),
                            'oversight' => $query->where(fn (Builder $q) => $q
                                ->where('event', 'like', 'governance.credit.%')
                                ->orWhere('event', 'like', 'governance.projects.%')
                                ->orWhere('event', 'like', 'governance.accounting.%')
                                ->orWhere('event', 'like', 'governance.sales.%')
                                ->orWhere('event', 'like', 'governance.hr.%')
                                ->orWhere('event', 'like', 'governance.marketing.%')
                                ->orWhere('event', 'like', 'governance.inventory.%')),
                            'access' => $query->where('event', 'like', '%access%'),
                            default => $query,
                        };
                    }),
                SelectFilter::make('actor_id')
                    ->label('Actor')
                    ->relationship('actor', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('created_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('From'),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Event')
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Recorded At')
                        ->dateTime(),
                    TextEntry::make('event')
                        ->badge(),
                    TextEntry::make('actor.name')
                        ->label('Actor')
                        ->default('System'),
                    TextEntry::make('actor.email')
                        ->label('Actor Email')
                        ->placeholder('-'),
                    TextEntry::make('subject_type')
                        ->label('Subject Type')
                        ->formatStateUsing(fn (?string $state): string => blank($state) ? '-' : class_basename($state)),
                    TextEntry::make('subject_id')
                        ->label('Subject ID')
                        ->placeholder('-'),
                    TextEntry::make('payload_ip')
                        ->label('IP Address')
                        ->state(fn (GovernanceAuditLog $record): string => (string) ($record->payload['ip_address'] ?? '-'))
                        ->hidden(fn (GovernanceAuditLog $record): bool => ! isset($record->payload['ip_address'])),
                ])
                ->columns(2),
            Section::make('Payload')
                ->schema(fn (): array => static::payloadSchema()),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGovernanceAuditLogs::route('/'),
            'view' => ViewGovernanceAuditLog::route('/{record}'),
        ];
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('Governance Observability', 'admin.audit.view');
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('Governance Observability', 'admin.audit.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    protected static function subjectTypeLabel(GovernanceAuditLog $record): string
    {
        return blank($record->subject_type) ? '-' : class_basename($record->subject_type);
    }

    protected static function payloadSchema(): array
    {
        return [
            KeyValueEntry::make('payload_before')
                ->label('Before')
                ->state(fn (GovernanceAuditLog $record): array => static::flattenPayloadKey($record, 'before'))
                ->columnSpanFull()
                ->hidden(fn (GovernanceAuditLog $record): bool => ! is_array($record->payload['before'] ?? null)),
            KeyValueEntry::make('payload_after')
                ->label('After')
                ->state(fn (GovernanceAuditLog $record): array => static::flattenPayloadKey($record, 'after'))
                ->columnSpanFull()
                ->hidden(fn (GovernanceAuditLog $record): bool => ! is_array($record->payload['after'] ?? null)),
            KeyValueEntry::make('payload_full')
                ->label('Details')
                ->state(fn (GovernanceAuditLog $record): array => static::payloadState($record))
                ->columnSpanFull()
                ->hidden(fn (GovernanceAuditLog $record): bool => is_array($record->payload['before'] ?? null) && is_array($record->payload['after'] ?? null)),
        ];
    }

    protected static function flattenPayloadKey(GovernanceAuditLog $record, string $key): array
    {
        $data = $record->payload[$key] ?? [];

        if (! is_array($data)) {
            return [$key => static::stringifyValue($data)];
        }

        return collect($data)
            ->mapWithKeys(fn (mixed $value, string $k): array => [$k => static::stringifyValue($value)])
            ->all();
    }

    protected static function payloadState(GovernanceAuditLog $record): array
    {
        $payload = $record->payload ?? [];

        if (isset($payload['before'], $payload['after']) && is_array($payload['before']) && is_array($payload['after'])) {
            return [];
        }

        return collect($payload)
            ->mapWithKeys(fn (mixed $value, string $key): array => [$key => static::stringifyValue($value)])
            ->all();
    }

    protected static function stringifyValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null || $value === '') {
            return '-';
        }

        return (string) $value;
    }
}
