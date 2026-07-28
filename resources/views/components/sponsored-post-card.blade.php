@props(['post'])

<div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border-2 border-blue-200 hover:shadow-md transition-shadow duration-200 p-6">
    <div class="flex justify-between items-start mb-4">
        <div class="flex items-center">
            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-300 to-blue-600 flex items-center justify-center font-bold text-white">
                S
            </div>
            <div class="ml-4">
                <div class="font-semibold text-blue-900">{{ $post->title }}</div>
                <div class="text-xs text-blue-600">Sponsored Content</div>
            </div>
        </div>
        <span class="text-xs font-semibold bg-blue-200 text-blue-800 px-3 py-1 rounded-full">SPONSORED</span>
    </div>
    <p class="text-gray-700 mb-4">
        {{ $post->description ?? $post->content }}
    </p>
    <div class="mt-4">
        <button class="bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">
            Learn More
        </button>
    </div>
</div>
