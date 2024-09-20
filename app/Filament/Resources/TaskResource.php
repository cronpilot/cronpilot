<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskResource\Pages\CreateTask;
use App\Filament\Resources\TaskResource\Pages\EditTask;
use App\Filament\Resources\TaskResource\Pages\ListTasks;
use App\Filament\Resources\TaskResource\Pages\ViewTask;
use App\Filament\Resources\TaskResource\RelationManagers\ParametersRelationManager;
use App\Filament\Resources\TaskResource\RelationManagers\RunsRelationManager;
use App\Models\Task;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
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
                        Select::make('server_id')
                            ->relationship('server', 'name')
                            ->searchable()
                            ->preload()
                            ->visible($serverSelect),
                        Select::make('server_credential_id')
                            ->relationship('serverCredential', 'username')
                            ->preload()
                            ->searchable(),
                        TextInput::make('name')
                            ->columnSpanFull()
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->columnSpanFull(),
                        Textarea::make('command')
                            ->columnSpanFull(),
                        Checkbox::make('has_schedule')
                            ->formatStateUsing(fn (?bool $state): bool => $state ?? true)
                            ->live()
                            ->columnSpanFull(),
                    ]),
                Section::make('Schedule')
                    ->icon('tabler-clock')
                    ->columns(2)
                    ->visible(fn (Get $get): bool => $get('has_schedule'))
                    ->schema([
                        Select::make('frequency')
                            ->options([
                                'SECONDLY' => 'Secondly',
                                'MINUTELY' => 'Minutely',
                                'HOURLY' => 'Hourly',
                                'DAILY' => 'Daily',
                                'WEEKLY' => 'Weekly',
                                'MONTHLY' => 'Monthly',
                                'YEARLY' => 'Yearly',
                            ])
                            ->formatStateUsing(fn (?string $state): string => $state ?? 'DAILY')
                            ->required()
                            ->live()
                            ->native(false),
                        TextInput::make('interval')
                            ->prefix('Every')
                            ->suffix(fn (Get $get): string => match ($get('frequency')) {
                                'SECONDLY' => 'second',
                                'MINUTELY' => 'minute',
                                'HOURLY' => 'hour',
                                'DAILY' => 'day',
                                'WEEKLY' => 'week',
                                'MONTHLY' => 'month',
                                'YEARLY' => 'year',
                            }.'(s)')
                            ->integer()
                            ->formatStateUsing(fn (?int $state): int => $state ?? 1)
                            ->required(),
                        DateTimePicker::make('start_date'),
                        DateTimePicker::make('end_date'),
                    ]),
            ]);
    }

    public static function table(Table $table, bool $showServer = true): Table
    {
        return $table
            ->columns([
                TextColumn::make('server.name')
                    ->placeholder('No server')
                    ->icon(ServerResource::ICON)
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->visible($showServer),
                TextColumn::make('serverCredential.name')
                    ->placeholder('No credential')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
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
                TextColumn::make('scheduleForHumans')
                    ->label('Schedule')
                    ->limit(30)
                    ->sortable()
                    ->toggleable(),
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
                TextEntry::make('server.name')
                    ->placeholder('No server')
                    ->icon(ServerResource::ICON)
                    ->url(fn (Task $record): ?string => $record->server
                        ? ServerResource::getUrl('view', ['record' => $record->server])
                        : null
                    )
                    ->visible($showServer),
                TextEntry::make('scheduleForHumans')
                    ->label('Schedule'),
                TextEntry::make('description')
                    ->columnSpanFull()
                    ->color('gray'),
                TextEntry::make('status')
                    ->badge()
                    ->color(fn (Task $record): string => $record->status->getColor())
                    ->icon(fn (Task $record): string => $record->status->getIcon()),
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
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ParametersRelationManager::class,
            RunsRelationManager::class,
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
            $data['schedule'] = self::getSchedule($data);
        }

        return $data;
    }

    private static function getSchedule(array $data): string
    {
        return (new Rule)
            ->setFreq($data['frequency'])
            ->setInterval($data['interval'])
            ->setStartDate($data['start_date'])
            ->setEndDate($data['end_date'])
            ->getString();
    }
}
