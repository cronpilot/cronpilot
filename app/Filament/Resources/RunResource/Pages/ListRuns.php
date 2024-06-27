<?php

namespace App\Filament\Resources\RunResource\Pages;

use App\Enums\RunStatus;
use App\Filament\Resources\RunResource;
use App\Models\Run;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRuns extends ListRecords
{
    protected static string $resource = RunResource::class;

    public function getTabs(): array
    {
        return collect(RunStatus::cases())
            ->map(fn (RunStatus $status) => Tab::make($status->getLabel())
                ->icon($status->getIcon())
                ->modifyQueryUsing(fn (Builder|Run $query) => $query->whereStatus($status))
            )
            ->prepend(Tab::make('All'))
            ->toArray();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
