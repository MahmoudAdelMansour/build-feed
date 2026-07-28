# Feed System — Architecture Document

> Companion to `MINDSTORM_CONCEPTS.md`. This document turns the scoped decisions into a concrete architecture: data model, generation strategy, blending layer, failure modes, and front-end approach.

---

## 1. Overview

The system is a social feed: users follow each other, and on open they see a blended stream of posts from people they follow, suggested accounts to follow, and sponsored posts. The brief is deliberately vague; scoping decisions below turn it into a buildable system rather than an open-ended one.

**Core challenge:** feed generation is an aggregation problem — merging organic posts, ads, and suggestions from separate sources into one ordered, low-latency stream.

---

## 2. Scope & Assumptions

These are the product decisions this architecture is built against. Changing any one of them changes the design meaningfully — they're listed here as the contract, not just notes.

| Dimension | Decision | Why it matters architecturally |
|---|---|---|
| Ordering | Chronological (algorithmic ranking deferred) | Keeps the feed a simple sorted merge; ranking would require a scoring service |
| Scale | 100k DAU, 10:1 read-to-write ratio | Confirms the system is read-heavy → optimize reads, tolerate slower writes |
| Media | Text-only | No CDN/transcoding pipeline needed for MVP |
| Engagement | None (no likes/comments/reposts) | No engagement-count fan-in or cache invalidation on interaction |
| Sponsorship | Randomly injected every N posts | Blending layer can inject by position, not by relevance model |
| Follow graph | Unidirectional (Twitter-style: follow ≠ followed back automatically) | `follows` is a directed edge table, not a symmetric friendship table |
| Fan-out strategy | Push model (fan-out on write) | Optimizes for the read-heavy, app-open-latency-sensitive case |

---

## 3. High-Level Architecture

```
                        ┌────────────────────┐
                        │      Client         │
                        │ (Web / Livewire UI) │
                        └─────────┬───────────┘
                                  │ GET /feed
                                  ▼
                        ┌────────────────────┐
                        │   Feed Controller   │
                        │  (Blending Layer)   │
                        └─────┬────────┬──────┘
                 ┌────────────┘        └────────────┐
                 ▼                                   ▼
        ┌────────────────┐                  ┌──────────────────┐
        │  Redis Feed     │                  │  Ad Server /      │
        │  Cache          │                  │  Suggestions Job  │
        │ user:{id}:feed  │                  │  (async, cached)  │
        └───────┬─────────┘                  └──────────────────┘
                 │ (miss → fallback)
                 ▼
        ┌────────────────┐
        │  MySQL: posts,  │
        │  follows, users │
        └────────────────┘

        ┌──────────────────────────────────────────────┐
        │              Write Path (async)                │
        │  Post created → posts table → FanoutPostJob     │
        │  → queue → ZADD into each follower's feed set   │
        │  (Follow action → same path, backfilled, §5.1)  │
        └──────────────────────────────────────────────┘
```

---

## 4. Data Model

Kept minimal and indexed for read performance, per the scoping decisions.

**`users`**

| Column | Type | Notes |
|---|---|---|
| id | bigint, PK | |
| name | string | |
| email | string, unique | |
| created_at | timestamp | |

**`follows`**

| Column | Type | Notes |
|---|---|---|
| follower_id | bigint, FK → users.id | |
| followee_id | bigint, FK → users.id | |
| created_at | timestamp | |
| — | unique composite index | `[follower_id, followee_id]` — prevents duplicate follows, and is the lookup path for fan-out |

**`posts`**

| Column | Type | Notes |
|---|---|---|
| id | bigint, PK | |
| user_id | bigint, FK → users.id | |
| content | text | text-only, per scope |
| created_at | timestamp | index on `(user_id, created_at)` — supports the cold-start fallback query |

**`sponsored_posts`**

| Column | Type | Notes |
|---|---|---|
| id | bigint, PK | |
| title | string | |
| content | text | |
| is_active | boolean | toggled from the admin panel |

No engagement tables (likes/comments), no media tables — deliberately excluded per scope, not omitted by oversight. If engagement is added later, it's an additive migration, not a redesign.

---

## 5. Feed Generation: Push Model (Fan-out on Write)

Chosen deliberately over the pull model, given the 10:1 read-heavy ratio — the cost of fan-out is paid once at write time so every subsequent read is a cheap Redis fetch.

**Data structure: sorted set, not a plain list.** The original notes used a Redis List (`LPUSH` / `LTRIM`), which works fine as long as posts are always inserted in the order they happen. It breaks the moment a post needs to be inserted *out of order* — which is exactly what backfilling an old post into a newly-followed user's feed requires (Section 5.1). A List has no way to insert "in the middle, by timestamp." A **sorted set** does: `user:{id}:feed` is a ZSET where the score is the post's `created_at` (unix timestamp) and the member is the `post_id`. Any post — new or backfilled — lands in the right position purely from its score, regardless of insertion order.

**Write path:**
1. Post is saved to the `posts` table (source of truth).
2. A `FanoutPostJob` is dispatched to a queue — publishing a post must never block on fan-out.
3. The job loads the author's follower IDs from `follows`.
4. For each follower, `ZADD user:{follower_id}:feed {created_at} {post_id}`.
5. `ZREMRANGEBYRANK user:{follower_id}:feed 0 -801` — trims each set down to the newest 800 entries so Redis memory stays bounded per user.

**Read path:**
1. Feed request fetches the latest page via `ZREVRANGE user:{id}:feed 0 19` — newest-first, no join needed.
2. Post IDs are hydrated (batch fetch from `posts` or a post cache).
3. Hydrated posts are handed to the blending layer (Section 6).

Storing IDs rather than full post bodies in Redis keeps the cache small and avoids data duplication/staleness if a post is edited or deleted.

### 5.1 Backfill on Follow

**The gap:** fan-out only pushes a post to followers who existed *at the time the post was published*. If A follows B today, B's fan-out from last week never touched A's feed set — A's feed would show nothing from B until B's *next* post. For a feed product, a newly-followed account showing up empty reads as broken, not as a technical nuance, so this is worth fixing rather than accepting.

**Solution:** treat "follow" as its own fan-out trigger, symmetric to "post created," just scoped to one user instead of many.

1. Follow action is saved to the `follows` table (via the Livewire "Follow" button).
2. A `FollowBackfillJob(follower_id, followee_id)` is dispatched to the queue — same reasoning as post fan-out: don't make the follow action wait on it.
3. The job fetches the followee's **most recent K posts** (e.g. K = 30) from `posts` — bounded, not their full history, since a feed shows recent activity, not an archive.
4. Each fetched post is `ZADD`ed into `user:{follower_id}:feed` with its original `created_at` as the score.
5. Because it's a sorted set, these backfilled posts land at their correct chronological position automatically — no risk of an old post jumping to the top the way it would with `LPUSH`.
6. The existing `ZREMRANGEBYRANK` trim still applies, so backfilling a very active account can't blow past the 800-entry cap.

**Bounding K matters for cost.** Backfill is O(K) per follow action, not O(follower's entire history) and not O(followee's follower count) — so it stays cheap even for a follow-count spike, unlike the celebrity fan-out problem in Section 9, which is O(followers).

**Unfollow (not symmetric, by design):** an unfollow does *not* proactively scrub the followee's post IDs out of the follower's feed set. Actively removing them would mean scanning/removing by author across the whole set — real cost for a rare action. Instead, stale posts simply age out naturally via the normal 800-entry trim and stop being replaced (no more fan-out from that author). Acceptable trade-off for MVP scope; worth revisiting only if user complaints about "unfollowed but still seeing them" show up in practice.

---

## 6. Feed Blending Layer

The feed response is never a raw DB/cache result — it's assembled from three sources: the organic feed cache, the ad server, and the suggestions job.

**Pattern choice: Strategy.** Even though ordering is chronological today, the blending rules (how many organic posts, how ads are positioned, whether suggestions appear) are exactly the part likely to change per user tier or future ranking mode. A `FeedBlendingStrategy` interface with a `StandardFeedStrategy` implementation now, and room for e.g. `PremiumFeedStrategy` (no ads) or `RankedFeedStrategy` (algorithmic) later, means that shift doesn't require touching the read/write path — only swapping the strategy.

A simpler Pipeline (sequential transform chain) was considered and is architecturally cleaner for a single fixed blending rule, but Strategy is the better fit here specifically because the blending *rule itself* is expected to vary — by user type now, by ranking mode later.

**Blending sequence for a feed request:**
1. Fetch next batch of organic post IDs from the Redis feed list (e.g. 20).
2. Fetch N sponsored posts from the ad server (active campaigns, randomly selected).
3. Fetch suggested users from the precomputed suggestions cache.
4. Interleave sponsored posts into the organic stream at fixed positions (e.g. slots 3 and 10).
5. Attach the suggested-users block for sidebar rendering.
6. Return the merged, ordered payload to the client.

---

## 7. Suggested Users

Computed asynchronously, not on the request path — recommendation queries are too expensive to run per feed load.

- A background job periodically computes candidates per user (e.g. mutual-follower / "friend of a friend" overlap, or general high-engagement accounts as a fallback for users with a sparse graph).
- Results are cached (Redis or a `suggested_users` cache table) and simply read at request time.
- Follow/unfollow actions from the suggestions widget are handled via a Livewire action — no full page reload. A follow triggers the backfill job described in Section 5.1 so the newly-followed account's recent posts appear immediately rather than only after their next post.

---

## 8. Sponsored Posts

- Managed independently of the organic post pipeline — `sponsored_posts` with an `is_active` flag, editable via the admin panel.
- Injected at request time by the blending layer, not written into any user's Redis feed list — ads are stateless with respect to the follow graph, so they don't participate in fan-out.
- Injection is positional per scope ("every N posts"), which keeps the ad-serving logic decoupled from any targeting/ranking model — a future targeted-ads version would only change *which* sponsored posts are selected, not how they're spliced in.

---

## 9. Failure Modes & Mitigations

**The "Celebrity" Problem (fan-out bottleneck).** A pure push model breaks down for high-follower accounts — pushing to 100k+ Redis keys synchronously in one job spikes CPU/memory and delays the job queue for everyone else. Mitigation path (not required for the 100k-DAU MVP scope, but the design should not preclude it): a hybrid model — push for normal accounts, pull (fan-out on read, merged at request time) for accounts above a follower threshold. The Strategy pattern used for blending extends naturally to a `FeedSourceStrategy` making this swap contained.

**Cold starts.** If Redis is empty for a user — first login, cache eviction, or Redis itself being down — the read path must not simply return an empty feed. Fallback: query `posts` directly (`WHERE user_id IN (followed_ids) ORDER BY created_at DESC LIMIT 20`), backed by the `(user_id, created_at)` index, serve that, and asynchronously repopulate the Redis list so subsequent reads hit cache again.

---

## 10. Front-End Architecture

- **Admin (Filament):** resources for `Users`, `Posts`, and `Sponsored Posts` — gives fast CRUD for seeding mock data and managing ad campaigns without custom admin UI work.
- **Feed (Tailwind, single column):** loop over the blended item list; standard posts and sponsored posts render through shared card components so visual distinction (e.g. a "Sponsored" label) is a template-level concern, not a data-level one.
- **Sidebar:** suggested users list with a "Follow" action.
- **Livewire:** follow/unfollow updates the feed and sidebar without a full page reload — chosen over a separate AJAX/JS layer to stay inside the Laravel-native stack.

---

## 11. Non-Functional Targets

| Concern | Target / Approach |
|---|---|
| DAU | 100k |
| Read:Write ratio | 10:1 — architecture optimizes reads (cache-first) |
| Feed read latency | Redis sorted-set fetch + batch hydrate — should stay sub-100ms under normal load |
| Cache size per user | Capped at 800 post IDs via `ZREMRANGEBYRANK` |
| Write durability | `posts` table is source of truth; Redis is a derived, rebuildable cache |

---

## 12. Testing Strategy

Coverage should concentrate where the architecture actually carries risk — fan-out correctness, cache/DB consistency, and blending order — rather than aiming for blanket coverage.

**Unit level**
- `FanoutPostJob`: correct follower set is queried; the 800-entry `ZREMRANGEBYRANK` cap is enforced; job is idempotent if retried.
- `FollowBackfillJob` (Section 5.1): fetches exactly K most-recent posts from the followee, not their full history; backfilled posts land at the correct score/position in the sorted set regardless of insertion order.
- Blending layer: sponsored posts land at the configured positions; suggested-users block is attached; each `FeedBlendingStrategy` implementation is testable in isolation from the others.

**Integration level**
- Post creation → queue → Redis: a follower's `user:{id}:feed` sorted set is populated after the job runs (queue processed synchronously in the test environment).
- Cold-start fallback: with the feed set empty, a feed request falls back to the SQL query and repopulates the cache.
- Follow → backfill: following an account with existing posts surfaces those posts on the very next feed read, in correct chronological position — this is the regression test for the fresh-follow gap this section exists to close.
- Unfollow: graph edge is removed; feed set is *not* actively scrubbed (by design, Section 5.1) — test asserts that behavior explicitly so it isn't "fixed" by accident later.

**Data layer**
- The unique composite index on `follows` rejects duplicate follow edges.
- The `(user_id, created_at)` index on `posts` is confirmed via query plan to actually serve the fallback query — not just assumed present.

**Load / performance**
- This is where the 10:1 read-heavy assumption gets validated, not just stated: simulate concurrent feed reads at a load representative of 100k DAU and confirm the sub-100ms cache-hit target.
- Fan-out job duration for a typical follower count. Celebrity-scale fan-out is explicitly *not* load-tested at MVP stage — it's out of scope until the hybrid model in Section 9 is built.

**Manual / QA**
- Filament CRUD flows for Users, Posts, Sponsored Posts.
- Livewire follow interaction: feed and sidebar update without a full page reload.

**Explicitly out of scope**, matching the product scope in Section 2: engagement metrics, media handling, algorithmic-ranking correctness. Testing effort shouldn't outpace what the product actually does yet.

---

## 13. Future Evolution

The design intentionally leaves these seams open, without building them prematurely:

- **Algorithmic ranking:** swap the chronological `FeedBlendingStrategy` for a ranked one — the read/write/fan-out path doesn't change.
- **Hybrid fan-out:** add a follower-count threshold to route celebrity accounts to pull-on-read instead of push-on-write.
- **Engagement (likes/comments):** additive schema change; would introduce cache invalidation concerns for engagement counts shown in-feed, but doesn't touch the fan-out mechanism.
- **Targeted ads:** replace random sponsored-post selection with a targeting query in the ad-fetch step of the blending layer — the injection mechanism itself is unchanged.

---

## 14. Implemented Testing

A comprehensive suite of unit and feature tests has been implemented to ensure the correctness of the system.

- **Unit Tests:** Cover model relationships, job operations (fan-out and backfill), and the feed blending strategy.
- **Feature Tests:** Cover feed generation (including cache fallback), the follow/unfollow flow, and API endpoint authentication.
- **Tools:** PHPUnit, Mockery for mocking services, and Laravel's built-in testing helpers for database and queue faking.
