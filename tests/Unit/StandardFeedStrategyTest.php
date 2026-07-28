<?php

namespace Tests\Unit;

use App\Models\Post;
use App\Models\SponsoredPost;
use App\Models\User;
use App\Strategies\StandardFeedStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandardFeedStrategyTest extends TestCase
{
    use RefreshDatabase;

    
    public function test_blends_organic_and_sponsored_posts()
    {
        $user = User::factory()->create();
        $organicPosts = Post::factory()->count(20)->create();
        SponsoredPost::factory()->count(5)->create(['is_active' => true]);

        $strategy = new StandardFeedStrategy();
        $blendedFeed = $strategy->blendFeed($user, $organicPosts);

        $this->assertCount(22, $blendedFeed); // 20 organic + 2 sponsored
        $this->assertInstanceOf(SponsoredPost::class, $blendedFeed[2]);
        $this->assertInstanceOf(SponsoredPost::class, $blendedFeed[9]);
    }
}
