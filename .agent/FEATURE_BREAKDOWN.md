# Feature Breakdown for Feed System Implementation

## Introduction

This document provides a detailed breakdown of each feature from the implementation plan, with specific tasks, expected outcomes, and implementation details. Each feature is designed to be implemented independently and tested before moving to the next.

---

## Feature 1: Database Schema Implementation

### Task 1.1: Create users table migration

**File to create:** `database/migrations/xxxx_create_users_table.php`

**Implementation steps:**
1. Create migration file using Laravel artisan: `php artisan make:migration create_users_table`
2. Define schema with:
   - id: bigIncrements (auto-incrementing primary key)
   - name: string (255 characters)
   - email: string (unique, 255 characters)
   - email_verified_at: timestamp (nullable)
   - password: string (60 characters)
   - remember_token: string (100 characters, nullable)
   - timestamps: created_at, updated_at

**Expected outcome:** Users table created with proper columns and indexes

**Test criteria:**
- Migration runs without errors
- Table exists in database
- Email field is unique
- Timestamps are tracked

### Task 1.2: Create posts table migration

**File to create:** `database/migrations/xxxx_create_posts_table.php`

**Implementation steps:**
1. Create migration file: `php artisan make:migration create_posts_table`
2. Define schema with:
   - id: bigIncrements
   - user_id: unsignedBigInteger (foreign key to users.id)
   - content: text
   - timestamps: created_at, updated_at
3. Add index on (user_id, created_at)

**Expected outcome:** Posts table with foreign key constraint and composite index

**Test criteria:**
- Table created with correct columns
- Foreign key to users table
- Composite index exists on (user_id, created_at)

### Task 1.3: Create follows table migration

**File to create:** `database/migrations/xxxx_create_follows_table.php`

**Implementation steps:**
1. Create migration file: `php artisan make:migration create_follows_table`
2. Define schema with:
   - follower_id: unsignedBigInteger (foreign key to users.id)
   - followee_id: unsignedBigInteger (foreign key to users.id)
   - timestamps: created_at, updated_at
3. Add unique composite index on [follower_id, followee_id]

**Expected outcome:** Follows table with bidirectional foreign keys and unique constraint

**Test criteria:**
- Table created with correct columns
- Both foreign keys reference users.id
- Unique composite index prevents duplicate follows
- Cannot follow oneself (add check constraint or validation)

### Task 1.4: Create sponsored_posts table migration

**File to create:** `database/migrations/xxxx_create_sponsored_posts_table.php`

**Implementation steps:**
1. Create migration file: `php artisan make:migration create_sponsored_posts_table`
2. Define schema with:
   - id: bigIncrements
   - title: string
   - content: text
   - is_active: boolean (default: false)
   - timestamps: created_at, updated_at
3. Add index on is_active

**Expected outcome:** SponsoredPosts table with active flag and index

**Test criteria:**
- Table created with correct columns
- is_active defaults to false
- Index exists on is_active for efficient filtering

### Task 1.5: Run all migrations

**Implementation steps:**
1. Run migrations: `php artisan migrate`
2. Verify all tables exist: `php artisan migrate:status`
3. Check table structures: `php artisan tinker` then inspect tables

**Expected outcome:** All tables created successfully

**Test criteria:**
- No migration errors
- All 4 tables exist in database
- Indexes and constraints are in place

---

## Feature 2: Model Layer Implementation

### Task 2.1: Create User model

**File to create:** `app/Models/User.php`

**Implementation steps:**
1. Extend Laravel's Authenticatable class
2. Define fillable attributes: name, email, password
3. Define hidden attributes: password, remember_token
4. Define casts: email_verified_at as datetime
5. Add relationships:
   - posts() - hasMany(Post::class)
   - followers() - belongsToMany(User::class, 'follows', 'followee_id', 'follower_id')
   - following() - belongsToMany(User::class, 'follows', 'follower_id', 'followee_id')

**Expected outcome:** User model with proper relationships and security

**Test criteria:**
- Model can be instantiated
- Relationships return correct query builder instances
- Password is hidden in serialization
- Fillable attributes work correctly

### Task 2.2: Create Post model

**File to create:** `app/Models/Post.php`

**Implementation steps:**
1. Extend Laravel's Model class
2. Define fillable attributes: user_id, content
3. Add relationship:
   - user() - belongsTo(User::class)

**Expected outcome:** Post model with user relationship

**Test criteria:**
- Model can be instantiated
- User relationship works correctly
- Fillable attributes work as expected

### Task 2.3: Create Follow model

**File to create:** `app/Models/Follow.php`

**Implementation steps:**
1. Extend Laravel's Model class
2. Define fillable attributes: follower_id, followee_id
3. Add relationships:
   - follower() - belongsTo(User::class, 'follower_id')
   - followee() - belongsTo(User::class, 'followee_id')

**Expected outcome:** Follow model with bidirectional relationships

**Test criteria:**
- Model represents follow relationship correctly
- Both relationships work properly
- Composite unique index is enforced at database level

### Task 2.4: Create SponsoredPost model

**File to create:** `app/Models/SponsoredPost.php`

**Implementation steps:**
1. Extend Laravel's Model class
2. Define fillable attributes: title, content, is_active
3. Add scope:
   - active() - query only active sponsored posts

**Expected outcome:** SponsoredPost model with active scope

**Test criteria:**
- Model works with fillable attributes
- active() scope filters correctly
- is_active boolean is handled properly

---

## Feature 3: Redis Feed Cache Implementation

### Task 3.1: Configure Redis connection

**File to modify:** `config/database.php`

**Implementation steps:**
1. Add Redis configuration under redis array
2. Set default connection to 'default'
3. Configure connection parameters (host, password, port, database)
4. Add support for clustering if needed

**Expected outcome:** Redis connection configured and accessible

**Test criteria:**
- Redis connection can be established
- Configuration is accessible via config('database.redis')
- Connection parameters are correct

### Task 3.2: Implement feed cache operations

**File to create:** `app/Services/RedisFeedService.php`

**Implementation steps:**
1. Create RedisFeedService class
2. Implement methods:
   - addPostToFeed($userId, $postId, $score) - ZADD operation
   - getFeed($userId, $limit = 20, $offset = 0) - ZREVRANGE operation
   - trimFeed($userId, $limit = 800) - ZREMRANGEBYRANK operation
   - removePostFromFeed($userId, $postId) - ZREM operation
   - getFeedCount($userId) - ZCARD operation

**Expected outcome:** Service with clean Redis interface for feed operations

**Test criteria:**
- All methods execute without errors
- Redis operations return expected results
- Sorted set maintains proper ordering

---

## Feature 4: Queue System Setup

### Task 4.1: Configure queue connection

**File to modify:** `config/queue.php`

**Implementation steps:**
1. Set default queue driver to 'database'
2. Configure database queue connection
3. Create failed_jobs table if not exists
4. Run queue table migration

**Expected outcome:** Queue system configured and ready

**Test criteria:**
- Queue connection is established
- Failed jobs table exists
- Workers can process jobs

### Task 4.2: Create FanoutPostJob

**File to create:** `app/Jobs/FanoutPostJob.php`

**Implementation steps:**
1. Implement job with necessary dependencies
2. Add execute method that:
   - Fetches all followers of post author
   - For each follower, adds post to their feed in Redis
   - Trims feed to 800 entries
   - Handles errors gracefully

**Expected outcome:** Job that fans out a post to all followers

**Test criteria:**
- Job can be dispatched
- Post is added to all followers' feeds
- Feed is trimmed to 800 entries
- Job is idempotent

### Task 4.3: Create FollowBackfillJob

**File to create:** `app/Jobs/FollowBackfillJob.php`

**Implementation steps:**
1. Implement job with follower_id and followee_id
2. Add execute method that:
   - Fetches last 30 posts from followee
   - Adds each post to follower's feed with correct timestamp score
   - Trims feed to 800 entries

**Expected outcome:** Job that backfills recent posts when following

**Test criteria:**
- Job executes successfully
- Recent posts appear in correct chronological order
- Feed is properly populated after follow

---

## Feature 5: Feed Service Implementation

### Task 5.1: Create FeedService

**File to create:** `app/Services/FeedService.php`

**Implementation steps:**
1. Create FeedService class with RedisFeedService dependency
2. Implement method:
   - getFeed($userId, $page = 0, $limit = 20)
     - Fetch post IDs from Redis feed
     - If empty, fallback to SQL query
     - Hydrate posts in batch
     - Return array of posts

**Expected outcome:** Service that returns feed with fallback mechanism

**Test criteria:**
- Returns posts from Redis when available
- Falls back to SQL when Redis is empty
- Hydrates posts efficiently (batch query)

### Task 5.2: Implement cache miss handling

**Implementation steps:**
1. Add detection for empty Redis feed
2. Implement SQL fallback query:
   - WHERE user_id IN (followed user IDs)
   - ORDER BY created_at DESC
   - LIMIT 20
3. Add cache population after fallback
4. Implement async cache warming

**Expected outcome:** Graceful handling of cold starts

**Test criteria:**
- SQL fallback works correctly
- Cache is populated after fallback
- Performance is acceptable

---

## Feature 6: Blending Layer Implementation

### Task 6.1: Create FeedBlendingStrategy interface

**File to create:** `app/Services/Blending/FeedBlendingStrategy.php`

**Implementation steps:**
1. Create interface with method:
   - blend(array $organicPosts, array $sponsoredPosts, array $suggestions): array
2. Define return structure with:
   - posts: blended post array
   - suggestions: suggested users array

**Expected outcome:** Clean interface for blending strategies

**Test criteria:**
- Interface is properly defined
- Implementations can be created
- Return structure is consistent

### Task 6.2: Implement ad injection logic

**File to create:** `app/Services/Blending/StandardFeedStrategy.php`

**Implementation steps:**
1. Implement FeedBlendingStrategy interface
2. Implement blend method:
   - Interleave sponsored posts at positions 3 and 10
   - Maintain chronological ordering of organic posts
   - Mark sponsored posts with type indicator

**Expected outcome:** Standard blending with ad injection

**Test criteria:**
- Ads appear at correct positions
- Organic posts remain in chronological order
- Sponsored posts are distinguishable

### Task 6.3: Implement suggestions integration

**Implementation steps:**
1. Add method to fetch suggested users
2. Integrate suggestions into feed response
3. Ensure suggestions are cached for performance

**Expected outcome:** Feed includes suggested users

**Test criteria:**
- Suggestions appear in response
- Suggestions are cached
- Follow action updates feed

---

## Feature 7: Feed Controller Implementation

### Task 7.1: Create FeedController

**File to create:** `app/Http/Controllers/FeedController.php`

**Implementation steps:**
1. Create controller with FeedService and FeedBlendingStrategy dependencies
2. Implement index method:
   - Get authenticated user
   - Fetch feed from FeedService
   - Apply blending strategy
   - Return JSON response

**Expected outcome:** Controller that returns blended feed

**Test criteria:**
- Returns valid JSON response
- Includes organic posts, ads, and suggestions
- Handles errors gracefully

### Task 7.2: Create API routes

**File to modify:** `routes/api.php`

**Implementation steps:**
1. Add route: GET /feed → FeedController@index
2. Add route: POST /users/{id}/follow → FollowController@store
3. Add route: DELETE /users/{id}/follow → FollowController@destroy
4. Add auth middleware to routes

**Expected outcome:** API routes properly defined

**Test criteria:**
- Routes are accessible
- Authentication is required
- Correct controllers handle requests

---

## Feature 8: Follow/Unfollow API

### Task 8.1: Implement FollowController

**File to create:** `app/Http/Controllers/FollowController.php`

**Implementation steps:**
1. Create controller with Follow model dependency
2. Implement store method:
   - Validate follow action (can't follow self)
   - Create Follow record
   - Dispatch FollowBackfillJob
   - Return success response
3. Implement destroy method:
   - Find and delete Follow record
   - Return success response

**Expected outcome:** API for following/unfollowing users

**Test criteria:**
- Follow creates relationship
- Follow dispatches backfill job
- Unfollow removes relationship
- Validation prevents self-follow

### Task 8.2: Create Follow API routes

**File to modify:** `routes/api.php`

**Implementation steps:**
1. Add route: POST /users/{user}/follow
2. Add route: DELETE /users/{user}/follow
3. Add route group with auth middleware

**Expected outcome:** Follow routes accessible and secured

**Test criteria:**
- Routes are properly defined
- Authentication is enforced
- Correct HTTP methods used

---

## Feature 9: Livewire Components (Tailwind + Real-time)

### Task 9.1: Create Feed Display Livewire Component

**File to create:** `app/Livewire/FeedDisplay.php` and `resources/views/livewire/feed-display.blade.php`

**Implementation steps:**
1. Generate Livewire component: `php artisan make:livewire FeedDisplay`
2. Implement properties:
   - `$feed = []` - Array of blended posts
   - `$suggestions = []` - Array of suggested users
   - `$loading = false` - Loading state
   - `$page = 1` - Pagination support
3. Implement methods:
   - `mount()` - Initialize component
   - `loadFeed()` - Fetch feed from FeedService
   - `refreshFeed()` - Real-time refresh
   - `loadMore()` - Pagination

**Blade template structure (Tailwind):**
```html
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
  <!-- Main Feed Column -->
  <div class="md:col-span-2">
    <div wire:loading class="space-y-4">
      <!-- Skeleton Cards -->
    </div>
    
    <div wire:loading.remove class="space-y-4">
      @foreach($feed as $item)
        @if($item['type'] === 'organic')
          <x-post-card :post="$item" />
        @else
          <x-sponsored-post-card :post="$item" />
        @endif
      @endforeach
    </div>
    
    <button wire:click="loadMore" class="w-full py-2 px-4 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
      Load More
    </button>
  </div>
  
  <!-- Sidebar -->
  <div class="md:col-span-1">
    <livewire:suggestions-sidebar :suggestions="$suggestions" />
  </div>
</div>
```

**Expected outcome:** Real-time feed display with Tailwind styling

**Test criteria:**
- Component loads and displays feed
- Real-time updates trigger correctly
- Pagination works
- Loading states show/hide properly
- Responsive on mobile and desktop

### Task 9.2: Create Follow Button Livewire Component

**File to create:** `app/Livewire/FollowButton.php` and `resources/views/livewire/follow-button.blade.php`

**Implementation steps:**
1. Generate Livewire component: `php artisan make:livewire FollowButton`
2. Implement properties:
   - `$userId` - Target user ID
   - `$isFollowing = false` - Follow state
   - `$loading = false` - Button loading state
3. Implement methods:
   - `mount($userId)` - Load follow status
   - `toggleFollow()` - Follow/unfollow action
   - `emit()` - Emit event to refresh feed

**Blade template with Tailwind:**
```html
<button 
  wire:click="toggleFollow"
  wire:loading.attr="disabled"
  class="px-4 py-2 rounded-lg font-medium transition-all 
    {{ $isFollowing 
      ? 'bg-gray-200 text-gray-800 hover:bg-gray-300' 
      : 'bg-blue-500 text-white hover:bg-blue-600' 
    }}"
>
  <span wire:loading.remove>
    {{ $isFollowing ? 'Following' : 'Follow' }}
  </span>
  <span wire:loading class="inline-block">
    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
  </span>
</button>
```

**Expected outcome:** Interactive follow button with real-time feedback

**Test criteria:**
- Follow/unfollow toggles state
- Loading indicator displays during action
- Feed updates after follow
- Error handling and validation work
- Button style changes based on follow state

### Task 9.3: Create Suggestions Sidebar Livewire Component

**File to create:** `app/Livewire/SuggestionsSidebar.php` and `resources/views/livewire/suggestions-sidebar.blade.php`

**Implementation steps:**
1. Generate Livewire component: `php artisan make:livewire SuggestionsSidebar`
2. Implement properties:
   - `$suggestions = []` - Array of suggested users
   - `$refreshing = false` - Loading state
3. Implement methods:
   - `mount()` - Load suggestions on component init
   - `loadSuggestions()` - Fetch from SuggestionService
   - `refresh()` - Get new suggestions
4. Add listeners for follow events

**Blade template with Tailwind:**
```html
<div class="sticky top-20 bg-white rounded-lg shadow-md p-4">
  <div class="flex justify-between items-center mb-4">
    <h3 class="text-lg font-semibold text-gray-800">Suggestions For You</h3>
    <button wire:click="refresh" wire:loading.attr="disabled" class="text-blue-500 hover:text-blue-600">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
      </svg>
    </button>
  </div>
  
  <div wire:loading class="space-y-3">
    @for($i = 0; $i < 3; $i++)
      <div class="animate-pulse bg-gray-200 rounded-lg p-3 h-20"></div>
    @endfor
  </div>
  
  <div wire:loading.remove class="space-y-3">
    @foreach($suggestions as $user)
      <x-user-suggestion-card :user="$user" />
    @endforeach
  </div>
</div>
```

**Expected outcome:** Responsive sidebar with suggested users

**Test criteria:**
- Suggestions load on component init
- Refresh button gets new suggestions
- User cards display correctly
- Follow buttons integrate with main feed
- Sticky positioning works on desktop

---

## Feature 10: Tailwind Blade Components & Views

### Task 10.1: Create Main Feed Layout View

**File to create:** `resources/views/feed/index.blade.php`

**Implementation steps:**
1. Create main feed layout extending app layout
2. Implement two-column grid with Tailwind:
   - `grid grid-cols-1 md:grid-cols-4 gap-4 lg:gap-6`
   - Main feed: `col-span-1 md:col-span-3`
   - Sidebar: `col-span-1`
3. Add responsive padding and spacing
4. Include Livewire feed component

**Complete template structure:**
```blade
<div class="min-h-screen bg-gray-50">
  <!-- Top Navigation -->
  <nav class="bg-white shadow-sm sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900">BuildFeed</h1>
        <div class="flex items-center gap-4">
          <span class="text-gray-600">{{ auth()->user()->name }}</span>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="text-gray-600 hover:text-gray-900">Logout</button>
          </form>
        </div>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 lg:gap-6">
      <!-- Main Feed Column -->
      <div class="md:col-span-3">
        <livewire:feed-display />
      </div>
      
      <!-- Sidebar Column -->
      <div class="md:col-span-1">
        <!-- Sticky sidebar on desktop -->
        <div class="space-y-6">
          <livewire:suggestions-sidebar />
          
          <!-- Additional info card -->
          <div class="bg-white rounded-lg shadow-md p-4 hidden md:block">
            <h3 class="font-semibold text-gray-800 mb-2">About</h3>
            <p class="text-sm text-gray-600">
              Stay connected with people you follow and discover new content.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
```

**Expected outcome:** Responsive two-column layout with proper spacing

**Test criteria:**
- Single column on mobile, two columns on md+
- Sidebar sticky on desktop
- Proper padding and gaps
- Navigation accessible

### Task 10.2: Create Post Card Component

**File to create:** `resources/views/components/post-card.blade.php`

**Implementation steps:**
1. Create reusable component with post prop
2. Display author info with avatar placeholder
3. Show post content with line clamping
4. Add timestamp with relative time formatting
5. Style with Tailwind transitions

**Component template:**
```blade
<article class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200 overflow-hidden">
  <!-- Post Header -->
  <div class="p-4 border-b border-gray-200">
    <div class="flex items-start justify-between">
      <div class="flex items-center gap-3">
        <!-- Avatar Placeholder -->
        <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center text-white font-bold">
          {{ strtoupper(substr($post['author_name'] ?? 'U', 0, 1)) }}
        </div>
        
        <div>
          <p class="font-semibold text-gray-900">{{ $post['author_name'] ?? 'Unknown' }}</p>
          <p class="text-sm text-gray-500">
            {{ isset($post['created_at']) ? \Carbon\Carbon::parse($post['created_at'])->diffForHumans() : 'Recently' }}
          </p>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Post Content -->
  <div class="p-4">
    <p class="text-gray-800 leading-relaxed line-clamp-4">
      {{ $post['content'] ?? '' }}
    </p>
  </div>
  
  <!-- Post Footer -->
  <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 flex justify-between text-sm text-gray-600">
    <button class="hover:text-blue-600 transition">💬 Reply</button>
    <button class="hover:text-red-600 transition">❤️ Like</button>
    <button class="hover:text-green-600 transition">🔄 Share</button>
  </div>
</article>
```

**Expected outcome:** Beautiful, responsive post card

**Test criteria:**
- Posts display with author info
- Timestamps format correctly
- Hover effects work smoothly
- Line clamping prevents overflow
- Card padding and spacing proper

### Task 10.3: Create Sponsored Post Card Component

**File to create:** `resources/views/components/sponsored-post-card.blade.php`

**Implementation steps:**
1. Extend post card with sponsored styling
2. Add blue left border accent: `border-l-4 border-blue-500`
3. Add "Sponsored" badge in top-right
4. Distinct background: `bg-blue-50`

**Component template:**
```blade
<article class="bg-blue-50 rounded-lg shadow-md border-l-4 border-blue-500 hover:shadow-lg transition-shadow duration-200 overflow-hidden">
  <!-- Sponsored Badge -->
  <div class="px-4 pt-3 flex justify-between items-start">
    <span class="text-xs font-semibold px-2 py-1 bg-blue-100 text-blue-800 rounded">
      SPONSORED
    </span>
  </div>
  
  <!-- Post Header -->
  <div class="p-4 border-b border-blue-200">
    <h3 class="font-bold text-gray-900 text-lg">{{ $post['title'] ?? 'Sponsored Content' }}</h3>
  </div>
  
  <!-- Post Content -->
  <div class="p-4">
    <p class="text-gray-800 leading-relaxed line-clamp-4">
      {{ $post['content'] ?? '' }}
    </p>
  </div>
  
  <!-- Call to Action -->
  <div class="px-4 py-3 bg-blue-100 border-t border-blue-200">
    <button class="w-full py-2 px-4 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition">
      Learn More →
    </button>
  </div>
</article>
```

**Expected outcome:** Visually distinct sponsored content

**Test criteria:**
- Sponsored badge displays
- Blue styling consistent
- Call-to-action button prominent
- Distinguishable from organic posts

### Task 10.4: Create User Suggestion Card Component

**File to create:** `resources/views/components/user-suggestion-card.blade.php`

**Implementation steps:**
1. Create compact card for sidebar
2. Display avatar (initial or image)
3. Show user name (truncate if long)
4. Include follow button with Livewire action
5. Add hover effect

**Component template:**
```blade
<div class="flex items-center justify-between p-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition">
  <div class="flex items-center gap-2 min-w-0 flex-1">
    <!-- User Avatar -->
    <div class="w-8 h-8 bg-gradient-to-br from-purple-400 to-purple-600 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
      {{ strtoupper(substr($user['name'] ?? 'U', 0, 1)) }}
    </div>
    
    <!-- User Info -->
    <div class="min-w-0 flex-1">
      <p class="text-sm font-semibold text-gray-900 truncate">
        {{ $user['name'] ?? 'User' }}
      </p>
    </div>
  </div>
  
  <!-- Follow Button -->
  <div class="ml-2 flex-shrink-0">
    <livewire:follow-button :userId="$user['id']" />
  </div>
</div>
```

**Expected outcome:** Compact, usable suggestion cards

**Test criteria:**
- Cards fit in sidebar
- User name truncates long names
- Follow button accessible
- Hover effect visible
- Avatar initials display correctly

---

## Feature 11: Filament Admin Panel Setup (Full Implementation)

### Task 11.1: Create User Resource

**File to create:** `app/Filament/Resources/UserResource.php`

**Implementation steps:**
1. Generate Filament resource: `php artisan make:filament-resource User`
2. Configure list columns with information
3. Add form fields for create/edit operations
4. Add relation managers for posts and follows
5. Create search and filter functionality

**Configuration details:**
```php
public static function getColumns()
{
    return [
        Tables\Columns\TextColumn::make('id')->sortable(),
        Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
        Tables\Columns\TextColumn::make('email')->sortable()->searchable(),
        Tables\Columns\BadgeColumn::make('status')
            ->formatStateUsing(fn () => 'Active')
            ->color('success'),
        Tables\Columns\TextColumn::make('created_at')
            ->dateTime()
            ->sortable(),
    ];
}

public static function getForm()
{
    return [
        Forms\Components\TextInput::make('name')
            ->required()
            ->maxLength(255),
        Forms\Components\TextInput::make('email')
            ->email()
            ->required()
            ->unique(),
        Forms\Components\TextInput::make('password')
            ->password()
            ->revealable()
            ->visible(fn (Forms\Get $get) => $get('id') === null),
    ];
}
```

**Expected outcome:** Full user management interface

**Test criteria:**
- List, create, edit, delete operations work
- Search and filtering functional
- Relationships display correctly
- Validation enforced

### Task 11.2: Create Post Resource

**File to create:** `app/Filament/Resources/PostResource.php`

**Implementation steps:**
1. Generate Filament resource: `php artisan make:filament-resource Post`
2. Configure list with post preview
3. Add author relation display
4. Create rich text editor for content
5. Add filters by date range and author
6. Implement bulk delete action

**Configuration details:**
```php
public static function getColumns()
{
    return [
        Tables\Columns\TextColumn::make('id')->sortable(),
        Tables\Columns\TextColumn::make('user.name')
            ->label('Author')
            ->sortable()
            ->searchable(),
        Tables\Columns\TextColumn::make('content')
            ->limit(50)
            ->tooltip(fn (Post $record) => $record->content),
        Tables\Columns\TextColumn::make('created_at')
            ->dateTime()
            ->sortable(),
    ];
}

public static function getForm()
{
    return [
        Forms\Components\Select::make('user_id')
            ->relationship('user', 'name')
            ->required(),
        Forms\Components\RichEditor::make('content')
            ->required()
            ->columnSpanFull(),
    ];
}

public static function getFilters()
{
    return [
        Tables\Filters\SelectFilter::make('user')
            ->relationship('user', 'name'),
        Tables\Filters\Filter::make('created_at')
            ->form([
                Forms\Components\DatePicker::make('created_from'),
                Forms\Components\DatePicker::make('created_until'),
            ]),
    ];
}
```

**Expected outcome:** Comprehensive post management

**Test criteria:**
- CRUD operations work smoothly
- Author relation loads correctly
- Content displays with preview tooltip
- Filters function properly
- Bulk actions executable

### Task 11.3: Create SponsoredPost Resource

**File to create:** `app/Filament/Resources/SponsoredPostResource.php`

**Implementation steps:**
1. Generate Filament resource: `php artisan make:filament-resource SponsoredPost`
2. Add toggle switch for is_active status
3. Configure status badge (Active/Inactive)
4. Implement bulk activation/deactivation
5. Add admin notes field for campaign tracking

**Configuration details:**
```php
public static function getColumns()
{
    return [
        Tables\Columns\TextColumn::make('id')->sortable(),
        Tables\Columns\TextColumn::make('title')->sortable()->searchable(),
        Tables\Columns\BooleanColumn::make('is_active')
            ->label('Active')
            ->sortable(),
        Tables\Columns\TextColumn::make('created_at')
            ->dateTime()
            ->sortable(),
    ];
}

public static function getForm()
{
    return [
        Forms\Components\TextInput::make('title')
            ->required()
            ->maxLength(255),
        Forms\Components\RichEditor::make('content')
            ->required()
            ->columnSpanFull(),
        Forms\Components\Toggle::make('is_active')
            ->label('Campaign Active')
            ->default(false),
        Forms\Components\Textarea::make('notes')
            ->label('Admin Notes')
            ->columnSpanFull(),
    ];
}

public static function getActions()
{
    return [
        Tables\Actions\BulkActionGroup::make([
            Tables\Actions\BulkAction::make('activate')
                ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                ->requiresConfirmation(),
            Tables\Actions\BulkAction::make('deactivate')
                ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                ->requiresConfirmation(),
        ]),
    ];
}
```

**Expected outcome:** Professional campaign management interface

**Test criteria:**
- Toggle switch updates is_active immediately
- Status badge reflects current state
- Bulk actions confirm before execution
- Filters and search work
- Created/updated timestamps track changes

---

## Feature 12: Unit Tests

### Task 12.1: Test model relationships

**File to create:** `tests/Unit/ModelsTest.php`

**Implementation steps:**
1. Create test for User model relationships
2. Create test for Post model relationships
3. Create test for Follow model relationships
4. Create test for SponsoredPost model scopes

**Expected outcome:** All model relationships tested

**Test criteria:**
- All relationships tested
- Scopes tested
- 100% code coverage for models

### Task 12.2: Test job operations

**File to create:** `tests/Unit/JobsTest.php`

**Implementation steps:**
1. Create test for FanoutPostJob
2. Create test for FollowBackfillJob
3. Test idempotency
4. Test error handling

**Expected outcome:** All jobs tested

**Test criteria:**
- Job logic verified
- Idempotency tested
- Error cases covered

### Task 12.3: Test blending layer

**File to create:** `tests/Unit/BlendingTest.php`

**Implementation steps:**
1. Create test for FeedBlendingStrategy interface
2. Create test for StandardFeedStrategy
3. Test ad injection positions
4. Test post ordering

**Expected outcome:** Blending layer fully tested

**Test criteria:**
- Strategies work correctly
- Ads inserted at correct positions
- Ordering maintained

---

## Feature 13: Integration Tests

### Task 13.1: Test feed generation

**File to create:** `tests/Integration/FeedGenerationTest.php`

**Implementation steps:**
1. Create test for Redis cache population
2. Create test for SQL fallback mechanism
3. Create test for cache miss handling
4. Test performance under load

**Expected outcome:** Feed generation thoroughly tested

**Test criteria:**
- Redis cache works
- SQL fallback works
- Cold start handled
- Performance acceptable

### Task 13.2: Test follow/unfollow flow

**File to create:** `tests/Integration/FollowFlowTest.php`

**Implementation steps:**
1. Create test for follow action and backfill
2. Create test for feed update after follow
3. Create test for unfollow behavior
4. Test edge cases

**Expected outcome:** Follow flow fully tested

**Test criteria:**
- Follow creates relationship
- Backfill works
- Feed updates correctly
- Unfollow works as expected

### Task 13.3: Test API endpoints

**File to create:** `tests/Integration/ApiTest.php`

**Implementation steps:**
1. Create test for GET /feed endpoint
2. Create test for POST /follow endpoint
3. Create test for authentication
4. Create test for validation

**Expected outcome:** All API endpoints tested

**Test criteria:**
- Endpoints return correct data
- Authentication enforced
- Validation works
- Error handling works

---

## Feature 14: API Documentation

### Task 14.1: Create API documentation

**File to create:** `docs/API.md`

**Implementation steps:**
1. Document all API endpoints
2. Add request/response examples
3. Document authentication requirements
4. Add error codes and messages

**Expected outcome:** Complete API documentation

**Test criteria:**
- All endpoints documented
- Examples are accurate
- Documentation is clear

---

## Feature 15: Developer Documentation

### Task 15.1: Update architecture documentation

**File to modify:** `.agent/ARCHITECTURE.md`

**Implementation steps:**
1. Add implementation details
2. Document Redis data structures
3. Document queue job workflows
4. Add diagrams for data flow

**Expected outcome:** Updated architecture documentation

**Test criteria:**
- Documentation is complete
- Diagrams are accurate
- Implementation details are clear

### Task 15.2: Create README updates

**File to modify:** `README.md`

**Implementation steps:**
1. Add setup instructions
2. Add deployment notes
3. Add contribution guidelines
4. Add troubleshooting section

**Expected outcome:** Complete README

**Test criteria:**
- Setup instructions work
- Deployment is documented
- Contribution process is clear

---

## Implementation Timeline

**Estimated time per feature:**
- Features 1-2: 2 hours
- Features 3-4: 2 hours
- Features 5-6: 3 hours
- Features 7-8: 2 hours
- Features 9-10: 3 hours
- Features 11-12: 2 hours
- Features 13-15: 2 hours

**Total estimated time:** 16 hours

**Recommended implementation order:** Follow the feature order listed above

**Dependencies:**
- Features 1-2 must be completed before Features 3-4
- Features 3-4 must be completed before Features 5-6
- Features 5-6 must be completed before Features 7-8
- Features 7-8 must be completed before Features 9-10
- Features 9-10 must be completed before Features 11-12
- Features 11-12 must be completed before Features 13-15