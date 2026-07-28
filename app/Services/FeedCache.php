<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class FeedCache
{
    const FEED_SET_PREFIX = 'user:';
    const FEED_SET_SUFFIX = ':feed';
    const FEED_MAX_SIZE = 800;

    public function addToFeed(int $userId, int $postId, int $timestamp): void
    {
        $feedKey = self::FEED_SET_PREFIX . $userId . self::FEED_SET_SUFFIX;
        Redis::zadd($feedKey, $timestamp, $postId);
    }

    public function getFeed(int $userId, int $start = 0, int $stop = 19): array
    {
        $feedKey = self::FEED_SET_PREFIX . $userId . self::FEED_SET_SUFFIX;
        return Redis::zrevrange($feedKey, $start, $stop);
    }

    public function trimFeed(int $userId): void
    {
        $feedKey = self::FEED_SET_PREFIX . $userId . self::FEED_SET_SUFFIX;
        Redis::zremrangebyrank($feedKey, 0, -(self::FEED_MAX_SIZE + 1));
    }
}
