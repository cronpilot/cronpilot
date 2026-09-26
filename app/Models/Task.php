<?php

namespace App\Models;

use App\Enums\RunStatus;
use App\Enums\TaskStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Recurr\Frequency;
use Recurr\Recurrence as Occurrence;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\ArrayTransformerConfig;
use Recurr\Transformer\Constraint\AfterConstraint;
use Recurr\Transformer\TextTransformer;
use Throwable;

/**
 * @method static find(array|bool|string|null $argument)
 *
 * @property Rule $rrule
 */
class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [
        'id',
        'tenant_id',
    ];

    protected $casts = [
        'status' => TaskStatus::class,
        'allow_overlapping' => 'boolean',
        'paused' => 'boolean',
    ];

    protected static function booted(): void
    {
        // A paused task has no next run. Clearing it on every save while
        // paused keeps the edit form and Run now from setting one again, and
        // resuming schedules the next run from now, so the task waits for its
        // next scheduled time instead of running straight away to catch up.
        static::saving(function (Task $task): void {
            if ($task->paused) {
                $task->next_run_at = null;

                return;
            }

            if ($task->getOriginal('paused') && $task->schedule) {
                $task->scheduleNextRun(now());
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function serverCredential(): BelongsTo
    {
        return $this->belongsTo(ServerCredential::class);
    }

    public function alertChannel(): BelongsTo
    {
        return $this->belongsTo(AlertChannel::class);
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(Parameter::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(Run::class);
    }

    public function isRunning(): bool
    {
        return $this->runs()->where('status', RunStatus::RUNNING)->exists();
    }

    public function scopeReadyToRun(Builder $query): void
    {
        $query->where('next_run_at', '<=', now())
            ->where('status', '!=', TaskStatus::DISABLED)
            ->where('paused', '!=', true);
    }

    public function getRruleAttribute(): ?Rule
    {
        if (! $this->schedule) {
            return null;
        }

        return new Rule($this->schedule);
    }

    public function getScheduleForHumansAttribute(): ?string
    {
        if (! $this->rrule) {
            return null;
        }

        try {
            $translatedRrule = (new TextTransformer)->transform($this->rrule);
        } catch (Throwable $e) {
            return 'Custom';
        }

        if ($translatedRrule === 'Unable to fully convert this rrule to text.') {
            return 'Custom';
        }

        return ucfirst($translatedRrule);
    }

    public function getFrequencyAttribute(): null|int|string
    {
        return $this->rrule?->getFreq();
    }

    public function getIntervalAttribute(): ?string
    {
        return $this->rrule?->getInterval();
    }

    public function getByDayAttribute(): ?array
    {
        if (! $this->rrule) {
            return null;
        }

        $byDay = collect($this->rrule->getByDay());

        if ($byDay->isEmpty()) {
            return null;
        }

        if ($this->frequency === Frequency::WEEKLY) {
            return $byDay->toArray();
        }

        return $byDay
            ->map(function (string $byDay): array {
                preg_match('/^(-?\d+)?([A-Z]{2})$/', $byDay, $matches);

                return [
                    'ordinal' => $matches[0],
                    'day' => $matches[1],
                ];
            })
            ->toArray();
    }

    public function getByMonthDayAttribute(): ?array
    {
        return $this->rrule?->getByMonthDay();
    }

    /**
     * The schedule's start, as the wall-clock time entered in the form.
     */
    public function getStartDateAttribute(): ?CarbonImmutable
    {
        return self::wallClockTime($this->scheduleParts()['DTSTART'] ?? null);
    }

    /**
     * The schedule's end, as the wall-clock time entered in the form.
     */
    public function getEndDateAttribute(): ?CarbonImmutable
    {
        return self::wallClockTime($this->scheduleParts()['UNTIL'] ?? null);
    }

    /**
     * The outcome of the most recent run that actually executed.
     *
     * Skipped runs are left out so a skip can't hide the failure before it.
     */
    public function getLastRunStatusAttribute(): ?RunStatus
    {
        return $this->runs
            ->whereNotIn('status', [RunStatus::RUNNING, RunStatus::SKIPPED])
            ->sortBy([['created_at', 'desc'], ['id', 'desc']])
            ->first()
            ?->status;
    }

    public function getNextRunAtCarbonAttribute(): ?CarbonImmutable
    {
        if (! $this->next_run_at) {
            return null;
        }

        return CarbonImmutable::parse($this->next_run_at, 'UTC');
    }

    public function getUpcomingRunTimesAttribute(): Collection
    {
        return $this->runTimesAfter(now(), 3);
    }

    public function scheduleNextRun(CarbonInterface $lastOccurrenceTime): void
    {
        $nextOccurrenceTime = $this->runTimesAfter($lastOccurrenceTime)->first();

        if (! $nextOccurrenceTime) {
            // @todo: have better logic to figure out what to do here
            $this->status = TaskStatus::DISABLED;
            $this->next_run_at = null;

            return;
        }

        $this->next_run_at = $nextOccurrenceTime->utc()->toDateTimeString();
    }

    /**
     * The next times the schedule is due after $after, in the task's timezone.
     *
     * A schedule's DTSTART and UNTIL are wall-clock times in the task's
     * timezone. Recurr would read them as UTC and convert them, so they are
     * parsed here instead, and occurrences are generated in the task's
     * timezone so a 9 AM task stays at 9 AM across daylight saving changes.
     * Every calculation of when a task runs goes through this method.
     *
     * @return Collection<int, CarbonImmutable>
     */
    public function runTimesAfter(CarbonInterface $after, int $count = 1): Collection
    {
        $parts = $this->scheduleParts();

        if (! $parts) {
            return collect();
        }

        $timezone = $this->timezone ?: config('app.timezone');
        $after = CarbonImmutable::instance($after)->setTimezone($timezone);

        try {
            $start = self::wallClockTime($parts['DTSTART'] ?? null, $timezone) ?? $after->startOfDay();
            $until = self::wallClockTime($parts['UNTIL'] ?? null, $timezone);
            unset($parts['DTSTART'], $parts['UNTIL']);

            $rule = new Rule(
                self::scheduleString($parts),
                self::latestStartBefore($start, $after, $parts),
                null,
                $timezone,
            );

            $occurrences = (new ArrayTransformer((new ArrayTransformerConfig)->enableLastDayOfMonthFix()))
                ->transform($rule, new AfterConstraint($after->toDateTime(), false));
        } catch (Throwable $e) {
            return collect();
        }

        return collect($occurrences)
            ->map(fn (Occurrence $occurrence): CarbonImmutable => CarbonImmutable::instance($occurrence->getStart())->setTimezone($timezone))
            ->reject(fn (CarbonImmutable $time): bool => $until && $time->greaterThan($until))
            ->take($count)
            ->values();
    }

    /**
     * Move a fixed-period schedule's start up to just before $after.
     *
     * Recurr only generates a limited number of occurrences from the start,
     * so a frequent schedule that started long ago would otherwise run out
     * before reaching $after. Moving by whole intervals keeps the same
     * occurrences.
     */
    private static function latestStartBefore(CarbonImmutable $start, CarbonImmutable $after, array $parts): CarbonImmutable
    {
        $unit = match ($parts['FREQ'] ?? null) {
            'SECONDLY' => 'Seconds',
            'MINUTELY' => 'Minutes',
            'HOURLY' => 'Hours',
            'DAILY' => 'Days',
            'WEEKLY' => 'Weeks',
            default => null,
        };

        if (! $unit || isset($parts['COUNT']) || $start->greaterThanOrEqualTo($after)) {
            return $start;
        }

        $interval = max(1, (int) ($parts['INTERVAL'] ?? 1));
        $intervals = intdiv((int) floor($start->{"diffIn{$unit}"}($after)), $interval) - 1;

        return $intervals > 0
            ? $start->{"add{$unit}"}($intervals * $interval)
            : $start;
    }

    /**
     * @return array<string, string>
     */
    private function scheduleParts(): array
    {
        if (! $this->schedule) {
            return [];
        }

        return collect(explode(';', $this->schedule))
            ->filter()
            ->mapWithKeys(function (string $part): array {
                [$name, $value] = array_pad(explode('=', $part, 2), 2, '');

                return [strtoupper($name) => $value];
            })
            ->all();
    }

    /**
     * @param  array<string, string>  $parts
     */
    private static function scheduleString(array $parts): string
    {
        return collect($parts)->map(fn (string $value, string $name): string => "{$name}={$value}")->implode(';');
    }

    /**
     * Read an RRULE date-time (20260105T090000) as a wall-clock time.
     */
    private static function wallClockTime(?string $value, ?string $timezone = null): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Ymd\THis', rtrim($value, 'Z'), $timezone ?? config('app.timezone')) ?: null;
    }
}
