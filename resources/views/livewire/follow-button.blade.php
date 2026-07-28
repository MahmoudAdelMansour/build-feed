<div>
    @if($isFollowing)
        <button
            wire:click="toggleFollow"
            class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium transition-colors"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-50"
        >
            <span wire:loading.remove>Following</span>
            <span wire:loading>Updating...</span>
        </button>
    @else
        <button
            wire:click="toggleFollow"
            class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 font-medium transition-colors"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-50"
        >
            <span wire:loading.remove>Follow</span>
            <span wire:loading>Following...</span>
        </button>
    @endif
</div>
