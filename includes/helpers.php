<?php
/**
 * Helper functions for Woo Fast Filter.
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
 * Check if the Pro version is active.
 *
 * Central feature flag for gating Pro-only functionality.
 * In Free, this always returns false. The Pro plugin will
 * override this by defining WFF_PRO_ACTIVE as true, or by
 * hooking into the 'wff_is_pro_active' filter.
 *
 * Usage:
 *   if ( is_pro_active() ) { // Pro-only logic }
 *
 * @return bool True if Pro is active.
 */
function is_pro_active(): bool {
	// Constant check: Pro plugin defines this on load.
	if ( defined( 'WFF_PRO_ACTIVE' ) && WFF_PRO_ACTIVE ) {
		return true;
	}

	// Filter check: allows Pro to activate via hook.
	return (bool) apply_filters( 'wff_is_pro_active', false );
}

/**
 * Get Free-version defaults for block attributes.
 *
 * These are the hard-coded values enforced in Free.
 * Pro can override them via is_pro_active() checks.
 *
 * @return array Default attribute values.
 */
function get_free_defaults(): array {
	return [
		'layout'            => 'sidebar',  // Pro: top, modal.
		'style'             => 'clean',    // Pro: soft, editorial.
		'autoApply'         => false,      // Pro: user-configurable.
		'showActiveFilters' => true,       // Free: user-configurable.
	];
}

/**
 * Check if a specific Pro feature is enabled.
 *
 * Granular feature flag for gating individual Pro capabilities.
 * In Free, all Pro features return false.
 * Pro can enable features selectively via the 'wff_feature_enabled' filter.
 *
 * Pro features:
 *   - 'caching'            Smart transient-based query caching.
 *   - 'cache_invalidation' Auto-invalidate cache on product changes.
 *   - 'debounce'           Debounced AJAX filter requests.
 *   - 'auto_apply'         Apply filters without button click.
 *   - 'layout_options'     Top bar, modal layouts.
 *   - 'style_options'      Soft, editorial visual styles.
 *   - 'cache_warming'      Pre-populate cache after flush.
 *
 * @param string $feature Feature identifier.
 * @return bool True if the feature is enabled.
 */
function is_feature_enabled( string $feature ): bool {
	if ( ! is_pro_active() ) {
		return false;
	}

	return (bool) apply_filters( 'wff_feature_enabled', true, $feature );
}

/**
 * Get available product categories for filtering.
 *
 * Returns only categories that have products assigned.
 * Results are cached to avoid repeated database queries.
 *
 * @param bool $hierarchical Whether to return hierarchical structure.
 * @return array Array of category data.
 */
function get_filter_categories( bool $hierarchical = true ): array {
	// Pro feature — transient caching disabled in Free.
	// In Free, categories are queried fresh on every request.
	if ( is_feature_enabled( 'caching' ) ) {
		$cache_key = 'wff_categories_' . ( $hierarchical ? 'hier' : 'flat' );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}
	}

	$args = [
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'orderby'    => 'name',
		'order'      => 'ASC',
	];

	if ( $hierarchical ) {
		$args['parent'] = 0;
	}

	$terms = get_terms( $args );

	if ( is_wp_error( $terms ) ) {
		return [];
	}

	$categories = [];

	foreach ( $terms as $term ) {
		$category = [
			'id'    => $term->term_id,
			'name'  => $term->name,
			'slug'  => $term->slug,
			'count' => $term->count,
		];

		// Get children if hierarchical.
		if ( $hierarchical ) {
			$children = get_terms(
				[
					'taxonomy'   => 'product_cat',
					'hide_empty' => true,
					'parent'     => $term->term_id,
					'orderby'    => 'name',
					'order'      => 'ASC',
				]
			);

			if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
				$category['children'] = array_map(
					function ( $child ) {
						return [
							'id'    => $child->term_id,
							'name'  => $child->name,
							'slug'  => $child->slug,
							'count' => $child->count,
						];
					},
					$children
				);
			}
		}

		$categories[] = $category;
	}

	// Pro feature — cache result for 1 hour.
	if ( is_feature_enabled( 'caching' ) ) {
		set_transient( $cache_key, $categories, WFF_CACHE_TTL );
	}

	return $categories;
}

/**
 * Get available product attributes for filtering.
 *
 * Only returns attributes marked as "Used for filter" in WooCommerce.
 *
 * @return array Array of attribute data with terms.
 */
function get_filter_attributes(): array {
	// Pro feature — transient caching disabled in Free.
	if ( is_feature_enabled( 'caching' ) ) {
		$cache_key = 'wff_attributes';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}
	}

	$attributes = [];

	// Get all product attributes.
	$attribute_taxonomies = wc_get_attribute_taxonomies();

	foreach ( $attribute_taxonomies as $attribute ) {
		$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );

		// Get terms for this attribute.
		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			continue;
		}

		$attributes[] = [
			'id'       => $attribute->attribute_id,
			'name'     => $attribute->attribute_label,
			'slug'     => $attribute->attribute_name,
			'taxonomy' => $taxonomy,
			'type'     => $attribute->attribute_type,
			'terms'    => array_map(
				function ( $term ) {
					return [
						'id'    => $term->term_id,
						'name'  => $term->name,
						'slug'  => $term->slug,
						'count' => $term->count,
					];
				},
				$terms
			),
		];
	}

	// Pro feature — cache result for 1 hour.
	if ( is_feature_enabled( 'caching' ) ) {
		set_transient( $cache_key, $attributes, WFF_CACHE_TTL );
	}

	return $attributes;
}

/**
 * Get price range for products.
 *
 * Returns min and max prices across all visible products.
 * Cached to avoid expensive queries.
 *
 * @return array Array with 'min' and 'max' keys.
 */
function get_price_range(): array {
	// Pro feature — transient caching disabled in Free.
	if ( is_feature_enabled( 'caching' ) ) {
		$cache_key = 'wff_price_range';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}
	}

	global $wpdb;

	// Direct query is faster than WC_Product_Query for aggregates.
	// Uses postmeta for compatibility with both HPOS and legacy storage.
	$result = $wpdb->get_row(
		"
		SELECT
			MIN( CAST( pm.meta_value AS DECIMAL(10,2) ) ) as min_price,
			MAX( CAST( pm.meta_value AS DECIMAL(10,2) ) ) as max_price
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
		WHERE p.post_type IN ( 'product', 'product_variation' )
		AND p.post_status = 'publish'
		AND pm.meta_key = '_price'
		AND pm.meta_value != ''
		AND pm.meta_value IS NOT NULL
		"
	);

	$range = [
		'min' => $result ? (float) $result->min_price : 0,
		'max' => $result ? (float) $result->max_price : 0,
	];

	// Pro feature — cache result for 1 hour.
	if ( is_feature_enabled( 'caching' ) ) {
		set_transient( $cache_key, $range, WFF_CACHE_TTL );
	}

	return $range;
}

/**
 * Sanitize filter parameters from request.
 *
 * Ensures all filter values are properly sanitized before use.
 *
 * @param array $params Raw parameters from request.
 * @return array Sanitized parameters.
 */
function sanitize_filter_params( array $params ): array {
	$sanitized = [];

	// Categories - array of integers.
	if ( ! empty( $params['categories'] ) ) {
		$sanitized['categories'] = array_filter(
			array_map( 'absint', (array) $params['categories'] )
		);
	}

	// Attributes - array of taxonomy => terms.
	if ( ! empty( $params['attributes'] ) && is_array( $params['attributes'] ) ) {
		$sanitized['attributes'] = [];
		foreach ( $params['attributes'] as $taxonomy => $terms ) {
			$taxonomy = sanitize_key( $taxonomy );
			if ( taxonomy_exists( $taxonomy ) ) {
				$sanitized['attributes'][ $taxonomy ] = array_filter(
					array_map( 'absint', (array) $terms )
				);
			}
		}
	}

	// Price range.
	if ( isset( $params['min_price'] ) ) {
		$sanitized['min_price'] = (float) $params['min_price'];
	}
	if ( isset( $params['max_price'] ) ) {
		$sanitized['max_price'] = (float) $params['max_price'];
	}

	// Pagination.
	$sanitized['page']     = isset( $params['page'] ) ? max( 1, absint( $params['page'] ) ) : 1;
	$sanitized['per_page'] = isset( $params['per_page'] ) ? min( 100, max( 1, absint( $params['per_page'] ) ) ) : 12;

	// Sorting.
	$allowed_orderby = [ 'date', 'price', 'popularity', 'rating', 'title', 'menu_order' ];
	$sanitized['orderby'] = isset( $params['orderby'] ) && in_array( $params['orderby'], $allowed_orderby, true )
		? $params['orderby']
		: 'menu_order';

	$sanitized['order'] = isset( $params['order'] ) && strtoupper( $params['order'] ) === 'DESC'
		? 'DESC'
		: 'ASC';

	return $sanitized;
}

/**
 * Format product data for JSON response.
 *
 * Returns minimal data needed for frontend rendering.
 * Keeping response small improves transfer speed.
 *
 * @param \WC_Product $product Product object.
 * @return array Formatted product data.
 */
function format_product_for_response( \WC_Product $product ): array {
	$image_id = $product->get_image_id();

	return [
		'id'        => $product->get_id(),
		'name'      => $product->get_name(),
		'slug'      => $product->get_slug(),
		'permalink' => $product->get_permalink(),
		'price'     => [
			'regular' => $product->get_regular_price(),
			'sale'    => $product->get_sale_price(),
			'html'    => $product->get_price_html(),
		],
		'image'     => $image_id ? [
			'src'    => wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ),
			'srcset' => wp_get_attachment_image_srcset( $image_id, 'woocommerce_thumbnail' ),
			'alt'    => get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
		] : null,
		'rating'    => [
			'average' => (float) $product->get_average_rating(),
			'count'   => (int) $product->get_rating_count(),
		],
		'on_sale'   => $product->is_on_sale(),
		'in_stock'  => $product->is_in_stock(),
	];
}

/**
 * Generate cache key from filter parameters.
 *
 * Creates a unique, consistent key for caching query results.
 *
 * @param array $params Sanitized filter parameters.
 * @return string Cache key.
 */
function generate_cache_key( array $params ): string {
	// Sort arrays for consistent key generation.
	if ( isset( $params['categories'] ) ) {
		sort( $params['categories'] );
	}
	if ( isset( $params['attributes'] ) ) {
		ksort( $params['attributes'] );
		foreach ( $params['attributes'] as &$terms ) {
			sort( $terms );
		}
	}

	return 'wff_products_' . md5( wp_json_encode( $params ) );
}
