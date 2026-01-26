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
 * Get available product categories for filtering.
 *
 * Returns only categories that have products assigned.
 * Results are cached to avoid repeated database queries.
 *
 * @param bool $hierarchical Whether to return hierarchical structure.
 * @return array Array of category data.
 */
function get_filter_categories( bool $hierarchical = true ): array {
	$cache_key = 'wff_categories_' . ( $hierarchical ? 'hier' : 'flat' );
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached;
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

	// Cache for 1 hour.
	set_transient( $cache_key, $categories, WFF_CACHE_TTL );

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
	$cache_key = 'wff_attributes';
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached;
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

	// Cache for 1 hour.
	set_transient( $cache_key, $attributes, WFF_CACHE_TTL );

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
	$cache_key = 'wff_price_range';
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached;
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

	// Cache for 1 hour.
	set_transient( $cache_key, $range, WFF_CACHE_TTL );

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
