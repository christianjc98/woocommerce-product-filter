<?php
/**
 * REST API Controller for Woo Fast Filter.
 *
 * Provides custom REST endpoints for product filtering.
 * Custom endpoints are used instead of WooCommerce REST API because:
 * - We can return minimal JSON (smaller payloads).
 * - No authentication overhead for public product data.
 *
 * Caching layer (Pro only):
 *   The controller calls Cache::get() and Cache::set() around queries.
 *   In Free, these are no-ops (get returns false, set does nothing),
 *   so every request hits the database directly. Pro activates
 *   transient-based result caching via is_feature_enabled('caching').
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
 * REST Controller class.
 *
 * Endpoints:
 * - GET /woo-fast-filter/v1/filters  - Get available filter options.
 * - GET /woo-fast-filter/v1/products - Get filtered products.
 */
class REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'woo-fast-filter/v1';

	/**
	 * Cache handler.
	 *
	 * @var Cache
	 */
	private Cache $cache;

	/**
	 * Constructor.
	 *
	 * @param Cache $cache Cache handler instance.
	 */
	public function __construct( Cache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Get available filter options (categories, attributes, price range).
		register_rest_route(
			self::NAMESPACE,
			'/filters',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_filters' ],
				'permission_callback' => '__return_true', // Public data.
			]
		);

		// Get filtered products.
		register_rest_route(
			self::NAMESPACE,
			'/products',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_products' ],
				'permission_callback' => '__return_true', // Public data.
				'args'                => $this->get_product_endpoint_args(),
			]
		);
	}

	/**
	 * Get available filter options.
	 *
	 * Returns categories, attributes, and price range in a single request.
	 * This minimizes HTTP requests on initial page load.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_filters( \WP_REST_Request $request ): \WP_REST_Response {
		// Pro feature — cache lookup. Returns false in Free (always miss).
		$cache_key = 'filter_options';
		$cached    = $this->cache->get( $cache_key );

		if ( false !== $cached ) {
			$response = new \WP_REST_Response( $cached );
			$response->header( 'X-WFF-Cache', 'HIT' );
			return $response;
		}

		$data = [
			'categories'  => get_filter_categories(),
			'attributes'  => get_filter_attributes(),
			'price_range' => get_price_range(),
		];

		// Pro feature — cache store. No-op in Free.
		$this->cache->set( $cache_key, $data );

		$response = new \WP_REST_Response( $data );
		$response->header( 'X-WFF-Cache', 'MISS' );
		return $response;
	}

	/**
	 * Get filtered products.
	 *
	 * Main filtering endpoint. Accepts filter parameters and returns
	 * matching products with pagination info.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_products( \WP_REST_Request $request ): \WP_REST_Response {
		$params    = sanitize_filter_params( $request->get_params() );
		$cache_key = generate_cache_key( $params );

		// Pro feature — cache lookup. Returns false in Free (always miss).
		$cached = $this->cache->get( $cache_key );

		if ( false !== $cached ) {
			$response = new \WP_REST_Response( $cached );
			$response->header( 'X-WFF-Cache', 'HIT' );
			return $response;
		}

		// Build and execute query.
		$query   = new Query_Builder( $params );
		$results = $query->execute();

		// Pro feature — cache store. No-op in Free.
		$this->cache->set( $cache_key, $results );

		$response = new \WP_REST_Response( $results );
		$response->header( 'X-WFF-Cache', 'MISS' );
		$response->header( 'X-WFF-Total', (string) $results['pagination']['total'] );
		$response->header( 'X-WFF-Total-Pages', (string) $results['pagination']['total_pages'] );

		return $response;
	}

	/**
	 * Define product endpoint arguments with validation.
	 *
	 * @return array Endpoint argument definitions.
	 */
	private function get_product_endpoint_args(): array {
		return [
			'categories' => [
				'description' => __( 'Filter by category IDs.', 'woo-fast-filter' ),
				'type'        => 'array',
				'items'       => [
					'type' => 'integer',
				],
				'default'     => [],
			],
			'attributes' => [
				'description' => __( 'Filter by attribute taxonomies and term IDs.', 'woo-fast-filter' ),
				'type'        => 'object',
				'default'     => [],
			],
			'min_price' => [
				'description' => __( 'Minimum price.', 'woo-fast-filter' ),
				'type'        => 'number',
				'minimum'     => 0,
			],
			'max_price' => [
				'description' => __( 'Maximum price.', 'woo-fast-filter' ),
				'type'        => 'number',
				'minimum'     => 0,
			],
			'page' => [
				'description' => __( 'Current page.', 'woo-fast-filter' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			],
			'per_page' => [
				'description' => __( 'Products per page.', 'woo-fast-filter' ),
				'type'        => 'integer',
				'default'     => 12,
				'minimum'     => 1,
				'maximum'     => 100,
			],
			'orderby' => [
				'description' => __( 'Sort by field.', 'woo-fast-filter' ),
				'type'        => 'string',
				'default'     => 'menu_order',
				'enum'        => [ 'date', 'price', 'popularity', 'rating', 'title', 'menu_order' ],
			],
			'order' => [
				'description' => __( 'Sort direction.', 'woo-fast-filter' ),
				'type'        => 'string',
				'default'     => 'ASC',
				'enum'        => [ 'ASC', 'DESC' ],
			],
		];
	}
}
