<?php

namespace App\Filament\Resources\ServerCredentialResource\Pages;

use App\Filament\Resources\ServerCredentialResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServerCredentials extends ListRecords
{
    protected static string $resource = ServerCredentialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
