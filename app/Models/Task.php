<?php

namespace App\Models;

use App\Enums\RunStatus;
use App\Enums\TaskStatus;
use App\Helpers\Recurrence;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Error;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Recurr\Rule;
use Recurr\Transformer\TextTransformer;
use Throwable;

/**
 * @method static find(array|bool|string|null $argument)
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
            $translatedRrule = (new TextTransformer())->transform($this->rrule);
        } catch (Error $e) {
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

    public function getStartDateAttribute(): ?Carbon
    {
        if (! $this->rrule?->getStartDate()) {
            return null;
        }

        return new Carbon($this->rrule->getStartDate());
    }

    public function scopeReadyToRun(Builder $query): void
    {
        $query->where('next_run_at', '<=', now())
            ->where('status', '!=', TaskStatus::DISABLED)
            ->where('paused', '!=', true);
    }

    public function getEndDateAttribute(): ?Carbon
    {
        if (! $this->rrule?->getEndDate()) {
            return null;
        }

        return new Carbon($this->rrule->getEndDate());
    }

    public function getLastRunStatusAttribute(): RunStatus
    {
        return $this->runs()->latest()->first(['status'])->status;
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

    private function createRecurrence(): null|Recurrence
    {
        try {
            return new Recurrence($this->schedule, null);
        } catch (Throwable $e) {
            // @todo: should we be suppressing here?
            return null;
        }
    }

    private function calculateNextOccurrenceAfterDate(CarbonInterface $time): null|CarbonInterface
    {
        return $this->createRecurrence()?->next($time);
    }


}
