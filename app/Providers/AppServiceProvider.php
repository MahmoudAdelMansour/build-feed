<?php

namespace App\Providers;

use App\Models\Follow;
use App\Models\Post;
use App\Observers\FollowObserver;
use App\Observers\PostObserver;
use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register PostObserver to handle fan-out when posts are created
        Post::observe(PostObserver::class);

        // Register FollowObserver to backfill feeds when users follow someone
        Follow::observe(FollowObserver::class);
    }
}
