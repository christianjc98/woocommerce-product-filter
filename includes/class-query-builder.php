<?php
/**
 * Query builder for Woo Fast Filter.
 *
 * Constructs WC_Product_Query instances from sanitized filter parameters.
 * Uses WooCommerce-native query methods for compatibility and performance.
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
 * Query Builder class.
 *
 * Performance Strategy:
 * - Uses WC_Product_Query which is optimized by WooCommerce.
 * - Builds taxonomy queries using AND logic for combined filtering.
 * - Limits returned fields to only what's needed.
 * - Supports pagination to avoid loading entire catalog.
 */
class Query_Builder {

	/**
	 * Sanitized filter parameters.
	 *
	 * @var array
	 */
	private array $params;

	/**
	 * Constructor.
	 *
	 * @param array $params Sanitized filter parameters from sanitize_filter_params().
	 */
	public function __construct( array $params ) {
		$this->params = $params;
	}

	/**
	 * Build and return the product query arguments.
	 *
	 * Constructs a WC_Product_Query-compatible arguments array.
	 *
	 * @return array Query arguments.
	 */
	public function build(): array {
		$args = [
			'status'   => 'publish',
			'limit'    => $this->params['per_page'],
			'page'     => $this->params['page'],
			'return'   => 'objects', // Full objects needed for formatting.
			'paginate' => true,      // Get total count for pagination.
		];

		// Apply sorting.
		$args = $this->apply_sorting( $args );

		// Build taxonomy query.
		$tax_query = $this->build_tax_query();
		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query;
		}

		// Price filtering is handled via posts_clauses hook in execute()
		// rather than meta_query, for compatibility across WC versions.

		return $args;
	}

	/**
	 * Execute the query and return results.
	 *
	 * Price filtering uses a posts_clauses hook rather than meta_query
	 * because wc_get_products() doesn't reliably pass meta_query through
	 * to WP_Query in all WooCommerce versions. The posts_clauses approach
	 * matches what WooCommerce's own price filter widget uses internally.
	 *
	 * @return array Results with products and pagination info.
	 */
	public function execute(): array {
		$args = $this->build();

		// Add price filter via posts_clauses if needed.
		$has_price_filter = isset( $this->params['min_price'] ) || isset( $this->params['max_price'] );

		if ( $has_price_filter ) {
			add_filter( 'posts_clauses', [ $this, 'add_price_clauses' ], 10, 2 );
		}

		$results = wc_get_products( $args );

		if ( $has_price_filter ) {
			remove_filter( 'posts_clauses', [ $this, 'add_price_clauses' ], 10 );
		}

		$products = [];
		foreach ( $results->products as $product ) {
			$products[] = format_product_for_response( $product );
		}

		return [
			'products'   => $products,
			'pagination' => [
				'total'        => $results->total,
				'total_pages'  => $results->max_num_pages,
				'current_page' => $this->params['page'],
				'per_page'     => $this->params['per_page'],
			],
		];
	}

	/**
	 * Add price filtering to SQL clauses.
	 *
	 * This hooks into the WP_Query SQL directly, joining the postmeta table
	 * and adding WHERE conditions for price range. This is the same approach
	 * WooCommerce uses in its own price filter widget, ensuring compatibility
	 * across all WC versions and storage modes.
	 *
	 * @param array     $clauses SQL clauses (fields, join, where, orderby, etc.).
	 * @param \WP_Query $query   The WP_Query instance.
	 * @return array Modified clauses.
	 */
	public function add_price_clauses( array $clauses, \WP_Query $query ): array {
		global $wpdb;

		// Only modify product queries.
		$post_types = (array) $query->get( 'post_type' );
		if ( ! in_array( 'product', $post_types, true ) ) {
			return $clauses;
		}

		// Join price meta.
		$clauses['join'] .= " INNER JOIN {$wpdb->postmeta} AS wff_price_meta ON ( {$wpdb->posts}.ID = wff_price_meta.post_id AND wff_price_meta.meta_key = '_price' ) ";

		// Add price conditions.
		if ( isset( $this->params['min_price'] ) ) {
			$clauses['where'] .= $wpdb->prepare(
				' AND CAST( wff_price_meta.meta_value AS DECIMAL(10,2) ) >= %f',
				$this->params['min_price']
			);
		}

		if ( isset( $this->params['max_price'] ) ) {
			$clauses['where'] .= $wpdb->prepare(
				' AND CAST( wff_price_meta.meta_value AS DECIMAL(10,2) ) <= %f',
				$this->params['max_price']
			);
		}

		// Avoid duplicate results from products with multiple _price entries (variations).
		$clauses['groupby'] = "{$wpdb->posts}.ID";

		return $clauses;
	}

	/**
	 * Apply sorting parameters.
	 *
	 * Maps user-facing sort options to WC_Product_Query arguments.
	 *
	 * @param array $args Current query arguments.
	 * @return array Modified query arguments.
	 */
	private function apply_sorting( array $args ): array {
		$orderby = $this->params['orderby'] ?? 'menu_order';
		$order   = $this->params['order'] ?? 'ASC';

		switch ( $orderby ) {
			case 'price':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['order']    = $order;
				break;

			case 'popularity':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['order']    = 'DESC';
				break;

			case 'rating':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_wc_average_rating'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['order']    = 'DESC';
				break;

			case 'date':
				$args['orderby'] = 'date';
				$args['order']   = $order;
				break;

			case 'title':
				$args['orderby'] = 'title';
				$args['order']   = $order;
				break;

			case 'menu_order':
			default:
				$args['orderby'] = 'menu_order title';
				$args['order']   = 'ASC';
				break;
		}

		return $args;
	}

	/**
	 * Build taxonomy query for categories and attributes.
	 *
	 * Uses AND logic between different filter types:
	 * - Product must match at least one selected category.
	 * - Product must match at least one term per selected attribute.
	 *
	 * Within the same attribute, OR logic is used (e.g., Color: Red OR Blue).
	 * Between different filters, AND logic is used (e.g., Color: Red AND Size: Large).
	 *
	 * @return array WP_Tax_Query compatible array.
	 */
	private function build_tax_query(): array {
		$tax_query = [];

		// Category filter.
		if ( ! empty( $this->params['categories'] ) ) {
			$tax_query[] = [
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $this->params['categories'],
				'operator' => 'IN', // OR within categories.
			];
		}

		// Attribute filters.
		if ( ! empty( $this->params['attributes'] ) ) {
			foreach ( $this->params['attributes'] as $taxonomy => $terms ) {
				if ( ! empty( $terms ) ) {
					$tax_query[] = [
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $terms,
						'operator' => 'IN', // OR within same attribute.
					];
				}
			}
		}

		// Use AND relation between different filter groups.
		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}

		return $tax_query;
	}

	/**
	 * Get count of products matching current filters.
	 *
	 * Pro feature — used for live term counts (disabled in Free).
	 * Lightweight query that only returns count, not full product objects.
	 *
	 * @return int Number of matching products.
	 */
	public function get_count(): int {
		$args           = $this->build();
		$args['return'] = 'ids'; // Only get IDs for counting - much faster.
		$args['limit']  = -1;   // Get all matching.
		unset( $args['paginate'] );

		$has_price_filter = isset( $this->params['min_price'] ) || isset( $this->params['max_price'] );

		if ( $has_price_filter ) {
			add_filter( 'posts_clauses', [ $this, 'add_price_clauses' ], 10, 2 );
		}

		$products = wc_get_products( $args );

		if ( $has_price_filter ) {
			remove_filter( 'posts_clauses', [ $this, 'add_price_clauses' ], 10 );
		}

		return count( $products );
	}
}
