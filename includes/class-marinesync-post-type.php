<?php
namespace MarineSync\PostType;

/**
 * MarineSync Post Type Registration and Helper Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class MarineSync_Post_Type {
    
    /**
     * Register the boat listing custom post type
     */
    public static function register() {
        error_log('MS014: Starting post type registration');
        
        $labels = array(
            'name'               => \_x( 'Boats', 'post type general name', 'marinesync' ),
            'singular_name'      => \_x( 'Boat', 'post type singular name', 'marinesync' ),
            'menu_name'          => \_x( 'Boats', 'admin menu', 'marinesync' ),
            'name_admin_bar'     => \_x( 'Boat', 'add new on admin bar', 'marinesync' ),
            'add_new'            => \_x( 'Add New', 'boat', 'marinesync' ),
            'add_new_item'       => \__('Add New Boat', 'marinesync' ),
            'new_item'           => \__('New Boat', 'marinesync' ),
            'edit_item'          => \__('Edit Boat', 'marinesync' ),
            'view_item'          => \__('View Boat', 'marinesync' ),
            'all_items'          => \__('All Boats', 'marinesync' ),
            'search_items'       => \__('Search Boats', 'marinesync' ),
            'parent_item_colon'  => \__('Parent Boats:', 'marinesync' ),
            'not_found'          => \__('No boats found.', 'marinesync' ),
            'not_found_in_trash' => \__('No boats found in Trash.', 'marinesync' )
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'boats' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-anchor',
            'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields' ),
            'show_in_rest'       => true,
        );

        \register_post_type( 'marinesync-boats', $args );
        
        // Register taxonomies
        self::register_taxonomies();
        
        error_log('MS015: Post type registration complete');
    }
    
    /**
     * Register taxonomies for boat listings
     */
    public static function register_taxonomies() {
        // Register Boat Type taxonomy
        $labels = array(
            'name'                       => \_x( 'Boat Types', 'taxonomy general name', 'marinesync' ),
            'singular_name'              => \_x( 'Boat Type', 'taxonomy singular name', 'marinesync' ),
            'search_items'               => \__('Search Boat Types', 'marinesync' ),
            'popular_items'              => \__('Popular Boat Types', 'marinesync' ),
            'all_items'                  => \__('All Boat Types', 'marinesync' ),
            'parent_item'                => \__('Parent Boat Type', 'marinesync' ),
            'parent_item_colon'          => \__('Parent Boat Type:', 'marinesync' ),
            'edit_item'                  => \__('Edit Boat Type', 'marinesync' ),
            'update_item'                => \__('Update Boat Type', 'marinesync' ),
            'add_new_item'               => \__('Add New Boat Type', 'marinesync' ),
            'new_item_name'              => \__('New Boat Type Name', 'marinesync' ),
            'separate_items_with_commas' => \__('Separate boat types with commas', 'marinesync' ),
            'add_or_remove_items'        => \__('Add or remove boat types', 'marinesync' ),
            'choose_from_most_used'      => \__('Choose from the most used boat types', 'marinesync' ),
            'not_found'                  => \__('No boat types found.', 'marinesync' ),
            'menu_name'                  => \__('Boat Types', 'marinesync' ),
        );

        $args = array(
            'hierarchical'          => true,
            'labels'                => $labels,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'query_var'             => true,
            'rewrite'               => array( 'slug' => 'boat-type' ),
            'show_in_rest'          => true,
        );

        \register_taxonomy( 'boat-type', 'marinesync-boats', $args );
        
        // Register Manufacturer taxonomy
        $labels = array(
            'name'                       => \_x( 'Manufacturers', 'taxonomy general name', 'marinesync' ),
            'singular_name'              => \_x( 'Manufacturer', 'taxonomy singular name', 'marinesync' ),
            'search_items'               => \__('Search Manufacturers', 'marinesync' ),
            'popular_items'              => \__('Popular Manufacturers', 'marinesync' ),
            'all_items'                  => \__('All Manufacturers', 'marinesync' ),
            'parent_item'                => \__('Parent Manufacturer', 'marinesync' ),
            'parent_item_colon'          => \__('Parent Manufacturer:', 'marinesync' ),
            'edit_item'                  => \__('Edit Manufacturer', 'marinesync' ),
            'update_item'                => \__('Update Manufacturer', 'marinesync' ),
            'add_new_item'               => \__('Add New Manufacturer', 'marinesync' ),
            'new_item_name'              => \__('New Manufacturer Name', 'marinesync' ),
            'separate_items_with_commas' => \__('Separate manufacturers with commas', 'marinesync' ),
            'add_or_remove_items'        => \__('Add or remove manufacturers', 'marinesync' ),
            'choose_from_most_used'      => \__('Choose from the most used manufacturers', 'marinesync' ),
            'not_found'                  => \__('No manufacturers found.', 'marinesync' ),
            'menu_name'                  => \__('Manufacturers', 'marinesync' ),
        );

        $args = array(
            'hierarchical'          => true,
            'labels'                => $labels,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'query_var'             => true,
            'rewrite'               => array( 'slug' => 'manufacturer' ),
            'show_in_rest'          => true,
        );

        \register_taxonomy( 'manufacturer', 'marinesync-boats', $args );
    }
    
    /**
     * Helper methods for boat data
     */
    
    /**
     * Get boat ID
     */
    public static function get_boat_id($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        return \get_post_meta($post_id, 'boat_id', true);
    }
    
    /**
     * Get boat price
     */
    public static function get_asking_price($post_id = null) {
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
            $price = \get_post_meta($post_id, 'price', true);
        }
        
        return $price;
    }
    
    /**
     * Get boat name
     */
    public static function get_boat_name($post_id = null) {
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
            $name = \get_post_meta($post_id, 'name', true);
        }
        
        // If still empty, use the post title
        if (empty($name)) {
            $name = get_the_title($post_id);
        }
        
        return $name;
    }
    
    /**
     * Get boat manufacturer
     */
    public static function get_manufacturer($post_id = null) {
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
            $manufacturer = \get_post_meta($post_id, 'manufacturer', true);
        }
        
        return $manufacturer;
    }
    
    /**
     * Get boat model
     */
    public static function get_model($post_id = null) {
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
            $model = \get_post_meta($post_id, 'model', true);
        }
        
        return $model;
    }
    
    /**
     * Get boat year
     */
    public static function get_year($post_id = null) {
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
            $year = \get_post_meta($post_id, 'year', true);
        }
        
        return $year;
    }
    
    /**
     * Get boat location
     */
    public static function get_location($post_id = null) {
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
            $location = \get_post_meta($post_id, 'vessel_lying', true);
        }
        
        return $location;
    }
    
    /**
     * Get boat dimensions (LOA, Beam, Draft)
     */
    public static function get_dimensions($post_id = null) {
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
            $dimensions['loa'] = \get_post_meta($post_id, 'loa', true);
        }
        
        if (empty($dimensions['beam'])) {
            $dimensions['beam'] = \get_post_meta($post_id, 'beam', true);
        }
        
        if (empty($dimensions['draft'])) {
            $dimensions['draft'] = \get_post_meta($post_id, 'draft', true);
        }
        
        return $dimensions;
    }
    
    /**
     * Get a generic boat field with fallback
     */
    public static function get_boat_field($field_name, $post_id = null) {
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
            $value = \get_post_meta($post_id, $field_name, true);
        }
        
        return $value;
    }
}