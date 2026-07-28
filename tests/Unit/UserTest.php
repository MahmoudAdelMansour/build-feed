<?php

namespace Tests\Unit;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    
    public function a_user_can_have_posts()
    {
        $user = User::factory()->create();
        Post::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(Post::class, $user->posts->first());
    }

    
    public function a_user_can_follow_other_users()
    {
        $user = User::factory()->create();
        $followee = User::factory()->create();

        $user->follows()->create(['followee_id' => $followee->id]);

        $this->assertInstanceOf(User::class, $user->follows->first()->followee);
    }
}
