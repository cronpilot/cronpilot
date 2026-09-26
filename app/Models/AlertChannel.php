<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A Slack channel that tasks can send their failure alerts to.
 */
class AlertChannel extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [
        'id',
        'tenant_id',
    ];

    // Anyone with a webhook URL can post to its channel, so it's encrypted at
    // rest and never serialized.
    protected $hidden = [
        'webhook_url',
    ];

    protected function casts(): array
    {
        return [
            'webhook_url' => 'encrypted',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
