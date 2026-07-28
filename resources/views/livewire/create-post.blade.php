<div class="bg-white rounded-lg border border-gray-200 p-6 mb-6">
    <!-- User Info -->
    <div class="flex items-center mb-4">
        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center font-bold text-white">
            {{ substr(auth()->user()->name, 0, 1) }}
        </div>
        <div class="ml-4">
            <p class="font-semibold text-gray-900">{{ auth()->user()->name }}</p>
            <p class="text-xs text-gray-500">Create a new post</p>
        </div>
    </div>

    <!-- Form -->
    <form wire:submit.prevent="createPost" class="space-y-4">
        <!-- Textarea -->
        <textarea
            wire:model.defer="content"
            placeholder="What's on your mind?"
            class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none
            @error('content') border-red-500 @enderror"
            rows="4"
        ></textarea>

        @error('content')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror

        <!-- Button -->
        <div class="flex justify-end">
            <button
                type="submit"
                class="px-6 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove>Post</span>
                <span wire:loading>Posting...</span>
            </button>
        </div>
    </form>

    <!-- Success Message -->
    @if(session()->has('message'))
        <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
            <p class="text-sm text-green-700">{{ session('message') }}</p>
        </div>
    @endif
</div>
