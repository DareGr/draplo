<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DevUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'dev@draplo.test'],
            [
                'name' => 'Dev User',
                'github_id' => 'dev-12345',
                'github_username' => 'devuser',
                'generation_count' => 0,
                'is_admin' => true,
            ]
        );
    }
}
