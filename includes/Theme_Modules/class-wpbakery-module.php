<?php
// includes/theme-modules/class-wpbakery-module.php

namespace MarineSync\Theme_Modules;

// Exit if accessed directly
if(!defined('ABSPATH')) {
	exit;
}

/**
 * WPBakery Page Builder integration
 */
class WPBakery_Module extends Base_Module {

	/**
	 * Module name
	 */
	protected $name = 'wpbakery';

	/**
	 * Register the WPBakery shortcode
	 */
	public function register() {
		// Skip if WPBakery is not available
		if (!class_exists('WPBakeryShortCode')) {
			return;
		}

		// Register the shortcode with WPBakery
		add_action('vc_before_init', [$this, 'vc_map_shortcode']);
	}

	/**
	 * Map the shortcode to WPBakery
	 */
	public function vc_map_shortcode() {
		vc_map([
			'name' => __('Boat Listing', 'marinesync'),
			'base' => 'marinesync_boat_listing',
			'class' => '',
			'category' => __('MarineSync', 'marinesync'),
			'params' => $this->get_vc_params()
		]);

		// Register the shortcode handler
		add_shortcode('marinesync_boat_listing', [$this, 'render_shortcode']);
	}

	/**
	 * Get VC parameters based on common parameters
	 */
	private function get_vc_params() {
		$params = [];
		$parameters = $this->get_parameters();

		foreach ($parameters as $key => $param) {
			$vc_param = [
				'type' => $param['type'] === 'number' ? 'textfield' : 'dropdown',
				'heading' => $param['label'],
				'param_name' => $key,
				'description' => $param['description'] ?? '',
				'value' => $param['options'] ?? $param['default']
			];

			$params[] = $vc_param;
		}

		return $params;
	}

	/**
	 * Render the WPBakery shortcode
	 */
	public function render_shortcode($atts) {
		return $this->render_listing($atts);
	}
}