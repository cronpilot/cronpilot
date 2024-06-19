<?php

namespace App\Filament\Resources\TaskResource\RelationManagers;

use App\Enums\RunStatus;
use App\Models\Run;
use App\Models\Task;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\RestoreBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RunsRelationManager extends RelationManager
{
    protected static string $relationship = 'runs';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('output')
            ->columns([
                TextColumn::make('status')
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
                TextColumn::make('output')
                    ->limit(60)
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
                    ->toggleable(),
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
            ->filters([
                //
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('status')
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
}
