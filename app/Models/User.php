<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Filament\Resources\UserResource;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * @method static create(array $array)
 */
class User extends Authenticatable implements FilamentUser, HasTenants
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->tenants;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->tenants()->whereKey($tenant)->exists();
    }

    public static function getForm(): array
    {
        return [
            Section::make('User')
                ->description('Information about this user')
                ->icon(UserResource::ICON)
                ->schema([
                    FileUpload::make('avatar_url')
                        ->label('Avatar')
                        ->columnSpanFull()
                        ->avatar()
                        ->directory('avatars')
                        ->imageEditor()
                        ->maxSize(1024 * 1024 * 10),
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(255),
                    DateTimePicker::make('email_verified_at')
                        ->native(false)
                        ->closeOnDateSelection(),
                    TextInput::make('password')
                        ->password()
                        ->dehydrateStateUsing(fn (?string $state): string => Hash::make($state))
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->required(fn (string $context): bool => $context === 'create')
                        ->maxLength(255),
                ])->columns(2),
        ];
    }
}
