<?php

namespace App\Http\Controllers;

use App\Services\FeedService;
use App\Strategies\StandardFeedStrategy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeedController extends Controller
{
    protected FeedService $feedService;
    protected StandardFeedStrategy $feedStrategy;

    public function __construct(FeedService $feedService, StandardFeedStrategy $feedStrategy)
    {
        $this->feedService = $feedService;
        $this->feedStrategy = $feedStrategy;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $page = $request->input('page', 1);

        $organicPosts = $this->feedService->getFeedForUser($user, $page);
        $blendedFeed = $this->feedStrategy->blendFeed($user, $organicPosts);

        return response()->json($blendedFeed);
    }
}
