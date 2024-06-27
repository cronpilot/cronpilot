<?php

namespace App\Filament\Resources\TaskResource\RelationManagers;

use App\Filament\Resources\RunResource;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class RunsRelationManager extends RelationManager
{
    protected static string $relationship = 'runs';

    public function table(Table $table): Table
    {
        return RunResource::table($table, false);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return RunResource::infolist($infolist);
    }
}
