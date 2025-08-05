<?php
/**
 * Shortcodes and helper functions
 */

namespace MarineSync;

class Functions_MarineSync {

	/**
	 * @return Functions_MarineSync|null
	 * @since 1.0.1
	 * @version 1.0.1
	 */
	public static function get_instance(){
		static $instance = null;
		if ( is_null( $instance ) ) {
			$instance = new Functions_MarineSync();
		}
		return $instance;
	}

	/**
	 * Functions_MarineSync constructor.
	 * @since 1.0.1
	 * @version 1.0.1
	 * @return void
	 */
	private function __construct() {
		add_shortcode( 'marinesync_field', array( $this, 'marinesync_shortcode' ) );
	}

	/**
	 * Shortcode to display a field value from the current post
	 *
	 * @param array $atts
	 * @return string
	 */
	public function marinesync_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'field' => ''
		), $atts, 'marinesync' );

		// Check we're in the admin area
		if(is_admin()) return;

		// Check the post we are on is of the post type marinesync-boats
		$id = get_the_ID();
		if(get_post_type($id) != 'marinesync-boats') return;

		// Get the field value
		if (!class_exists('ACF')) {
			return get_post_meta($id, $atts['field'], true);
		} else {
			return get_field($atts['field'], $id);
		}
	}

	/**
	 * @param string $field
	 * @param int $i
	 *
	 * @return string|null
	 * @description Get field value from offices ACF options
	 */
	public static function get_office_field(
		string $field = 'id',
		int $i = 0
	): string|null
	{
		// Get office repeater
		$offices = get_field('offices', 'option');
		error_log(print_r($offices, true));

		// Sanity check
		if(empty($offices)) return null;

		// Return specified value
		return (string) $offices[$i][$field];
	}
}