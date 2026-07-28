<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    
    public function test_returns_the_feed_for_an_authenticated_user()
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $response = $this->get('/feed');

        $response->assertStatus(200);
    }

    
    public function test_redirects_unauthenticated_users_from_the_feed()
    {
        $response = $this->get('/feed');

        $response->assertRedirect('/login');
    }
}
