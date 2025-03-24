<?php
// includes/theme-modules/class-base-module.php

namespace MarineSync\Theme_Modules;

// Exit if accessed directly
if(!defined('ABSPATH')) {
	exit;
}

/**
 * Base Module class that all theme modules will extend
 */
abstract class Base_Module {

	/**
	 * Module name
	 */
	protected $name = '';

	/**
	 * Initialize the module
	 */
	public function __construct() {
		add_action('init', [$this, 'register']);
	}

	/**
	 * Register the module with the theme/page builder
	 */
	abstract public function register();

	/**
	 * Get common parameters for the module
	 */
	protected function get_parameters() {
		return \MarineSync\Boat_Listing::get_common_parameters();
	}

	/**
	 * Render the boat listing
	 */
	protected function render_listing($atts) {
		return \MarineSync\Boat_Listing::generate_listing($atts);
	}
}