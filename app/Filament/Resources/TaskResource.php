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
use App\Models\Task;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
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
                            ->live()
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
                        Radio::make('weekly_options')
                            ->hiddenLabel()
                            ->options([
                                'from_start_date' => 'From start date',
                                'by_day' => 'By day',
                            ])
                            ->default('from_start_date')
                            ->formatStateUsing(fn (?Task $record): string => $record?->byDay ? 'by_day' : 'from_start_date')
                            ->columnSpanFull()
                            ->live()
                            ->visible(fn (Get $get): bool => $get('frequency') == Frequency::WEEKLY),
                        Select::make('by_day')
                            ->multiple()
                            ->label('Days')
                            ->options(Day::class)
                            ->required()
                            ->formatStateUsing(fn (?Task $record): ?array => $record?->byDay)
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => $get('frequency') == Frequency::WEEKLY && $get('weekly_options') === 'by_day'),
                        DateTimePicker::make('start_date')
                            ->native(false)
                            ->formatStateUsing(fn (?Task $record): ?Carbon => $record?->startDate),
                        DateTimePicker::make('end_date')
                            ->native(false)
                            ->formatStateUsing(fn (?Task $record): ?Carbon => $record?->endDate),
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
                TextColumn::make('serverCredential.name')
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
            ? self::getSchedule($data)
            : null;

        return $data;
    }

    private static function getSchedule(array $data): string
    {
        $rrule = (new Rule)
            ->setFreq((int) $data['frequency'])
            ->setInterval($data['interval'])
            ->setStartDate($data['start_date'])
            ->setEndDate($data['end_date']);

        if ($data['frequency'] == Frequency::WEEKLY && $data['weekly_options'] === 'by_day') {
            $rrule->setByDay($data['by_day']);
        }

        return $rrule->getString();
    }
}
