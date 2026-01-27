<?php
/**
 * Block editor script asset manifest.
 *
 * Declares dependencies for index.js (the block entry point).
 * WordPress loads these scripts before index.js executes.
 *
 * Load order:
 *   1. wff-block-save  → attaches window.wffBlock.Save
 *   2. wff-block-edit  → attaches window.wffBlock.Edit
 *   3. index.js        → reads both and calls registerBlockType
 *
 * wp-blocks is needed by index.js for registerBlockType.
 * The remaining WP deps are pulled in by wff-block-edit's own
 * dependency list (registered in PHP), so they're not repeated here.
 */
return [
	'dependencies' => [
		'wp-blocks',
		'wff-block-save',
		'wff-block-edit',
	],
	'version'      => '1.0.0',
];
