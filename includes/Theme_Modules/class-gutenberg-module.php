<?php
// includes/theme-modules/class-gutenberg-module.php

namespace MarineSync\Theme_Modules;

// Exit if accessed directly
if(!defined('ABSPATH')) {
	exit;
}

/**
 * Gutenberg Block integration
 */
class Gutenberg_Module extends Base_Module {

	/**
	 * Module name
	 */
	protected $name = 'gutenberg';

	/**
	 * Register the Gutenberg block
	 */
	public function register() {
		// Skip if Gutenberg is not available
		if (!function_exists('register_block_type')) {
			return;
		}

		// Register JS for the block
		wp_register_script(
			'marinesync-boat-listing-block',
			MARINESYNC_PLUGIN_URL . 'assets/js/gutenberg-block.js',
			['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'],
			MARINESYNC_PLUGIN_VERSION,
			true
		);

		// Pass parameters to the JS
		wp_localize_script(
			'marinesync-boat-listing-block',
			'marineSyncBoatParams',
			$this->get_parameters()
		);

		// Register the block
		register_block_type('marinesync/boat-listing', [
			'editor_script' => 'marinesync-boat-listing-block',
			'render_callback' => [$this, 'render_block'],
			'attributes' => $this->get_block_attributes()
		]);
	}

	/**
	 * Get block attributes based on parameters
	 */
	private function get_block_attributes() {
		$attributes = [];
		$parameters = $this->get_parameters();

		foreach ($parameters as $key => $param) {
			$attributes[$key] = [
				'type' => $param['type'] === 'number' ? 'number' : 'string',
				'default' => $param['default']
			];
		}

		return $attributes;
	}

	/**
	 * Render the Gutenberg block
	 */
	public function render_block($attributes) {
		return $this->render_listing($attributes);
	}
}