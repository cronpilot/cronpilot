<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RunResource\Pages\CreateRun;
use App\Filament\Resources\RunResource\Pages\EditRun;
use App\Filament\Resources\RunResource\Pages\ListRuns;
use App\Filament\Resources\RunResource\Pages\ViewRun;
use App\Filament\Resources\RunResource\RelationManagers\ParametersRelationManager;
use App\Models\Run;
use App\Models\Task;
use App\Models\User;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ForceDeleteBulkAction;
use Filament\Tables\Actions\RestoreBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RunResource extends Resource
{
    public const ICON = 'tabler-run';

    protected static ?string $model = Run::class;

    protected static ?string $navigationIcon = self::ICON;

    protected static ?int $navigationSort = 60;

    public static function table(Table $table, bool $showTask = true): Table
    {
        return $table
            ->columns([
                TextColumn::make('task.name')
                    ->icon(TaskResource::ICON)
                    ->sortable()
                    ->searchable()
                    ->visible($showTask),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (Run $record): string => $record->status->getColor())
                    ->icon(fn (Run $record): string => $record->status->getIcon())
                    ->sortable()
                    ->searchable(),
                TextColumn::make('durationForHumans')
                    ->label('Run duration')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('triggerable.name')
                    ->label('Triggered by')
                    ->icon(fn (Run $record): ?string => match ($record->triggerable_type) {
                        User::class => UserResource::ICON,
                        Task::class => TaskResource::ICON,
                        default => null,
                    })
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: $showTask),
                TextColumn::make('created_at')
                    ->label('Start time')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('task')
                    ->relationship('task', 'name')
                    ->preload()
                    ->visible($showTask)
                    ->multiple(),
                // MultiSelectFilter::make('triggered_by')
                //     ->relationship('triggerable', 'name')
                //     ->preload(),
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

    public static function infolist(Infolist $infolist, bool $showTask = true): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Runs')
                    ->icon('tabler-info-hexagon')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('task.name')
                            ->icon(TaskResource::ICON)
                            ->url(fn (Run $record): string => TaskResource::getUrl('view', [
                                'record' => $record->task,
                            ]))
                            ->visible($showTask),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (Run $record): string => $record->status->getColor())
                            ->icon(fn (Run $record): string => $record->status->getIcon()),
                        TextEntry::make('durationForHumans')
                            ->label('Run duration'),
                        ViewEntry::make('output')
                            ->label('Output')
                            ->view('filament.infolists.entries.code-block')
                            ->columnSpanFull(),
                        TextEntry::make('triggerable.name')
                            ->label('Triggered by')
                            ->icon(fn (Run $record): ?string => match ($record->triggerable_type) {
                                User::class => UserResource::ICON,
                                Task::class => TaskResource::ICON,
                                default => null,
                            })
                            ->url(fn (Run $record): ?string => match ($record->triggerable_type) {
                                User::class => UserResource::getUrl('view', ['record' => $record->triggerable]),
                                Task::class => TaskResource::getUrl('view', ['record' => $record->triggerable]),
                                default => null,
                            }),
                        TextEntry::make('created_at')
                            ->label('Start time')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                        TextEntry::make('deleted_at')
                            ->dateTime()
                            ->hidden(fn (Run $record): bool => ! $record->deleted_at),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ParametersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRuns::route('/'),
            'create' => CreateRun::route('/create'),
            'view' => ViewRun::route('/{record}'),
            'edit' => EditRun::route('/{record}/edit'),
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
