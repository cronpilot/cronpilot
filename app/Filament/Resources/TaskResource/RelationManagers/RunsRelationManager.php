<?php

namespace App\Filament\Resources\TaskResource\RelationManagers;

use App\Enums\RunStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\RestoreBulkAction;
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
                    ]),
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
                    ->toggleable(),
                TextColumn::make('created_at')
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
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
