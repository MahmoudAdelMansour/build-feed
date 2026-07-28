<?php

namespace App\Strategies;

use App\Models\User;
use Illuminate\Support\Collection;

interface FeedBlendingStrategy
{
    public function blendFeed(User $user, Collection $organicPosts): Collection;
}
