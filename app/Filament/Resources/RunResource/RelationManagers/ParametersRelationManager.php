<?php

namespace App\Filament\Resources\RunResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ParametersRelationManager extends RelationManager
{
    protected static string $relationship = 'parameters';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('value')
            ->columns([
                TextColumn::make('parameter.name'),
                TextColumn::make('value'),
            ])
            ->filters([
                //
            ]);
    }
}
