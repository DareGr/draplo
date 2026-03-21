<?php

namespace App\Models;

use App\Enums\ProjectStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'template_slug',
        'description',
        'wizard_data',
        'generation_output',
        'skeleton_version',
        'input_hash',
        'github_repo_url',
        'github_repo_name',
        'coolify_app_id',
        'coolify_db_id',
        'deploy_url',
        'custom_domain',
        'status',
        'exported_at',
        'deployed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wizard_data' => 'array',
            'generation_output' => 'array',
            'status' => ProjectStatusEnum::class,
            'exported_at' => 'datetime',
            'deployed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class);
    }
}
