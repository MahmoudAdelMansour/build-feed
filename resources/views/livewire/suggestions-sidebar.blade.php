<div class="bg-white rounded-lg border border-gray-200 p-6">
    <h2 class="text-lg font-bold text-gray-900 mb-4">Who to follow</h2>

    <div class="space-y-4">
        @forelse($suggestions as $user)
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900 hover:text-blue-600 cursor-pointer">
                        {{ $user->name }}
                    </h3>
                    <p class="text-xs text-gray-500">@{{ str_replace(' ', '', strtolower($user->name)) }}</p>
                </div>
                @livewire('follow-button', ['user' => $user], key('follow-' . $user->id))
            </div>
        @empty
            <p class="text-gray-500 text-sm text-center py-8">No suggestions right now</p>
        @endforelse
    </div>

    <!-- Stats Section -->
    <div class="mt-6 pt-6 border-t border-gray-100">
        <div class="grid grid-cols-2 gap-4">
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-900">0</p>
                <p class="text-xs text-gray-500">Posts</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-900">{{ auth()->user()->follows()->count() }}</p>
                <p class="text-xs text-gray-500">Following</p>
            </div>
        </div>
    </div>

    <button
        wire:click="loadSuggestions"
        class="w-full mt-6 px-4 py-2 text-sm font-medium text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
    >
        Refresh suggestions
    </button>
</div>
