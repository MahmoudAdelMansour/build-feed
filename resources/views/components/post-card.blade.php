@props(['post'])

<div class="bg-white rounded-lg border border-gray-200 hover:shadow-md transition-shadow duration-200 p-6">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center">
            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center font-bold text-white">
                {{ substr($post->user->name, 0, 1) }}
            </div>
            <div class="ml-4">
                <div class="font-semibold text-gray-900">{{ $post->user->name }}</div>
                <div class="text-xs text-gray-500">{{ $post->created_at->diffForHumans() }}</div>
            </div>
        </div>
        @if($post->user_id === auth()->id())
            <button class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 6a2 2 0 11-4 0 2 2 0 014 0zM10 12a2 2 0 11-4 0 2 2 0 014 0zM10 18a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </button>
        @endif
    </div>
    <p class="text-gray-700 mb-4 line-clamp-4">
        {{ $post->content }}
    </p>
    <div class="flex items-center gap-6 pt-4 border-t border-gray-100 text-gray-500 text-sm">
        <button class="flex items-center gap-2 hover:text-blue-600 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
            </svg>
            <span>Like</span>
        </button>
        <button class="flex items-center gap-2 hover:text-blue-600 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
            </svg>
            <span>Reply</span>
        </button>
        <button class="flex items-center gap-2 hover:text-blue-600 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
            </svg>
            <span>Share</span>
        </button>
    </div>
</div>
