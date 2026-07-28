<?php

namespace Tests\Unit;

use App\Models\Follow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowTest extends TestCase
{
    use RefreshDatabase;

    
    public function a_follow_belongs_to_a_follower_and_a_followee()
    {
        $follower = User::factory()->create();
        $followee = User::factory()->create();

        $follow = Follow::create([
            'follower_id' => $follower->id,
            'followee_id' => $followee->id,
        ]);

        $this->assertInstanceOf(User::class, $follow->follower);
        $this->assertInstanceOf(User::class, $follow->followee);
    }
}
