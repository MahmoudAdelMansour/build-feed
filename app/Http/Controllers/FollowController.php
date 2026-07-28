<?php

namespace App\Http\Controllers;

use App\Jobs\FollowBackfillJob;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FollowController extends Controller
{
    public function store(Request $request, User $user)
    {
        $follower = Auth::user();

        if ($follower->id === $user->id) {
            return back()->with('error', 'You cannot follow yourself.');
        }

        $follower->follows()->create([
            'followee_id' => $user->id,
        ]);

        FollowBackfillJob::dispatch($follower, $user);

        return back()->with('success', 'You are now following ' . $user->name);
    }

    public function destroy(Request $request, User $user)
    {
        $follower = Auth::user();

        $follower->follows()->where('followee_id', $user->id)->delete();

        return back()->with('success', 'You have unfollowed ' . $user->name);
    }
}
