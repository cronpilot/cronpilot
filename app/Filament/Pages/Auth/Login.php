<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BasePage;

class Login extends BasePage
{
    public function mount(): void
    {
        parent::mount();

        if (app()->environment('local')) {
            $this->form->fill([
                'email' => config('auth.local_admin_user.email'),
                'password' => config('auth.local_admin_user.password'),
                'remember' => true,
            ]);
        }
    }
}
