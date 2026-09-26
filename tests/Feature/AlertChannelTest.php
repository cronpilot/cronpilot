<?php

use App\Alerts\SlackWebhook;
use App\Enums\TaskStatus;
use App\Filament\Resources\AlertChannelResource\Pages\CreateAlertChannel;
use App\Filament\Resources\AlertChannelResource\Pages\EditAlertChannel;
use App\Filament\Resources\AlertChannelResource\Pages\ListAlertChannels;
use App\Filament\Resources\TaskResource\Pages\EditTask;
use App\Models\AlertChannel;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

const WEBHOOK = 'https://hooks.slack.com/services/T000/B000/abc123';

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $user = User::factory()->create(['timezone' => 'America/New_York']);
    $user->tenants()->attach($this->tenant);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($this->tenant);
});

it('adds a Slack channel and stores its webhook URL encrypted', function () {
    Livewire::test(CreateAlertChannel::class)
        ->fillForm(['name' => '#ops alerts', 'webhook_url' => WEBHOOK])
        ->call('create')
        ->assertHasNoFormErrors();

    $channel = AlertChannel::sole();

    expect($channel->tenant_id)->toBe($this->tenant->id)
        ->and($channel->webhook_url)->toBe(WEBHOOK)
        ->and(DB::table('alert_channels')->value('webhook_url'))->not->toContain('hooks.slack.com')
        ->and($channel->toArray())->not->toHaveKey('webhook_url');
});

it('only accepts Slack webhook URLs', function (string $url) {
    Livewire::test(CreateAlertChannel::class)
        ->fillForm(['name' => 'Not Slack', 'webhook_url' => $url])
        ->call('create')
        ->assertHasFormErrors(['webhook_url']);

    expect(AlertChannel::count())->toBe(0);
})->with([
    'another host' => 'https://example.com/hooks/abc',
    'plain http' => 'http://hooks.slack.com/services/T000/B000/abc123',
    'a lookalike host' => 'https://hooks.slack.com.evil.example/services/x',
    'an internal address' => 'http://169.254.169.254/latest/meta-data',
]);

it('keeps the webhook URL when a channel is edited without replacing it', function () {
    $channel = AlertChannel::factory()->for($this->tenant)->create(['webhook_url' => WEBHOOK]);

    Livewire::test(EditAlertChannel::class, ['record' => $channel->getRouteKey()])
        ->assertFormSet(['webhook_url' => null])
        ->fillForm(['name' => '#renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($channel->fresh())
        ->name->toBe('#renamed')
        ->webhook_url->toBe(WEBHOOK);
});

it('replaces the webhook URL when a new one is entered', function () {
    $channel = AlertChannel::factory()->for($this->tenant)->create(['webhook_url' => WEBHOOK]);
    $replacement = 'https://hooks.slack.com/services/T000/B000/new456';

    Livewire::test(EditAlertChannel::class, ['record' => $channel->getRouteKey()])
        ->fillForm(['webhook_url' => $replacement])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($channel->fresh()->webhook_url)->toBe($replacement);
});

it('sends a test message to the channel', function () {
    Http::fake(['hooks.slack.com/*' => Http::response('ok')]);
    $channel = AlertChannel::factory()->for($this->tenant)->create(['name' => '#ops', 'webhook_url' => WEBHOOK]);

    Livewire::test(ListAlertChannels::class)
        ->callTableAction('sendTest', $channel)
        ->assertNotified('Test message sent to #ops');

    Http::assertSent(fn (Request $request) => $request->url() === WEBHOOK
        && str_contains(json_encode($request->data()), 'test message from Cron Pilot'));
});

it('says so when Slack rejects the test message, without revealing the URL', function () {
    Http::fake(['hooks.slack.com/*' => Http::response('invalid_token', 403)]);
    $channel = AlertChannel::factory()->for($this->tenant)->create(['name' => '#ops', 'webhook_url' => WEBHOOK]);

    $component = Livewire::test(ListAlertChannels::class)
        ->callTableAction('sendTest', $channel)
        ->assertNotified('Slack rejected the test message');

    expect(json_encode($component->effects))->not->toContain('abc123');
});

it('only lists and offers the current tenant\'s channels', function () {
    $mine = AlertChannel::factory()->for($this->tenant)->create(['name' => '#mine']);
    $theirs = AlertChannel::factory()->for(Tenant::factory())->create(['name' => '#theirs']);

    Livewire::test(ListAlertChannels::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);

    $task = Task::factory()->for($this->tenant)->create([
        'status' => TaskStatus::ACTIVE,
        'schedule' => 'FREQ=HOURLY',
        'timezone' => 'America/New_York',
    ]);

    Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
        ->assertFormFieldExists('alert_channel_id', function ($field): bool {
            $options = $field->getOptions();

            return in_array('#mine', $options, true) && ! in_array('#theirs', $options, true);
        });
});

it('sends a task\'s alerts to the channel chosen on the task form', function () {
    $channel = AlertChannel::factory()->for($this->tenant)->create(['name' => '#ops']);
    $task = Task::factory()->for($this->tenant)->create([
        'status' => TaskStatus::ACTIVE,
        'schedule' => 'FREQ=HOURLY',
        'timezone' => 'America/New_York',
    ]);

    Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
        ->fillForm(['alert_channel_id' => $channel->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($task->fresh()->alertChannel->is($channel))->toBeTrue();
});

it('stops alerting a task when its channel is deleted', function () {
    $channel = AlertChannel::factory()->for($this->tenant)->create();
    $task = Task::factory()->for($this->tenant)->create(['alert_channel_id' => $channel->id]);

    $channel->forceDelete();

    expect($task->fresh()->alert_channel_id)->toBeNull();
});

it('never logs the webhook URL, even when Slack cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out for '.WEBHOOK));
    $logged = [];
    Log::listen(function ($message) use (&$logged) {
        $logged[] = $message->message.' '.json_encode($message->context);
    });

    $sent = app(SlackWebhook::class)->send(WEBHOOK, ['text' => 'hi']);

    expect($sent)->toBeFalse()
        ->and(implode("\n", $logged))->toContain('Could not reach Slack')
        ->and(implode("\n", $logged))->not->toContain('abc123');
});

it('refuses another tenant\'s channel even when submitted directly', function () {
    $theirs = AlertChannel::factory()->for(Tenant::factory())->create();
    $task = Task::factory()->for($this->tenant)->create([
        'status' => TaskStatus::ACTIVE,
        'schedule' => 'FREQ=HOURLY',
        'timezone' => 'America/New_York',
    ]);

    Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
        ->fillForm(['alert_channel_id' => $theirs->id])
        ->call('save')
        ->assertHasFormErrors(['alert_channel_id']);

    expect($task->fresh()->alert_channel_id)->toBeNull();
});
