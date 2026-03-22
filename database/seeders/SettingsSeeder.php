<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'ai_provider' => config('services.ai.provider', 'anthropic'),
            'ai_model' => config('services.ai.model', 'claude-sonnet-4-6'),
            'ai_max_tokens' => (string) config('services.ai.max_tokens', 16000),
            'generation_rate_limit' => '5',
        ];

        foreach ($defaults as $key => $value) {
            $exists = DB::table('settings')->where('key', $key)->exists();
            if (!$exists) {
                DB::table('settings')->insert([
                    'key' => $key,
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
