<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Artisan;

class ViewTask extends ViewRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
            Action::make('run')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    Artisan::call('app:do-server-stuff');
                })
        ];
    }
}
