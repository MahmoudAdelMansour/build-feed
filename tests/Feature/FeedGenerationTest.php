<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\FeedCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class FeedGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_a_feed_from_cache()
    {
        $user = User::factory()->create();
        $followee = User::factory()->create();
        $user->follows()->create(['followee_id' => $followee->id]);
        $post = Post::factory()->create(['user_id' => $followee->id]);

        $feedCache = new FeedCache();
        $feedCache->addToFeed($user->id, $post->id, $post->created_at->timestamp);

        $this->actingAs($user);
        $response = $this->get('/feed');

        $response->assertStatus(200);
        $response->assertSee($post->content, false);
    }

    public function test_it_falls_back_to_the_database_if_cache_is_empty()
    {
        $user = User::factory()->create();
        $followee = User::factory()->create();
        $user->follows()->create(['followee_id' => $followee->id]);
        $post = Post::factory()->create(['user_id' => $followee->id]);

        // Clear Redis for this user
        $feedKey = 'laravel-database-user:' . $user->id . ':feed';
        Redis::del($feedKey);

        $this->actingAs($user);
        $response = $this->get('/feed');

        $response->assertStatus(200);
        $response->assertSee($post->content, false);
    }
}
