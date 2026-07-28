<x-app-layout>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Feed -->
        <main class="lg:col-span-2">
            @livewire('create-post')
            @livewire('feed-display')
        </main>

        <!-- Sidebar -->
        <aside class="hidden lg:block">
            @livewire('suggestions-sidebar')
        </aside>
    </div>
</x-app-layout>
