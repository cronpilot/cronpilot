<?php

namespace App\Filament\Resources;

use App\Actions\RunTask;
use App\Enums\Day;
use App\Enums\TaskStatus;
use App\Filament\Resources\TaskResource\Pages\CreateTask;
use App\Filament\Resources\TaskResource\Pages\EditTask;
use App\Filament\Resources\TaskResource\Pages\ListTasks;
use App\Filament\Resources\TaskResource\Pages\ViewTask;
use App\Filament\Resources\TaskResource\RelationManagers\ParametersRelationManager;
use App\Filament\Resources\TaskResource\RelationManagers\RunsRelationManager;
use App\Helpers\Recurrence;
use App\Models\Task;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ForceDeleteBulkAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\RestoreBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\MultiSelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Recurr\Frequency;
use Recurr\Rule;

class TaskResource extends Resource
{
    public const ICON = 'tabler-checkbox';

    protected static ?string $model = Task::class;

    protected static ?string $navigationIcon = self::ICON;

    protected static ?int $navigationSort = 50;

    public static function form(Form $form, bool $serverSelect = true): Form
    {
        return $form
            ->schema([
                Section::make('Task Information')
                    ->icon('tabler-info-hexagon')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('status')
                            ->options(TaskStatus::class),
                        Select::make('server_id')
                            ->relationship('server', 'name')
                            ->searchable()
                            ->preload()
                            ->visible($serverSelect),
                        Select::make('server_credential_id')
                            ->relationship('serverCredential', 'title')
                            ->preload()
                            ->searchable(),
                        Textarea::make('description')
                            ->columnSpanFull(),
                        Textarea::make('command')
                            ->columnSpanFull(),
                        Toggle::make('has_schedule')
                            ->formatStateUsing(fn (?Task $record): bool => (bool) $record?->schedule ?? true)
                            ->live()
                            ->columnSpanFull(),
                    ]),
                Section::make('Schedule')
                    ->icon('tabler-clock')
                    ->columns(2)
                    ->visible(fn (Get $get): bool => (bool) $get('has_schedule'))
                    ->live()
                    ->schema([
                        Select::make('frequency')
                            ->options([
                                Frequency::SECONDLY => 'Secondly',
                                Frequency::MINUTELY => 'Minutely',
                                Frequency::HOURLY => 'Hourly',
                                Frequency::DAILY => 'Daily',
                                Frequency::WEEKLY => 'Weekly',
                                Frequency::MONTHLY => 'Monthly',
                                Frequency::YEARLY => 'Yearly',
                            ])
                            ->formatStateUsing(fn (?Task $record): int => $record?->frequency ?? Frequency::DAILY)
                            ->required()
                            ->native(false),
                        TextInput::make('interval')
                            ->prefix('Every')
                            ->suffix(fn (Get $get): string => match ((int) $get('frequency')) {
                                Frequency::SECONDLY => 'second',
                                Frequency::MINUTELY => 'minute',
                                Frequency::HOURLY => 'hour',
                                Frequency::DAILY => 'day',
                                Frequency::WEEKLY => 'week',
                                Frequency::MONTHLY => 'month',
                                Frequency::YEARLY => 'year',
                            }.'(s)')
                            ->integer()
                            ->formatStateUsing(fn (?Task $record): int => $record?->interval ?? 1)
                            ->required(),
                        Radio::make('by')
                            ->hiddenLabel()
                            ->options(fn (Get $get): array => match ((int) $get('frequency')) {
                                Frequency::WEEKLY => [
                                    'start_date' => 'From start date',
                                    'day' => 'By weekday',
                                ],
                                Frequency::MONTHLY => [
                                    'start_date' => 'From start date',
                                    'month_day' => 'By day of the month',
                                    'day' => 'By weekday',
                                ],
                            })
                            ->default('start_date')
                            ->formatStateUsing(fn (?Task $record): string => match (true) {
                                (bool) $record?->byDay => 'day',
                                (bool) $record?->byMonthDay => 'month_day',
                                default => 'start_date',
                            })
                            ->visible(fn (Get $get): bool => in_array($get('frequency'), [
                                Frequency::WEEKLY,
                                Frequency::MONTHLY,
                            ]))
                            ->columnSpanFull(),
                        Select::make('by_day_weekly')
                            ->multiple()
                            ->label('Days')
                            ->options(Day::class)
                            ->required()
                            ->formatStateUsing(fn (?Task $record): array => $record?->byDay ?? [])
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => $get('frequency') == Frequency::WEEKLY && $get('by') === 'day'),
                        Select::make('by_month_day')
                            ->multiple()
                            ->label('Days')
                            ->options(range(1, 31))
                            ->required()
                            ->formatStateUsing(fn (?Task $record): array => $record?->byMonthDay ?? [])
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => $get('frequency') == Frequency::MONTHLY && $get('by') === 'month_day'),
                        Repeater::make('by_day_monthly')
                            ->schema([
                                Select::make('ordinal')
                                    ->hiddenLabel()
                                    ->options([
                                        1 => 'First',
                                        2 => 'Second',
                                        3 => 'Third',
                                        4 => 'Fourth',
                                        -1 => 'Last',
                                    ])
                                    ->prefix('On the'),
                                Select::make('day')
                                    ->hiddenLabel()
                                    ->options(Day::class)
                                    ->postfix('of the month'),
                            ])
                            ->label('By weekday')
                            ->hiddenLabel()
                            ->required()
                            ->columns(2)
                            ->columnSpanFull()
                            ->reorderable(false)
                            ->formatStateUsing(fn (?Task $record): array => $record?->frequency === Frequency::MONTHLY && $record?->byDay
                                ? $record->byDay
                                : [[
                                    'ordinal' => 1,
                                    'day' => Day::MONDAY->value,
                                ]]
                            )
                            ->visible(fn (Get $get): bool => $get('frequency') == Frequency::MONTHLY && $get('by') === 'day'),
                        DateTimePicker::make('start_date')
                            ->native(false)
                            ->required()
                            ->formatStateUsing(fn (?Task $record): Carbon => $record?->startDate ?? today()),
                        DateTimePicker::make('end_date')
                            ->native(false)
                            ->formatStateUsing(fn (?Task $record): ?Carbon => $record?->endDate),
                        Placeholder::make('upcoming_run_times')
                            ->content(fn (Get $get): HtmlString => new HtmlString(
                                '<ul class="list-disc list-inside">'
                                .implode(
                                    self::getUpcomingRunTimes($get())
                                        ->map(fn (string $runTime): string => "<li>{$runTime}</li>")
                                        ->toArray()
                                )
                                .'</ul>'
                            ))
                            ->columnSpanFull(),
                        Placeholder::make('rrule_preview')
                            ->content(fn (Get $get): string => self::getRrule($get())->getString())
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table, bool $showServer = true): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('description')
                    ->limit(40)
                    ->color('gray')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (Task $record): string => $record->status->getColor())
                    ->icon(fn (Task $record): string => $record->status->getIcon())
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('lastRunStatus')
                    ->badge()
                    ->color(fn (Task $record): string => $record->lastRunStatus->getColor())
                    ->icon(fn (Task $record): string => $record->lastRunStatus->getIcon())
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('next_run_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('scheduleForHumans')
                    ->label('Schedule')
                    ->limit(30)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('server.name')
                    ->placeholder('No server')
                    ->icon(ServerResource::ICON)
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible($showServer),
                TextColumn::make('serverCredential.title')
                    ->placeholder('No credential')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('runs_count')
                    ->counts('runs')
                    ->badge()
                    ->color('warning')
                    ->icon(RunResource::ICON)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                MultiSelectFilter::make('server')
                    ->relationship('server', 'name')
                    ->preload()
                    ->visible($showServer),
                TrashedFilter::make(),
            ])
            ->actions([
                Action::make('run')
                    ->color('success')
                    ->icon('tabler-player-play-filled')
                    ->requiresConfirmation()
                    ->action(function (Task $record, RunTask $runTask): void {
                        $runTask->handle($record->id);
                    }),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist, bool $showServer = true): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('name'),
                TextEntry::make('status')
                    ->badge()
                    ->color(fn (Task $record): string => $record->status->getColor())
                    ->icon(fn (Task $record): string => $record->status->getIcon()),
                TextEntry::make('description')
                    ->columnSpanFull()
                    ->color('gray'),
                TextEntry::make('server.name')
                    ->placeholder('No server')
                    ->icon(ServerResource::ICON)
                    ->url(fn (Task $record): ?string => $record->server
                        ? ServerResource::getUrl('view', ['record' => $record->server])
                        : null
                    )
                    ->visible($showServer),
                TextEntry::make('serverCredential.username')
                    ->label('Credential')
                    ->placeholder('No credential')
                    ->icon(ServerCredentialResource::ICON)
                    ->visible($showServer),
                TextEntry::make('scheduleForHumans')
                    ->label('Schedule'),
                TextEntry::make('lastRunStatus')
                    ->badge()
                    ->color(fn (Task $record): string => $record->lastRunStatus->getColor())
                    ->icon(fn (Task $record): string => $record->lastRunStatus->getIcon()),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->hidden(fn (Task $record): bool => ! $record->deleted_at),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('next_run_at')
                    ->dateTime(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RunsRelationManager::class,
            ParametersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
            'create' => CreateTask::route('/create'),
            'view' => ViewTask::route('/{record}'),
            'edit' => EditTask::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function mutateFormData(array $data): array
    {
        $data['schedule'] = $data['has_schedule']
            ? self::getRrule($data)->getString()
            : null;

        return $data;
    }

    private static function getRrule(array $data): Rule
    {
        $rrule = (new Rule)
            ->setFreq((int) $data['frequency'])
            ->setInterval($data['interval'])
            ->setStartDate(new Carbon($data['start_date']), true);

        switch ((int) $data['frequency']) {
            case Frequency::WEEKLY:
                if ($data['by'] === 'day' && $data['by_day_weekly']) {
                    $rrule->setByDay($data['by_day_weekly']);
                }

                break;
            case Frequency::MONTHLY:
                if ($data['by'] === 'month_day' && $data['by_month_day']) {
                    $rrule->setByMonthDay($data['by_month_day']);
                }

                if ($data['by'] === 'day' && $data['by_day_monthly']) {
                    $byDay = collect($data['by_day_monthly'])
                        ->map(fn (array $byDay): ?string => $byDay['ordinal'] && $byDay['day']
                            ? $byDay['ordinal'].$byDay['day']
                            : null)
                        ->filter()
                        ->unique();

                    if (! $byDay->isEmpty()) {
                        $rrule->setByDay($byDay->toArray());
                    }

                    break;
                }
        }

        if ($data['end_date']) {
            $rrule->setEndDate(new Carbon($data['end_date']));
        }

        return $rrule;
    }

    private static function getUpcomingRunTimes(array $data): Collection
    {
        $schedule = self::getRrule($data)->getString();

        $scheduleStart = new Carbon($data['start_date']);

        $scheduler = new Recurrence($schedule, $scheduleStart);

        $lastRunTime = $scheduleStart->subSecond();

        $upcomingRunTimes = collect();

        for ($i = 0; $i < 3; $i++) {
            if ($lastRunTime) {
                $lastRunTime = $scheduler->next($lastRunTime);

                $upcomingRunTimes->push($lastRunTime->toDayDateTimeString());
            }
        }

        return $upcomingRunTimes;
    }
}
