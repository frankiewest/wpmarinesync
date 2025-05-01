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

namespace MarineSync;

use MarineSync\PostType\MarineSync_Post_Type;

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MARINESYNC_PLUGIN_VERSION', '1.0.1');
define('MARINESYNC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MARINESYNC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load product updater
if (file_exists(MARINESYNC_PLUGIN_DIR . 'product-updater.php')) {
    require_once MARINESYNC_PLUGIN_DIR . 'product-updater.php';
}

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    // Project-specific namespace prefix
    $prefix = 'MarineSync\\';
    
    // Base directory for the namespace prefix
    $base_dir = MARINESYNC_PLUGIN_DIR . 'includes/';
    
    // Check if the class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Get the relative class name
    $relative_class = substr($class, $len);
    
    // Convert the relative class name to a file path
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    error_log('MS050: Attempting to load class file: ' . $file);
    
    // If the file exists, require it
    if (file_exists($file)) {
        error_log('MS051: Found class file: ' . $file);
        require $file;
        if (class_exists($class)) {
            error_log('MS052: Successfully loaded class: ' . $class);
        } else {
            error_log('MS053: Class not found after loading file: ' . $class);
        }
    } else {
        error_log('MS054: Class file not found: ' . $file);
    }
});

// Add shortcode for ms_field
add_shortcode('ms_field', ['\\MarineSync\\PostType\\MarineSync_Post_Type', 'marinesync_shortcode']);

// Add shortcode for custom boat search
add_shortcode('ms_custom_search', ['\\MarineSync\\Search\\MarineSync_Search', 'render_search_form']);

// Check for ACF dependency
function marinesync_check_acf() {
    if (!class_exists('ACF')) {
        \add_action('admin_notices', function() {
            echo '<div class="error"><p>' . \__('MarineSync requires Advanced Custom Fields PRO to be installed and activated.', 'marinesync') . '</p></div>';
        });
        return false;
    }
    return true;
}

// Activation hook
function marinesync_activate() {
    error_log('MS001: Starting plugin activation');
    
    if (!marinesync_check_acf()) {
        error_log('MS002: ACF not found, activation aborted');
        return;
    }
    
    error_log('MS003: ACF found, proceeding with activation');
    
    // Register post type
    PostType\MarineSync_Post_Type::register();
    error_log('MS004: Post type registered');

	add_action('acf/init', ['\MarineSync\ACF\Acf_add_boat_data', 'add_boat_data']);
	add_action('acf/init', ['\MarineSync\ACF\Acf_add_boat_data', 'add_options_page']);
	error_log('MS005: ACF fields added directly');
    
    // Flush rewrite rules
    \flush_rewrite_rules();
    error_log('MS007: Rewrite rules flushed');
    
    error_log('MS008: Plugin activation complete');
}

// Deactivation hook
function marinesync_deactivate() {
    error_log('MS009: Starting plugin deactivation');
    
    // Clear scheduled hooks
    \wp_clear_scheduled_hook('marinesync_process_feed');
    error_log('MS010: Scheduled hooks cleared');
    
    // Flush rewrite rules
    \flush_rewrite_rules();
    error_log('MS011: Rewrite rules flushed');
    
    error_log('MS012: Plugin deactivation complete');
}

// Initialize plugin
function marinesync_init() {
    error_log('MS013: Starting plugin initialization');
    
    // Register post type
    PostType\MarineSync_Post_Type::register();
    
    error_log('MS014: Plugin initialization complete');
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, __NAMESPACE__ . '\\marinesync_activate');
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\\marinesync_deactivate');

// Initialize plugin
add_action('init', __NAMESPACE__ . '\\marinesync_init');

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
add_action('admin_footer', 'MarineSync\\marinesync_add_deactivation_dialog');

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
add_action('wp_ajax_marinesync_delete_data', 'MarineSync\\marinesync_delete_data');

function marinesync_register_acf_fields() {
	if (function_exists('acf_add_local_field_group')) {
		error_log('MS200: Registering ACF fields via global hook');
		\MarineSync\ACF\Acf_add_boat_data::add_boat_data();
		\MarineSync\ACF\Acf_add_boat_data::add_options_page();
	}
}
add_action('acf/init', 'MarineSync\\marinesync_register_acf_fields');

// Initialize admin interface
require_once plugin_dir_path(__FILE__) . 'admin/admin-page.php';
MarineSync_Admin_Page::get_instance();

// Initialise functions.php
require_once plugin_dir_path(__FILE__) . 'includes/functions.php';
Functions_MarineSync::get_instance();

// CSV Import/Export handlers
function marinesync_handle_csv_import() {
	if (!current_user_can('manage_options')) {
		return;
	}

	if (isset($_FILES['marinesync_csv_import']) && isset($_POST['marinesync_import_nonce'])) {
		if (wp_verify_nonce($_POST['marinesync_import_nonce'], 'marinesync_import_action')) {
			$uploaded_file = $_FILES['marinesync_csv_import']['tmp_name'];
			Importer\BoatImporter::process_csv($uploaded_file);
		}
	}
}
add_action('admin_init', 'MarineSync\\marinesync_handle_csv_import');

function marinesync_handle_csv_template_download() {
	if (!current_user_can('manage_options') || !isset($_GET['marinesync_download_template'])) {
		return;
	}

	$template_path = Importer\BoatImporter::generate_csv_template();
	if ($template_path && file_exists($template_path)) {
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="boat_import_template.csv"');
		header('Pragma: no-cache');
		header('Expires: 0');
		readfile($template_path);
		unlink($template_path);
		exit;
	}
}
add_action('admin_init', 'MarineSync\\marinesync_handle_csv_template_download');