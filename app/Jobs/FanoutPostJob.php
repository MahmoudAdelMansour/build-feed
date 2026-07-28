<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\FeedCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FanoutPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Post $post)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(FeedCache $feedCache): void
    {
        // Reload the post with user relationship to avoid serialization issues
        $post = $this->post->fresh(['user']);

        if (!$post || !$post->user) {
            // Post or user was deleted, skip
            return;
        }

        // Get follower IDs from the Follow records
        $followerIds = $post->user->followers()->pluck('follower_id');

        foreach ($followerIds as $followerId) {
            $feedCache->addToFeed($followerId, $post->id, $post->created_at->timestamp);
            $feedCache->trimFeed($followerId);
        }
    }
}
