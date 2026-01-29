/**
 * Woo Fast Filter - Block Edit Component.
 *
 * Renders the block editor UI: InspectorControls sidebar and
 * a static placeholder preview in the editor canvas.
 *
 * Free vs Pro gating:
 *   - Layout, Style, Auto-apply: VISIBLE but DISABLED in Free.
 *     Each shows "Available in Pro" in its label.
 *   - Show Active Filters: fully functional in Free.
 *   - The isPro flag is injected from PHP via wffEditorConfig.
 *     When Pro ships, it sets WFF_PRO_ACTIVE = true, which flips
 *     isPro to true and unlocks all controls — no JS changes needed.
 *
 * @package WooFastFilter
 */

( function () {
	'use strict';

	var el                 = wp.element.createElement;
	var Fragment           = wp.element.Fragment;
	var InspectorControls  = wp.blockEditor.InspectorControls;
	var useBlockProps       = wp.blockEditor.useBlockProps;
	var PanelBody          = wp.components.PanelBody;
	var SelectControl      = wp.components.SelectControl;
	var ToggleControl      = wp.components.ToggleControl;
	var Placeholder        = wp.components.Placeholder;
	var Icon               = wp.components.Icon;
	var __                 = wp.i18n.__;

	// --- Feature flag ---
	// FREE FEATURE FREEZE — v1.0
	// Passed from PHP (register_block → wp_add_inline_script).
	// Free: false. Pro: true.
	// In Free, Layout/Style/Auto-apply controls are VISIBLE but DISABLED.
	// Only showActiveFilters is editable. Do not unlock new controls
	// without gating behind isPro.
	var isPro = window.wffEditorConfig && window.wffEditorConfig.isPro;

	/**
	 * Append "Available in Pro" to a control label when not Pro.
	 *
	 * @param {string} text Original label text.
	 * @return {string} Label with optional Pro badge.
	 */
	function proLabel( text ) {
		return isPro
			? text
			: text + ' \u2014 ' + __( 'Available in Pro', 'woo-fast-filter' );
	}

	// --- Option definitions ---
	// Defined once outside the component to avoid re-creating on every render.

	var layoutOptions = [
		{ label: __( 'Sidebar', 'woo-fast-filter' ), value: 'sidebar' },
		{ label: __( 'Top bar', 'woo-fast-filter' ), value: 'top' },     // Pro only.
		{ label: __( 'Modal', 'woo-fast-filter' ), value: 'modal' },     // Pro only.
	];

	var styleOptions = [
		{ label: __( 'Clean', 'woo-fast-filter' ), value: 'clean' },
		{ label: __( 'Soft', 'woo-fast-filter' ), value: 'soft' },           // Pro only.
		{ label: __( 'Editorial', 'woo-fast-filter' ), value: 'editorial' }, // Pro only.
	];

	/**
	 * Edit component.
	 *
	 * @param {Object} props Block props (attributes, setAttributes, etc.).
	 * @return {WPElement} Editor UI.
	 */
	function Edit( props ) {
		var attributes        = props.attributes;
		var setAttributes     = props.setAttributes;
		var showActiveFilters = attributes.showActiveFilters;
		var blockProps        = useBlockProps();

		// In Free, these are display-only. Values are locked to defaults.
		var currentLayout  = isPro ? attributes.layout    : 'sidebar';
		var currentStyle   = isPro ? attributes.style     : 'clean';
		var currentAutoApply = isPro ? attributes.autoApply : false;

		return el(
			Fragment,
			null,

			// =============================================
			// Inspector Controls (editor sidebar)
			// =============================================
			el(
				InspectorControls,
				null,

				// --- Layout panel ---
				el(
					PanelBody,
					{
						title: __( 'Layout', 'woo-fast-filter' ),
						initialOpen: true,
					},

					// Layout — locked in Free.
					el( SelectControl, {
						label: proLabel( __( 'Filter layout', 'woo-fast-filter' ) ),
						value: currentLayout,
						options: layoutOptions,
						disabled: ! isPro,
						onChange: function ( value ) {
							// Pro gate: only allow changes when Pro is active.
							if ( isPro ) {
								setAttributes( { layout: value } );
							}
						},
					} ),

					// Style — locked in Free.
					el( SelectControl, {
						label: proLabel( __( 'Visual style', 'woo-fast-filter' ) ),
						value: currentStyle,
						options: styleOptions,
						disabled: ! isPro,
						onChange: function ( value ) {
							if ( isPro ) {
								setAttributes( { style: value } );
							}
						},
					} )
				),

				// --- Behavior panel ---
				el(
					PanelBody,
					{
						title: __( 'Behavior', 'woo-fast-filter' ),
						initialOpen: true,
					},

					// Auto-apply — locked in Free.
					el( ToggleControl, {
						label: proLabel( __( 'Auto-apply filters', 'woo-fast-filter' ) ),
						help: isPro
							? __( 'Apply filters automatically as they change.', 'woo-fast-filter' )
							: __( 'Upgrade to Pro to enable auto-apply.', 'woo-fast-filter' ),
						checked: currentAutoApply,
						disabled: ! isPro,
						onChange: function ( value ) {
							if ( isPro ) {
								setAttributes( { autoApply: value } );
							}
						},
					} ),

					// Show active filters — Free feature, fully editable.
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

			// =============================================
			// Editor canvas placeholder
			// =============================================
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
							__( 'Auto-apply: ', 'woo-fast-filter' ) +
							( currentAutoApply
								? __( 'Yes', 'woo-fast-filter' )
								: __( 'No', 'woo-fast-filter' )
							)
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
	}

	// Export to global namespace so index.js can reference it.
	window.wffBlock = window.wffBlock || {};
	window.wffBlock.Edit = Edit;
} )();
