<?php

namespace App\Filament\Resources;

use App\Enums\ServerType;
use App\Filament\Resources\ServerResource\Pages\CreateServer;
use App\Filament\Resources\ServerResource\Pages\EditServer;
use App\Filament\Resources\ServerResource\Pages\ListServers;
use App\Filament\Resources\ServerResource\Pages\ViewServer;
use App\Filament\Resources\ServerResource\RelationManagers\TasksRelationManager;
use App\Helpers\Connection;
use App\Models\Server;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Get;
use Filament\Infolists\Components\Section;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ForceDeleteBulkAction;
use Filament\Tables\Actions\RestoreBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;


class ServerResource extends Resource
{
    public const ICON = 'tabler-server-2';

    protected static ?string $model = Server::class;
    protected static ?string $navigationIcon = self::ICON;
    protected static ?string $navigationLabel = 'Runners';

    protected static ?string $modelLabel = 'Runners';
    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                FormSection::make('Server Information')
                    ->columns(2)
                    ->icon(ServerResource::ICON)
                    ->schema([
                        Select::make('server_type')
                            ->options(
                                collect(
                                    ServerType::cases())
                                    ->mapWithKeys(fn($type) => [$type->value => $type->label()])
                            )
                            ->columnSpanFull()
                            ->required()
                            ->reactive()
                            ->native(false),
                        TextInput::make('name')
                            ->required()
                            ->columnSpanFull()
                            ->maxLength(255),
                        TextInput::make('hostname')
                            ->required(fn(Get $get) => $get('server_type') !== ServerType::LOCAL->value)
                            ->hidden(fn(Get $get) => $get('server_type') === ServerType::LOCAL->value)
                            ->maxLength(255),
                        TextInput::make('ssh_port')
                            ->default(22)
                            ->required(fn(Get $get) => $get('server_type') !== ServerType::LOCAL->value)
                            ->hidden(fn(Get $get) => $get('server_type') === ServerType::LOCAL->value)
                            ->numeric(),
                        Select::make('server_credentials')
                            ->relationship('credentials', 'title')
                            ->searchable()
                            ->required()
                            ->columnSpanFull()
                            ->required(fn(Get $get) => $get('server_type') !== ServerType::LOCAL->value)
                            ->hidden(fn(Get $get) => $get('server_type') === ServerType::LOCAL->value)
                            ->label('Select Credential')
                            ->multiple()
                            ->reactive(),
                    ])
                    ->headerActions([
                        Action::make('test_connection')
                            ->color('danger')
                            ->label('Test Connection')
                            ->action(function ($state) {
                                if (!$state['server_credentials']) {
                                    Notification::make()
                                        ->title('Please select a credential to test connection')
                                        ->danger()
                                        ->send();
                                    return;
                                }
                                $connectionResponse = self::testConnection($state);
                                foreach ($connectionResponse as $response) {
                                    if ($response["connected"]) {
                                        Notification::make()
                                            ->title('Connection failed for credential: ' . $response['credentials'])
                                            ->success()
                                            ->send();
                                    } else {
                                        Notification::make()
                                            ->title('Connection failed for credential: ' . $response['credentials'])
                                            ->danger()
                                            ->send();
                                    }

                                }
                            })
                            ->disabled(fn(Get $get) => $get('server_type') === ServerType::LOCAL->value
                                || $get('server_type') === null
                                || (!filled($get('server_credentials_id') || !filled($get('hostname'))))),
                    ])
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('hostname')
                    ->label('Hostname')
                    ->limit(30)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('ssh_port')
                    ->label('SSH port')
                    ->numeric()
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('tasks_count')
                    ->counts('tasks')
                    ->badge()
                    ->color('info')
                    ->icon(TaskResource::ICON)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema(self::getServerInfoList());
    }

    /**
     * @throws \Exception
     */
    public static function testConnection(array $state): array
    {
        $connectionStatus = [];
        foreach ($state["server_credentials"] as $credentialId) {
            $connection = new Connection(
                $state["hostname"],
                (int)$credentialId,
            );
            $connectionStatus[] = $connection->connectToServer();
        }

        return $connectionStatus;


    }

    private static function getServerInfoList(): array
    {
        return [
            Section::make('Server Information')
                ->icon(self::ICON)
                ->columns(2)
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('ssh_port')
                        ->label('SSH port')
                        ->numeric()
                        ->badge(),
                    TextEntry::make('hostname')
                        ->columnSpanFull()
                        ->label('Hostname'),
                    TextEntry::make('deleted_at')
                        ->dateTime()
                        ->hidden(fn(Server $record): bool => !$record->deleted_at),
                    TextEntry::make('created_at')
                        ->dateTime(),
                    TextEntry::make('updated_at')
                        ->dateTime(),
                ]),
        ];
    }

    public static function getRelations(): array
    {
        return [
            TasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServers::route('/'),
            'create' => CreateServer::route('/create'),
            'view' => ViewServer::route('/{record}'),
            'edit' => EditServer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
