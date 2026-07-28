<?php

namespace App\Strategies;

use App\Models\SponsoredPost;
use App\Models\User;
use Illuminate\Support\Collection;

class StandardFeedStrategy implements FeedBlendingStrategy
{
    public function blendFeed(User $user, Collection $organicPosts): Collection
    {
        $sponsoredPosts = SponsoredPost::active()->inRandomOrder()->take(2)->get();

        $blendedFeed = $organicPosts->collect();

        // Inject sponsored posts at positions 3 and 10
        if ($blendedFeed->count() > 2 && $sponsoredPosts->first()) {
            $blendedFeed->splice(2, 0, [$sponsoredPosts->first()]);
        }
        if ($blendedFeed->count() > 9 && $sponsoredPosts->last()) {
            $blendedFeed->splice(9, 0, [$sponsoredPosts->last()]);
        }

        // Return only posts and sponsored posts (suggestions are handled by separate component)
        return $blendedFeed->filter()->values();
    }

    protected function getSuggestions(User $user): Collection
    {
        // "Friend of a friend" suggestions
        $followeeIds = $user->follows()->pluck('followee_id');

        if ($followeeIds->isEmpty()) {
            // Fallback to just popular users if the user follows no one
            return User::withCount('followers')->orderByDesc('followers_count')->limit(3)->get();
        }

        $suggestions = User::whereHas('follows', function ($query) use ($followeeIds) {
            $query->whereIn('followee_id', $followeeIds);
        })
        ->where('id', '!=', $user->id)
        ->whereNotIn('id', $followeeIds)
        ->withCount('followers')
        ->orderByDesc('followers_count')
        ->limit(3)
        ->get();

        if ($suggestions->count() < 3) {
            $fallbackSuggestions = User::withCount('followers')
                ->orderByDesc('followers_count')
                ->where('id', '!=', $user->id)
                ->whereNotIn('id', $followeeIds)
                ->whereNotIn('id', $suggestions->pluck('id'))
                ->limit(3 - $suggestions->count())
                ->get();
            $suggestions = $suggestions->concat($fallbackSuggestions);
        }

        return $suggestions;
    }
}
