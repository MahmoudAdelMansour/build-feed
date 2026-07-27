# Quick Reference: Feed System Implementation

## Overview

This document provides a quick reference guide for implementing the feed system architecture based on the mindstorm concepts. It summarizes the key components, data flow, and implementation priorities.

---

## Architecture at a Glance

```
┌─────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   User      │────►│  Feed Request   │────►│  Redis Cache    │
│             │     │                 │     │  (Sorted Set)   │
└─────────────┘     └─────────────────┘     └────────┬────────┘
                                                    │
                                                    ▼
                                             ┌─────────────────┐
                                             │  Hydrate Posts  │
                                             │  (Batch Query)  │
                                             └────────┬────────┘
                                                      │
                                                      ▼
                                             ┌─────────────────┐
                                             │  Blending Layer │
                                             │  - Ads          │
                                             │  - Suggestions  │
                                             └────────┬────────┘
                                                      │
                                                      ▼
                                             ┌─────────────────┐
                                             │   Response      │
                                             │   (JSON)        │
                                             └─────────────────┘
```

---

## Key Components

### 1. Database Schema
- **users**: User authentication and profile data
- **posts**: Organic posts (text-only, per scope)
- **follows**: Follow relationships (directed graph)
- **sponsored_posts**: Ad content

### 2. Redis Cache Layer
- **Data Structure**: Sorted Sets (`ZSET`)
- **Key Pattern**: `user:{id}:feed`
- **Score**: Unix timestamp of post creation
- **Max Size**: 800 entries per user
- **Operations**: ZADD, ZREVRANGE, ZREMRANGEBYRANK

### 3. Queue System
- **Driver**: Database queue (can be upgraded to Redis)
- **Jobs**:
  - `FanoutPostJob`: Pushes new posts to followers
  - `FollowBackfillJob`: Backfills recent posts on follow

### 4. Service Layer
- **FeedService**: Manages feed retrieval with fallback
- **RedisFeedService**: Low-level Redis operations
- **Blending Strategy**: Merges organic, ads, and suggestions

### 5. API Layer
- **Endpoints**:
  - `GET /feed`: Get blended feed
  - `POST /users/{id}/follow`: Follow user
  - `DELETE /users/{id}/follow`: Unfollow user

### 6. Front-End (Tailwind CSS + Livewire)
- **Framework**: Livewire (real-time component refresh) + Blade templates
- **Styling**: Tailwind CSS (utility-first, responsive design)
- **Layout**: Two-column grid (75% feed, 25% sidebar on desktop; single column on mobile)
- **Components**:
  - **FeedDisplay**: Livewire component fetching/rendering blended feed with pagination
  - **FollowButton**: Livewire component toggling follow state with real-time feedback
  - **SuggestionsSidebar**: Sticky sidebar with suggested users and follow buttons
  - **PostCard**: Reusable Blade component for organic posts with author info, timestamp, content preview
  - **SponsoredPostCard**: Post variant with blue border, badge, and CTA button
  - **UserSuggestionCard**: Compact card with avatar, name, and inline follow button
- **Key Tailwind Classes**:
  - Grid: `grid grid-cols-1 md:grid-cols-4 gap-6`
  - Cards: `bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow`
  - Responsive: `hidden md:block` for desktop-only elements
  - Loading: `wire:loading` class toggling with skeleton loaders
  - Sponsored: `border-l-4 border-blue-500 bg-blue-50`
- **Real-time Updates**: Livewire event listeners auto-refresh feed when user follows someone

### 7. Admin Panel (Filament)
- **Framework**: Filament (headless Laravel admin builder)
- **Resources**:
  - **Users**: List (ID, Name, Email, Status, Created), Create/Edit/Delete with password field
  - **Posts**: List with author link and content preview, Search/Filter by author and date range, Rich text editor
  - **Sponsored Posts**: Toggle is_active, Status badge, Bulk activate/deactivate actions, Campaign tracking notes

---

## Data Flow

### Feed Generation (Push Model)

```
[ User Creates Post ]
        │
        ▼
[ Save to posts table ]
        │
        ▼
[ Dispatch FanoutPostJob ]
        │
        ▼
[ For each follower: ]
  - ZADD user:{follower_id}:feed {timestamp} {post_id}
  - ZREMRANGEBYRANK user:{follower_id}:feed 0 -801
```

### Feed Retrieval

```
[ User Requests Feed ]
        │
        ▼
[ Check Redis cache ]
        │
        ├─── [ Hit ] ──► [ Fetch post IDs ]
        │                  [ Hydrate posts ]
        │                  [ Return ]
        │
        └─── [ Miss ] ─► [ Query SQL: WHERE user_id IN (...) 
                           ORDER BY created_at DESC LIMIT 20 ]
                          │
                          ▼
                     [ Populate Redis ]
                          │
                          ▼
                     [ Return Results ]
```

### Follow Action

```
[ User Clicks Follow ]
        │
        ▼
[ Create Follow record ]
        │
        ▼
[ Dispatch FollowBackfillJob ]
        │
        ▼
[ Fetch last 30 posts from followee ]
        │
        ▼
[ For each post: ]
  - ZADD user:{follower_id}:feed {timestamp} {post_id}
```

### Feed Blending

```
[ Get Organic Posts ]
        │
        ▼
[ Get Sponsored Posts (random, active) ]
        │
        ▼
[ Get Suggestions ]
        │
        ▼
[ Interleave: ]
  - Position 3: Ad
  - Position 10: Ad
        │
        ▼
[ Return Blended Feed ]
```

---

## Implementation Priority

### Phase 1: Foundation (Must Have)
1. ✅ Database migrations and models
2. ✅ Redis configuration
3. ✅ Queue setup
4. ✅ Basic feed service

### Phase 2: Core Functionality (Must Have)
1. ✅ FanoutPostJob implementation
2. ✅ FollowBackfillJob implementation
3. ✅ Feed retrieval with fallback
4. ✅ Follow/unfollow API

### Phase 3: Enhancement (Should Have)
1. ✅ Blending layer (ads and suggestions)
2. ✅ Livewire components
3. ✅ Blade views
4. ✅ Filament admin

### Phase 4: Quality (Nice to Have)
1. ✅ Comprehensive tests
2. ✅ API documentation
3. ✅ Developer documentation
4. ✅ Performance optimization

---

## Key Design Decisions

### Why Sorted Sets?
- **Problem**: Lists can't insert posts out of chronological order
- **Solution**: ZSET uses timestamp as score, ensuring correct ordering
- **Benefit**: Backfill works without manual positioning

### Why Push Model?
- **Read-heavy workload**: 10:1 ratio favors read optimization
- **App-open latency**: Pre-computed feeds are instantly available
- **Cost**: Write-time fan-out is acceptable for normal users

### Why Strategy Pattern?
- **Flexibility**: Easy to change blending rules
- **Extensibility**: Can add premium feeds, ranked feeds
- **Testability**: Each strategy can be tested independently

### Why Backfill on Follow?
- **User Experience**: New follows show recent posts immediately
- **Performance**: Limited to last 30 posts, not full history
- **Correctness**: Posts appear in proper chronological position

---

## Redis Commands Reference

| Operation | Command | Example |
|-----------|---------|---------|
| Add post | `ZADD` | `ZADD user:1:feed 1234567890 42` |
| Get posts | `ZREVRANGE` | `ZREVRANGE user:1:feed 0 19` |
| Trim feed | `ZREMRANGEBYRANK` | `ZREMRANGEBYRANK user:1:feed 0 -801` |
| Remove post | `ZREM` | `ZREM user:1:feed 42` |
| Get count | `ZCARD` | `ZCARD user:1:feed` |
| Check exists | `EXISTS` | `EXISTS user:1:feed` |

---

## API Endpoints

### GET /feed
**Description**: Get user's blended feed
**Parameters**: 
- `page` (optional): Page number, default 0
- `limit` (optional): Posts per page, default 20
**Response**:
```json
{
  "posts": [
    {
      "id": 1,
      "user_id": 2,
      "content": "Post content",
      "created_at": "2024-01-01 12:00:00",
      "type": "organic"
    },
    {
      "id": 3,
      "title": "Sponsored Post",
      "content": "Ad content",
      "created_at": "2024-01-01 11:00:00",
      "type": "sponsored"
    }
  ],
  "suggestions": [
    {
      "id": 5,
      "name": "User Name",
      "email": "user@example.com"
    }
  ]
}
```

### POST /users/{id}/follow
**Description**: Follow a user
**Response**:
```json
{
  "message": "Following user"
}
```

### DELETE /users/{id}/follow
**Description**: Unfollow a user
**Response**:
```json
{
  "message": "Unfollowed user"
}
```

---

## Testing Strategy

### Unit Tests
- Model relationships
- Job operations
- Blending logic

### Integration Tests
- Feed generation with Redis
- SQL fallback mechanism
- Follow/unfollow flow
- API endpoints

### Performance Tests
- Feed load time under load
- Fanout job performance
- Redis memory usage

---

## Common Commands

```bash
# Run migrations
php artisan migrate

# Run tests
php artisan test

# Clear cache
php artisan cache:clear

# Queue workers
php artisan queue:work

# Redis commands (via tinker)
php artisan tinker
>>> Redis::ping()
>>> Redis::zAdd('user:1:feed', 1234567890, 42)
>>> Redis::zRevRange('user:1:feed', 0, 19)

# Filament admin
php artisan filament:admin:create
```

---

## Troubleshooting

### Issue: Redis connection failed
**Solution**: Check `.env` configuration and ensure Redis server is running

### Issue: Feed is empty
**Solution**: Check if user follows anyone and if Redis cache is populated

### Issue: Follow doesn't update feed
**Solution**: Verify FollowBackfillJob is dispatched and queue worker is running

### Issue: Ads not showing
**Solution**: Check if sponsored posts are active and in correct positions

---

## Front-End Implementation Guide (Tailwind + Filament)

### Livewire Components Architecture

**FeedDisplay Component** (`app/Livewire/FeedDisplay.php`)
- Properties: `$feed`, `$suggestions`, `$loading`, `$page`
- Methods:
  - `mount()`: Initialize and load feed
  - `loadFeed()`: Fetch from FeedService
  - `loadMore()`: Pagination
  - `refreshFeed()`: Event listener for follow actions
- View: `resources/views/livewire/feed-display.blade.php`

**FollowButton Component** (`app/Livewire/FollowButton.php`)
- Properties: `$userId`, `$isFollowing`, `$loading`
- Methods:
  - `mount($userId)`: Check follow status
  - `toggleFollow()`: Follow/unfollow action
  - `emit('refreshFeed')`: Trigger feed update
- View: `resources/views/livewire/follow-button.blade.php`

**SuggestionsSidebar Component** (`app/Livewire/SuggestionsSidebar.php`)
- Properties: `$suggestions`, `$refreshing`
- Methods:
  - `mount()`: Load suggestions on init
  - `loadSuggestions()`: Fetch from SuggestionService
  - `refresh()`: Get new suggestions
- View: `resources/views/livewire/suggestions-sidebar.blade.php`

### Blade Component Structure

**Post Card** (`resources/views/components/post-card.blade.php`)
```
┌─────────────────────────────────────┐
│ [Avatar] Author Name                │
│          Posted 2 hours ago         │
├─────────────────────────────────────┤
│ Post content text (line-clamp-4)    │
│ ...                                 │
├─────────────────────────────────────┤
│ 💬 Reply  ❤️ Like  🔄 Share         │
└─────────────────────────────────────┘
```

**Sponsored Post Card** (`resources/views/components/sponsored-post-card.blade.php`)
```
┌─────────────────────────────────────┐
│ 🎯 SPONSORED                        │
├─────────────────────────────────────┤
│ Campaign Title                      │
├─────────────────────────────────────┤
│ Ad content text (line-clamp-4)      │
│ ...                                 │
├─────────────────────────────────────┤
│   [Learn More →] (Blue CTA)         │
└─────────────────────────────────────┘
```

**User Suggestion Card** (`resources/views/components/user-suggestion-card.blade.php`)
```
┌──────────────────────────────────┐
│ [Avatar] User Name    [Follow]   │
└──────────────────────────────────┘
```

### Tailwind CSS Utilities Reference

| Purpose | Classes |
|---------|---------|
| Grid Layout | `grid grid-cols-1 md:grid-cols-4 gap-6` |
| Card Base | `bg-white rounded-lg shadow-md` |
| Card Hover | `hover:shadow-lg transition-shadow` |
| Sticky Sidebar | `sticky top-20` |
| Line Clamp | `line-clamp-4` |
| Responsive Hide | `hidden md:block` |
| Sponsored Border | `border-l-4 border-blue-500` |
| Sponsored Background | `bg-blue-50` |
| Avatar Circle | `w-10 h-10 rounded-full` |
| Button Base | `py-2 px-4 rounded-lg font-semibold` |
| Button Primary | `bg-blue-500 hover:bg-blue-600` |
| Button Secondary | `bg-gray-200 hover:bg-gray-300` |
| Loading State | `wire:loading` with skeleton |

### Directory Structure

```
resources/
├── views/
│   ├── feed/
│   │   └── index.blade.php          # Main feed layout
│   ├── livewire/
│   │   ├── feed-display.blade.php
│   │   ├── follow-button.blade.php
│   │   └── suggestions-sidebar.blade.php
│   ├── components/
│   │   ├── post-card.blade.php
│   │   ├── sponsored-post-card.blade.php
│   │   └── user-suggestion-card.blade.php
│   └── layouts/
│       └── app.blade.php            # Main app layout
app/
├── Livewire/
│   ├── FeedDisplay.php
│   ├── FollowButton.php
│   └── SuggestionsSidebar.php
├── Filament/
│   └── Resources/
│       ├── UserResource.php
│       ├── PostResource.php
│       └── SponsoredPostResource.php
```

### Implementation Checklist

- [ ] Create FeedDisplay Livewire component with real-time refresh
- [ ] Create FollowButton component with state toggle
- [ ] Create SuggestionsSidebar with refresh functionality
- [ ] Build main feed layout with responsive grid
- [ ] Create post-card component with Tailwind styling
- [ ] Create sponsored-post-card with blue accent styling
- [ ] Create user-suggestion-card for sidebar
- [ ] Configure Filament User resource with CRUD
- [ ] Configure Filament Post resource with filters
- [ ] Configure Filament SponsoredPost with toggles
- [ ] Test responsive layout on mobile/tablet/desktop
- [ ] Verify real-time updates with Livewire
- [ ] Test follow/unfollow flow with feed updates
- [ ] Verify sponsored posts inject at correct positions
- [ ] Test admin panel CRUD operations

---

## Issue: Troubleshooting Front-End

### Issue: Feed doesn't update after follow
**Solution**: Check that Livewire component has `#[On('refreshFeed')]` listener and that FollowButton emits the event

### Issue: Tailwind classes not applying
**Solution**: Ensure `resources/views/**/*.blade.php` included in Tailwind config, run `npm run build` after changes

### Issue: Livewire component not mounting
**Solution**: Verify Livewire directive in layout, check console for JS errors, ensure component class namespace correct

### Issue: Admin panel sidebar not showing
**Solution**: Run `php artisan filament:install --panels=admin`, clear cache with `php artisan cache:clear`

---

## Next Steps

1. **Implement Phase 1**: Database and basic services
2. **Add Phase 2**: Fanout and follow functionality
3. **Enhance with Phase 3**: Blending and UI
4. **Polish with Phase 4**: Tests and documentation

---

## References

- [MINDSTORM_CONCEPTS.md](MINDSTORM_CONCEPTS.md) - Original requirements and decisions
- [ARCHITECTURE.md](ARCHITECTURE.md) - Detailed architecture documentation
- [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md) - Feature breakdown
- [FEATURE_BREAKDOWN.md](FEATURE_BREAKDOWN.md) - Detailed tasks
- [STEP_BY_STEP_GUIDE.md](STEP_BY_STEP_GUIDE.md) - Implementation walkthrough