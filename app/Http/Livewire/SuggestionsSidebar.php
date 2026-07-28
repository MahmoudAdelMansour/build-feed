<?php

namespace App\Http\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SuggestionsSidebar extends Component
{
    public $suggestions;

    public function mount()
    {
        $this->loadSuggestions();
    }

    public function loadSuggestions()
    {
        $user = Auth::user();
        $followeeIds = $user->follows()->pluck('followee_id');

        $this->suggestions = User::whereNotIn('id', $followeeIds)
            ->where('id', '!=', $user->id)
            ->inRandomOrder()
            ->limit(5)
            ->get();
    }

    public function render()
    {
        return view('livewire.suggestions-sidebar');
    }
}
