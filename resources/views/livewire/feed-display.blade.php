<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Feed Column -->
        <div class="lg:col-span-2 space-y-4">
            @if(is_array($feed) || $feed instanceof \Illuminate\Support\Collection)
                @forelse ($feed as $item)
                @if ($item instanceof \App\Models\Post)
                    @include('components.post-card', ['post' => $item])
                @elseif ($item instanceof \App\Models\SponsoredPost)
                    @include('components.sponsored-post-card', ['post' => $item])
                @endif
            @empty
                <div class="bg-white rounded-lg border border-gray-200 p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10a4 4 0 018 0m-9 9h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="mt-4 text-lg font-medium text-gray-900">No posts yet</h3>
                    <p class="mt-1 text-gray-500">Follow some users to see posts in your feed</p>
                    <p class="mt-2 text-sm text-blue-600">Check out suggested users on the right →</p>
                </div>
            @endforelse
            @else
                <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                    <p class="text-red-600">Error loading feed. Please refresh the page.</p>
                </div>
            @endif

            @if(is_countable($feed) && count($feed) > 0)
                <div class="text-center mt-8">
                    <button
                        wire:click="loadMore"
                        class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                    >
                        <span wire:loading.remove>Load More</span>
                        <span wire:loading>Loading...</span>
                    </button>
                </div>
            @endif
        </div>

        <!-- Sidebar with Suggestions -->
        <div class="lg:col-span-1">
            <div class="sticky top-4">
                @livewire('suggested-users')
            </div>
        </div>
    </div>
</div>
