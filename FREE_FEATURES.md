# Woo Fast Filter — Free Feature Set (v1.0)

This document defines the feature boundary between Free and Pro.
All items below are frozen for the Free v1.0 release.

## Free — Included

| Feature | Details |
|---|---|
| AJAX product filtering | Categories, attributes, price range |
| Sidebar layout | Fixed to `sidebar` |
| Clean visual style | Fixed to `clean` |
| Manual apply button | `autoApply` forced to `false` |
| Show active filters toggle | User-configurable in block editor |
| Sorting | Default, popularity, rating, date, price asc/desc |
| Pagination | Page-based with configurable per_page (1-100) |
| Mobile bottom-sheet panel | Responsive filter panel with overlay |
| Collapsible filter groups | Expand/collapse categories, attributes, price |
| Price range slider | Dual range inputs with number fields |
| AbortController | Cancels stale fetch requests (correctness) |
| Gutenberg block | Single instance per page (`"multiple": false`) |
| HPOS compatibility | Declared for WooCommerce 8.0+ |

## Free — Locked (visible but disabled in editor)

| Control | Free Value | Pro Unlocks |
|---|---|---|
| Layout | `sidebar` | `top`, `modal` |
| Visual style | `clean` | `soft`, `editorial` |
| Auto-apply filters | `false` | User-configurable toggle |

## Pro — Gated Features (not active in Free)

| Feature | Gate | Free Behavior |
|---|---|---|
| Transient caching | `is_feature_enabled('caching')` | All cache methods are no-ops |
| Cache invalidation hooks | `is_feature_enabled('cache_invalidation')` | Hooks not registered |
| Cache warming | `is_feature_enabled('cache_warming')` | `warm()` is a no-op |
| Debounced auto-apply | `is_feature_enabled('debounce')` via `autoApply` | `autoApply` forced false server-side |
| Layout options | `is_pro_active()` | Locked to `sidebar` |
| Style options | `is_pro_active()` | Locked to `clean` |

## Enforcement Points

Free defaults are enforced at three layers:

1. **PHP render** (`woo-fast-filter.php` → `render_filter_block()`) — overrides saved attributes.
2. **Template** (`templates/filter-block.php`) — guards against direct template loading.
3. **Editor JS** (`blocks/product-filter/edit.js`) — disables controls, shows "Available in Pro".

## How Pro Activates

Pro sets `WFF_PRO_ACTIVE = true` (constant) or returns `true` via the `wff_is_pro_active` filter.
Individual features can be toggled via the `wff_feature_enabled` filter.
No Free code changes are needed — Pro plugs into existing gates.
