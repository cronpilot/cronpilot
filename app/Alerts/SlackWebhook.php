<?php

namespace App\Alerts;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Posts messages to Slack incoming webhooks.
 *
 * Webhook URLs are secrets, so they're never logged, and only Slack's own
 * webhook host is ever contacted, so a channel can't be pointed at an
 * internal address.
 */
class SlackWebhook
{
    public static function isAllowedUrl(?string $url): bool
    {
        return is_string($url) && preg_match('#^https://hooks\.slack\.com/[^\s]+$#', $url) === 1;
    }

    /**
     * Returns whether Slack accepted the message.
     *
     * @param  array<string, mixed>  $payload  Slack message JSON: text and optional blocks
     */
    public function send(string $url, array $payload): bool
    {
        if (! self::isAllowedUrl($url)) {
            Log::warning('Refused to send a Slack message to a URL that is not a Slack webhook');

            return false;
        }

        try {
            $response = Http::timeout(10)->acceptJson()->post($url, $payload);
        } catch (ConnectionException $e) {
            // Connection errors quote the URL they failed on, so hide it.
            Log::warning('Could not reach Slack', ['error' => str_replace($url, '[webhook URL]', $e->getMessage())]);

            return false;
        }

        if ($response->failed()) {
            Log::warning('Slack rejected a message', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 200),
            ]);

            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public static function testMessage(string $channelName): array
    {
        $text = "This is a test message from Cron Pilot. Alerts for tasks using {$channelName} will appear here.";

        return [
            'text' => $text,
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => ['type' => 'mrkdwn', 'text' => ":white_check_mark: {$text}"],
                ],
            ],
        ];
    }
}
