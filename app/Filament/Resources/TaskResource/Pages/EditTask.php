<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use Carbon\CarbonImmutable;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['start_date'] = $this->record->rrule?->getStartDate()
            ? new CarbonImmutable($this->record->rrule->getStartDate())
            : null;

        $data['end_date'] = $this->record->rrule?->getEndDate()
            ? new CarbonImmutable($this->record->rrule->getEndDate())
            : null;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return TaskResource::mutateFormData($data);
    }
}
