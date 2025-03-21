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
    if (!class_exists('ACF')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php _e('MarineSync requires Advanced Custom Fields (ACF) to be installed and activated. Please <a href="' . admin_url('plugin-install.php?tab=plugin-information&plugin=advanced-custom-fields&TB_iframe=true&width=600&height=550') . '">install ACF</a> to use this plugin.', 'marinesync'); ?></p>
            </div>
            <?php
        });
        
        // Disable the activate link
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
            unset($links['activate']);
            return $links;
        });
        
        return false;
    }
    return true;
}

// Define activation and deactivation hooks
function marinesync_activate() {
    // Check for ACF dependency
    if (!marinesync_check_acf_dependency()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('MarineSync requires Advanced Custom Fields (ACF) to be installed and activated. Please install ACF and try again.', 'marinesync'));
    }
    
    // Register post type on activation to enable permalink flushing
    MarineSync_Post_Type::register();
    
    // Add ACF fields
    Acf_add_boat_data::add_boat_data();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'marinesync_activate' );

function marinesync_deactivate() {
    // The actual cleanup is handled via admin-ajax
    // This function now only flushes rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'marinesync_deactivate' );

// Initialize the plugin
function marinesync_init() {
    // Load text domain for translations
    load_plugin_textdomain( 'marinesync', false, MARINESYNC_PLUGIN_DIR . 'languages' );
    
    // Register the custom post type
    MarineSync_Post_Type::register();
}
add_action( 'init', 'marinesync_init' );

// Add deactivation confirmation
function marinesync_add_deactivation_dialog() {
    // Only add the script on the plugins page
    $screen = get_current_screen();
    if ($screen && $screen->id == 'plugins') {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Find the MarineSync deactivation link
                $('tr[data-slug="wpmarinesync"] .deactivate a').click(function(e) {
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
                    
                    // Handle Export & Deactivate
                    $('#marinesync-export-deactivate').click(function() {
                        window.location.href = ajaxurl + '?action=marinesync_export_boats&then=deactivate&redirect=' + encodeURIComponent(deactivateURL);
                    });
                    
                    // Handle Delete & Deactivate
                    $('#marinesync-delete-deactivate').click(function() {
                        if (confirm('Are you sure? This will permanently delete all your boat listings!')) {
                            $.post(ajaxurl, {
                                action: 'marinesync_delete_data'
                            }).done(function() {
                                window.location.href = deactivateURL;
                            });
                        }
                    });
                    
                    // Handle Just Deactivate
                    $('#marinesync-just-deactivate').click(function() {
                        window.location.href = deactivateURL;
                    });
                    
                    // Handle Cancel
                    $('#marinesync-cancel').click(function() {
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
    // Check for admin capabilities
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    global $wpdb;
    
    // Get all boat posts
    $boat_posts = get_posts(array(
        'post_type' => 'marinesync-boats',
        'numberposts' => -1,
        'post_status' => 'any',
    ));
    
    $export_data = array();
    
    foreach ($boat_posts as $post) {
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
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    
    echo json_encode($export_data, JSON_PRETTY_PRINT);
    
    // Check if we should deactivate after export
    if (isset($_GET['then']) && $_GET['then'] === 'deactivate' && isset($_GET['redirect'])) {
        ?>
        <script type="text/javascript">
            window.location.href = <?php echo json_encode(esc_url_raw($_GET['redirect'])); ?>;
        </script>
        <?php
    }
    
    exit;
}
add_action('wp_ajax_marinesync_export_boats', 'marinesync_handle_export_boats');

function marinesync_delete_data() {
    // Check for admin capabilities
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    global $wpdb;
    
    // Get all boat posts
    $boat_posts = get_posts(array(
        'post_type' => 'marinesync-boats',
        'numberposts' => -1,
        'post_status' => 'any',
    ));
    
    // Delete each post and its meta
    foreach ($boat_posts as $post) {
        wp_delete_post($post->ID, true);
    }
    
    // Remove ACF field group
    if (function_exists('acf_get_field_groups')) {
        $field_groups = acf_get_field_groups(array(
            'title' => 'Boat Data'
        ));
        
        if (!empty($field_groups)) {
            foreach ($field_groups as $field_group) {
                acf_delete_field_group($field_group['ID']);
            }
        }
    }
    
    // Clean up options
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'marinesync_%'");
    
    wp_send_json_success();
    exit;
}
add_action('wp_ajax_marinesync_delete_data', 'marinesync_delete_data');