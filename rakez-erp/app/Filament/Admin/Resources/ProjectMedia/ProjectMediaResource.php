<?php

namespace App\Filament\Admin\Resources\ProjectMedia;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\ProjectMedia\Pages\ListProjectMedia;
use App\Filament\Admin\Resources\ProjectMedia\Pages\ViewProjectMedia;
use App\Models\ProjectMedia;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProjectMediaResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = ProjectMedia::class;

    protected static string $viewPermission = 'projects.view';

    protected static ?string $slug = 'project-media';

    protected static ?string $navigationLabel = 'Image Review';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string | \UnitEnum | null $navigationGroup = 'Contracts & Projects';

    protected static ?int $navigationSort = 12;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['contract']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->latest())
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('contract.project_name')->label('Project')->searchable()->placeholder('-'),
                TextColumn::make('department')->badge()->placeholder('-'),
                TextColumn::make('type')->badge()->placeholder('-'),
                TextColumn::make('url')->limit(50)->copyable(),
                TextColumn::make('created_at')->since(),
            ])
            ->filters([
                SelectFilter::make('department')
                    ->options([
                        'boards' => 'Boards',
                        'photography' => 'Photography',
                        'montage' => 'Montage',
                    ]),
                SelectFilter::make('type')
                    ->options(fn (): array => ProjectMedia::query()
                        ->whereNotNull('type')
                        ->distinct()
                        ->pluck('type', 'type')
                        ->sort()
                        ->all()),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Image Review')
                ->schema([
                    TextEntry::make('id'),
                    TextEntry::make('contract.project_name')->label('Project')->placeholder('-'),
                    TextEntry::make('department')->badge()->placeholder('-'),
                    TextEntry::make('type')->badge()->placeholder('-'),
                    TextEntry::make('url')
                        ->label('Asset URL')
                        ->url(fn (ProjectMedia $record): ?string => filled($record->url) ? $record->url : null)
                        ->openUrlInNewTab()
                        ->copyable()
                        ->columnSpanFull(),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('updated_at')->dateTime(),
                ])
                ->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjectMedia::route('/'),
            'view' => ViewProjectMedia::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('Contracts & Projects', static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('Contracts & Projects', static::$viewPermission);
    }
}
