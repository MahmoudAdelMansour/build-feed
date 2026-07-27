# Implementation Plan for /.agent/architecture

## Overview

This document outlines the step-by-step implementation plan for building the feed system architecture as described in the MINDSTORM_CONCEPTS.md file. The implementation follows a feature-driven approach, breaking down the system into manageable tasks.

## Phase 1: Foundation Setup

### Feature 1: Database Schema Implementation
**Task 1.1**: Create users table migration
- Create migration for users table with fields: id, name, email, created_at
- Add unique constraint on email field

**Task 1.2**: Create posts table migration
- Create migration for posts table with fields: id, user_id, content, created_at
- Add index on (user_id, created_at) for efficient queries

**Task 1.3**: Create follows table migration
- Create migration for follows table with fields: follower_id, followee_id, created_at
- Add unique composite index on [follower_id, followee_id] to prevent duplicate follows

**Task 1.4**: Create sponsored_posts table migration
- Create migration for sponsored_posts table with fields: id, title, content, is_active
- Add index on is_active for filtering active campaigns

**Task 1.5**: Run all migrations
- Execute migrations to create all tables
- Verify table structures and indexes

### Feature 2: Model Layer Implementation
**Task 2.1**: Create User model
- Define fillable attributes: name, email
- Add relationship methods for follows and posts

**Task 2.2**: Create Post model
- Define fillable attributes: user_id, content
- Add relationship methods for user

**Task 2.3**: Create Follow model
- Define fillable attributes: follower_id, followee_id
- Add relationship methods for follower and followee

**Task 2.4**: Create SponsoredPost model
- Define fillable attributes: title, content, is_active
- Add scope for active posts

## Phase 2: Redis Cache Layer

### Feature 3: Redis Feed Cache Implementation
**Task 3.1**: Configure Redis connection
- Set up Redis configuration in config/database.php
- Create Redis facade wrapper for feed operations

**Task 3.2**: Implement feed cache operations
- Create methods for ZADD, ZREVRANGE, ZREMRANGEBYRANK operations
- Implement cache warming and fallback mechanisms

### Feature 4: Queue System Setup
**Task 4.1**: Configure queue connection
- Set up database queue driver for job processing
- Create queue worker configuration

**Task 4.2**: Create FanoutPostJob
- Implement job to push posts to followers' feed caches
- Add logic for trimming old posts (800 entry limit)

**Task 4.3**: Create FollowBackfillJob
- Implement job to backfill posts when user follows another
- Add logic to fetch recent posts and add to feed cache

## Phase 3: Feed Generation Service

### Feature 5: Feed Service Implementation
**Task 5.1**: Create FeedService
- Implement method to fetch organic posts from Redis
- Add fallback to SQL query when Redis is empty
- Implement batch hydration of post data

**Task 5.2**: Implement cache miss handling
- Create logic to detect empty Redis feed
- Implement fallback query to posts table
- Add cache population after fallback

### Feature 6: Blending Layer Implementation
**Task 6.1**: Create FeedBlendingStrategy interface
- Define method signatures for blending operations
- Create StandardFeedStrategy implementation

**Task 6.2**: Implement ad injection logic
- Create method to fetch sponsored posts
- Add logic to inject at specific positions (slots 3 and 10)

**Task 6.3**: Implement suggestions integration
- Create method to fetch suggested users
- Add logic to attach suggestions to feed response

## Phase 4: API Layer

### Feature 7: Feed Controller Implementation
**Task 7.1**: Create FeedController
- Implement index method to return blended feed
- Add logic to use blending strategy
- Implement proper response formatting

**Task 7.2**: Create API routes
- Add route for GET /feed endpoint
- Add route for POST /follow endpoint
- Add middleware for authentication

### Feature 8: Follow/Unfollow API
**Task 8.1**: Implement FollowController
- Create store method for following users
- Implement destroy method for unfollowing users
- Add validation for follow actions

**Task 8.2**: Create Follow API routes
- Add route for POST /users/{id}/follow
- Add route for DELETE /users/{id}/follow

## Phase 5: Front-End Implementation (Tailwind + Livewire)

### Feature 9: Livewire Components for Real-time Interactions
**Task 9.1**: Create FeedDisplay Livewire component
- Build component to fetch and display blended feed
- Implement real-time refresh when new posts are published
- Add pagination with "Load More" button
- Show loading skeleton cards during fetch
- Render organic posts and sponsored posts with appropriate styling

**Task 9.2**: Create FollowButton Livewire component
- Implement component for follow/unfollow toggle
- Add loading state during API call
- Trigger feed refresh after follow action
- Change button styling based on follow state
- Emit events to sync with feed display

**Task 9.3**: Create SuggestionsSidebar Livewire component
- Build sidebar component with suggested users list
- Implement refresh button to get new suggestions
- Add skeleton loaders while fetching
- Integrate follow buttons with FeedDisplay updates
- Make sidebar sticky on desktop views

### Feature 10: Tailwind Blade Components & Main Feed View
**Task 10.1**: Create main feed layout (`resources/views/feed/index.blade.php`)
- Design two-column layout: main feed (75% width) + sidebar (25% width)
- Implement responsive grid: single column on mobile, two columns on md breakpoint
- Add sticky top navigation with logout button
- Use max-width container for centered content
- Include Livewire feed and suggestions components

**Task 10.2**: Create post card component (`resources/views/components/post-card.blade.php`)
- Design card with white background, shadow, and hover effects
- Display author avatar (gradient background with initial)
- Show author name, timestamp, and post content
- Add footer with placeholder interaction buttons (Reply, Like, Share)
- Use line-clamp-4 to limit content preview
- Apply Tailwind classes: `bg-white rounded-lg shadow-md hover:shadow-lg`

**Task 10.3**: Create sponsored post card component (`resources/views/components/sponsored-post-card.blade.php`)
- Extend post card with sponsored styling
- Add left border accent: `border-l-4 border-blue-500`
- Display "SPONSORED" badge in top-right corner
- Use distinct background: `bg-blue-50`
- Add prominent "Learn More" call-to-action button
- Differentiate from organic posts visually

**Task 10.4**: Create user suggestion card component (`resources/views/components/user-suggestion-card.blade.php`)
- Build compact card for sidebar suggestions
- Show user avatar (gradient with initial)
- Display user name (truncated if long)
- Include inline follow button (Livewire action)
- Apply hover effect: `hover:bg-gray-100 transition`
- Keep card minimal and space-efficient: `p-3 rounded-lg`

### Feature 11: Filament Admin Panel (Post & Campaign Management)
**Task 11.1**: Create Filament User Resource
- Generate CRUD interface for user management
- List columns: ID, Name, Email, Status badge, Created date
- Form fields: Name, Email, Password (create only)
- Add search on name and email
- Implement relation managers for posts and follows
- Display follow count and post count in list

**Task 11.2**: Create Filament Post Resource
- Generate full-featured post management interface
- List columns: ID, Author name (linked), Content preview (50 char limit), Created date
- Add tooltip showing full content on hover
- Form: Author selector (dropdown), Rich text editor for content
- Filters: By author dropdown, Date range picker
- Bulk delete action with confirmation
- Sort by created_at (newest first)

**Task 11.3**: Create Filament SponsoredPost Resource
- Build campaign management interface
- List columns: ID, Campaign title, Active status (boolean badge), Created date
- Form fields: Title, Rich text content, Active toggle, Admin notes textarea
- Bulk actions: Activate/Deactivate multiple campaigns (with confirmation)
- Filter by active/inactive status
- Add created_at and updated_at timestamps
- Search functionality for campaign titles

## Phase 7: Testing

### Feature 12: Unit Tests
**Task 12.1**: Test model relationships
- Create tests for User, Post, Follow, SponsoredPost models
- Verify relationship methods work correctly

**Task 12.2**: Test job operations
- Create tests for FanoutPostJob
- Create tests for FollowBackfillJob
- Verify job idempotency and correctness

**Task 12.3**: Test blending layer
- Create tests for FeedBlendingStrategy
- Create tests for StandardFeedStrategy
- Verify ad and suggestion injection

### Feature 13: Integration Tests
**Task 13.1**: Test feed generation
- Create test for Redis cache population
- Create test for SQL fallback mechanism
- Verify cold start handling

**Task 13.2**: Test follow/unfollow flow
- Create test for follow action and backfill
- Create test for feed update after follow
- Create test for unfollow behavior

**Task 13.3**: Test API endpoints
- Create tests for GET /feed endpoint
- Create tests for POST /follow endpoint
- Verify authentication and validation

## Phase 8: Documentation and Polish

### Feature 14: API Documentation
**Task 14.1**: Create API documentation
- Document all API endpoints
- Add request/response examples
- Add authentication requirements

### Feature 15: Developer Documentation
**Task 15.1**: Update architecture documentation
- Add implementation details
- Document Redis data structures
- Document queue job workflows

**Task 15.2**: Create README updates
- Add setup instructions
- Add deployment notes
- Add contribution guidelines

## Implementation Order

The implementation will follow this order:

1. **Database Layer** (Phase 1) - Foundation for all other components
2. **Redis Cache Layer** (Phase 2) - Essential for feed performance
3. **Feed Service** (Phase 3) - Core business logic
4. **API Layer** (Phase 4) - Exposes functionality to frontend
5. **Front-End** (Phase 5) - User interface
6. **Admin Panel** (Phase 6) - Content management
7. **Testing** (Phase 7) - Quality assurance
8. **Documentation** (Phase 8) - Knowledge transfer

## Key Design Decisions

1. **Push Model**: Posts are fan-out to followers' caches at write time
2. **Sorted Sets**: Redis sorted sets for feed storage (not lists) for proper ordering
3. **Strategy Pattern**: For blending layer to allow future changes
4. **Queue Jobs**: Asynchronous processing for fan-out operations
5. **Cold Start Fallback**: SQL query when Redis is empty

## Success Criteria

- All database migrations run successfully
- Models have correct relationships
- Redis cache properly stores and retrieves feed data
- Queue jobs process correctly
- Feed blending includes organic posts, ads, and suggestions
- API endpoints return correct data
- Livewire components update in real-time
- Admin panel allows content management
- All tests pass
- Documentation is complete