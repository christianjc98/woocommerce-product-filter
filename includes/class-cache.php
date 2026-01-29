<?php
/**
 * Cache handler for Woo Fast Filter.
 *
 * Pro feature — disabled in Free.
 *
 * Implements a versioned caching strategy using WordPress transients.
 * Cache invalidation is handled through version increments rather than
 * deleting individual cache entries, which is more efficient for large catalogs.
 *
 * In Free, all cache methods are no-ops:
 *   - get() always returns false (cache miss).
 *   - set() is a no-op (never stores).
 *   - delete() is a no-op.
 *   - flush() is a no-op.
 *   - warm() is a no-op.
 *
 * Pro activates caching via is_feature_enabled('caching').
 *
 * @package WooFastFilter
 */

declare(strict_types=1);

namespace WooFastFilter;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache class for managing filter result caching.
 *
 * FREE FEATURE FREEZE — v1.0
 * Pro feature — all transient operations are gated behind is_feature_enabled('caching').
 * Free version runs without any caching layer. Every REST request queries the database
 * directly, which is acceptable for stores under 500 products.
 * Do not enable any caching in Free.
 */
class Cache {

	/**
	 * Transient key for cache version.
	 *
	 * @var string
	 */
	private const VERSION_KEY = 'wff_cache_version';

	/**
	 * Current cache version.
	 *
	 * @var int|null
	 */
	private ?int $version = null;

	/**
	 * Get the current cache version.
	 *
	 * @return int Cache version number.
	 */
	public function get_version(): int {
		if ( null === $this->version ) {
			$this->version = (int) get_option( self::VERSION_KEY, 1 );
		}
		return $this->version;
	}

	/**
	 * Get a cached value.
	 *
	 * Pro feature — always returns false in Free (cache miss).
	 *
	 * @param string $key Cache key (without version prefix).
	 * @return mixed|false Cached value or false if not found / caching disabled.
	 */
	public function get( string $key ): mixed {
		// Pro feature — caching disabled in Free.
		if ( ! is_feature_enabled( 'caching' ) ) {
			return false;
		}

		$versioned_key = $this->get_versioned_key( $key );
		return get_transient( $versioned_key );
	}

	/**
	 * Set a cached value.
	 *
	 * Pro feature — no-op in Free.
	 *
	 * @param string $key        Cache key (without version prefix).
	 * @param mixed  $value      Value to cache.
	 * @param int    $expiration Optional. Time until expiration in seconds. Default is WFF_CACHE_TTL.
	 * @return bool True if value was set, false otherwise.
	 */
	public function set( string $key, mixed $value, int $expiration = 0 ): bool {
		// Pro feature — caching disabled in Free.
		if ( ! is_feature_enabled( 'caching' ) ) {
			return false;
		}

		if ( 0 === $expiration ) {
			$expiration = WFF_CACHE_TTL;
		}

		$versioned_key = $this->get_versioned_key( $key );
		return set_transient( $versioned_key, $value, $expiration );
	}

	/**
	 * Delete a specific cache entry.
	 *
	 * Pro feature — no-op in Free.
	 *
	 * @param string $key Cache key (without version prefix).
	 * @return bool True if deleted, false otherwise.
	 */
	public function delete( string $key ): bool {
		// Pro feature — caching disabled in Free.
		if ( ! is_feature_enabled( 'caching' ) ) {
			return false;
		}

		$versioned_key = $this->get_versioned_key( $key );
		return delete_transient( $versioned_key );
	}

	/**
	 * Flush all filter caches by incrementing version.
	 *
	 * Pro feature — no-op in Free (nothing to flush).
	 *
	 * Instead of deleting individual transients (which could be hundreds),
	 * we simply increment the version number. This makes all existing cache
	 * entries stale, and they'll be regenerated on next request.
	 *
	 * @return void
	 */
	public function flush(): void {
		// Pro feature — caching disabled in Free.
		if ( ! is_feature_enabled( 'caching' ) ) {
			return;
		}

		$new_version   = $this->get_version() + 1;
		$this->version = $new_version;
		update_option( self::VERSION_KEY, $new_version, false );

		// Also clear the helper function caches.
		delete_transient( 'wff_categories_hier' );
		delete_transient( 'wff_categories_flat' );
		delete_transient( 'wff_attributes' );
		delete_transient( 'wff_price_range' );
	}

	/**
	 * Get a versioned cache key.
	 *
	 * @param string $key Original cache key.
	 * @return string Versioned cache key.
	 */
	private function get_versioned_key( string $key ): string {
		return 'wff_v' . $this->get_version() . '_' . $key;
	}

	/**
	 * Get cache statistics.
	 *
	 * Pro feature — returns empty stats in Free.
	 *
	 * @return array Cache statistics.
	 */
	public function get_stats(): array {
		// Pro feature — no stats in Free.
		if ( ! is_feature_enabled( 'caching' ) ) {
			return [
				'version'     => 0,
				'entry_count' => 0,
				'ttl_seconds' => 0,
				'storage'     => 'disabled',
			];
		}

		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_wff_v' . $this->get_version() . '_%'
			)
		);

		return [
			'version'       => $this->get_version(),
			'entry_count'   => (int) $count,
			'ttl_seconds'   => WFF_CACHE_TTL,
			'storage'       => wp_using_ext_object_cache() ? 'object_cache' : 'database',
		];
	}

	/**
	 * Warm the cache with common queries.
	 *
	 * Pro feature — no-op in Free (caching is disabled).
	 *
	 * @return void
	 */
	public function warm(): void {
		// Pro feature — cache warming disabled in Free.
		if ( ! is_feature_enabled( 'cache_warming' ) ) {
			return;
		}

		get_filter_categories( true );
		get_filter_categories( false );
		get_filter_attributes();
		get_price_range();
	}
}
