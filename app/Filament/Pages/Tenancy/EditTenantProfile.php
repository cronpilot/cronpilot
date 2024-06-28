<?php

namespace App\Filament\Pages\Tenancy;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\EditTenantProfile as FilamentEditTenantProfile;

class EditTenantProfile extends FilamentEditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Tenant profile';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('avatar_url')
                    ->label('Avatar')
                    ->columnSpanFull()
                    ->avatar()
                    ->directory('avatars')
                    ->imageEditor()
                    ->maxSize(1024 * 1024 * 10),
                TextInput::make('name'),
            ]);
    }
}
