<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServerResource\Pages\CreateServer;
use App\Filament\Resources\ServerResource\Pages\EditServer;
use App\Filament\Resources\ServerResource\Pages\ListServers;
use App\Filament\Resources\ServerResource\Pages\ViewServer;
use App\Filament\Resources\ServerResource\RelationManagers\TasksRelationManager;
use App\Models\Server;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
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

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('hostname')
                    ->required()
                    ->maxLength(255),
                TextInput::make('ssh_port')
                    ->default(22)
                    ->required()
                    ->numeric(),
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
                    ->hidden(fn (Server $record): bool => ! $record->deleted_at),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
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
