<?php

namespace App\Models;

use App\Enums\RunStatus;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public function parameters(): HasMany
    {
        return $this->hasMany(Parameter::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(Run::class);
    }

    public function getLastRunStatusAttribute(): RunStatus
    {
        return $this->runs()->latest()->first(['status'])->status;
    }
}
