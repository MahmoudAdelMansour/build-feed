<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FeedCache;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('feeds:backfill')]
#[Description('Backfill Redis feed cache for all users based on their follow graph')]
class BackfillFeeds extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FeedCache $feedCache)
    {
        $this->info('Starting feed backfill...');

        $users = User::all();

        $progressBar = $this->output->createProgressBar($users->count());
        $progressBar->start();

        $totalPostsAdded = 0;

        foreach ($users as $user) {
            $followeeIds = $user->follows()->pluck('followee_id');

            if ($followeeIds->isEmpty()) {
                $progressBar->advance();
                continue;
            }

            // Get all posts from users they follow
            $posts = \App\Models\Post::whereIn('user_id', $followeeIds)
                ->latest()
                ->limit(800)
                ->get();

            foreach ($posts as $post) {
                $feedCache->addToFeed($user->id, $post->id, $post->created_at->timestamp);
                $totalPostsAdded++;
            }

            $feedCache->trimFeed($user->id);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);
        $this->info("✅ Backfill complete!");
        $this->info("   Users processed: {$users->count()}");
        $this->info("   Total posts added to feeds: {$totalPostsAdded}");

        return Command::SUCCESS;
    }
}
