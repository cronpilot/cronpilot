<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServerCredential extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [
        'id',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'ssh_private_key' => 'encrypted',
            'passphrase' => 'encrypted',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class, 'server_server_credentials');
    }
}
