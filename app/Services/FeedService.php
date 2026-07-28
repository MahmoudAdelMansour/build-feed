<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Collection;

class FeedService
{
    protected FeedCache $feedCache;

    public function __construct(FeedCache $feedCache)
    {
        $this->feedCache = $feedCache;
    }

    public function getFeedForUser(User $user, int $page = 1, int $perPage = 20): Collection
    {
        $offset = ($page - 1) * $perPage;
        $postIds = $this->feedCache->getFeed($user->id, $offset, $offset + $perPage - 1);

        if (empty($postIds)) {
            // Cold start or cache miss: fallback to database
            $posts = $this->getFeedFromDatabase($user, $perPage);
            // Asynchronously repopulate cache if needed (can be a job)
            // For now, we'll just return the DB results directly
            return $posts;
        }

        return $this->hydratePosts($postIds);
    }

    protected function getFeedFromDatabase(User $user, int $limit): Collection
    {
        $followeeIds = $user->follows()->pluck('followee_id');

        if ($followeeIds->isEmpty()) {
            return collect();
        }

        return Post::whereIn('user_id', $followeeIds)
            ->latest()
            ->limit($limit)
            ->get();
    }

    protected function hydratePosts(array $postIds): Collection
    {
        if (empty($postIds)) {
            return collect();
        }

        // Ensure order is maintained as returned by Redis
        $posts = Post::findMany($postIds)
            ->keyBy('id');

        return collect($postIds)->map(fn ($id) => $posts->get($id))->filter();
    }
}
