/**
 * Woo Fast Filter - Gutenberg Block Registration.
 *
 * Registers the product-filter block with the WordPress block editor.
 * Uses server-side rendering (PHP) for the frontend output.
 *
 * @package WooFastFilter
 */

( function () {
	'use strict';

	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const {
		PanelBody,
		SelectControl,
		ToggleControl,
		Placeholder,
		Icon,
	} = wp.components;
	const { __ } = wp.i18n;

	registerBlockType( 'woo-fast-filter/product-filter', {
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const { layout, style, autoApply, showActiveFilters } = attributes;
			const blockProps = useBlockProps();

			const layoutOptions = [
				{ label: __( 'Sidebar', 'woo-fast-filter' ), value: 'sidebar' },
				{ label: __( 'Top bar', 'woo-fast-filter' ), value: 'top' },
				{ label: __( 'Modal', 'woo-fast-filter' ), value: 'modal' },
			];

			const styleOptions = [
				{ label: __( 'Clean', 'woo-fast-filter' ), value: 'clean' },
				{ label: __( 'Soft', 'woo-fast-filter' ), value: 'soft' },
				{ label: __( 'Editorial', 'woo-fast-filter' ), value: 'editorial' },
			];

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: __( 'Layout', 'woo-fast-filter' ),
							initialOpen: true,
						},
						el( SelectControl, {
							label: __( 'Filter layout', 'woo-fast-filter' ),
							value: layout,
							options: layoutOptions,
							onChange: function ( value ) {
								setAttributes( { layout: value } );
							},
						} ),
						el( SelectControl, {
							label: __( 'Visual style', 'woo-fast-filter' ),
							value: style,
							options: styleOptions,
							onChange: function ( value ) {
								setAttributes( { style: value } );
							},
						} )
					),
					el(
						PanelBody,
						{
							title: __( 'Behavior', 'woo-fast-filter' ),
							initialOpen: true,
						},
						el( ToggleControl, {
							label: __( 'Auto-apply filters', 'woo-fast-filter' ),
							help: __(
								'Apply filters automatically as they change.',
								'woo-fast-filter'
							),
							checked: autoApply,
							onChange: function ( value ) {
								setAttributes( { autoApply: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show active filters', 'woo-fast-filter' ),
							help: __(
								'Display active filter tags above results.',
								'woo-fast-filter'
							),
							checked: showActiveFilters,
							onChange: function ( value ) {
								setAttributes( { showActiveFilters: value } );
							},
						} )
					)
				),
				el(
					'div',
					blockProps,
					el(
						Placeholder,
						{
							icon: el( Icon, { icon: 'filter' } ),
							label: __( 'Product Filter', 'woo-fast-filter' ),
							instructions: __(
								'This block will display an AJAX product filter on the frontend. Configure options in the block settings panel.',
								'woo-fast-filter'
							),
						},
						el(
							'div',
							{ className: 'wff-editor-preview' },
							el(
								'p',
								null,
								__(
									'Layout: ',
									'woo-fast-filter'
								) +
									layoutOptions.find( function ( o ) {
										return o.value === layout;
									} ).label
							),
							el(
								'p',
								null,
								__(
									'Style: ',
									'woo-fast-filter'
								) +
									styleOptions.find( function ( o ) {
										return o.value === style;
									} ).label
							),
							el(
								'p',
								null,
								__(
									'Auto-apply: ',
									'woo-fast-filter'
								) + ( autoApply ? __( 'Yes', 'woo-fast-filter' ) : __( 'No', 'woo-fast-filter' ) )
							)
						)
					)
				)
			);
		},

		save: function () {
			// Server-side rendered block - no save output needed.
			return null;
		},
	} );
} )();
