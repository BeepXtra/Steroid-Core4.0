<?php
defined('_SECURED') or die('Restricted access');

/**
 * Lightweight in-process cache backed by APCu when available,
 * falling back to a PHP static array for the lifetime of a single request.
 *
 * Why APCu?  At 2M+ blocks the most-queried values (current block, difficulty,
 * masternode winner) hit MySQL on every forge attempt and every API call.
 * APCu shares memory across PHP-FPM worker processes on the same host, so a
 * value fetched once is reused by all concurrent requests until it expires.
 *
 * Usage:
 *   $val = SCache::get('current_block');
 *   if ($val === false) {
 *       $val = <expensive DB query>;
 *       SCache::set('current_block', $val, 20);  // TTL seconds
 *   }
 *
 * Or with the helper:
 *   $val = SCache::remember('current_block', 20, function() use ($db) {
 *       return $db->row("SELECT * FROM blocks ORDER BY height DESC LIMIT 1");
 *   });
 */
class SCache {

    private static $local = [];
    private static $apcu  = null;

    /**
     * Returns true if APCu is loaded and enabled for the current SAPI.
     */
    public static function available(): bool {
        if (self::$apcu === null) {
            self::$apcu = function_exists('apcu_fetch') && apcu_enabled();
        }
        return self::$apcu;
    }

    /**
     * Fetch a cached value.  Returns false on miss.
     */
    public static function get(string $key) {
        if (self::available()) {
            $success = false;
            $val = apcu_fetch($key, $success);
            return $success ? $val : false;
        }
        // Process-local fallback: honour a simple TTL stored alongside the value
        if (isset(self::$local[$key])) {
            [$val, $expires] = self::$local[$key];
            if ($expires === 0 || $expires > time()) {
                return $val;
            }
            unset(self::$local[$key]);
        }
        return false;
    }

    /**
     * Store a value.  $ttl = 0 means permanent for this request.
     */
    public static function set(string $key, $value, int $ttl = 30): void {
        if (self::available()) {
            apcu_store($key, $value, $ttl);
            return;
        }
        self::$local[$key] = [$value, $ttl > 0 ? time() + $ttl : 0];
    }

    /**
     * Delete a specific cache key (e.g. after a block is added).
     */
    public static function delete(string $key): void {
        if (self::available()) {
            apcu_delete($key);
        }
        unset(self::$local[$key]);
    }

    /**
     * Delete all keys matching a prefix.
     */
    public static function delete_prefix(string $prefix): void {
        if (self::available()) {
            $info = apcu_cache_info(true);
            foreach ($info['cache_list'] ?? [] as $entry) {
                if (strpos($entry['info'], $prefix) === 0) {
                    apcu_delete($entry['info']);
                }
            }
        }
        foreach (self::$local as $key => $_) {
            if (strpos($key, $prefix) === 0) {
                unset(self::$local[$key]);
            }
        }
    }

    /**
     * Fetch-or-compute pattern.
     * $callback is only called on a cache miss; its return value is stored.
     *
     * Example:
     *   $block = SCache::remember('current_block', 20, fn() => $db->row("SELECT ..."));
     */
    public static function remember(string $key, int $ttl, callable $callback) {
        $val = self::get($key);
        if ($val !== false) {
            return $val;
        }
        $val = $callback();
        if ($val !== false && $val !== null) {
            self::set($key, $val, $ttl);
        }
        return $val;
    }

    /**
     * Invalidate all block-related cached values.
     * Must be called whenever a block is added or removed.
     */
    public static function invalidate_block_cache(): void {
        self::delete('current_block');
        self::delete('block_difficulty');
        self::delete('masternode_winner');
        self::delete('masternode_list');
        self::delete('vote_flags');
    }
}
?>
