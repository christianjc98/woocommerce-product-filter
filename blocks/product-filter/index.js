/**
 * Woo Fast Filter - Gutenberg Block Registration.
 *
 * Free version:
 *   - Layout, Style, Auto-apply controls are VISIBLE but DISABLED.
 *   - Each shows "Available in Pro" help text.
 *   - Show Active Filters toggle remains functional.
 *
 * Pro version:
 *   - All controls are unlocked when wffEditorConfig.isPro === true.
 *
 * @package WooFastFilter
 */

( function () {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var Placeholder = wp.components.Placeholder;
	var Icon = wp.components.Icon;
	var __ = wp.i18n.__;

	// Feature flag passed from PHP via wp_add_inline_script.
	var isPro = window.wffEditorConfig && window.wffEditorConfig.isPro;

	// Pro badge helper — small inline note for locked controls.
	function proLabel( text ) {
		return isPro ? text : text + ' \u2014 ' + __( 'Available in Pro', 'woo-fast-filter' );
	}

	registerBlockType( 'woo-fast-filter/product-filter', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var layout = attributes.layout;
			var style = attributes.style;
			var autoApply = attributes.autoApply;
			var showActiveFilters = attributes.showActiveFilters;
			var blockProps = useBlockProps();

			var layoutOptions = [
				{ label: __( 'Sidebar', 'woo-fast-filter' ), value: 'sidebar' },
				{ label: __( 'Top bar', 'woo-fast-filter' ), value: 'top' },
				{ label: __( 'Modal', 'woo-fast-filter' ), value: 'modal' },
			];

			var styleOptions = [
				{ label: __( 'Clean', 'woo-fast-filter' ), value: 'clean' },
				{ label: __( 'Soft', 'woo-fast-filter' ), value: 'soft' },
				{ label: __( 'Editorial', 'woo-fast-filter' ), value: 'editorial' },
			];

			return el(
				Fragment,
				null,

				// --- Inspector Controls (sidebar panel) ---
				el(
					InspectorControls,
					null,

					// Layout panel.
					el(
						PanelBody,
						{
							title: __( 'Layout', 'woo-fast-filter' ),
							initialOpen: true,
						},

						// Layout selector — Pro only.
						el( SelectControl, {
							label: proLabel( __( 'Filter layout', 'woo-fast-filter' ) ),
							value: isPro ? layout : 'sidebar',
							options: layoutOptions,
							disabled: ! isPro,
							onChange: function ( value ) {
								if ( isPro ) {
									setAttributes( { layout: value } );
								}
							},
						} ),

						// Style selector — Pro only.
						el( SelectControl, {
							label: proLabel( __( 'Visual style', 'woo-fast-filter' ) ),
							value: isPro ? style : 'clean',
							options: styleOptions,
							disabled: ! isPro,
							onChange: function ( value ) {
								if ( isPro ) {
									setAttributes( { style: value } );
								}
							},
						} )
					),

					// Behavior panel.
					el(
						PanelBody,
						{
							title: __( 'Behavior', 'woo-fast-filter' ),
							initialOpen: true,
						},

						// Auto-apply — Pro only.
						el( ToggleControl, {
							label: proLabel( __( 'Auto-apply filters', 'woo-fast-filter' ) ),
							help: isPro
								? __( 'Apply filters automatically as they change.', 'woo-fast-filter' )
								: __( 'Upgrade to Pro to enable auto-apply.', 'woo-fast-filter' ),
							checked: isPro ? autoApply : false,
							disabled: ! isPro,
							onChange: function ( value ) {
								if ( isPro ) {
									setAttributes( { autoApply: value } );
								}
							},
						} ),

						// Show active filters — available in Free.
						el( ToggleControl, {
							label: __( 'Show active filters', 'woo-fast-filter' ),
							help: __( 'Display active filter tags above results.', 'woo-fast-filter' ),
							checked: showActiveFilters,
							onChange: function ( value ) {
								setAttributes( { showActiveFilters: value } );
							},
						} )
					)
				),

				// --- Block preview in editor ---
				el(
					'div',
					blockProps,
					el(
						Placeholder,
						{
							icon: el( Icon, { icon: 'filter' } ),
							label: __( 'Product Filter', 'woo-fast-filter' ),
							instructions: __(
								'This block displays an AJAX product filter on the frontend.',
								'woo-fast-filter'
							),
						},
						el(
							'div',
							{ className: 'wff-editor-preview' },
							el( 'p', null,
								__( 'Layout: ', 'woo-fast-filter' ) +
								__( 'Sidebar', 'woo-fast-filter' ) +
								( isPro ? '' : ' (' + __( 'Free', 'woo-fast-filter' ) + ')' )
							),
							el( 'p', null,
								__( 'Style: ', 'woo-fast-filter' ) +
								__( 'Clean', 'woo-fast-filter' ) +
								( isPro ? '' : ' (' + __( 'Free', 'woo-fast-filter' ) + ')' )
							),
							el( 'p', null,
								__( 'Active filters: ', 'woo-fast-filter' ) +
								( showActiveFilters
									? __( 'Visible', 'woo-fast-filter' )
									: __( 'Hidden', 'woo-fast-filter' )
								)
							)
						)
					)
				)
			);
		},

		save: function () {
			// Server-side rendered — no save output.
			return null;
		},
	} );
} )();
