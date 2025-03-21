<?php
/**
 * Plugin Name: MarineSync
 * Description: A plugin to sync down boat listings from various providers.
 * Version: 1.0
 * Author: Hampshire Web Design
 * Author URI: https://hampshirewebdesign.net
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: marinesync
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Tested up to: 6.7.2
 * WP-Plugin-Repository: wp-marinesync
 * GitHub Plugin URI: https://github.com/frankiewest/wp-marinesync
 * GitHub Branch: main
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define constants for the plugin
if ( ! defined( 'MARINESYNC_PLUGIN_DIR' ) ) {
    define( 'MARINESYNC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'MARINESYNC_PLUGIN_URL' ) ) {
    define( 'MARINESYNC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'MARINESYNC_PLUGIN_VERSION' ) ) {
    define( 'MARINESYNC_PLUGIN_VERSION', '1.0' );
}

// Include required files
require_once MARINESYNC_PLUGIN_DIR . 'includes/acf-add-boat-data.php';
require_once MARINESYNC_PLUGIN_DIR . 'includes/class-marinesync-post-type.php';

// Check for ACF dependency
function marinesync_check_acf_dependency() {
    error_log('MS001: Checking ACF dependency');
    if (!class_exists('ACF')) {
        error_log('MS002: ACF not found - displaying warning notice');
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php _e('MarineSync requires Advanced Custom Fields (ACF) to be installed and activated. Please <a href="' . admin_url('plugin-install.php?tab=plugin-information&plugin=advanced-custom-fields&TB_iframe=true&width=600&height=550') . '">install ACF</a> to use this plugin.', 'marinesync'); ?></p>
            </div>
            <?php
        });
        
        error_log('MS003: Disabling activate link for MarineSync');
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
            unset($links['activate']);
            return $links;
        });
        
        return false;
    }
    error_log('MS004: ACF dependency check passed');
    return true;
}

// Define activation and deactivation hooks
function marinesync_activate() {
    error_log('MS005: Starting MarineSync activation');
    
    // Check for ACF dependency
    if (!marinesync_check_acf_dependency()) {
        error_log('MS006: ACF dependency check failed - deactivating plugin');
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('MarineSync requires Advanced Custom Fields (ACF) to be installed and activated. Please install ACF and try again.', 'marinesync'));
    }
    
    error_log('MS007: Registering post type');
    MarineSync_Post_Type::register();
    
    error_log('MS008: Attempting to add ACF fields');
    try {
        if (!class_exists('Acf_add_boat_data')) {
            error_log('MS008a: Acf_add_boat_data class not found');
            return;
        }
        
        if (!method_exists('Acf_add_boat_data', 'add_boat_data')) {
            error_log('MS008b: add_boat_data method not found');
            return;
        }
        
        $result = Acf_add_boat_data::add_boat_data();
        error_log('MS008c: ACF fields addition result: ' . ($result ? 'success' : 'failed'));
    } catch (Exception $e) {
        error_log('MS008d: Error adding ACF fields: ' . $e->getMessage());
    }
    
    error_log('MS009: Flushing rewrite rules');
    flush_rewrite_rules();
    
    error_log('MS010: MarineSync activation completed');
}
register_activation_hook( __FILE__, 'marinesync_activate' );

function marinesync_deactivate() {
    error_log('MS011: Starting MarineSync deactivation');
    flush_rewrite_rules();
    error_log('MS012: MarineSync deactivation completed');
}
register_deactivation_hook( __FILE__, 'marinesync_deactivate' );

// Initialize the plugin
function marinesync_init() {
    error_log('MS013: Initializing MarineSync');
    load_plugin_textdomain( 'marinesync', false, MARINESYNC_PLUGIN_DIR . 'languages' );
    error_log('MS014: Loading text domain');
    
    MarineSync_Post_Type::register();
    error_log('MS015: Registering post type');
}
add_action( 'init', 'marinesync_init' );

// Add deactivation confirmation
function marinesync_add_deactivation_dialog() {
    error_log('MS016: Checking if deactivation dialog should be added');
    $screen = get_current_screen();
    if ($screen && $screen->id == 'plugins') {
        error_log('MS017: Adding deactivation dialog to plugins page');
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Find the MarineSync deactivation link
                $('tr[data-slug="wpmarinesync"] .deactivate a').click(function(e) {
                    error_log('MS018: Deactivation link clicked');
                    e.preventDefault();
                    
                    var deactivateURL = $(this).attr('href');
                    
                    // Create and show modal
                    var modalHtml = `
                        <div id="marinesync-deactivate-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999999;">
                            <div style="position: relative; margin: 100px auto; width: 400px; background: #fff; padding: 20px; border-radius: 5px;">
                                <h2>Deactivate MarineSync</h2>
                                <p>Would you like to export your boat listings before deactivating?</p>
                                <p><strong>Warning:</strong> If you choose to delete data, all boat listings will be permanently removed.</p>
                                <div style="text-align: right; margin-top: 20px;">
                                    <button id="marinesync-export-deactivate" class="button button-primary" style="margin-right: 10px;">Export & Deactivate</button>
                                    <button id="marinesync-delete-deactivate" class="button" style="margin-right: 10px;">Delete Data & Deactivate</button>
                                    <button id="marinesync-just-deactivate" class="button">Just Deactivate</button>
                                    <button id="marinesync-cancel" class="button" style="margin-left: 10px;">Cancel</button>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    $('body').append(modalHtml);
                    $('#marinesync-deactivate-modal').show();
                    error_log('MS019: Deactivation modal displayed');
                    
                    // Handle Export & Deactivate
                    $('#marinesync-export-deactivate').click(function() {
                        error_log('MS020: Export & Deactivate button clicked');
                        window.location.href = ajaxurl + '?action=marinesync_export_boats&then=deactivate&redirect=' + encodeURIComponent(deactivateURL);
                    });
                    
                    // Handle Delete & Deactivate
                    $('#marinesync-delete-deactivate').click(function() {
                        error_log('MS021: Delete & Deactivate button clicked');
                        if (confirm('Are you sure? This will permanently delete all your boat listings!')) {
                            $.post(ajaxurl, {
                                action: 'marinesync_delete_data'
                            }).done(function() {
                                error_log('MS022: Data deletion completed, proceeding with deactivation');
                                window.location.href = deactivateURL;
                            });
                        }
                    });
                    
                    // Handle Just Deactivate
                    $('#marinesync-just-deactivate').click(function() {
                        error_log('MS023: Just Deactivate button clicked');
                        window.location.href = deactivateURL;
                    });
                    
                    // Handle Cancel
                    $('#marinesync-cancel').click(function() {
                        error_log('MS024: Cancel button clicked');
                        $('#marinesync-deactivate-modal').remove();
                    });
                });
            });
        </script>
        <?php
    }
}
add_action('admin_footer', 'marinesync_add_deactivation_dialog');

// Handle AJAX actions for export and delete
function marinesync_handle_export_boats() {
    error_log('MS025: Starting boat export process');
    
    // Check for admin capabilities
    if (!current_user_can('manage_options')) {
        error_log('MS026: Unauthorized access attempt to export boats');
        wp_die('Unauthorized access');
    }
    
    global $wpdb;
    
    // Get all boat posts
    $boat_posts = get_posts(array(
        'post_type' => 'marinesync-boats',
        'numberposts' => -1,
        'post_status' => 'any',
    ));
    error_log('MS027: Found ' . count($boat_posts) . ' boat posts to export');
    
    $export_data = array();
    
    foreach ($boat_posts as $post) {
        error_log('MS028: Processing boat post ID: ' . $post->ID);
        // Get all meta for each post
        $meta = get_post_meta($post->ID);
        
        // Format the data
        $boat_data = array(
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'date' => $post->post_date,
            'status' => $post->post_status,
            'meta' => $meta
        );
        
        $export_data[] = $boat_data;
    }
    
    // Generate JSON file
    $filename = 'marinesync-export-' . date('Y-m-d') . '.json';
    error_log('MS029: Generating export file: ' . $filename);
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    
    echo json_encode($export_data, JSON_PRETTY_PRINT);
    
    // Check if we should deactivate after export
    if (isset($_GET['then']) && $_GET['then'] === 'deactivate' && isset($_GET['redirect'])) {
        error_log('MS030: Export completed, proceeding with deactivation');
        ?>
        <script type="text/javascript">
            window.location.href = <?php echo json_encode(esc_url_raw($_GET['redirect'])); ?>;
        </script>
        <?php
    }
    
    error_log('MS031: Export process completed');
    exit;
}
add_action('wp_ajax_marinesync_export_boats', 'marinesync_handle_export_boats');

function marinesync_delete_data() {
    error_log('MS032: Starting data deletion process');
    
    // Check for admin capabilities
    if (!current_user_can('manage_options')) {
        error_log('MS033: Unauthorized access attempt to delete data');
        wp_die('Unauthorized access');
    }
    
    global $wpdb;
    
    // Get all boat posts
    $boat_posts = get_posts(array(
        'post_type' => 'marinesync-boats',
        'numberposts' => -1,
        'post_status' => 'any',
    ));
    error_log('MS034: Found ' . count($boat_posts) . ' boat posts to delete');
    
    // Delete each post and its meta
    foreach ($boat_posts as $post) {
        error_log('MS035: Deleting boat post ID: ' . $post->ID);
        wp_delete_post($post->ID, true);
    }
    
    // Remove ACF field group
    if (function_exists('acf_get_field_groups')) {
        error_log('MS036: Checking for ACF field groups to delete');
        $field_groups = acf_get_field_groups(array(
            'title' => 'Boat Data'
        ));
        
        if (!empty($field_groups)) {
            error_log('MS037: Found ' . count($field_groups) . ' ACF field groups to delete');
            foreach ($field_groups as $field_group) {
                error_log('MS038: Deleting ACF field group ID: ' . $field_group['ID']);
                acf_delete_field_group($field_group['ID']);
            }
        }
    }
    
    // Clean up options
    error_log('MS039: Cleaning up plugin options');
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'marinesync_%'");
    
    error_log('MS040: Data deletion process completed successfully');
    wp_send_json_success();
    exit;
}
add_action('wp_ajax_marinesync_delete_data', 'marinesync_delete_data');

// Enqueue admin assets
function marinesync_enqueue_admin_assets($hook) {
    if ('toplevel_page_marinesync' !== $hook) {
        return;
    }

    wp_enqueue_style(
        'marinesync-admin',
        plugin_dir_url(__FILE__) . 'assets/css/admin.css',
        array(),
        MARINESYNC_VERSION
    );

    wp_enqueue_script(
        'marinesync-admin',
        plugin_dir_url(__FILE__) . 'assets/js/admin.js',
        array('jquery'),
        MARINESYNC_VERSION,
        true
    );

    wp_localize_script('marinesync-admin', 'marinesyncAdmin', array(
        'nonce' => wp_create_nonce('marinesync_admin_nonce'),
        'ajaxurl' => admin_url('admin-ajax.php')
    ));
}
add_action('admin_enqueue_scripts', 'marinesync_enqueue_admin_assets');

// Initialize admin interface
require_once plugin_dir_path(__FILE__) . 'admin/admin-page.php';
MarineSync_Admin_Page::get_instance();