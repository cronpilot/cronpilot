<?php

namespace App\Models;

use App\Filament\Resources\ServerResource;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Server extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [
        'id',
        'tenant_id',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public static function getForm():array
    {
        return [
            Section::make('Server Information')
                ->columns(2)
                ->icon(ServerResource::ICON)
                ->schema([ TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                    TextInput::make('hostname')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('ssh_port')
                        ->default(22)
                        ->required()
                        ->numeric()])
        ];
    }
}
