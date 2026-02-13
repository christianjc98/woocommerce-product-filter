/**
 * Woo Fast Filter - Block Registration.
 *
 * Entry point loaded by block.json editorScript.
 * Delegates to edit.js (editor UI) and save.js (null output).
 * Both are loaded as script dependencies before this file runs.
 *
 * Attributes and defaults are defined in block.json.
 * This file contains no UI logic — only the registration call.
 *
 * @package WooFastFilter
 */

( function () {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;

	// Edit and Save are loaded as separate scripts and attached
	// to window.wffBlock before this file executes.
	var Edit = window.wffBlock && window.wffBlock.Edit;
	var Save = window.wffBlock && window.wffBlock.Save;

	if ( ! Edit || ! Save ) {
		return;
	}

	registerBlockType( 'woo-fast-filter/product-filter', {
		edit: Edit,
		save: Save,
	} );
} )();
