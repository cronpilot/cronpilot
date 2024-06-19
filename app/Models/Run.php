<?php

namespace App\Models;

use App\Enums\RunStatus;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Run extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'status' => RunStatus::class,
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function triggerable(): MorphTo
    {
        return $this->morphTo();
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(RunParameter::class);
    }

    public function getDurationForHumansAttribute(): string
    {
        return CarbonInterval::seconds($this->duration)->cascade()->forHumans();
    }
}
