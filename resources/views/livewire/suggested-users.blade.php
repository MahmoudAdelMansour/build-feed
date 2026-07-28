<div class="space-y-4">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Suggested for you</h3>
    </div>

    @if($suggestions && $suggestions->count() > 0)
        <div class="grid grid-cols-1 gap-4">
            @foreach($suggestions as $suggestion)
                <div class="bg-white rounded-lg border border-gray-200 hover:shadow-md transition-shadow duration-200 p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center flex-1">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center font-bold text-white">
                                {{ substr($suggestion->name, 0, 1) }}
                            </div>
                            <div class="ml-4 flex-1">
                                <div class="font-semibold text-gray-900">{{ $suggestion->name }}</div>
                                <div class="text-sm text-gray-500 w-48 truncate">{{ $suggestion->email }}</div>
                                <div class="text-xs text-gray-400 mt-1">
                                    {{ $suggestion->followers_count ?? 0 }} followers
                                </div>
                            </div>
                        </div>
                        <button
                            wire:click="toggleFollow({{ $suggestion->id }})"
                            wire:loading.attr="disabled"
                            wire:target="toggleFollow({{ $suggestion->id }})"
                            class="px-4 py-2 rounded-lg font-medium transition-colors {{ $followingStatus[$suggestion->id] ?? false ? 'bg-gray-200 text-gray-700 hover:bg-gray-300' : 'bg-blue-600 text-white hover:bg-blue-700' }}"
                        >
                            <span wire:loading.remove wire:target="toggleFollow({{ $suggestion->id }})">
                                {{ $followingStatus[$suggestion->id] ?? false ? 'Following' : 'Follow' }}
                            </span>
                            <span wire:loading wire:target="toggleFollow({{ $suggestion->id }})">
                                <svg class="animate-spin h-4 w-4 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-white rounded-lg border border-gray-200 p-6 text-center">
            <p class="text-gray-500">No suggestions available at the moment</p>
        </div>
    @endif
</div>
