<?php

namespace Tests\Unit;

use App\Jobs\FanoutPostJob;
use App\Models\Post;
use App\Models\User;
use App\Services\FeedCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class FanoutPostJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fans_out_a_post_to_followers()
    {
        $user = User::factory()->create();
        $follower = User::factory()->create();
        $user->followers()->create(['follower_id' => $follower->id]);

        $post = Post::factory()->create(['user_id' => $user->id]);

        $feedCache = Mockery::mock(FeedCache::class);
        $feedCache->shouldReceive('addToFeed')->once()->with($follower->id, $post->id, $post->created_at->timestamp);
        $feedCache->shouldReceive('trimFeed')->once()->with($follower->id);

        $job = new FanoutPostJob($post);
        $job->handle($feedCache);
    }
}
