<?php

namespace App\Filament\Resources\ServerResource\RelationManagers;

use App\Filament\Resources\TaskResource;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return TaskResource::form($form, false);
    }

    public function table(Table $table): Table
    {
        return TaskResource::table($table, false);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return TaskResource::infolist($infolist, false);
    }
}
