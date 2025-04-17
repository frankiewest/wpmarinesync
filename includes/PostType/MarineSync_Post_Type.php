<?php
namespace MarineSync\PostType;

/**
 * MarineSync Post Type Registration and Helper Functions
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class MarineSync_Post_Type {
    /**
     * @return void
     */
    public static function register(): void {
        error_log('MS014: Starting post type registration');
        
        $labels = array(
            'name'               => _x('Boats', 'post type general name', 'marinesync'),
            'singular_name'      => _x('Boat', 'post type singular name', 'marinesync'),
            'menu_name'          => _x('Boats', 'admin menu', 'marinesync'),
            'name_admin_bar'     => _x('Boat', 'add new on admin bar', 'marinesync'),
            'add_new'            => _x('Add New', 'boat', 'marinesync'),
            'add_new_item'       => __('Add New Boat', 'marinesync'),
            'new_item'           => __('New Boat', 'marinesync'),
            'edit_item'          => __('Edit Boat', 'marinesync'),
            'view_item'          => __('View Boat', 'marinesync'),
            'all_items'          => __('All Boats', 'marinesync'),
            'search_items'       => __('Search Boats', 'marinesync'),
            'parent_item_colon'  => __('Parent Boats:', 'marinesync'),
            'not_found'          => __('No boats found.', 'marinesync'),
            'not_found_in_trash' => __('No boats found in Trash.', 'marinesync'),
        );
        
        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'boats'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-anchor',
            'supports'           => array('title', 'editor', 'thumbnail', 'excerpt'),
            'show_in_rest'       => true,
        );
        
        register_post_type('marinesync-boats', $args);

	    // Register boat status taxonomy
	    $status_labels = array(
		    'name'              => _x('Boat Statuses', 'taxonomy general name', 'marinesync'),
		    'singular_name'     => _x('Boat Status', 'taxonomy singular name', 'marinesync'),
		    'search_items'      => __('Search Boat Statuses', 'marinesync'),
		    'all_items'         => __('All Boat Statuses', 'marinesync'),
		    'parent_item'       => __('Parent Boat Status', 'marinesync'),
		    'parent_item_colon' => __('Parent Boat Status:', 'marinesync'),
		    'edit_item'         => __('Edit Boat Status', 'marinesync'),
		    'update_item'       => __('Update Boat Status', 'marinesync'),
		    'add_new_item'      => __('Add New Boat Status', 'marinesync'),
		    'new_item_name'     => __('New Boat Status Name', 'marinesync'),
		    'menu_name'         => __('Boat Statuses', 'marinesync'),
	    );

	    register_taxonomy('boat-status', array('marinesync-boats'), array(
		    'hierarchical'      => true,
		    'labels'            => $status_labels,
		    'show_ui'           => true,
		    'show_admin_column' => true,
		    'query_var'         => true,
		    'rewrite'           => array('slug' => 'boat-status'),
		    'show_in_rest'      => true,
	    ));

	    // Register boat condition taxonomy
	    $condition_labels = array(
		    'name'              => _x('Boat Conditions', 'taxonomy general name', 'marinesync'),
		    'singular_name'     => _x('Boat Condition', 'taxonomy singular name', 'marinesync'),
		    'search_items'      => __('Search Boat Conditions', 'marinesync'),
		    'all_items'         => __('All Boat Conditions', 'marinesync'),
		    'parent_item'       => __('Parent Boat Condition', 'marinesync'),
		    'parent_item_colon' => __('Parent Boat Condition:', 'marinesync'),
		    'edit_item'         => __('Edit Boat Condition', 'marinesync'),
		    'update_item'       => __('Update Boat Condition', 'marinesync'),
		    'add_new_item'      => __('Add New Boat Condition', 'marinesync'),
		    'new_item_name'     => __('New Boat Condition Name', 'marinesync'),
		    'menu_name'         => __('Boat Conditions', 'marinesync'),
	    );

	    register_taxonomy('boat-condition', array('marinesync-boats'), array(
		    'hierarchical'      => true,
		    'labels'            => $condition_labels,
		    'show_ui'           => true,
		    'show_admin_column' => true,
		    'query_var'         => true,
		    'rewrite'           => array('slug' => 'boat-condition'),
		    'show_in_rest'      => true,
	    ));
        
        // Register boat type taxonomy
        $type_labels = array(
            'name'              => _x('Boat Types', 'taxonomy general name', 'marinesync'),
            'singular_name'     => _x('Boat Type', 'taxonomy singular name', 'marinesync'),
            'search_items'      => __('Search Boat Types', 'marinesync'),
            'all_items'         => __('All Boat Types', 'marinesync'),
            'parent_item'       => __('Parent Boat Type', 'marinesync'),
            'parent_item_colon' => __('Parent Boat Type:', 'marinesync'),
            'edit_item'         => __('Edit Boat Type', 'marinesync'),
            'update_item'       => __('Update Boat Type', 'marinesync'),
            'add_new_item'      => __('Add New Boat Type', 'marinesync'),
            'new_item_name'     => __('New Boat Type Name', 'marinesync'),
            'menu_name'         => __('Boat Types', 'marinesync'),
        );
        
        register_taxonomy('boat-type', array('marinesync-boats'), array(
            'hierarchical'      => true,
            'labels'            => $type_labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'boat-type'),
            'show_in_rest'      => true,
        ));
        
        // Register manufacturer taxonomy
        $manufacturer_labels = array(
            'name'              => _x('Manufacturers', 'taxonomy general name', 'marinesync'),
            'singular_name'     => _x('Manufacturer', 'taxonomy singular name', 'marinesync'),
            'search_items'      => __('Search Manufacturers', 'marinesync'),
            'all_items'         => __('All Manufacturers', 'marinesync'),
            'parent_item'       => __('Parent Manufacturer', 'marinesync'),
            'parent_item_colon' => __('Parent Manufacturer:', 'marinesync'),
            'edit_item'         => __('Edit Manufacturer', 'marinesync'),
            'update_item'       => __('Update Manufacturer', 'marinesync'),
            'add_new_item'      => __('Add New Manufacturer', 'marinesync'),
            'new_item_name'     => __('New Manufacturer Name', 'marinesync'),
            'menu_name'         => __('Manufacturers', 'marinesync'),
        );
        
        register_taxonomy('manufacturer', array('marinesync-boats'), array(
            'hierarchical'      => true,
            'labels'            => $manufacturer_labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'manufacturer'),
            'show_in_rest'      => true,
        ));
        
        error_log('MS015: Post type registration complete');
    }

    /**
     * @param $post_id
     * @return mixed
     */
    public static function get_boat_id($post_id = null): mixed {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        return get_post_meta($post_id, 'boat_id', true);
    }

	/**
	 * @param $post_id
	 * @return string
	 */
	public static function get_asking_price($post_id = null): string {
		if (!$post_id) {
			$post_id = get_the_ID();
		}

		$price = '';

		// Try ACF first
		if (function_exists('get_field')) {
			$price = get_field('asking_price', $post_id);
		}

		// Fallback to post meta
		if (empty($price)) {
			$price = get_post_meta($post_id, 'price', true);
		}

		return $price;
	}

	/**
	 * @param int $post_id
	 * @param mixed $price
	 *
	 * @return bool
	 */
	public static function set_asking_price( int $post_id, mixed $price): bool {
		if ( $price === null ) {
			return false;
		}

		// Update ACF if available
		if (function_exists('update_field')) {
			update_field('asking_price', $price, $post_id);
		}

		// Always update post meta as fallback
		update_post_meta($post_id, 'price', $price);

		return true;
	}

	/**
	 * @param $post_id
	 * @return string
	 */
	public static function get_boat_name($post_id = null): string {
		if (!$post_id) {
			$post_id = get_the_ID();
		}

		$name = '';

		// Try ACF first
		if (function_exists('get_field')) {
			$name = get_field('boat_name', $post_id);
		}

		// Fallback to post meta
		if (empty($name)) {
			$name = get_post_meta($post_id, 'name', true);
		}

		// If still empty, use the post title
		if (empty($name)) {
			$name = get_the_title($post_id);
		}

		return $name;
	}

	/**
	 * @param int $post_id
	 * @param mixed $name
	 *
	 * @return bool
	 */
	public static function set_boat_name( int $post_id, mixed $name): bool {
		if ($post_id === null || $name === null) {
			return false;
		}

		// Update ACF if available
		if (function_exists('update_field')) {
			update_field('boat_name', $name, $post_id);
		}

		// Always update post meta as fallback
		update_post_meta($post_id, 'name', $name);

		return true;
	}

	/**
	 * @param $post_id
	 * @return string
	 */
	public static function get_manufacturer($post_id = null): string {
		if (!$post_id) {
			$post_id = get_the_ID();
		}

		$manufacturer = '';

		// Try ACF first
		if (function_exists('get_field')) {
			$manufacturer = get_field('manufacturer', $post_id);
		}

		// Fallback to post meta
		if (empty($manufacturer)) {
			$manufacturer = get_post_meta($post_id, 'manufacturer', true);
		}

		return $manufacturer;
	}

	/**
	 * @param int $post_id
	 * @param mixed $manufacturer
	 *
	 * @return bool
	 */
	public static function set_manufacturer( int $post_id, mixed $manufacturer): bool {
		if ( $manufacturer === null ) {
			return false;
		}

		// Update ACF if available
		if (function_exists('update_field')) {
			update_field('manufacturer', $manufacturer, $post_id);
		}

		// Always update post meta as fallback
		update_post_meta($post_id, 'manufacturer', $manufacturer);

		return true;
	}

	/**
	 * @param $post_id
	 * @return string
	 */
	public static function get_model($post_id = null): string {
		if (!$post_id) {
			$post_id = get_the_ID();
		}

		$model = '';

		// Try ACF first
		if (function_exists('get_field')) {
			$model = get_field('model', $post_id);
		}

		// Fallback to post meta
		if (empty($model)) {
			$model = get_post_meta($post_id, 'model', true);
		}

		return $model;
	}

	/**
	 * @param int $post_id
	 * @param mixed $model
	 *
	 * @return bool
	 */
	public static function set_model( int $post_id, mixed $model): bool {
		if ( $model === null ) {
			return false;
		}

		// Update ACF if available
		if (function_exists('update_field')) {
			update_field('model', $model, $post_id);
		}

		// Always update post meta as fallback
		update_post_meta($post_id, 'model', $model);

		return true;
	}

	/**
	 * @param $post_id
	 * @return string
	 */
	public static function get_year($post_id = null): string {
		if (!$post_id) {
			$post_id = get_the_ID();
		}

		$year = '';

		// Try ACF first
		if (function_exists('get_field')) {
			$year = get_field('year', $post_id);
		}

		// Fallback to post meta
		if (empty($year)) {
			$year = get_post_meta($post_id, 'year', true);
		}

		return $year;
	}

	/**
	 * @param int $post_id
	 * @param mixed $year
	 *
	 * @return bool
	 */
	public static function set_year( int $post_id, mixed $year): bool {
		if ( $year === null ) {
			return false;
		}

		// Update ACF if available
		if (function_exists('update_field')) {
			update_field('year', $year, $post_id);
		}

		// Always update post meta as fallback
		update_post_meta($post_id, 'year', $year);

		return true;
	}

	/**
	 * @param $post_id
	 * @return string
	 */
	public static function get_location($post_id = null): string {
		if (!$post_id) {
			$post_id = get_the_ID();
		}

		$location = '';

		// Try ACF first
		if (function_exists('get_field')) {
			$location = get_field('vessel_lying', $post_id);
		}

		// Fallback to post meta
		if (empty($location)) {
			$location = get_post_meta($post_id, 'vessel_lying', true);
		}

		return $location;
	}

	/**
	 * @param int $post_id
	 * @param mixed $location
	 *
	 * @return bool
	 */
	public static function set_location( int $post_id, mixed $location): bool {
		if ( $location === null ) {
			return false;
		}

		// Update ACF if available
		if (function_exists('update_field')) {
			update_field('vessel_lying', $location, $post_id);
		}

		// Always update post meta as fallback
		update_post_meta($post_id, 'vessel_lying', $location);

		return true;
	}

	/**
	 * @param $post_id
	 * @return string[]
	 */
	public static function get_dimensions($post_id = null): array {
		if (!$post_id) {
			$post_id = get_the_ID();
		}

		$dimensions = array(
			'loa' => '',
			'beam' => '',
			'draft' => ''
		);

		// Try ACF first
		if (function_exists('get_field')) {
			$dimensions['loa'] = get_field('loa', $post_id);
			$dimensions['beam'] = get_field('beam', $post_id);
			$dimensions['draft'] = get_field('draft', $post_id);
		}

		// Fallback to post meta
		if (empty($dimensions['loa'])) {
			$dimensions['loa'] = get_post_meta($post_id, 'loa', true);
		}

		if (empty($dimensions['beam'])) {
			$dimensions['beam'] = get_post_meta($post_id, 'beam', true);
		}

		if (empty($dimensions['draft'])) {
			$dimensions['draft'] = get_post_meta($post_id, 'draft', true);
		}

		return $dimensions;
	}

	/**
	 * @param int $post_id
	 * @param array $dimensions
	 *
	 * @return bool
	 */
	public static function set_dimensions( int $post_id, array $dimensions): bool {
		// Extract dimension values with defaults
		$loa = $dimensions['loa'] ?? '';
		$beam = $dimensions['beam'] ?? '';
		$draft = $dimensions['draft'] ?? '';

		// Update ACF if available
		if (function_exists('update_field')) {
			if (!empty($loa)) update_field('loa', $loa, $post_id);
			if (!empty($beam)) update_field('beam', $beam, $post_id);
			if (!empty($draft)) update_field('draft', $draft, $post_id);
		}

		// Always update post meta as fallback
		if (!empty($loa)) update_post_meta($post_id, 'loa', $loa);
		if (!empty($beam)) update_post_meta($post_id, 'beam', $beam);
		if (!empty($draft)) update_post_meta($post_id, 'draft', $draft);

		return true;
	}

	/**
	 * @param $field_name
	 * @param $post_id
	 * @return string
	 */
	public static function get_boat_field($field_name, $post_id = null): string {
		if (!$post_id) {
			$post_id = get_the_ID();
		}

		$value = '';

		// Try ACF first
		if (function_exists('get_field')) {
			$value = get_field($field_name, $post_id);
		}

		// Fallback to post meta
		if (empty($value)) {
			$value = get_post_meta($post_id, $field_name, true);
		}

		return $value;
	}

	/**
	 * @param int $post_id
	 * @param string $field_name
	 * @param mixed $value
	 *
	 * @return bool
	 */
	public static function set_boat_field( int $post_id, string $field_name, mixed $value): bool {
		if ($value === null) {
			return false;
		}

		// Update ACF if available
		if (function_exists('update_field')) {
			update_field($field_name, $value, $post_id);
		}

		// Always update post meta as fallback
		update_post_meta($post_id, $field_name, $value);

		return true;
	}

	/**
	 * Get a boat by its reference ID.
	 *
	 * @param string $ref The reference ID of the boat.
	 * @return int|\WP_Post|null The boat post object or null if not found.
	 */
	public static function get_boat_by_ref($ref): int|\WP_Post|null {
		$args = array(
			'post_type' => 'marinesync-boats',
			'meta_query' => array(
				array(
					'key' => 'boat_id',
					'value' => $ref,
					'compare' => '='
				)
			)
		);

		$query = new \WP_Query($args);

		if ($query->have_posts()) {
			return $query->posts[0];
		}

		return null;
	}
}