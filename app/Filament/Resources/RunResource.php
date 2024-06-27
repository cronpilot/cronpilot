<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RunResource\Pages\CreateRun;
use App\Filament\Resources\RunResource\Pages\EditRun;
use App\Filament\Resources\RunResource\Pages\ListRuns;
use App\Filament\Resources\RunResource\Pages\ViewRun;
use App\Models\Run;
use App\Models\Task;
use App\Models\User;
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
use Filament\Tables\Filters\MultiSelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RunResource extends Resource
{
    protected static ?string $model = Run::class;

    protected static ?string $navigationIcon = 'tabler-run';

    public static function table(Table $table, bool $showTask = true): Table
    {
        $columns = collect();
        $filters = collect();

        if ($showTask) {
            $columns->push(
                TextColumn::make('task.name')
                    ->icon('tabler-checkbox')
                    ->sortable()
                    ->searchable(),
            );

            $filters->push(
                MultiSelectFilter::make('task')
                    ->relationship('task', 'name')
                    ->preload(),
            );
        }

        $columns->push(
            TextColumn::make('status')
                ->badge()
                ->color(fn (Run $record): string => $record->status->getColor())
                ->icon(fn (Run $record): string => $record->status->getIcon())
                ->searchable(),
            TextColumn::make('output')
                ->limit(50)
                ->color('gray')
                ->searchable()
                ->toggleable(),
            TextColumn::make('durationForHumans')
                ->label('Run duration')
                ->sortable()
                ->toggleable(),
            TextColumn::make('triggerable.name')
                ->label('Triggered by')
                ->icon(fn (Run $record): ?string => match ($record->triggerable_type) {
                    User::class => 'tabler-user',
                    Task::class => 'tabler-checkbox',
                    default => null,
                })
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
        );

        $filters->push(
            // MultiSelectFilter::make('triggered_by')
            //     ->relationship('triggerable', 'name')
            //     ->preload(),
            TrashedFilter::make(),
        );

        return $table
            ->columns($columns->toArray())
            ->defaultSort('created_at', 'desc')
            ->filters($filters->toArray())
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
                TextEntry::make('status')
                    ->badge()
                    ->color(fn (Run $record): string => $record->status->getColor())
                    ->icon(fn (Run $record): string => $record->status->getIcon()),
                TextEntry::make('durationForHumans')
                    ->label('Run duration'),
                TextEntry::make('output')
                    ->columnSpanFull()
                    ->color('gray'),
                TextEntry::make('triggerable.name')
                    ->label('Triggered by')
                    ->icon(fn (Run $record): ?string => match ($record->triggerable_type) {
                        User::class => 'tabler-user',
                        Task::class => 'tabler-checkbox',
                        default => null,
                    }),
                TextEntry::make('created_at')
                    ->label('Start time')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('deleted_at')
                    ->dateTime(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
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
