<?php

namespace Tests\Unit;

use App\Jobs\FollowBackfillJob;
use App\Models\Post;
use App\Models\User;
use App\Services\FeedCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FollowBackfillJobTest extends TestCase
{
    use RefreshDatabase;

    
    public function test_backfills_a_followees_posts_to_a_new_follower()
    {
        $follower = User::factory()->create();
        $followee = User::factory()->create();
        Post::factory()->count(5)->create(['user_id' => $followee->id]);

        $feedCache = Mockery::mock(FeedCache::class);
        $feedCache->shouldReceive('addToFeed')->times(5);
        $feedCache->shouldReceive('trimFeed')->once()->with($follower->id);

        $job = new FollowBackfillJob($follower, $followee);
        $job->handle($feedCache);
    }
}
