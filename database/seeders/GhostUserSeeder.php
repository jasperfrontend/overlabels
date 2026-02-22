<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GhostUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['twitch_id' => 'GHOST_USER'],
            [
                'name' => 'Ghost User',
                'email' => 'ghost@overlabels.internal',
                'password' => Hash::make(Str::random(64)),
                'role' => 'user',
                'is_system_user' => true,
                'onboarded_at' => now(),
                'webhook_secret' => Str::random(64),
            ]
        );
    }
}
