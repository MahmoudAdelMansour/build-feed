<?php

namespace Tests\Feature;

use App\Jobs\FollowBackfillJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FollowTest extends TestCase
{
    use RefreshDatabase;

    
    public function a_user_can_follow_another_user()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user);
        $response = $this->post(route('users.follow', $otherUser));

        $response->assertRedirect();
        $this->assertDatabaseHas('follows', [
            'follower_id' => $user->id,
            'followee_id' => $otherUser->id,
        ]);
    }

    
    public function a_user_can_unfollow_another_user()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $user->follows()->create(['followee_id' => $otherUser->id]);

        $this->actingAs($user);
        $response = $this->delete(route('users.unfollow', $otherUser));

        $response->assertRedirect();
        $this->assertDatabaseMissing('follows', [
            'follower_id' => $user->id,
            'followee_id' => $otherUser->id,
        ]);
    }

    
    public function following_a_user_dispatches_the_backfill_job()
    {
        Queue::fake();

        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user);
        $this->post(route('users.follow', $otherUser));

        Queue::assertPushed(FollowBackfillJob::class);
    }
}
