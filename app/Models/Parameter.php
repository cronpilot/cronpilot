<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Parameter extends Model
{
    use HasFactory, SoftDeletes;

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(RunParameter::class);
    }
}
