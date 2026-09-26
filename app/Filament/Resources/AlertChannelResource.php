<?php

namespace App\Filament\Resources;

use App\Alerts\SlackWebhook;
use App\Filament\Resources\AlertChannelResource\Pages\CreateAlertChannel;
use App\Filament\Resources\AlertChannelResource\Pages\EditAlertChannel;
use App\Filament\Resources\AlertChannelResource\Pages\ListAlertChannels;
use App\Models\AlertChannel;
use Closure;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AlertChannelResource extends Resource
{
    public const ICON = 'tabler-bell';

    protected static ?string $model = AlertChannel::class;

    protected static ?string $navigationIcon = self::ICON;

    protected static ?int $navigationSort = 45;

    public static function form(Form $form): Form
    {
        $creating = $form->getOperation() === 'create';

        return $form
            ->schema([
                Section::make('Alert Channel')
                    ->icon(self::ICON)
                    ->description('A Slack channel that tasks can send failure alerts to.')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('#ops-alerts')
                            ->helperText('Shown when choosing a channel for a task.'),
                        TextInput::make('webhook_url')
                            ->label($creating ? 'Slack webhook URL' : 'Replace Slack webhook URL')
                            ->password()
                            ->revealable(false)
                            ->required($creating)
                            // Never send the saved URL back to the browser, and
                            // only change it when a new one is entered.
                            ->formatStateUsing(fn (): ?string => null)
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && ! SlackWebhook::isAllowedUrl($value)) {
                                    $fail('Enter a Slack incoming webhook URL, starting with https://hooks.slack.com/.');
                                }
                            })
                            ->helperText($creating
                                ? 'In Slack: Apps → Incoming Webhooks → Add to Slack, then pick the channel.'
                                : 'Leave blank to keep the current URL.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('tasks_count')
                    ->counts('tasks')
                    ->label('Tasks')
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                Action::make('sendTest')
                    ->label('Send test message')
                    ->icon('tabler-send')
                    ->color('gray')
                    ->action(function (AlertChannel $record, SlackWebhook $slack): void {
                        if ($slack->send($record->webhook_url, SlackWebhook::testMessage($record->name))) {
                            Notification::make()
                                ->title("Test message sent to {$record->name}")
                                ->success()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Slack rejected the test message')
                            ->body('Check that the webhook URL is correct and the Slack app is still installed.')
                            ->danger()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAlertChannels::route('/'),
            'create' => CreateAlertChannel::route('/create'),
            'edit' => EditAlertChannel::route('/{record}/edit'),
        ];
    }
}
