<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Actions\RunTask;
use App\Filament\Resources\TaskResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTask extends ViewRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('run')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (RunTask $runTask): void {
                    $runTask->handle($this->record->id);
                }),
            EditAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
