<?php

namespace App\Observers;

use App\Jobs\FollowBackfillJob;
use App\Models\Follow;

class FollowObserver
{
    /**
     * Handle the Follow "created" event.
     */
    public function created(Follow $follow): void
    {
        // Dispatch backfill job to populate the follower's feed with the followee's recent posts
        FollowBackfillJob::dispatch($follow->follower, $follow->followee);
    }

    /**
     * Handle the Follow "updated" event.
     */
    public function updated(Follow $follow): void
    {
        //
    }

    /**
     * Handle the Follow "deleted" event.
     */
    public function deleted(Follow $follow): void
    {
        //
    }

    /**
     * Handle the Follow "restored" event.
     */
    public function restored(Follow $follow): void
    {
        //
    }

    /**
     * Handle the Follow "force deleted" event.
     */
    public function forceDeleted(Follow $follow): void
    {
        //
    }
}
