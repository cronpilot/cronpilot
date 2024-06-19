<?php

namespace App\Filament\Resources;

use App\Enums\RunStatus;
use App\Enums\TaskStatus;
use App\Filament\Resources\TaskResource\Pages\CreateTask;
use App\Filament\Resources\TaskResource\Pages\EditTask;
use App\Filament\Resources\TaskResource\Pages\ListTasks;
use App\Filament\Resources\TaskResource\Pages\ViewTask;
use App\Filament\Resources\TaskResource\RelationManagers\RunsRelationManager;
use App\Models\Task;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\RestoreBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $navigationIcon = 'tabler-checkbox';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('tenant_id')
                    ->relationship('tenant', 'name')
                    ->required(),
                Select::make('server_id')
                    ->relationship('server', 'name'),
                TextInput::make('name')
                    ->columnSpanFull()
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->columnSpanFull(),
                Textarea::make('schedule')
                    ->columnSpanFull(),
                Textarea::make('command')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tenant.name')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('server.name')
                    ->placeholder('No server')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('description')
                    ->limit(40)
                    ->color('gray')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => TaskStatus::ACTIVE,
                        'danger' => TaskStatus::DISABLED,
                        'info' => TaskStatus::PREFLIGHT,
                    ])
                    ->icons([
                        'tabler-check' => TaskStatus::ACTIVE,
                        'tabler-x' => TaskStatus::DISABLED,
                        'tabler-plane-departure' => TaskStatus::PREFLIGHT,
                    ])
                    ->searchable(),
                TextColumn::make('runs.0.status')
                    ->label('Last run status')
                    ->badge()
                    ->colors([
                        'success' => RunStatus::SUCCESSFUL,
                        'danger' => RunStatus::FAILED,
                        'gray' => RunStatus::RUNNING,
                    ])
                    ->icons([
                        'tabler-check' => RunStatus::SUCCESSFUL,
                        'tabler-x' => RunStatus::FAILED,
                        'tabler-run' => RunStatus::RUNNING,
                    ])
                    ->searchable(),
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
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->columns(3)
            ->schema([
                TextEntry::make('name'),
                TextEntry::make('tenant.name'),
                TextEntry::make('server.name')
                    ->placeholder('No server'),
                TextEntry::make('description')
                    ->columnSpanFull()
                    ->color('gray'),
                TextEntry::make('status')
                    ->badge()
                    ->colors([
                        'success' => TaskStatus::ACTIVE,
                        'danger' => TaskStatus::DISABLED,
                        'info' => TaskStatus::PREFLIGHT,
                    ])
                    ->icons([
                        'tabler-check' => TaskStatus::ACTIVE,
                        'tabler-x' => TaskStatus::DISABLED,
                        'tabler-plane-departure' => TaskStatus::PREFLIGHT,
                    ]),
                TextEntry::make('runs.0.status')
                    ->label('Last run status')
                    ->badge()
                    ->colors([
                        'success' => RunStatus::SUCCESSFUL,
                        'danger' => RunStatus::FAILED,
                        'gray' => RunStatus::RUNNING,
                    ])
                    ->icons([
                        'tabler-check' => RunStatus::SUCCESSFUL,
                        'tabler-x' => RunStatus::FAILED,
                        'tabler-run' => RunStatus::RUNNING,
                    ]),
                TextEntry::make('deleted_at')
                    ->dateTime(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RunsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
            'create' => CreateTask::route('/create'),
            'view' => ViewTask::route('/{record}'),
            'edit' => EditTask::route('/{record}/edit'),
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
