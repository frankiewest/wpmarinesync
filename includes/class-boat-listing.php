<?php

namespace MarineSync;

// Exit if accessed directly
if(!defined('ABSPATH')) {
	exit;
}

class Boat_Listing {

	/**
	 * Initialize the boat listing integrations
	 */
	public function __construct() {
		add_action('init', [$this, 'initialize_theme_integrations']);
	}

	/**
	 * Initialize integrations based on active theme/page builder
	 */
	public function initialize_theme_integrations() {
		// Load the base module first
		require_once MARINESYNC_PLUGIN_DIR . 'includes/theme-modules/class-base-module.php';

		// Check which theme/builder is active and load appropriate modules
		if ($this->is_gutenberg_active()) {
			require_once MARINESYNC_PLUGIN_DIR . 'includes/theme-modules/class-gutenberg-module.php';
			new Theme_Modules\Gutenberg_Module();
		}

		if ($this->is_wpbakery_active()) {
			require_once MARINESYNC_PLUGIN_DIR . 'includes/theme-modules/class-wpbakery-module.php';
			new Theme_Modules\WPBakery_Module();
		}
	}

	/**
	 * Check if Gutenberg is being used
	 */
	private function is_gutenberg_active() {
		return function_exists('register_block_type');
	}

	/**
	 * Check if WPBakery Page Builder is active
	 */
	private function is_wpbakery_active() {
		return class_exists('WPBakeryVisualComposerAbstract') || class_exists('WPBakeryShortCode');
	}

	/**
	 * Get common boat listing parameters for modules
	 */
	public static function get_common_parameters() {
		return [
			'num_posts' => [
				'type' => 'number',
				'default' => 10,
				'label' => __('Number of Boats', 'marinesync'),
				'description' => __('How many boats to display', 'marinesync')
			],
			'order_by' => [
				'type' => 'select',
				'default' => 'date',
				'label' => __('Order By', 'marinesync'),
				'options' => [
					'date' => __('Date', 'marinesync'),
					'price' => __('Price', 'marinesync'),
					'title' => __('Title', 'marinesync'),
					'year' => __('Year', 'marinesync')
				]
			],
			'order' => [
				'type' => 'select',
				'default' => 'DESC',
				'label' => __('Order', 'marinesync'),
				'options' => [
					'DESC' => __('Descending', 'marinesync'),
					'ASC' => __('Ascending', 'marinesync')
				]
			],
			'boat_type' => [
				'type' => 'select',
				'default' => '',
				'label' => __('Boat Type', 'marinesync'),
				'options' => self::get_boat_type_options()
			],
			'manufacturer' => [
				'type' => 'select',
				'default' => '',
				'label' => __('Manufacturer', 'marinesync'),
				'options' => self::get_manufacturer_options()
			],
			'layout' => [
				'type' => 'select',
				'default' => 'grid',
				'label' => __('Layout', 'marinesync'),
				'options' => [
					'grid' => __('Grid', 'marinesync'),
					'list' => __('List', 'marinesync'),
					'carousel' => __('Carousel', 'marinesync')
				]
			],
			'columns' => [
				'type' => 'select',
				'default' => '3',
				'label' => __('Columns', 'marinesync'),
				'options' => [
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4'
				]
			]
		];
	}

	/**
	 * Get boat type options for module parameter
	 */
	private static function get_boat_type_options() {
		$options = ['' => __('All Boat Types', 'marinesync')];
		$terms = get_terms([
			'taxonomy' => 'boat-type',
			'hide_empty' => false
		]);

		if (!is_wp_error($terms)) {
			foreach ($terms as $term) {
				$options[$term->slug] = $term->name;
			}
		}

		return $options;
	}

	/**
	 * Get manufacturer options for module parameter
	 */
	private static function get_manufacturer_options() {
		$options = ['' => __('All Manufacturers', 'marinesync')];
		$terms = get_terms([
			'taxonomy' => 'manufacturer',
			'hide_empty' => false
		]);

		if (!is_wp_error($terms)) {
			foreach ($terms as $term) {
				$options[$term->slug] = $term->name;
			}
		}

		return $options;
	}

	/**
	 * Generate boat listing based on parameters
	 */
	public static function generate_listing($atts = []) {
		$default_atts = [
			'num_posts' => 10,
			'order_by' => 'date',
			'order' => 'DESC',
			'boat_type' => '',
			'manufacturer' => '',
			'layout' => 'grid',
			'columns' => '3'
		];

		$atts = wp_parse_args($atts, $default_atts);

		// Set up query args
		$args = [
			'post_type' => 'marinesync-boats',
			'posts_per_page' => $atts['num_posts'],
			'order' => $atts['order']
		];

		// Handle orderby for meta fields
		if ($atts['order_by'] === 'price') {
			$args['meta_key'] = 'price';
			$args['orderby'] = 'meta_value_num';
		} elseif ($atts['order_by'] === 'year') {
			$args['meta_key'] = 'year';
			$args['orderby'] = 'meta_value_num';
		} else {
			$args['orderby'] = $atts['order_by'];
		}

		// Add taxonomy filters
		$tax_query = [];

		if (!empty($atts['boat_type'])) {
			$tax_query[] = [
				'taxonomy' => 'boat-type',
				'field' => 'slug',
				'terms' => $atts['boat_type']
			];
		}

		if (!empty($atts['manufacturer'])) {
			$tax_query[] = [
				'taxonomy' => 'manufacturer',
				'field' => 'slug',
				'terms' => $atts['manufacturer']
			];
		}

		if (!empty($tax_query)) {
			$args['tax_query'] = $tax_query;
		}

		// Run the query
		$query = new \WP_Query($args);

		// Start output buffer
		ob_start();

		// Get the template based on layout
		$template_path = MARINESYNC_PLUGIN_DIR . 'templates/boat-listing-' . $atts['layout'] . '.php';
		$default_template = MARINESYNC_PLUGIN_DIR . 'templates/boat-listing-grid.php';

		$template = file_exists($template_path) ? $template_path : $default_template;

		// Include the template with variables
		include $template;

		return ob_get_clean();
	}
}