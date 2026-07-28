<?php

namespace Tests\Unit;

use App\Models\SponsoredPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SponsoredPostTest extends TestCase
{
    use RefreshDatabase;

    
    public function test_can_scope_to_active_posts()
    {
        SponsoredPost::factory()->create(['is_active' => true]);
        SponsoredPost::factory()->create(['is_active' => false]);

        $this->assertCount(1, SponsoredPost::active()->get());
    }
}
