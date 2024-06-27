<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Enums\TaskStatus;
use App\Filament\Resources\TaskResource;
use App\Models\Task;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    public function getTabs(): array
    {
        return collect(TaskStatus::cases())
            ->map(fn (TaskStatus $status) => Tab::make($status->getLabel())
                ->icon($status->getIcon())
                ->modifyQueryUsing(fn (Builder|Task $query) => $query->whereStatus($status))
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
