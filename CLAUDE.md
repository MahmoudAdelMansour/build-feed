# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Build Feed is a social networking platform built with Laravel 13.8, Livewire 3, and Tailwind CSS 4. It implements a Redis-backed feed generation system using a **fan-out on write** (push model) architecture optimized for read-heavy workloads (10:1 read-to-write ratio).

**Key Technologies:**
- PHP 8.3+ with Laravel 13.8
- Livewire/Flux for reactive UI components
- Filament 3.2 for admin panel
- Redis for feed caching (sorted sets)
- SQLite default (MySQL/PostgreSQL supported)
- Tailwind CSS 4
- Vite for asset bundling

## Development Commands

### Setup and Running

```bash
# Initial setup (runs migrations, installs dependencies, builds assets)
composer setup

# Start all development servers (Laravel, Queue, Logs, Vite) concurrently
composer dev

# Alternative: Manual start in separate terminals
php artisan serve              # Terminal 1: Web server
php artisan queue:work         # Terminal 2: Queue worker (REQUIRED for feed system)
npm run dev                    # Terminal 3: Vite dev server
```

**IMPORTANT:** The queue worker is not optional - the feed system depends on `FanoutPostJob` and `FollowBackfillJob` running to populate user feeds.

### Database Operations

```bash
# Fresh database with test data (10 users, 50 posts, follows, sponsored posts)
php artisan migrate:fresh --seed

# Seed only admin user (admin@example.com / password)
php artisan db:seed --class=AdminSeeder

# Interactive shell for testing models and relationships
php artisan tinker
```

### Testing

```bash
# Run full test suite
composer test
# or
php artisan test

# Run specific test file
php artisan test tests/Feature/FeedGenerationTest.php

# Run with coverage
php artisan test --coverage
```

### Cache Management

```bash
# Clear all caches
php artisan cache:clear
php artisan view:clear
php artisan config:clear

# Cache configuration (production)
php artisan config:cache
```

### Admin Panel

Access at `http://localhost:8000/admin` with credentials: `admin@example.com` / `password`

Create additional admin users:
```bash
php artisan make:filament-user
```

## Core Architecture

### Feed System: Fan-out on Write (Push Model)

The feed system is optimized for read performance by pre-computing feeds at write time rather than on-demand.

**Key Data Structure:** Redis sorted sets (`ZSET`), not lists. Sorted sets allow posts to be inserted in chronological order regardless of insertion time, which is critical for the backfill mechanism.

**Write Path:**
1. Post saved to `posts` table (source of truth)
2. `FanoutPostJob` dispatched to queue
3. Job loads author's follower IDs from `follows` table
4. For each follower: `ZADD user:{follower_id}:feed {created_at_timestamp} {post_id}`
5. Feed trimmed to newest 800 entries: `ZREMRANGEBYRANK user:{follower_id}:feed 0 -801`

**Read Path:**
1. `ZREVRANGE user:{id}:feed 0 19` (newest-first, 20 items)
2. Post IDs hydrated from `posts` table (batch fetch)
3. Posts passed to blending layer (see below)
4. **Cold start fallback:** If Redis empty, query `posts` directly with `WHERE user_id IN (followed_ids) ORDER BY created_at DESC` (indexed on `user_id, created_at`)

### Follow Backfill Mechanism

**Problem:** Standard fan-out only pushes posts to followers who existed at publish time. New followers would see an empty feed until the next post.

**Solution:** `FollowBackfillJob` treats follows as fan-out triggers.

1. Follow action saved to `follows` table
2. `FollowBackfillJob(follower_id, followee_id)` dispatched
3. Job fetches followee's **30 most recent posts** (bounded, not full history)
4. Each post `ZADD`ed into follower's feed with original `created_at` as score
5. Posts land in correct chronological position automatically (sorted set property)

**Unfollow behavior:** Does NOT remove posts from feed. Posts age out naturally via 800-entry trim. This avoids expensive scan operations for a rare action.

### Feed Blending Strategy Pattern

The `FeedBlendingStrategy` interface allows different feed composition rules without changing the read/write path.

**Current implementation:** `StandardFeedStrategy`
- Fetches organic posts from Redis feed cache
- Fetches sponsored posts from database (`is_active = true`)
- Injects sponsored posts at fixed positions (e.g., slots 3, 10)
- Attaches suggested users (precomputed, cached)

**Future strategies:** `PremiumFeedStrategy` (no ads), `RankedFeedStrategy` (algorithmic ranking)

Location: `app/Strategies/`

### Services Layer

- **`FeedService`**: Orchestrates feed retrieval, handles cache miss fallback
- **`FeedCache`**: Redis operations for sorted set management
- **`SuggestedUsersService`**: Precomputes user suggestions (friend-of-friend, high-engagement accounts)

Location: `app/Services/`

### Jobs (Queue)

- **`FanoutPostJob`**: Distributes new post to all followers' feed caches
- **`FollowBackfillJob`**: Backfills recent posts when user follows someone

Location: `app/Jobs/`

**Queue Connection:** `database` (see `.env.example`). Redis queue recommended for production.

## Models and Relationships

**User** (app/Models/User.php)
- `posts()` - hasMany
- `follows()` - belongsToMany (users being followed, via `follows.followee_id`)
- `followers()` - belongsToMany (users following this user, via `follows.follower_id`)
- `following()` - alias for `follows()`

**Post** (app/Models/Post.php)
- `user()` - belongsTo
- `content` - text only (no media in MVP)

**Follow** (app/Models/Follow.php)
- `follower_id` + `followee_id` with unique composite index
- Unidirectional (Twitter-style, not symmetric friendship)

**SponsoredPost** (app/Models/SponsoredPost.php)
- `is_active` boolean flag
- Managed via Filament admin panel
- Injected at request time, NOT fan-out to feeds

## Testing Architecture

Tests focus on fan-out correctness, cache/DB consistency, and blending order.

**Key Test Files:**
- `tests/Unit/FanoutPostJobTest.php` - Feed distribution, 800-entry cap, idempotency
- `tests/Unit/FollowBackfillJobTest.php` - K-recent posts fetch, score positioning
- `tests/Unit/StandardFeedStrategyTest.php` - Sponsored post injection, blending order
- `tests/Feature/FeedGenerationTest.php` - End-to-end feed flow, cold start fallback
- `tests/Feature/FollowTest.php` - Follow → backfill → feed read integration

**Test Environment:**
- Queue processed synchronously (`Queue::fake()` or sync driver)
- SQLite in-memory for speed
- Redis operations mocked or tested with real Redis

## Important Architectural Decisions

### Why Sorted Sets over Lists?

Original design considered Redis Lists (`LPUSH`/`LTRIM`) but switched to sorted sets (`ZSET`) because:
- Lists only support insertion at head/tail
- Backfilling old posts into a new follower's feed requires insertion at arbitrary chronological positions
- Sorted sets use `created_at` timestamp as score, allowing posts to land in correct order regardless of insertion time
- Trade-off: Slightly more memory per feed, but eliminates ordering bugs

### Why Push Model (Fan-out on Write)?

Given 10:1 read-to-write ratio and feed-open latency being critical:
- **Push model:** Pay fan-out cost once at write time, every read is fast Redis fetch
- **Pull model:** Would query and merge on every feed request

**Limitation:** Doesn't scale to "celebrity" accounts (100k+ followers). Mitigation documented but not implemented - future hybrid model would push to normal users, pull for high-follower accounts at request time.

### Why Strategy Pattern for Blending?

Blending rules (organic vs sponsored ratio, injection positions, ranking) are expected to vary:
- By user tier (premium users see no ads)
- By ranking mode (chronological vs algorithmic)
- By A/B test

Strategy pattern allows swapping blending logic without touching feed generation/caching layer.

## Configuration Notes

**Environment Variables:**
- `QUEUE_CONNECTION=database` - Uses `jobs` table. Set to `redis` for production.
- `CACHE_STORE=database` - Feed cache uses Redis directly via `FeedCache` service, not Laravel cache facade
- `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` - Required for feed system
- `DB_CONNECTION=sqlite` - Default. MySQL/PostgreSQL supported with schema changes

**Redis Connection:**
Feed system assumes Redis is available. If Redis is unavailable, system falls back to database queries (slower but functional).

## Filament Admin Resources

Located in `app/Filament/Resources/`:
- `UserResource.php` - User CRUD
- `PostResource.php` - Post management
- `SponsoredPostResource.php` - Campaign management (`is_active` toggle)

Admin panel runs on same Laravel instance at `/admin` route.

## Livewire Components

Located in `app/Http/Livewire/`:
- `FeedDisplay.php` - Main feed rendering
- `FollowButton.php` - Follow/unfollow actions (updates feed without page reload)
- `SuggestedUsers.php` - Sidebar suggestions

Components use Livewire/Flux for reactivity - no separate AJAX layer.

## Important Index Requirements

**Database indexes required for performance:**

```sql
-- posts table
INDEX `user_id_created_at` (user_id, created_at)  -- Cold start fallback query

-- follows table
UNIQUE INDEX `follower_followee` (follower_id, followee_id)  -- Fan-out query + duplicate prevention
```

Verify with: `EXPLAIN SELECT * FROM posts WHERE user_id IN (...) ORDER BY created_at DESC LIMIT 20`

## Known Limitations (by Design)

- **No engagement metrics** (likes, comments, reposts) - deferred to avoid cache invalidation complexity
- **Text-only posts** - no media pipeline
- **Chronological feed** - algorithmic ranking deferred
- **Random sponsored post selection** - no targeting/relevance model
- **Celebrity accounts** - fan-out will be slow for 100k+ followers (hybrid model needed)
- **Unfollow doesn't scrub feed** - posts age out naturally via trim

## Common Development Scenarios

### Testing Feed Generation Flow
```bash
php artisan tinker

# Create users and post
$user1 = User::factory()->create();
$user2 = User::factory()->create();
$user1->follows()->attach($user2->id);

# Create post and manually process queue
$post = Post::factory()->create(['user_id' => $user2->id]);
Queue::fake();  // In test environment
dispatch(new FanoutPostJob($post));

# Check feed
$feedService = app(FeedService::class);
$feed = $feedService->getFeedForUser($user1);
```

### Debugging Feed Cache
```bash
# In Redis CLI
ZREVRANGE user:1:feed 0 -1 WITHSCORES  # View all feed entries with timestamps
ZCARD user:1:feed                       # Count entries
```

### Adding New Feed Strategy
1. Create class implementing `FeedBlendingStrategy` in `app/Strategies/`
2. Implement `blend(Collection $organicPosts, User $user): Collection`
3. Bind in service provider based on user type/context
4. Write unit tests in `tests/Unit/`

## Deployment Considerations

```bash
# Production build
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# Cache optimization
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate --force

# Start queue worker (supervisord recommended)
php artisan queue:work --tries=3 --timeout=90
```

**Redis in production:**
- Use persistent Redis (not cache)
- Monitor memory usage (800 entries × user count × ~100 bytes per entry)
- Set `maxmemory-policy allkeys-lru` if memory constrained

**Queue monitoring:**
- Use Laravel Horizon for Redis queues
- Or monitor `jobs` table size if using database queue
- Failed jobs logged to `failed_jobs` table

## Test Credentials (After Seeding)

**Admin:** admin@example.com / password
**Regular users:** Generated users all have password: `password`

## External Documentation

Detailed docs in repository root:
- `ARCHITECTURE.md` - Complete architectural reasoning and design decisions
- `IMPLEMENTATION_SUMMARY.md` - Frontend features and patterns
- `API_DOCUMENTATION.md` - API endpoints (if applicable)
- `BUG_FIX_REPORT.md` - Known issues and resolutions
