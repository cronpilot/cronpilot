<?php

namespace App\Filament\Resources\AlertChannelResource\Pages;

use App\Filament\Resources\AlertChannelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAlertChannels extends ListRecords
{
    protected static string $resource = AlertChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
