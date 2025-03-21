<?php
namespace MarineSync\PostType;

/**
 * MarineSync Post Type Registration and Helper Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

class MarineSync_Post_Type {
    public static function register() {
        error_log('MS014: Starting post type registration');
        
        $labels = array(
            'name'               => \_x('Boats', 'post type general name', 'marinesync'),
            'singular_name'      => \_x('Boat', 'post type singular name', 'marinesync'),
            'menu_name'          => \_x('Boats', 'admin menu', 'marinesync'),
            'name_admin_bar'     => \_x('Boat', 'add new on admin bar', 'marinesync'),
            'add_new'            => \_x('Add New', 'boat', 'marinesync'),
            'add_new_item'       => \__('Add New Boat', 'marinesync'),
            'new_item'           => \__('New Boat', 'marinesync'),
            'edit_item'          => \__('Edit Boat', 'marinesync'),
            'view_item'          => \__('View Boat', 'marinesync'),
            'all_items'          => \__('All Boats', 'marinesync'),
            'search_items'       => \__('Search Boats', 'marinesync'),
            'parent_item_colon'  => \__('Parent Boats:', 'marinesync'),
            'not_found'          => \__('No boats found.', 'marinesync'),
            'not_found_in_trash' => \__('No boats found in Trash.', 'marinesync'),
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
        
        \register_post_type('marinesync-boats', $args);
        
        // Register boat type taxonomy
        $type_labels = array(
            'name'              => \_x('Boat Types', 'taxonomy general name', 'marinesync'),
            'singular_name'     => \_x('Boat Type', 'taxonomy singular name', 'marinesync'),
            'search_items'      => \__('Search Boat Types', 'marinesync'),
            'all_items'         => \__('All Boat Types', 'marinesync'),
            'parent_item'       => \__('Parent Boat Type', 'marinesync'),
            'parent_item_colon' => \__('Parent Boat Type:', 'marinesync'),
            'edit_item'         => \__('Edit Boat Type', 'marinesync'),
            'update_item'       => \__('Update Boat Type', 'marinesync'),
            'add_new_item'      => \__('Add New Boat Type', 'marinesync'),
            'new_item_name'     => \__('New Boat Type Name', 'marinesync'),
            'menu_name'         => \__('Boat Types', 'marinesync'),
        );
        
        \register_taxonomy('boat-type', array('marinesync-boats'), array(
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
            'name'              => \_x('Manufacturers', 'taxonomy general name', 'marinesync'),
            'singular_name'     => \_x('Manufacturer', 'taxonomy singular name', 'marinesync'),
            'search_items'      => \__('Search Manufacturers', 'marinesync'),
            'all_items'         => \__('All Manufacturers', 'marinesync'),
            'parent_item'       => \__('Parent Manufacturer', 'marinesync'),
            'parent_item_colon' => \__('Parent Manufacturer:', 'marinesync'),
            'edit_item'         => \__('Edit Manufacturer', 'marinesync'),
            'update_item'       => \__('Update Manufacturer', 'marinesync'),
            'add_new_item'      => \__('Add New Manufacturer', 'marinesync'),
            'new_item_name'     => \__('New Manufacturer Name', 'marinesync'),
            'menu_name'         => \__('Manufacturers', 'marinesync'),
        );
        
        \register_taxonomy('manufacturer', array('marinesync-boats'), array(
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
    
    public static function get_boat_id($post_id = null) {
        if (!$post_id) {
            $post_id = \get_the_ID();
        }
        return \get_post_meta($post_id, 'boat_id', true);
    }
    
    public static function get_asking_price($post_id = null) {
        if (!$post_id) {
            $post_id = \get_the_ID();
        }
        
        $price = '';
        
        // Try ACF first
        if (function_exists('get_field')) {
            $price = \get_field('asking_price', $post_id);
        }
        
        // Fallback to post meta
        if (empty($price)) {
            $price = \get_post_meta($post_id, 'price', true);
        }
        
        return $price;
    }
    
    public static function get_boat_name($post_id = null) {
        if (!$post_id) {
            $post_id = \get_the_ID();
        }
        
        $name = '';
        
        // Try ACF first
        if (function_exists('get_field')) {
            $name = \get_field('boat_name', $post_id);
        }
        
        // Fallback to post meta
        if (empty($name)) {
            $name = \get_post_meta($post_id, 'name', true);
        }
        
        // If still empty, use the post title
        if (empty($name)) {
            $name = \get_the_title($post_id);
        }
        
        return $name;
    }
    
    public static function get_manufacturer($post_id = null) {
        if (!$post_id) {
            $post_id = \get_the_ID();
        }
        
        $manufacturer = '';
        
        // Try ACF first
        if (function_exists('get_field')) {
            $manufacturer = \get_field('manufacturer', $post_id);
        }
        
        // Fallback to post meta
        if (empty($manufacturer)) {
            $manufacturer = \get_post_meta($post_id, 'manufacturer', true);
        }
        
        return $manufacturer;
    }
    
    public static function get_model($post_id = null) {
        if (!$post_id) {
            $post_id = \get_the_ID();
        }
        
        $model = '';
        
        // Try ACF first
        if (function_exists('get_field')) {
            $model = \get_field('model', $post_id);
        }
        
        // Fallback to post meta
        if (empty($model)) {
            $model = \get_post_meta($post_id, 'model', true);
        }
        
        return $model;
    }
    
    public static function get_year($post_id = null) {
        if (!$post_id) {
            $post_id = \get_the_ID();
        }
        
        $year = '';
        
        // Try ACF first
        if (function_exists('get_field')) {
            $year = \get_field('year', $post_id);
        }
        
        // Fallback to post meta
        if (empty($year)) {
            $year = \get_post_meta($post_id, 'year', true);
        }
        
        return $year;
    }
    
    public static function get_location($post_id = null) {
        if (!$post_id) {
            $post_id = \get_the_ID();
        }
        
        $location = '';
        
        // Try ACF first
        if (function_exists('get_field')) {
            $location = \get_field('vessel_lying', $post_id);
        }
        
        // Fallback to post meta
        if (empty($location)) {
            $location = \get_post_meta($post_id, 'vessel_lying', true);
        }
        
        return $location;
    }
    
    public static function get_dimensions($post_id = null) {
        if (!$post_id) {
            $post_id = \get_the_ID();
        }
        
        $dimensions = array(
            'loa' => '',
            'beam' => '',
            'draft' => ''
        );
        
        // Try ACF first
        if (function_exists('get_field')) {
            $dimensions['loa'] = \get_field('loa', $post_id);
            $dimensions['beam'] = \get_field('beam', $post_id);
            $dimensions['draft'] = \get_field('draft', $post_id);
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
    
    public static function get_boat_field($field_name, $post_id = null) {
        if (!$post_id) {
            $post_id = \get_the_ID();
        }
        
        $value = '';
        
        // Try ACF first
        if (function_exists('get_field')) {
            $value = \get_field($field_name, $post_id);
        }
        
        // Fallback to post meta
        if (empty($value)) {
            $value = \get_post_meta($post_id, $field_name, true);
        }
        
        return $value;
    }
}