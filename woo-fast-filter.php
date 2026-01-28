<?php
/**
 * Plugin Name: Woo Fast Filter
 * Plugin URI: https://example.com/woo-fast-filter
 * Description: High-performance WooCommerce product filtering with AJAX, caching, and Gutenberg block support.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-fast-filter
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 * WC tested up to: 8.0
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
 * Plugin constants.
 *
 * Using constants instead of global variables for better performance
 * and to avoid polluting the global namespace.
 */
define( 'WFF_VERSION', '1.0.0' );
define( 'WFF_PLUGIN_FILE', __FILE__ );
define( 'WFF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WFF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WFF_CACHE_GROUP', 'woo_fast_filter' );
define( 'WFF_CACHE_TTL', HOUR_IN_SECONDS ); // 1 hour default cache TTL.

/**
 * Main plugin class.
 *
 * Uses singleton pattern to ensure only one instance runs.
 * This prevents duplicate hooks and memory waste.
 */
final class Plugin {

	/**
	 * Single instance of the plugin.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * REST Controller instance.
	 *
	 * @var REST_Controller|null
	 */
	private ?REST_Controller $rest_controller = null;

	/**
	 * Cache handler instance.
	 *
	 * @var Cache|null
	 */
	private ?Cache $cache = null;

	/**
	 * Get plugin instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load required files.
	 *
	 * Files are loaded in dependency order.
	 * Helpers first, then core classes.
	 */
	private function load_dependencies(): void {
		require_once WFF_PLUGIN_DIR . 'includes/helpers.php';
		require_once WFF_PLUGIN_DIR . 'includes/class-cache.php';
		require_once WFF_PLUGIN_DIR . 'includes/class-query-builder.php';
		require_once WFF_PLUGIN_DIR . 'includes/class-rest-controller.php';
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * Hooks are organized by type and timing for clarity.
	 */
	private function init_hooks(): void {
		// Check WooCommerce dependency.
		add_action( 'plugins_loaded', [ $this, 'check_dependencies' ] );

		// Initialize components after WooCommerce is ready.
		add_action( 'woocommerce_init', [ $this, 'init_components' ] );

		// Register REST API routes.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Register Gutenberg block.
		add_action( 'init', [ $this, 'register_block' ] );

		// Enqueue frontend assets.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

		// Pro feature — cache invalidation hooks.
		// Only registered when caching is active (Pro).
		// In Free, caching is disabled so there's nothing to invalidate.
		// These hooks are registered late via woocommerce_init callback
		// to ensure is_feature_enabled() can resolve correctly.
		add_action( 'woocommerce_init', [ $this, 'register_cache_hooks' ] );

		// HPOS compatibility declaration.
		add_action( 'before_woocommerce_init', [ $this, 'declare_hpos_compatibility' ] );
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return void
	 */
	public function check_dependencies(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', [ $this, 'woocommerce_missing_notice' ] );
		}
	}

	/**
	 * Display admin notice when WooCommerce is not active.
	 *
	 * @return void
	 */
	public function woocommerce_missing_notice(): void {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				esc_html_e(
					'Woo Fast Filter requires WooCommerce to be installed and active.',
					'woo-fast-filter'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Initialize plugin components.
	 *
	 * @return void
	 */
	public function init_components(): void {
		$this->cache           = new Cache();
		$this->rest_controller = new REST_Controller( $this->cache );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		if ( null !== $this->rest_controller ) {
			$this->rest_controller->register_routes();
		}
	}

	/**
	 * Register Gutenberg block.
	 *
	 * Block is registered from block.json for better performance.
	 * WordPress caches block metadata when loaded from JSON.
	 *
	 * The editor code is split across three files:
	 *   - save.js  → window.wffBlock.Save  (null output)
	 *   - edit.js  → window.wffBlock.Edit   (editor UI + Pro gating)
	 *   - index.js → registerBlockType call (entry point, loaded last)
	 *
	 * save.js and edit.js are registered as separate scripts and listed
	 * as dependencies in index.asset.php so they load before index.js.
	 *
	 * @return void
	 */
	public function register_block(): void {
		$block_path = WFF_PLUGIN_DIR . 'blocks/product-filter';
		$block_url  = WFF_PLUGIN_URL . 'blocks/product-filter';

		if ( ! file_exists( $block_path . '/block.json' ) ) {
			return;
		}

		// Register save.js — no UI dependencies, just wp-element.
		wp_register_script(
			'wff-block-save',
			$block_url . '/save.js',
			[],
			WFF_VERSION,
			true
		);

		// Register edit.js — needs the full Gutenberg component stack.
		wp_register_script(
			'wff-block-edit',
			$block_url . '/edit.js',
			[ 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ],
			WFF_VERSION,
			true
		);

		// Inject the Free/Pro flag before edit.js executes.
		// edit.js reads window.wffEditorConfig.isPro to gate controls.
		wp_add_inline_script(
			'wff-block-edit',
			'var wffEditorConfig = ' . wp_json_encode( [
				'isPro' => is_pro_active(),
			] ) . ';',
			'before'
		);

		// Register the block from block.json.
		// index.js is loaded via editorScript in block.json.
		// index.asset.php lists wff-block-save and wff-block-edit as
		// dependencies so they execute before index.js.
		register_block_type(
			$block_path,
			[
				'render_callback' => [ $this, 'render_filter_block' ],
			]
		);
	}

	/**
	 * Render the filter block on the frontend.
	 *
	 * Server-side rendering for better SEO and initial load performance.
	 * The block HTML is generated once and cached by the browser.
	 *
	 * Free version enforces fixed values for layout, style, and autoApply.
	 * Only showActiveFilters is user-configurable in Free.
	 * Pro unlocks all attributes via is_pro_active().
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered HTML.
	 */
	public function render_filter_block( array $attributes ): string {
		// Don't render if WooCommerce is not active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '';
		}

		$defaults = get_free_defaults();

		if ( is_pro_active() ) {
			// Pro: respect all saved block attributes.
			$attributes = wp_parse_args( $attributes, $defaults );
		} else {
			// Free: enforce fixed defaults for Pro-locked attributes.
			// Only showActiveFilters can be changed by the user.
			$attributes = array_merge(
				$defaults,
				[
					'showActiveFilters' => $attributes['showActiveFilters'] ?? $defaults['showActiveFilters'],
				]
			);
		}

		// Load the template.
		ob_start();
		include WFF_PLUGIN_DIR . 'templates/filter-block.php';
		return ob_get_clean();
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * Assets are only loaded on shop pages for performance.
	 * CSS is minimal and inlined critical styles could be added.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		// Only load on WooCommerce pages.
		if ( ! is_shop() && ! is_product_category() && ! is_product_tag() && ! has_block( 'woo-fast-filter/product-filter' ) ) {
			return;
		}

		// Enqueue CSS.
		wp_enqueue_style(
			'wff-frontend',
			WFF_PLUGIN_URL . 'assets/css/frontend.css',
			[],
			WFF_VERSION
		);

		// Enqueue JS.
		// Using vanilla JS with no dependencies for minimal footprint.
		wp_enqueue_script(
			'wff-frontend',
			WFF_PLUGIN_URL . 'assets/js/frontend.js',
			[], // No dependencies - vanilla JS.
			WFF_VERSION,
			true // Load in footer for better performance.
		);

		// Pass configuration to JS.
		wp_localize_script(
			'wff-frontend',
			'wffConfig',
			[
				'restUrl'   => esc_url_raw( rest_url( 'woo-fast-filter/v1' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'currency'  => [
					// Decode HTML entities so JS can use the raw symbol.
					// WooCommerce returns entities like &#36; for $, which
					// display as literal text when set via textContent in JS.
					'symbol'   => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
					'position' => get_option( 'woocommerce_currency_pos' ),
				],
				'i18n'      => [
					'loading'      => __( 'Loading...', 'woo-fast-filter' ),
					'noProducts'   => __( 'No products found', 'woo-fast-filter' ),
					'filterButton' => __( 'Filter', 'woo-fast-filter' ),
					'clearAll'     => __( 'Clear all', 'woo-fast-filter' ),
					'apply'        => __( 'Apply filters', 'woo-fast-filter' ),
				],
			]
		);
	}

	/**
	 * Register cache invalidation hooks.
	 *
	 * Pro feature — only registers hooks when caching is active.
	 * In Free, caching is disabled so these hooks are never attached,
	 * saving a small amount of overhead on every product save.
	 *
	 * @return void
	 */
	public function register_cache_hooks(): void {
		// Pro feature — cache invalidation disabled in Free.
		if ( ! is_feature_enabled( 'cache_invalidation' ) ) {
			return;
		}

		add_action( 'woocommerce_update_product', [ $this, 'invalidate_cache' ] );
		add_action( 'woocommerce_new_product', [ $this, 'invalidate_cache' ] );
		add_action( 'woocommerce_delete_product', [ $this, 'invalidate_cache' ] );
		add_action( 'created_product_cat', [ $this, 'invalidate_cache' ] );
		add_action( 'edited_product_cat', [ $this, 'invalidate_cache' ] );
		add_action( 'delete_product_cat', [ $this, 'invalidate_cache' ] );
		add_action( 'woocommerce_attribute_added', [ $this, 'invalidate_cache' ] );
		add_action( 'woocommerce_attribute_updated', [ $this, 'invalidate_cache' ] );
		add_action( 'woocommerce_attribute_deleted', [ $this, 'invalidate_cache' ] );
	}

	/**
	 * Invalidate all filter caches.
	 *
	 * Pro feature — called by cache invalidation hooks.
	 * No-op in Free since Cache::flush() is gated internally.
	 *
	 * @return void
	 */
	public function invalidate_cache(): void {
		if ( null !== $this->cache ) {
			$this->cache->flush();
		}
	}

	/**
	 * Declare HPOS compatibility.
	 *
	 * Required for WooCommerce 8.0+ High-Performance Order Storage.
	 *
	 * @return void
	 */
	public function declare_hpos_compatibility(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				WFF_PLUGIN_FILE,
				true
			);
		}
	}

	/**
	 * Get cache instance.
	 *
	 * @return Cache|null
	 */
	public function get_cache(): ?Cache {
		return $this->cache;
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 *
	 * @throws \Exception When attempting to unserialize.
	 */
	public function __wakeup(): void {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}

/**
 * Initialize the plugin.
 *
 * @return Plugin
 */
function wff_init(): Plugin {
	return Plugin::get_instance();
}

// Bootstrap the plugin.
wff_init();
