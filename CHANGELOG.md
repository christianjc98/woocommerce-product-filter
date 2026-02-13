# Changelog

All notable changes to Woo Fast Filter will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.2] - 2025-02-13

### Changed
- Improved loading and no-results states
- Better mobile touch targets (44px minimum)
- Removed static term counts for cleaner UX

### Fixed
- Filter tag removal now updates results immediately
- Mobile spacing improvements for pagination

### Compliance
- WordPress.org readme.txt added
- Plugin header cleaned up
- Code hygiene pass completed

## [1.0.0] - 2025-02-13

### Added
- Initial public release
- AJAX-powered product filtering without page reloads
- Category filter with hierarchical support
- Product attribute filters (color, size, etc.)
- Price range filter with dual slider and number inputs
- Sort dropdown (default, popularity, rating, date, price)
- Pagination with configurable products per page
- Gutenberg block with sidebar controls
- Mobile-responsive filter panel with slide-in drawer
- Active filters display with removable tags
- "No results" state with clear filters button
- Full internationalization (i18n) support
- Accessibility: ARIA labels, keyboard navigation, screen reader support
- WooCommerce HPOS compatibility

### Fixed
- Filter tag removal now updates product grid immediately
- Mobile touch targets meet 44px accessibility minimum
- All user-facing strings are translatable

### Developer Notes
- Free version enforces sidebar layout, clean style, manual apply
- Pro-only features (layout variants, auto-apply, caching) are gated via `is_pro_active()`
- Term counts hidden in Free to avoid stale count confusion
