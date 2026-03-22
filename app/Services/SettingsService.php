<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SettingsService
{
    private array $cache = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $row = DB::table('settings')->where('key', $key)->first();

        $value = $row ? $row->value : $default;
        $this->cache[$key] = $value;

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $exists = DB::table('settings')->where('key', $key)->exists();

        if ($exists) {
            DB::table('settings')->where('key', $key)->update([
                'value' => (string) $value,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('settings')->insert([
                'key' => $key,
                'value' => (string) $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->cache[$key] = (string) $value;
    }

    public function all(): array
    {
        return DB::table('settings')->pluck('value', 'key')->toArray();
    }

    public function getAiConfig(): array
    {
        return [
            'provider' => $this->get('ai_provider', config('services.ai.provider', 'anthropic')),
            'model' => $this->get('ai_model', config('services.ai.model', 'claude-sonnet-4-6')),
            'max_tokens' => (int) $this->get('ai_max_tokens', config('services.ai.max_tokens', 16000)),
        ];
    }
}
