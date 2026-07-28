<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Post;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        $users->each(function ($user) {
            Post::factory(5)->create([
                'user_id' => $user->id,
            ]);
        });
    }
}
