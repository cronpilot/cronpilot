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
use App\Filament\TenantScope;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Group;
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
use Filament\Infolists\Components\Card;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ForceDeleteBulkAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\RestoreBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Exists;
use Recurr\Frequency;
use Recurr\Rule;
use Tapp\FilamentTimezoneField\Forms\Components\TimezoneSelect;

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
                    ->icon(self::ICON)
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('status')
                            ->required()
                            ->default(TaskStatus::ACTIVE)
                            ->options(TaskStatus::class),
                        Select::make('server_id')
                            ->relationship('server', 'name', TenantScope::query())
                            ->rule(fn (): Exists => TenantScope::exists('servers'))
                            ->searchable()
                            ->preload()
                            ->visible($serverSelect),
                        Select::make('server_credential_id')
                            ->relationship('serverCredential', 'title', TenantScope::query())
                            ->rule(fn (): Exists => TenantScope::exists('server_credentials'))
                            ->preload()
                            ->searchable(),
                        Select::make('alert_channel_id')
                            ->label('Alert channel')
                            ->relationship('alertChannel', 'name', TenantScope::query())
                            ->rule(fn (): Exists => TenantScope::exists('alert_channels'))
                            ->preload()
                            ->placeholder('No alerts')
                            ->helperText('Where to post in Slack when this task starts failing, and when it recovers.'),
                        Textarea::make('description')
                            ->columnSpanFull(),
                        Textarea::make('command')
                            ->columnSpanFull(),
                        Toggle::make('paused')
                            ->label('Pause task')
                            ->helperText('A paused task keeps its schedule but is not run until it is resumed.')
                            ->onColor('warning')
                            ->columnSpanFull(),
                        Toggle::make('allow_overlapping')
                            ->label('Allow overlapping runs')
                            ->helperText('When off, a scheduled run is skipped if the previous run is still in progress.')
                            ->columnSpanFull(),
                        Toggle::make('has_schedule')
                            ->formatStateUsing(fn (?Task $record): bool => (bool) $record?->schedule ?? true)
                            ->live()
                            ->columnSpanFull(),
                    ]),
                Section::make('Schedule')
                    ->icon('tabler-clock')
                    ->visible(fn (Get $get): bool => (bool) $get('has_schedule'))
                    ->live()
                    ->schema([
                        Group::make([
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
                                ->minValue(1)
                                ->formatStateUsing(fn (?Task $record): int => $record?->interval ?? 1)
                                ->required(),
                        ])
                            ->columns(2),
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
                            ])),
                        Select::make('by_day_weekly')
                            ->multiple()
                            ->label('Days')
                            ->options(Day::class)
                            ->required()
                            ->formatStateUsing(fn (?Task $record): array => $record?->byDay ?? [])
                            ->visible(fn (Get $get): bool => $get('frequency') == Frequency::WEEKLY && $get('by') === 'day'),
                        Select::make('by_month_day')
                            ->multiple()
                            ->label('Days')
                            ->options(range(1, 31))
                            ->required()
                            ->formatStateUsing(fn (?Task $record): array => $record?->byMonthDay ?? [])
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
                            ->reorderable(false)
                            ->formatStateUsing(fn (?Task $record): array => $record?->frequency === Frequency::MONTHLY && $record?->byDay
                                ? $record->byDay
                                : [[
                                    'ordinal' => 1,
                                    'day' => Day::MONDAY->value,
                                ]]
                            )
                            ->visible(fn (Get $get): bool => $get('frequency') == Frequency::MONTHLY && $get('by') === 'day'),
                        Group::make([
                            DateTimePicker::make('start_date')
                                ->native(false),
                            DateTimePicker::make('end_date')
                                ->native(false),
                            TimezoneSelect::make('timezone')
                                ->native(false)
                                ->searchable()
                                ->formatStateUsing(fn (?Task $record): string => $record->timezone ?? auth()->user()->timezone),
                        ])
                            ->columns(3),
                        Group::make([
                            Placeholder::make('upcoming_run_times_task_timezone')
                                ->label(fn (Get $get): string => "Upcoming run times ({$get('timezone')})")
                                ->content(fn (Get $get): HtmlString => self::getUpcomingRunTimesHTMLList($get(), $get('timezone'))),
                            Placeholder::make('upcoming_run_times_user_timezone')
                                ->label(function (): string {
                                    $userTimezone = auth()->user()->timezone;

                                    return "Upcoming run times ({$userTimezone})";
                                })
                                ->content(fn (Get $get): HtmlString => self::getUpcomingRunTimesHTMLList($get(), auth()->user()->timezone))
                                ->visible(fn (Get $get): bool => $get('timezone') !== auth()->user()->timezone),
                        ])
                            ->columns(2),
                        Placeholder::make('rrule_preview')
                            ->content(fn (Get $get): string => self::getRrule($get())->getString()),
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
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                ToggleColumn::make('paused')
                    ->onColor('warning')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('lastRunStatus')
                    ->badge()
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('nextRunAtCarbon')
                    ->label('Next run at')
                    ->dateTime()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('next_run_at', $direction))
                    ->toggleable(),
                TextColumn::make('scheduleForHumans')
                    ->label('Schedule')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('command')
                    ->fontFamily(FontFamily::Mono)
                    ->limit(30)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('runs'))
            ->defaultSort(fn (Builder $query): Builder => $query->orderByRaw('next_run_at IS NULL, next_run_at ASC'))
            ->filters([
                SelectFilter::make('server')
                    ->relationship('server', 'name', TenantScope::query())
                    ->preload()
                    ->multiple()
                    ->visible($showServer),
                TrashedFilter::make(),
            ])
            ->actions([
                Action::make('run')
                    ->color('success')
                    ->icon('tabler-player-play-filled')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Task $record): ?string => static::runWarning($record))
                    ->modalIconColor(fn (Task $record): string => $record->isRunning() ? 'warning' : 'success')
                    ->action(function (Task $record, RunTask $runTask): void {
                        $runTask->handle($record->id, ignoreLock: $record->isRunning());
                    }),
                ViewAction::make(),
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * The Run now confirmation text, warning when the task is already running.
     *
     * Confirming a run while another is in progress starts a second, overlapping
     * run rather than being skipped like a scheduled one would be.
     */
    public static function runWarning(Task $task): ?string
    {
        return $task->isRunning()
            ? 'This task is already running. Running it now will start a second run alongside it.'
            : null;
    }

    public static function infolist(Infolist $infolist, bool $showServer = true): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Task Information')
                    ->icon(self::ICON)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('status')
                            ->badge()
                            ->hint(fn (Task $record): ?string => $record->paused ? 'Paused' : null)
                            ->hintIcon(fn (Task $record): ?string => $record->paused ? 'tabler-player-pause' : null)
                            ->hintColor('warning'),
                        TextEntry::make('description')
                            ->color('gray')
                            ->columnSpanFull(),
                        Card::make('Command')
                            ->schema([
                                TextEntry::make('command')
                                    ->fontFamily(FontFamily::Mono)
                                    ->hiddenLabel(),
                            ]),
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
                            ->badge(),
                        TextEntry::make('upcomingRunTimes')
                            ->dateTime()
                            ->listWithLineBreaks()
                            ->bulleted(),
                        TextEntry::make('deleted_at')
                            ->dateTime()
                            ->hiddenLabel(fn (Task $record): bool => ! $record->deleted_at)
                            ->placeholder(''),
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ]),
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
        if ($data['has_schedule']) {
            $data['schedule'] = self::getRrule($data)->getString();

            $data['next_run_at'] = self::getUpcomingRunTimes($data, 1)->first()?->utc()->toDateTimeString();
        }

        return $data;
    }

    private static function getRrule(array $data): Rule
    {
        $rrule = (new Rule)
            ->setFreq((int) $data['frequency'])
            ->setInterval($data['interval']);

        if ($data['start_date']) {
            $rrule->setStartDate(CarbonImmutable::parse($data['start_date']), true);
        }

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
            $rrule->setUntil(CarbonImmutable::parse($data['end_date']));
        }

        return $rrule;
    }

    /**
     * The next run times for the schedule currently in the form.
     *
     * The unsaved schedule goes through the same calculation the scheduler
     * uses, so the preview always matches when the task will really run.
     */
    private static function getUpcomingRunTimes(array $data, int $count = 3): Collection
    {
        $task = new Task([
            'schedule' => self::getRrule($data)->getString(),
            'timezone' => $data['timezone'] ?? null,
        ]);

        return $task->runTimesAfter(now(), $count);
    }

    private static function getUpcomingRunTimesHTMLList(array $data, string $timezone, int $count = 3): HtmlString
    {
        return new HtmlString(
            '<ul class="list-disc list-inside">'
            .implode(
                self::getUpcomingRunTimes($data, $count)
                    ->map(
                        fn (CarbonImmutable $runTime): string => "<li>{$runTime->timezone($timezone)->toDayDateTimeString()}</li>"
                    )
                    ->toArray()
            )
            .'</ul>'
        );
    }
}
