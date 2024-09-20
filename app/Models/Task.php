<?php

namespace App\Models;

use App\Enums\RunStatus;
use App\Enums\TaskStatus;
use Carbon\Carbon;
use Error;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Recurr\Rule;
use Recurr\Transformer\TextTransformer;

/**
 * @method static find(array|bool|string|null $argument)
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
}
