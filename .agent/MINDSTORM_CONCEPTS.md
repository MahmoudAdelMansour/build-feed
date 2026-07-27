## Mind storm
**Main Task Say's:**
```
Build feed
People can follow each other and follow back. When you open the app or web page (as you like), you see posts from the people you follow, a few suggested people to follow, and some sponsored posts.
That's the whole brief. It's vague on purpose working out what it should actually mean is the exercise, and that's mostly what we'll talk about afterwards. )
```
At this part i'll think before the Ai start the implementation of the project. I will think about the architecture of the project and how to implement it. I will also think about the different components of the project and how they will interact with each other.
For me the task is a classic, excellent system design and product scoping exercise. The intentional vagueness is exactly where the real work happens, as building a feed for 100 users requires a fundamentally different approach than building one for 100 million.

To plan this out properly, we need to bridge the gap between product requirements and technical architecture. Let's break down the hidden complexities within your brief.

The Core Challenge: A feed system is ultimately an aggregation problem. We are taking data from many different sources (followed users, ad servers, recommendation engines) and merging them into a single, cohesive, highly available stream.

Product Scoping & Ambiguities
Before sketching out databases and API endpoints, we need to define what the brief doesn't say. Here are the product decisions that will heavily dictate our technical design:

Feed Ordering: Is the feed strictly chronological, or is it algorithmic (ranked by relevance/engagement)?

Scale and Traffic: What is the expected Daily Active User (DAU) count, and what is the read-to-write ratio? (Feeds are typically very read-heavy).

Media Types: Are posts strictly text-based, or do they include heavy media like high-res images and videos?

Engagement: Can users interact with posts (likes, comments, reposts)? The brief omits this, but a feed without engagement metrics is rare.

Sponsorship Logic: Are sponsored posts targeted based on user data, or just randomly injected every N posts?

Asymmetry: Does "follow and follow back" imply a unidirectional graph (like Twitter) or a bidirectional graph (like Facebook friends)?

### Answers for architecture bloom

At first the feed ordering will be chronological, but we will keep in mind that we can change it to algorithmic in the future.
as an interview task that 'll make sense for me .

For the scale and traffic we 'll DAU as 100k users, and the read-to-write ratio will be 10:1, meaning that for every write there will be 10 reads.
that's the normal behavior ratio for a feed system, as users will be reading the feed more than they will be posting to it.

### Product Scoping & Ambiguities ( problems or blocks )
this question get around my mind at the first but bro we've like 5 hours for that so 'll go minimum

Feed Ordering: Is the feed strictly chronological, or is it algorithmic (ranked by relevance/engagement)?

    A : chronological
Scale and Traffic: What is the expected Daily Active User (DAU) count, and what is the read-to-write ratio? (Feeds are typically very read-heavy).

    A : 100k DAU, 10:1 read-to-write ratio

Media Types: Are posts strictly text-based, or do they include heavy media like high-res images and videos?

    A : text-based

Engagement: Can users interact with posts (likes, comments, reposts)? The brief omits this, but a feed without engagement metrics is rare.

    A : no engagement metrics for now

Sponsorship Logic: Are sponsored posts targeted based on user data, or just randomly injected every N posts?

    A : randomly injected every N posts

Asymmetry: Does "follow and follow back" imply a unidirectional graph (like Twitter) or a bidirectional graph (like Facebook friends)?
### High-Level Data Entities
Based on the brief, we can identify a few core models we will need to store and manage:

User: Stores basic profile data, authentication, and metadata.

Graph/Follow: A mapping table linking a follower_id to a followee_id.

Post: Stores the content, timestamp, and author ID.

Sponsored Campaign: Stores ad content, target demographics, and view budgets.

### The Feed Generation Engine

1. Pull Model (Fan-out on Load)
   When a user opens the app, the system queries the database to find everyone they follow, fetches their most recent posts, sorts them, and returns the result.
   Pros: Cheap on storage, simple to build, great for users who post constantly (like celebrities).
   Cons: Very slow and expensive at read time, especially if a user follows thousands of people.


2. Push Model (Fan-out on Write)
   Every user has a pre-computed "inbox" or feed cache (often using an in-memory store like Redis). When User A publishes a post, the system instantly pushes that post ID into the feed caches of everyone who follows User A. When a follower opens the app, they just read their pre-compiled cache.
   Pros: Extremely fast read times (which is crucial for app opens).
   Cons: Expensive on storage and processing. If a user with 50 million followers makes a post, computing that single write is a massive operation (often called the "Justin Bieber problem").

Usually in a real-world scenario, a hybrid approach is used. For users with a small number of followers, we can use the push model, while for users with a large number of followers, we can use the pull model, but here 'll use the push model to showing up my muscles ;)
```
BTW usually i write ( muscles word) wrong and the Ai corrects me so that's a sign that i using CO-PILOT
``` 

### Injecting "Extras" (Suggestions & Ads)
The brief requires suggested users and sponsored posts to appear in the feed. This means our feed generation cannot just return the raw post list; it needs a blending layer.

**Suggested Users**: We will need an asynchronous background job to compute recommendations. This could be based on a "friend of a friend" algorithm (mutual followers) or users with high overall platform engagement.

**Sponsored Posts**: The ad server operates separately. When the user requests their feed, the blending layer fetches the next 20 organic posts from the cache, fetches 2 sponsored posts, and injects the ads at specific intervals (e.g., positions 3 and 10).


## So .then
#### 1. Database Schema
'll Keep my database tables minimal and indexed for high-performance reads.
any needed tables needed 'll consider at the archiutecture.md file

users: id, name, email, created_at

follows: follower_id, followee_id, created_at (Unique composite index on [follower_id, followee_id])

posts: id, user_id, content, created_at (Index on user_id, created_at)

sponsored_posts: id, title, content, is_active

#### 2. The Push Model (Fan-out on Write)

Since i'll choose the Push Model, 
post publishing becomes an asynchronous background job.
When a user creates a post, 
when immediately push its ID to the Redis feed list of every follower.

```
[ User Creates Post ] 
         │
         ▼
 1. Save to `posts` table
         │
         ▼
 2. Dispatch `FanoutPostJob` (Queue)
         │
         ▼
 3. Job fetches follower IDs ──► Loop through followers ──► LPUSH `user:{follower_id}:feed` post_id
                                                            LTRIM `user:{follower_id}:feed` 0 799 (Keep top 800)
```

### 3. Feed Generation & Blending Layer
When the user requests their feed, you do not query the posts table directly with WHERE user_id IN (...). Instead, you fetch post IDs directly from Redis, hydrate the posts, and blend in sponsored content and suggestions.
Since feed blending is essentially taking a raw stream of data and transforming/enriching it, three design patterns fit this problem cleanly.

If i decide to build something like this, i 'll use the following design patterns:
1. The Pipeline Pattern (Laravel Native / Functional Chain) as clanest code , but you'll think i go easy so 
2. The Strategy Pattern (Behavioral) Use Strategy if due to the feed blending rules need to change dynamically based on the user context (e.g., a StandardFeedStrategy vs. a PremiumUserFeedStrategy that skips ads).
 I know that i mentioned 'll use chronological ordering, but if we ever want to switch to algorithmic ranking, the Strategy Pattern will make that transition seamless.


### 4. Front-End Strategy (Tailwind + Filament)

Filament Admin Panel: Use Filament resources to quickly create CRUD interfaces for Users, Posts, and Sponsored Posts. This allows you to generate mock data and manage sponsored campaigns in minutes.
Tailwind Feed View: Keep the main feed single-column:
Main Column (Feed): Loop over the blended items. Render standard posts and sponsored posts using card components.
Sidebar Column (Suggested Users): Place suggested users with a simple "Follow" button that triggers an AJAX request or Livewire action.
since we're not in 1989 so 'll use Livewire for real-time updates. When a user follows someone, the feed should update without a full page refresh.

### Something personal to consider
Here we 'll face something like The "Celebrity" Problem (Fan-out Bottleneck): If a user has 100k followers, writing to 100k Redis keys synchronously in a queue job spikes memory and CPU. In production, you would use a Hybrid Model: Push for normal users, but Pull (fan-out on read) for high-follower accounts .
so don't freak please. :) 

And also 'll consider the cold start Cold Starts:
If Redis crashes or a user opens the app after 6 months of inactivity, their feed cache will be empty. The system must gracefully fall back to a SQL query (WHERE user_id IN (...) ORDER BY created_at DESC LIMIT 20) and re-populate Redis on the fly.

I notice that one thing worth a second look: I noted the "fresh follow" case in Section 7 — when someone follows a new account, that account's recent posts won't be in the follower's Redis cache until their next post triggers fan-out, so the newly-followed person's back-catalog won't show up immediately. Worth deciding whether that's acceptable for the interview scope or needs a backfill step.
`I need to update the architecture documentation`
- adding backfill test coverage
- Redis List → Sorted Set for user:{id}:feed, scored by created_at. A List only ever gets prepended to in real-time order, so there was no clean way to insert an old post into the right spot. A ZSET fixes that for free — insertion order stops mattering.
- And new FollowBackfillJob (Section 5.1): fires on follow, ZADDs the followee's last ~30 posts into the follower's set with their real timestamps as scores, so they land in correct position automatically.

- nfollow stays asymmetric on purpose — no active scrub, posts just age out via the existing trim. Called that out explicitly so it doesn't get "fixed" into an expensive scan-and-remove later.
  Propagated the change everywhere it touched: the diagram, the write/read path commands, the non-functional table, and the testing section (added backfill test cases, turned the old "open question" integration test into a defined regression test).
#### you can now look to the [ARCHITECTURE.md](ARCHITECTURE.md) and thank you for reading my funny mind storming, i hope you enjoy it and if you have any questions please ask me.
