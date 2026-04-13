<?php

namespace App\Filament\Admin\Resources\AssistantKnowledgeEntries;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Resources\AssistantKnowledgeEntries\Pages\CreateAssistantKnowledgeEntry;
use App\Filament\Admin\Resources\AssistantKnowledgeEntries\Pages\EditAssistantKnowledgeEntry;
use App\Filament\Admin\Resources\AssistantKnowledgeEntries\Pages\ListAssistantKnowledgeEntries;
use App\Filament\Admin\Resources\AssistantKnowledgeEntries\Pages\ViewAssistantKnowledgeEntry;
use App\Models\AssistantKnowledgeEntry;
use App\Services\AI\AssistantKnowledgeEntryService;
use App\Services\Governance\GovernanceCatalog;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AssistantKnowledgeEntryResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;

    protected static ?string $model = AssistantKnowledgeEntry::class;

    protected static ?string $slug = 'assistant-knowledge-entries';

    protected static ?string $navigationLabel = 'Knowledge Review';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string | \UnitEnum | null $navigationGroup = 'AI & Knowledge';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        $catalog = app(GovernanceCatalog::class);

        return $schema->components([
            TextInput::make('module')->required()->maxLength(100),
            TextInput::make('page_key')->maxLength(100),
            TextInput::make('title')->required()->maxLength(255),
            Select::make('language')
                ->options([
                    'ar' => 'Arabic',
                    'en' => 'English',
                ])
                ->required()
                ->default('ar'),
            TextInput::make('priority')->numeric()->default(0),
            Toggle::make('is_active')->default(true),
            TagsInput::make('tags')->columnSpanFull(),
            TagsInput::make('roles')
                ->suggestions(array_keys($catalog->governanceRoleOptions()))
                ->columnSpanFull(),
            Select::make('permissions')
                ->multiple()
                ->searchable()
                ->options($catalog->permissionOptions())
                ->columnSpanFull(),
            Textarea::make('content_md')
                ->label('Content')
                ->rows(16)
                ->required()
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('updatedByUser')->latest())
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('module')->badge(),
                TextColumn::make('page_key')->placeholder('-'),
                TextColumn::make('title')->searchable()->limit(40),
                TextColumn::make('language')->badge(),
                TextColumn::make('priority')->sortable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('updatedByUser.name')->label('Updated By')->placeholder('-'),
                TextColumn::make('updated_at')->since(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('deleteEntry')
                    ->label('Delete')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (AssistantKnowledgeEntry $record): bool => static::canDelete($record))
                    ->action(function (AssistantKnowledgeEntry $record): void {
                        app(AssistantKnowledgeEntryService::class)->delete($record);
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Knowledge Review')
                ->schema([
                    TextEntry::make('module')->badge(),
                    TextEntry::make('page_key')->placeholder('-'),
                    TextEntry::make('title'),
                    TextEntry::make('language')->badge(),
                    TextEntry::make('priority'),
                    IconEntry::make('is_active')->label('Active')->boolean(),
                    TextEntry::make('updatedByUser.name')->label('Updated By')->placeholder('-'),
                    TextEntry::make('updated_at')->dateTime(),
                    TextEntry::make('roles')
                        ->state(fn (AssistantKnowledgeEntry $record): array => $record->roles ?? [])
                        ->listWithLineBreaks()
                        ->placeholder('All roles')
                        ->columnSpanFull(),
                    TextEntry::make('permissions')
                        ->state(fn (AssistantKnowledgeEntry $record): array => $record->permissions ?? [])
                        ->listWithLineBreaks()
                        ->placeholder('All permissions')
                        ->columnSpanFull(),
                    TextEntry::make('tags')
                        ->state(fn (AssistantKnowledgeEntry $record): array => $record->tags ?? [])
                        ->listWithLineBreaks()
                        ->placeholder('No tags')
                        ->columnSpanFull(),
                    TextEntry::make('content_md')
                        ->label('Content')
                        ->markdown()
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssistantKnowledgeEntries::route('/'),
            'create' => CreateAssistantKnowledgeEntry::route('/create'),
            'edit' => EditAssistantKnowledgeEntry::route('/{record}/edit'),
            'view' => ViewAssistantKnowledgeEntry::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('AI & Knowledge', 'ai.knowledge.view');
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('AI & Knowledge', 'ai.knowledge.view');
    }

    public static function canCreate(): bool
    {
        return static::canGovernanceMutation('manage-ai-knowledge');
    }

    public static function canEdit(Model $record): bool
    {
        return static::canGovernanceMutation('manage-ai-knowledge');
    }

    public static function canDelete(Model $record): bool
    {
        return static::canGovernanceMutation('manage-ai-knowledge');
    }

    public static function canDeleteAny(): bool
    {
        return static::canGovernanceMutation('manage-ai-knowledge');
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }
}

