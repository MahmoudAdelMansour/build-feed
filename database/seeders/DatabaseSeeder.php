<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            UserSeeder::class,
            PostSeeder::class,
            FollowSeeder::class,
            SponsoredPostSeeder::class,
        ]);
    }
}
