<?php
/**
 * Block editor script asset manifest.
 *
 * When using `file:./index.js` in block.json, WordPress looks for this file
 * to determine script dependencies. Without it, the script loads before
 * wp.blocks is available, so the block never registers.
 */
return [
	'dependencies' => [
		'wp-blocks',
		'wp-element',
		'wp-block-editor',
		'wp-components',
		'wp-i18n',
	],
	'version'      => '1.0.0',
];
