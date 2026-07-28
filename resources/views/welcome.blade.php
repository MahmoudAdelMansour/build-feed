<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Build Feed</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <a href="/" class="text-2xl font-bold text-blue-600">
                        Build Feed
                    </a>
                </div>
                <div class="flex items-center gap-4">
                    @auth
                        <a href="{{ route('feed') }}" class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">
                            Go to Feed
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="bg-red-600 text-white hover:bg-red-700 px-4 py-2 rounded-md text-sm font-medium">
                                Logout
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">
                            Sign In
                        </a>
                        <a href="{{ route('register') }}" class="bg-blue-600 text-white hover:bg-blue-700 px-4 py-2 rounded-md text-sm font-medium">
                            Register
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 py-20 items-center">
            <!-- Left Content -->
            <div class="space-y-8">
                <div>
                    <h1 class="text-5xl md:text-6xl font-bold text-gray-900 mb-4">
                        Stay Connected
                    </h1>
                    <p class="text-xl text-gray-600">
                        Build Feed is a modern social platform where you can share, connect, and grow your network.
                    </p>
                </div>

                <div class="space-y-4">
                    <a href="{{ route('register') }}" class="block w-full sm:w-auto bg-blue-600 text-white hover:bg-blue-700 px-8 py-3 rounded-lg font-semibold text-center transition-colors">
                        Get Started
                    </a>
                    <p class="text-sm text-gray-600">
                        Already have an account? <a href="{{ route('login') }}" class="font-semibold text-blue-600 hover:text-blue-700">Sign in</a>
                    </p>
                </div>
            </div>

            <!-- Right - Features -->
            <div class="space-y-6">
                <div class="bg-white rounded-lg p-6 border border-gray-200 hover:shadow-lg transition-shadow">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Share Content</h3>
                            <p class="text-sm text-gray-600">Post updates and engage with your community</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg p-6 border border-gray-200 hover:shadow-lg transition-shadow">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-2a6 6 0 0112 0v2zm0 0h6v-2a6 6 0 00-9-5.684"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Build Network</h3>
                            <p class="text-sm text-gray-600">Follow people and grow your connections</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg p-6 border border-gray-200 hover:shadow-lg transition-shadow">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Personalized Feed</h3>
                            <p class="text-sm text-gray-600">See updates from the people you follow</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Section -->
        <div class="grid grid-cols-3 gap-8 py-12 border-t border-gray-200">
            <div class="text-center">
                <p class="text-4xl font-bold text-blue-600">{{ \App\Models\User::count() }}</p>
                <p class="text-gray-600 mt-2">Active Users</p>
            </div>
            <div class="text-center">
                <p class="text-4xl font-bold text-green-600">{{ \App\Models\Post::count() }}</p>
                <p class="text-gray-600 mt-2">Posts Shared</p>
            </div>
            <div class="text-center">
                <p class="text-4xl font-bold text-purple-600">{{ \App\Models\Follow::count() }}</p>
                <p class="text-gray-600 mt-2">Connections</p>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="text-center text-gray-600 text-sm">
                <p>&copy; 2026 Build Feed. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
