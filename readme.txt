=== Woo Fast Filter ===
Contributors: christianjc98
Tags: woocommerce, filter, ajax, product filter, woocommerce filter
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight AJAX product filter for WooCommerce. Fast filtering by category, attributes, and price without page reloads.

== Description ==

Woo Fast Filter adds a clean, fast product filter to your WooCommerce store.

Customers can filter products by category, attributes (like color or size), and price range. Results update instantly via AJAX without page reloads.

**Features:**

* AJAX filtering - no page reloads
* Category filter with subcategory support
* Product attribute filters
* Price range slider with min/max inputs
* Sort by price, popularity, rating, or date
* Mobile-friendly slide-in panel
* Active filters display with one-click removal
* Gutenberg block for easy placement
* Fully translatable

**Why Woo Fast Filter?**

* Lightweight - no bloat, no frameworks
* Fast - vanilla JavaScript, optimized queries
* Accessible - ARIA labels, keyboard support
* Mobile-first - touch-friendly, responsive

== Installation ==

1. Upload the `woo-fast-filter` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Add the "Woo Product Filter" block to any page using the block editor
4. The filter will automatically work on WooCommerce shop and category pages

== Frequently Asked Questions ==

= Does this work with WooCommerce? =

Yes. WooCommerce 7.0 or higher is required.

= Does it use AJAX? =

Yes. All filtering happens without page reloads for a smooth experience.

= Is it mobile friendly? =

Yes. The filter panel slides in from the side on mobile devices with touch-friendly controls.

= Can I filter by custom attributes? =

Yes. All product attributes registered in WooCommerce are automatically available as filters.

= Does it work with the block editor? =

Yes. Add the "Woo Product Filter" block to any page or template.

== Screenshots ==

1. Desktop filter sidebar
2. Desktop filter with active filter applied
3. Mobile filter button
4. Mobile filter panel open

== Changelog ==

= 1.0.2 =
* Improved loading and no-results states
* Better mobile touch targets
* Removed static term counts for cleaner UX
* Code cleanup and WordPress.org compliance

= 1.0.0 =
* Initial release
* AJAX product filtering
* Category, attribute, and price filters
* Mobile-responsive design
* Gutenberg block support

== Upgrade Notice ==

= 1.0.2 =
UX improvements and WordPress.org compliance. Recommended update.
