<?php

namespace App\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CreatePost extends Component
{
    public string $content = '';

    protected array $rules = [
        'content' => 'required|string|min:1|max:500',
    ];

    public function createPost()
    {
        $this->validate();

        Auth::user()->posts()->create([
            'content' => $this->content,
        ]);

        $this->content = '';
        $this->dispatch('feed:refresh');
        session()->flash('message', 'Post created successfully!');
    }

    public function render()
    {
        return view('livewire.create-post');
    }
}
