<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SuggestedUsers extends Component
{
    public $suggestions;
    public $followingStatus = [];

    protected $listeners = ['feed:refresh' => 'loadSuggestions'];

    public function mount()
    {
        $this->loadSuggestions();
    }

    public function loadSuggestions()
    {
        $user = Auth::user();
        $followeeIds = $user->follows()->pluck('followee_id');

        if ($followeeIds->isEmpty()) {
            // Show popular users if not following anyone
            // Order by followers, then by posts count for engagement, then random for variety
            $this->suggestions = User::withCount(['followers', 'posts'])
                ->where('id', '!=', $user->id)
                ->orderByDesc('followers_count')
                ->orderByDesc('posts_count')
                ->inRandomOrder()
                ->limit(5)
                ->get();
        } else {
            // Friend of a friend suggestions (users followed by people you follow)
            $this->suggestions = User::whereHas('follows', function ($query) use ($followeeIds) {
                $query->whereIn('followee_id', $followeeIds);
            })
            ->where('id', '!=', $user->id)
            ->whereNotIn('id', $followeeIds)
            ->withCount(['followers', 'posts'])
            ->orderByDesc('followers_count')
            ->orderByDesc('posts_count')
            ->limit(5)
            ->get();

            // Add popular/active users as fallback if we don't have enough
            if ($this->suggestions->count() < 5) {
                $alreadySuggested = $this->suggestions->pluck('id')->toArray();

                $fallback = User::withCount(['followers', 'posts'])
                    ->where('id', '!=', $user->id)
                    ->whereNotIn('id', $followeeIds)
                    ->whereNotIn('id', $alreadySuggested)
                    ->orderByDesc('followers_count')
                    ->orderByDesc('posts_count')
                    ->inRandomOrder()
                    ->limit(5 - $this->suggestions->count())
                    ->get();

                $this->suggestions = $this->suggestions->concat($fallback);
            }
        }

        // Load following status
        foreach ($this->suggestions as $suggestion) {
            $this->followingStatus[$suggestion->id] = $user->follows()
                ->where('followee_id', $suggestion->id)
                ->exists();
        }
    }

    public function toggleFollow($userId)
    {
        $user = Auth::user();

        if ($this->followingStatus[$userId] ?? false) {
            // Unfollow
            $user->follows()->where('followee_id', $userId)->delete();
            $this->followingStatus[$userId] = false;
        } else {
            // Follow
            $user->follows()->create(['followee_id' => $userId]);
            $this->followingStatus[$userId] = true;
        }

        $this->dispatch('feed:refresh');
    }

    public function render()
    {
        return view('livewire.suggested-users');
    }
}
