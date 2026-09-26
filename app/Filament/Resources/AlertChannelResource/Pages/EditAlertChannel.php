<?php

namespace App\Filament\Resources\AlertChannelResource\Pages;

use App\Filament\Resources\AlertChannelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAlertChannel extends EditRecord
{
    protected static string $resource = AlertChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
