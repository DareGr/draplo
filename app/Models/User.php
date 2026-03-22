<?php

namespace App\Models;

use App\Enums\UserPlanEnum;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'github_id',
        'github_token',
        'github_username',
        'avatar_url',
        'stripe_customer_id',
        'plan',
        'paid_at',
        'generation_count',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'github_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'plan' => UserPlanEnum::class,
            'paid_at' => 'datetime',
            'github_token' => 'encrypted',
            'is_admin' => 'boolean',
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function serverConnections(): HasMany
    {
        return $this->hasMany(ServerConnection::class);
    }

    public function isFree(): bool
    {
        return $this->plan === UserPlanEnum::Free;
    }

    public function isPaid(): bool
    {
        return $this->plan === UserPlanEnum::Paid;
    }

    public function isSubscriber(): bool
    {
        return $this->plan === UserPlanEnum::Subscriber;
    }
}
