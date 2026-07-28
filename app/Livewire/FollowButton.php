<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class FollowButton extends Component
{
    public User $user;
    public bool $isFollowing;

    public function mount()
    {
        $this->isFollowing = Auth::user()->follows()->where('followee_id', $this->user->id)->exists();
    }

    public function toggleFollow()
    {
        if ($this->isFollowing) {
            Auth::user()->follows()->where('followee_id', $this->user->id)->delete();
            $this->isFollowing = false;
        } else {
            Auth::user()->follows()->create(['followee_id' => $this->user->id]);
            $this->isFollowing = true;
        }

        $this->dispatch('feed:refresh');
    }

    public function render()
    {
        return view('livewire.follow-button');
    }
}
