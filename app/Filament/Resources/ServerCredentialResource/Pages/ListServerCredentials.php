<?php

namespace App\Filament\Resources\ServerCredentialResource\Pages;

use App\Filament\Resources\ServerCredentialResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServerCredentials extends ListRecords
{
    protected static string $resource = ServerCredentialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
