<?php

namespace App\Providers;

use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        TextColumn::configureUsing(
            fn (TextColumn $column): TextColumn => $column->placeholder('-')
        );
        TextEntry::configureUsing(
            fn (TextEntry $entry): TextEntry => $entry->placeholder('-')
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            URL::forceScheme('https');
        }
    }
}
