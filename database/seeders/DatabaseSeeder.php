<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(GhostUserSeeder::class);
        $this->call(FirstPartyRecipesSeeder::class);
        $this->call(FirstPartyKitsSeeder::class);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
        ]);
    }
}
