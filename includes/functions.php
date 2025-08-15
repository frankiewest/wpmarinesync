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
			'field' => '',
			'type'  => 'value', // 'key' (stored) or 'value' (label)
		), $atts, 'marinesync' );

		// Admin or missing field name -> no output
		if ( is_admin() || empty( $atts['field'] ) ) {
			return '';
		}

		$post_id = get_the_ID();
		if ( ! $post_id || get_post_type( $post_id ) !== 'marinesync-boats' ) {
			return '';
		}

		$field_name = $atts['field'];
		$type       = strtolower( trim( $atts['type'] ) );
		if ( $type !== 'value' ) {
			$type = 'key'; // default safety
		}

		// If ACF isn't available, fall back to raw post meta
		if ( ! function_exists( 'get_field_object' ) ) {
			$raw = get_post_meta( $post_id, $field_name, true );
			// Always return stored value when ACF isn't available
			return esc_html( is_array( $raw ) ? implode( ', ', $raw ) : (string) $raw );
		}

		$field = get_field_object( $field_name, $post_id );
		if ( ! $field ) {
			// Unknown field — fallback to meta
			$raw = get_post_meta( $post_id, $field_name, true );
			return esc_html( is_array( $raw ) ? implode( ', ', $raw ) : (string) $raw );
		}

		$value   = $field['value'] ?? '';
		$choices = isset( $field['choices'] ) && is_array( $field['choices'] ) ? $field['choices'] : [];

		// Normalize arrays (checkbox/multi-select)
		if ( is_array( $value ) ) {
			if ( $type === 'key' ) {
				$out = implode( ', ', array_map( 'strval', $value ) );
			} else {
				// Map each selected key to its label if available, else keep key
				$labels = array_map( function ( $k ) use ( $choices ) {
					return isset( $choices[ $k ] ) ? $choices[ $k ] : $k;
				}, $value );
				$out = implode( ', ', $labels );
			}
			return esc_html( $out );
		}

		// Scalar values (select/radio/text etc.)
		if ( $type === 'key' ) {
			return esc_html( (string) $value );
		}

		// type === 'value' → try to map to label (if choices exist), else return the stored value
		$label = isset( $choices[ $value ] ) ? $choices[ $value ] : $value;
		return esc_html( (string) $label );
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