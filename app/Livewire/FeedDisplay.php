<?php

namespace App\Livewire;

use App\Services\FeedService;
use App\Strategies\StandardFeedStrategy;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class FeedDisplay extends Component
{
    public $feed = [];
    public $page = 1;

    protected $listeners = ['feed:refresh' => 'refreshFeed'];

    public function mount(FeedService $feedService, StandardFeedStrategy $feedStrategy)
    {
        $this->loadFeed($feedService, $feedStrategy);
    }

    public function refreshFeed(FeedService $feedService, StandardFeedStrategy $feedStrategy)
    {
        $this->page = 1;
        $this->loadFeed($feedService, $feedStrategy);
    }

    public function loadMore(FeedService $feedService, StandardFeedStrategy $feedStrategy)
    {
        $this->page++;
        $this->loadFeed($feedService, $feedStrategy, true);
    }

    public function render()
    {
        return view('livewire.feed-display');
    }

    private function loadFeed(FeedService $feedService, StandardFeedStrategy $feedStrategy, $append = false)
    {
        $user = Auth::user();
        $organicPosts = $feedService->getFeedForUser($user, $this->page);
        $blendedFeed = $feedStrategy->blendFeed($user, $organicPosts);

        if ($append) {
            $this->feed = $this->feed->concat($blendedFeed);
        } else {
            $this->feed = $blendedFeed;
        }
    }
}
