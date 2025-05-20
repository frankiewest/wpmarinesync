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
		if ( is_admin() ) return;

		// Check the post we are on is of the post type marinesync-boats
		$id = get_the_ID();
		if ( get_post_type( $id ) != 'marinesync-boats' ) return;

		// Define mapping of field names to their corresponding tab names in the Boat Data Template group
		$field_to_tab = array(
			'construction_details' => 'Construction',
			'machinery_details' => 'Machinery',
			'electrics_details' => 'Electrics',
			'tankage_details' => 'Tankage',
			'accommodation_details' => 'Accommodation',
			'domestic_details' => 'Domestic',
			'deck_details' => 'Deck',
			'navigation_details' => 'Navigation',
			'tenders_details' => 'Tenders',
			'safety_details' => 'Safety',
		);

		// Get the field value
		$field_value = !class_exists( 'ACF' ) ? get_post_meta( $id, $atts['field'], true ) : get_field( $atts['field'], $id );

		// If no field value, return empty to avoid unnecessary output
		if ( empty( $field_value ) ) return;

		// Check if the field is in the Boat Data Template group
		if ( array_key_exists( $atts['field'], $field_to_tab ) ) {
			// Prepend the tab name as an h3 title
			return '<h3>' . esc_html( $field_to_tab[ $atts['field'] ] ) . '</h3>' . wp_kses_post( $field_value );
		}

		// Return the field value without modification for fields outside the group
		return wp_kses_post( $field_value );
	}
}