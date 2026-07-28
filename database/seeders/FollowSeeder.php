<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Follow;
use Illuminate\Database\Seeder;

class FollowSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        $users->each(function ($user) use ($users) {
            $usersToFollow = $users->where('id', '!=', $user->id)->random(3);
            $usersToFollow->each(function ($followee) use ($user) {
                Follow::create([
                    'follower_id' => $user->id,
                    'followee_id' => $followee->id,
                ]);
            });
        });
    }
}
