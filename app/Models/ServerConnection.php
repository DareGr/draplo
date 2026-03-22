<?php

namespace App\Models;

use Database\Factories\ServerConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerConnection extends Model
{
    /** @use HasFactory<ServerConnectionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'encrypted_api_key',
        'server_id',
        'server_ip',
        'coolify_url',
        'coolify_api_key',
        'server_name',
        'server_spec',
        'status',
        'last_health_check',
    ];

    protected $hidden = [
        'encrypted_api_key',
        'coolify_api_key',
    ];

    protected function casts(): array
    {
        return [
            'encrypted_api_key' => 'encrypted',
            'coolify_api_key' => 'encrypted',
            'last_health_check' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isProvisioning(): bool
    {
        return in_array($this->status, ['provisioning', 'installing']);
    }
}
