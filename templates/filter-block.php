<?php
/**
 * Template for the Product Filter block frontend output.
 *
 * This file is rendered server-side. It outputs the filter form HTML
 * which is then enhanced with JavaScript for AJAX behavior.
 *
 * Available variables:
 *  - $attributes (array) Block attributes from Gutenberg.
 *
 * @package WooFastFilter
 */

declare(strict_types=1);

namespace WooFastFilter;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Block attributes.
// FREE FEATURE FREEZE — v1.0
// Free version: layout, style, and autoApply are locked to defaults.
// These are already enforced in render_filter_block(), but we guard
// here too in case the template is loaded directly.
if ( is_pro_active() ) {
	$layout     = esc_attr( $attributes['layout'] ?? 'sidebar' );
	$style      = esc_attr( $attributes['style'] ?? 'clean' );
	$auto_apply = ! empty( $attributes['autoApply'] );
} else {
	// Free: hard-coded. Pro unlocks layout, style, autoApply.
	$layout     = 'sidebar';
	$style      = 'clean';
	$auto_apply = false;
}

// showActiveFilters is available in Free.
$show_active = ! empty( $attributes['showActiveFilters'] );

// Get filter data.
$categories  = get_filter_categories();
$filter_attributes = get_filter_attributes();
$price_range = get_price_range();

$wrapper_classes = sprintf(
	'wff-wrapper wff-layout-%s wff-style-%s',
	$layout,
	$style
);
?>
<div class="<?php echo esc_attr( $wrapper_classes ); ?>"
	data-auto-apply="<?php echo $auto_apply ? 'true' : 'false'; ?>"
	data-show-active="<?php echo $show_active ? 'true' : 'false'; ?>"
	data-layout="<?php echo esc_attr( $layout ); ?>">

	<?php // Mobile filter toggle button. ?>
	<button class="wff-mobile-toggle" aria-label="<?php esc_attr_e( 'Toggle filters', 'woo-fast-filter' ); ?>" aria-expanded="false">
		<svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
			<path d="M1 3h16M4 9h10M7 15h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
		</svg>
		<span><?php esc_html_e( 'Filter', 'woo-fast-filter' ); ?></span>
	</button>

	<?php // Filter panel. ?>
	<div class="wff-panel" role="search" aria-label="<?php esc_attr_e( 'Product filters', 'woo-fast-filter' ); ?>">
		<div class="wff-panel-header">
			<h3 class="wff-panel-title"><?php esc_html_e( 'Filters', 'woo-fast-filter' ); ?></h3>
			<button class="wff-panel-close" aria-label="<?php esc_attr_e( 'Close filters', 'woo-fast-filter' ); ?>">&times;</button>
		</div>

		<div class="wff-panel-body">
			<?php // Active filters. ?>
			<?php if ( $show_active ) : ?>
				<div class="wff-active-filters" hidden>
					<div class="wff-active-filters-header">
						<span class="wff-active-label"><?php esc_html_e( 'Active filters', 'woo-fast-filter' ); ?></span>
						<button class="wff-clear-all" type="button">
							<?php esc_html_e( 'Clear all', 'woo-fast-filter' ); ?>
						</button>
					</div>
					<div class="wff-active-tags"></div>
				</div>
			<?php endif; ?>

			<form class="wff-form" aria-label="<?php esc_attr_e( 'Filter products', 'woo-fast-filter' ); ?>">
				<?php // Category filter. ?>
				<?php if ( ! empty( $categories ) ) : ?>
					<fieldset class="wff-filter-group wff-collapsible" data-filter="categories">
						<legend class="wff-group-title" role="button" aria-expanded="true">
							<?php esc_html_e( 'Categories', 'woo-fast-filter' ); ?>
							<svg class="wff-chevron" width="12" height="12" viewBox="0 0 12 12" aria-hidden="true">
								<path d="M3 5l3 3 3-3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
							</svg>
						</legend>
						<div class="wff-group-content">
							<?php foreach ( $categories as $category ) : ?>
								<label class="wff-checkbox-label">
									<input type="checkbox"
										name="categories[]"
										value="<?php echo esc_attr( (string) $category['id'] ); ?>"
										class="wff-checkbox"
									/>
									<span class="wff-checkbox-text"><?php echo esc_html( $category['name'] ); ?></span>
									<?php // Pro feature: live term counts (disabled in Free). ?>
									<?php if ( is_pro_active() ) : ?>
										<span class="wff-count">(<?php echo esc_html( (string) $category['count'] ); ?>)</span>
									<?php endif; ?>
								</label>
								<?php if ( ! empty( $category['children'] ) ) : ?>
									<div class="wff-children">
										<?php foreach ( $category['children'] as $child ) : ?>
											<label class="wff-checkbox-label">
												<input type="checkbox"
													name="categories[]"
													value="<?php echo esc_attr( (string) $child['id'] ); ?>"
													class="wff-checkbox"
												/>
												<span class="wff-checkbox-text"><?php echo esc_html( $child['name'] ); ?></span>
												<?php // Pro feature: live term counts (disabled in Free). ?>
												<?php if ( is_pro_active() ) : ?>
													<span class="wff-count">(<?php echo esc_html( (string) $child['count'] ); ?>)</span>
												<?php endif; ?>
											</label>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					</fieldset>
				<?php endif; ?>

				<?php // Attribute filters. ?>
				<?php foreach ( $filter_attributes as $attribute ) : ?>
					<fieldset class="wff-filter-group wff-collapsible"
						data-filter="attribute"
						data-taxonomy="<?php echo esc_attr( $attribute['taxonomy'] ); ?>">
						<legend class="wff-group-title" role="button" aria-expanded="true">
							<?php echo esc_html( $attribute['name'] ); ?>
							<svg class="wff-chevron" width="12" height="12" viewBox="0 0 12 12" aria-hidden="true">
								<path d="M3 5l3 3 3-3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
							</svg>
						</legend>
						<div class="wff-group-content">
							<?php foreach ( $attribute['terms'] as $term ) : ?>
								<label class="wff-checkbox-label">
									<input type="checkbox"
										name="attributes[<?php echo esc_attr( $attribute['taxonomy'] ); ?>][]"
										value="<?php echo esc_attr( (string) $term['id'] ); ?>"
										class="wff-checkbox"
									/>
									<span class="wff-checkbox-text"><?php echo esc_html( $term['name'] ); ?></span>
									<?php // Pro feature: live term counts (disabled in Free). ?>
									<?php if ( is_pro_active() ) : ?>
										<span class="wff-count">(<?php echo esc_html( (string) $term['count'] ); ?>)</span>
									<?php endif; ?>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>
				<?php endforeach; ?>

				<?php // Price filter. ?>
				<?php if ( $price_range['max'] > 0 ) : ?>
					<fieldset class="wff-filter-group wff-collapsible" data-filter="price">
						<legend class="wff-group-title" role="button" aria-expanded="true">
							<?php esc_html_e( 'Price', 'woo-fast-filter' ); ?>
							<svg class="wff-chevron" width="12" height="12" viewBox="0 0 12 12" aria-hidden="true">
								<path d="M3 5l3 3 3-3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
							</svg>
						</legend>
						<div class="wff-group-content">
							<div class="wff-price-range">
								<div class="wff-price-inputs">
									<label class="wff-price-label">
										<span class="wff-sr-only"><?php esc_html_e( 'Minimum price', 'woo-fast-filter' ); ?></span>
										<span class="wff-price-currency"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
										<input type="number"
											name="min_price"
											class="wff-price-input"
											min="<?php echo esc_attr( (string) floor( $price_range['min'] ) ); ?>"
											max="<?php echo esc_attr( (string) ceil( $price_range['max'] ) ); ?>"
											value="<?php echo esc_attr( (string) floor( $price_range['min'] ) ); ?>"
											placeholder="<?php esc_attr_e( 'Min', 'woo-fast-filter' ); ?>"
											step="1"
										/>
									</label>
									<span class="wff-price-separator">&mdash;</span>
									<label class="wff-price-label">
										<span class="wff-sr-only"><?php esc_html_e( 'Maximum price', 'woo-fast-filter' ); ?></span>
										<span class="wff-price-currency"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
										<input type="number"
											name="max_price"
											class="wff-price-input"
											min="<?php echo esc_attr( (string) floor( $price_range['min'] ) ); ?>"
											max="<?php echo esc_attr( (string) ceil( $price_range['max'] ) ); ?>"
											value="<?php echo esc_attr( (string) ceil( $price_range['max'] ) ); ?>"
											placeholder="<?php esc_attr_e( 'Max', 'woo-fast-filter' ); ?>"
											step="1"
										/>
									</label>
								</div>
								<div class="wff-price-slider">
									<input type="range"
										class="wff-range wff-range-min"
										min="<?php echo esc_attr( (string) floor( $price_range['min'] ) ); ?>"
										max="<?php echo esc_attr( (string) ceil( $price_range['max'] ) ); ?>"
										value="<?php echo esc_attr( (string) floor( $price_range['min'] ) ); ?>"
										step="1"
										aria-label="<?php esc_attr_e( 'Minimum price', 'woo-fast-filter' ); ?>"
									/>
									<input type="range"
										class="wff-range wff-range-max"
										min="<?php echo esc_attr( (string) floor( $price_range['min'] ) ); ?>"
										max="<?php echo esc_attr( (string) ceil( $price_range['max'] ) ); ?>"
										value="<?php echo esc_attr( (string) ceil( $price_range['max'] ) ); ?>"
										step="1"
										aria-label="<?php esc_attr_e( 'Maximum price', 'woo-fast-filter' ); ?>"
									/>
								</div>
							</div>
						</div>
					</fieldset>
				<?php endif; ?>

				<?php // Apply button (only when auto-apply is off). ?>
				<?php if ( ! $auto_apply ) : ?>
					<div class="wff-actions">
						<button type="submit" class="wff-apply-btn">
							<?php esc_html_e( 'Apply filters', 'woo-fast-filter' ); ?>
						</button>
					</div>
				<?php endif; ?>
			</form>
		</div>
	</div>

	<?php // Overlay for mobile. ?>
	<div class="wff-overlay" aria-hidden="true"></div>

	<?php // Product results area. ?>
	<div class="wff-results">
		<div class="wff-results-header">
			<span class="wff-results-count"></span>
			<div class="wff-sort">
				<label for="wff-sort-select" class="wff-sr-only"><?php esc_html_e( 'Sort by', 'woo-fast-filter' ); ?></label>
				<select id="wff-sort-select" class="wff-sort-select">
					<option value="menu_order"><?php esc_html_e( 'Default sorting', 'woo-fast-filter' ); ?></option>
					<option value="popularity"><?php esc_html_e( 'Popularity', 'woo-fast-filter' ); ?></option>
					<option value="rating"><?php esc_html_e( 'Average rating', 'woo-fast-filter' ); ?></option>
					<option value="date"><?php esc_html_e( 'Latest', 'woo-fast-filter' ); ?></option>
					<option value="price-asc"><?php esc_html_e( 'Price: low to high', 'woo-fast-filter' ); ?></option>
					<option value="price-desc"><?php esc_html_e( 'Price: high to low', 'woo-fast-filter' ); ?></option>
				</select>
			</div>
		</div>

		<div class="wff-products-grid" aria-live="polite"></div>

		<div class="wff-loading" hidden>
			<div class="wff-spinner"></div>
		</div>

		<div class="wff-no-results" hidden>
			<p><?php esc_html_e( 'No products match your filters.', 'woo-fast-filter' ); ?></p>
		</div>

		<div class="wff-pagination"></div>
	</div>
</div>
