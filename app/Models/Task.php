<?php

namespace App\Models;

use App\Enums\RunStatus;
use App\Enums\TaskStatus;
use App\Helpers\Recurrence;
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
use Recurr\Rule;
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
    ];

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

    public function parameters(): HasMany
    {
        return $this->hasMany(Parameter::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(Run::class);
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

    public function getStartDateAttribute(): ?CarbonImmutable
    {
        if (! $this->rrule?->getStartDate()) {
            return null;
        }

        return new CarbonImmutable($this->rrule->getStartDate());
    }

    public function getEndDateAttribute(): ?CarbonImmutable
    {
        if (! $this->rrule?->getEndDate()) {
            return null;
        }

        return new CarbonImmutable($this->rrule->getEndDate());
    }

    public function getLastRunStatusAttribute(): ?RunStatus
    {
        return $this->runs
            ->where('status', '!=', RunStatus::RUNNING)
            ->sortBy('created_at')
            ->first()
            ?->status;
    }

    public function getNextRunAtCarbonAttribute(): ?CarbonImmutable
    {
        if (! $this->next_run_at) {
            return null;
        }

        return CarbonImmutable::parse($this->next_run_at)->shiftTimezone($this->timezone);
    }

    public function getUpcomingRunTimesAttribute(): Collection
    {
        $scheduleStart = $this->startDate?->copy() ?? today();
        $scheduleEnd = $this->endDate?->copy();

        $scheduleStart->shiftTimezone($this->timezone);
        $scheduleEnd?->shiftTimezone($this->timezone);

        $scheduler = new Recurrence($this->schedule, $scheduleStart);

        $lastRunTime = max($scheduleStart->subSecond(), now()->subSecond());

        $upcomingRunTimes = collect();

        for ($i = 0; $i < 3; $i++) {
            if ($lastRunTime) {
                $lastRunTime = $scheduler->next($lastRunTime)?->shiftTimezone($this->timezone);

                if ($lastRunTime && (! $scheduleEnd || $lastRunTime < $scheduleEnd)) {
                    $upcomingRunTimes->push($lastRunTime);
                }
            }
        }

        return $upcomingRunTimes;
    }

    public function scheduleNextRun(CarbonInterface $lastOccurrenceTime): void
    {
        $nextOccurrenceTime = $this->calculateNextOccurrenceAfterDate($lastOccurrenceTime);

        if (! $nextOccurrenceTime) {
            // @todo: have better logic to figure out what to do here
            $this->status = TaskStatus::DISABLED;
            $this->next_run_at = null;

            return;
        }

        $this->next_run_at = $nextOccurrenceTime;
    }

    private function createRecurrence(): ?Recurrence
    {
        try {
            return new Recurrence($this->schedule, null);
        } catch (Throwable $e) {
            // @todo: should we be suppressing here?
            return null;
        }
    }

    private function calculateNextOccurrenceAfterDate(CarbonInterface $time): ?CarbonInterface
    {
        return $this->createRecurrence()?->next($time);
    }
}
