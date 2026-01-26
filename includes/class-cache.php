<?php
/**
 * Cache handler for Woo Fast Filter.
 *
 * Implements a versioned caching strategy using WordPress transients.
 * Cache invalidation is handled through version increments rather than
 * deleting individual cache entries, which is more efficient for large catalogs.
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
 * Performance Strategy:
 * - Uses WordPress transients (object cache if available, otherwise DB).
 * - Version-based invalidation: When products change, we increment a version
 *   number rather than deleting all cache entries. Old entries naturally expire.
 * - This approach is faster than deleting potentially hundreds of cache keys.
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
	 * @param string $key Cache key (without version prefix).
	 * @return mixed|false Cached value or false if not found.
	 */
	public function get( string $key ): mixed {
		$versioned_key = $this->get_versioned_key( $key );
		return get_transient( $versioned_key );
	}

	/**
	 * Set a cached value.
	 *
	 * @param string $key        Cache key (without version prefix).
	 * @param mixed  $value      Value to cache.
	 * @param int    $expiration Optional. Time until expiration in seconds. Default is WFF_CACHE_TTL.
	 * @return bool True if value was set, false otherwise.
	 */
	public function set( string $key, mixed $value, int $expiration = 0 ): bool {
		if ( 0 === $expiration ) {
			$expiration = WFF_CACHE_TTL;
		}

		$versioned_key = $this->get_versioned_key( $key );
		return set_transient( $versioned_key, $value, $expiration );
	}

	/**
	 * Delete a specific cache entry.
	 *
	 * @param string $key Cache key (without version prefix).
	 * @return bool True if deleted, false otherwise.
	 */
	public function delete( string $key ): bool {
		$versioned_key = $this->get_versioned_key( $key );
		return delete_transient( $versioned_key );
	}

	/**
	 * Flush all filter caches by incrementing version.
	 *
	 * Instead of deleting individual transients (which could be hundreds),
	 * we simply increment the version number. This makes all existing cache
	 * entries stale, and they'll be regenerated on next request.
	 *
	 * Old transients will be cleaned up by WordPress's transient garbage
	 * collection when they expire.
	 *
	 * @return void
	 */
	public function flush(): void {
		$new_version   = $this->get_version() + 1;
		$this->version = $new_version;
		update_option( self::VERSION_KEY, $new_version, false ); // false = don't autoload.

		// Also clear the helper function caches.
		delete_transient( 'wff_categories_hier' );
		delete_transient( 'wff_categories_flat' );
		delete_transient( 'wff_attributes' );
		delete_transient( 'wff_price_range' );
	}

	/**
	 * Get a versioned cache key.
	 *
	 * Prepends the current version to the key, ensuring cache invalidation
	 * happens automatically when the version changes.
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
	 * Useful for debugging and monitoring cache effectiveness.
	 *
	 * @return array Cache statistics.
	 */
	public function get_stats(): array {
		global $wpdb;

		// Count current transients (only works with DB storage).
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
	 * Can be called after cache flush to pre-populate common queries.
	 * This reduces latency for the first users after a cache clear.
	 *
	 * @return void
	 */
	public function warm(): void {
		// Pre-fetch categories.
		get_filter_categories( true );
		get_filter_categories( false );

		// Pre-fetch attributes.
		get_filter_attributes();

		// Pre-fetch price range.
		get_price_range();
	}
}
