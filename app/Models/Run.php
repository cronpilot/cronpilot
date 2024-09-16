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

/**
 * @property int $tenant_id
 * @property int $task_id
 * @property RunStatus $status
 * @property int $duration
 * @property string|null $output
 */
class Run extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [
        'id',
        'tenant_id',
    ];

    protected $casts = [
        'status' => RunStatus::class,
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

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
