<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\FeedCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FollowBackfillJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public User $follower, public User $followee)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(FeedCache $feedCache): void
    {
        $posts = $this->followee->posts()->latest()->limit(30)->get();

        foreach ($posts as $post) {
            $feedCache->addToFeed($this->follower->id, $post->id, $post->created_at->timestamp);
        }
        $feedCache->trimFeed($this->follower->id);
    }
}
