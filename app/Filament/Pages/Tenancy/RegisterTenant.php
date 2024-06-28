<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Tenant;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\RegisterTenant as FilamentRegisterTenant;

class RegisterTenant extends FilamentRegisterTenant
{
    public static function getLabel(): string
    {
        return 'Register tenant';
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

    protected function handleRegistration(array $data): Tenant
    {
        $tenant = Tenant::create($data);

        $tenant->users()->attach(auth()->user());

        return $tenant;
    }
}
