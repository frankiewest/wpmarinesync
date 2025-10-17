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
	add_action('acf/init', ['\MarineSync\ACF\Acf_add_boat_data', 'add_boat_data_template']);
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
		\MarineSync\ACF\Acf_add_boat_data::add_boat_data_template();
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

// Add role
function marinesync_register_boat_admin_role_and_admin_caps() {
	// 1. Add or update Boat Admin role
	$role = get_role('boat_admin');
	if (!$role) {
		add_role('boat_admin', 'Boat Admin', ['read' => true]);
		$role = get_role('boat_admin');
	}

	$caps = [
		// Boat CPT caps
		'edit_boat',
		'read_boat',
		'delete_boat',
		'edit_boats',
		'edit_others_boats',
		'publish_boats',
		'read_private_boats',
		'delete_boats',
		'delete_others_boats',
		'edit_published_boats',
		'delete_published_boats',

		// Taxonomies: boat-cat
		'manage_boat-cat',
		'edit_boat-cat',
		'delete_boat-cat',
		'assign_boat-cat',

		// Taxonomies: boat-feature-cat
		'manage_boat-feature-cat',
		'edit_boat-feature-cat',
		'delete_boat-feature-cat',
		'assign_boat-feature-cat',

		// Taxonomies: boat-status
		'manage_boat-status',
		'edit_boat-status',
		'delete_boat-status',
		'assign_boat-status',

		// Taxonomies: boat-condition
		'manage_boat-condition',
		'edit_boat-condition',
		'delete_boat-condition',
		'assign_boat-condition',

		// Taxonomies: boat-type
		'manage_boat-type',
		'edit_boat-type',
		'delete_boat-type',
		'assign_boat-type',

		// Taxonomies: manufacturer
		'manage_manufacturer',
		'edit_manufacturer',
		'delete_manufacturer',
		'assign_manufacturer',

		// Taxonomies: designer
		'manage_designer',
		'edit_designer',
		'delete_designer',
		'assign_designer',

		// Posts
		'edit_posts',
		'edit_others_posts',
		'publish_posts',
		'delete_posts',
		'delete_others_posts',
		'edit_published_posts',
		'delete_published_posts',

		// Pages
		'edit_pages',
		'edit_others_pages',
		'publish_pages',
		'delete_pages',
		'delete_others_pages',
		'edit_published_pages',
		'delete_published_pages',

		// Media
		'upload_files',
	];

	foreach ($caps as $cap) {
		$role->add_cap($cap);
	}

	// 2. Also add these capabilities to the Administrator role
	$admin = get_role('administrator');
	if ($admin) {
		foreach ($caps as $cap) {
			$admin->add_cap($cap);
		}
	}
}
add_action('init', __NAMESPACE__ . '\\marinesync_register_boat_admin_role_and_admin_caps');

add_action('restrict_manage_posts', function($post_type) {
	if ($post_type !== 'marinesync-boats') return;

	$terms = get_terms([
		'taxonomy' => 'boat-status',
		'hide_empty' => false,
	]);
	if (empty($terms) || is_wp_error($terms)) return;

	// Output buttons after the tabs but before bulk actions
	echo '<div style="margin: 12px 0 8px; display: flex; gap: 10px;">';
    echo '<style>
        .alignleft {
            float: unset;
        }</style>';
	foreach ($terms as $term) {
		$url = admin_url('edit.php?boat-status=' . $term->slug . '&post_type=marinesync-boats');
		$current = isset($_GET['boat-status']) && $_GET['boat-status'] === $term->slug;
		echo '<a href="' . esc_url($url) . '" class="button' . ($current ? ' button-primary' : '') . '">' . esc_html($term->name) . '</a>';
	}
	echo '</div>';
}, 10, 1);

add_filter('manage_marinesync-boats_posts_columns', function($columns) {
	// Build columns in desired order: after 'title' put 'boat_ref', then 'loa', then 'featured_boat'
	$new_columns = [];
	foreach ($columns as $key => $label) {
		$new_columns[$key] = $label;
		if ($key === 'title') {
			$new_columns['loa'] = __('Length (ft)', 'marinesync');
			$new_columns['year'] = __('Year', 'marinesync');
			$new_columns['featured_boat'] = __('Featured', 'marinesync');
			$new_columns['boat_ref'] = __('Reference', 'marinesync');
        }
	}
	return $new_columns;
});

add_action('manage_marinesync-boats_posts_custom_column', function($column, $post_id) {
	switch ($column) {
		case 'boat_ref':
			echo esc_html(get_field('boat_ref', $post_id));
			break;
		case 'loa':
			$length = get_field('loa', $post_id);
			if ($length) echo esc_html($length);
			break;
		case 'year':
			$length = get_field('year', $post_id);
			if ($length) echo esc_html($length);
			break;
		case 'featured_boat':
			echo has_term('featured', 'boat-cat', $post_id) ? '<strong>Featured</strong>' : 'No';
			break;
	}
}, 10, 2);

add_filter('manage_edit-marinesync-boats_sortable_columns', function($columns) {
	$columns['loa'] = 'loa';
	$columns['year'] = 'year';
	$columns['featured_boat'] = 'featured_boat';
	$columns['boat_ref'] = 'boat_ref';
	return $columns;
});


add_action('admin_menu', function() {
	// Only add submenu if CPT exists
	add_submenu_page(
		'edit.php?post_type=marinesync-boats',
		'Active Boats',
		'Active',
		'edit_posts',
		'active-boats',
		function() {
			// Redirect to the main list filtered by 'active' boat-status
			$url = admin_url('edit.php?post_type=marinesync-boats&boat-status=active');
			echo '<script>window.location.href = "' . esc_url($url) . '";</script>';
			exit;
		}
	);
});

// Add [marinesync_pdf_button] shortcode
add_shortcode('marinesync_pdf_button', function($atts) {
	$atts = shortcode_atts([
		'id' => get_the_ID(),
		'label' => 'Download PDF',
		'class' => 'w-btn us-btn-style_1 icon_atright marinesync-pdf-btn'
	], $atts, 'marinesync_pdf_button');
	$post_id = intval($atts['id']);
	$label = esc_html($atts['label']);
	$class = esc_attr($atts['class']);

	// The endpoint URL for the PDF (see next section)
	$pdf_url = esc_url(add_query_arg([
		'marinesync_pdf' => $post_id
	], home_url('/')));
	return "<div class='w-btn-wrapper align_center'>
            <a href='{$pdf_url}' class='{$class}' target='_blank' rel='noopener'>{$label}</a>
            </div>";
});

/**
 * Add custom columns to marinesync-boats post type admin screen
 */
// Add custom columns to the marinesync-boats post type admin screen
add_filter('manage_marinesync-boats_posts_columns', function($columns) {
	$new_columns = [];
	// Keep existing columns up to 'title'
	foreach ($columns as $key => $value) {
		$new_columns[$key] = $value;
		if ($key === 'title') {
			// Insert custom columns after title
			$new_columns['boat_name'] = __('Boat Name', 'marinesync');
			$new_columns['vessel_lying'] = __('Vessel Lying', 'marinesync');
			$new_columns['asking_price'] = __('Asking Price', 'marinesync');
		}
	}
	return $new_columns;
});

// Display data in custom columns
add_action('manage_marinesync-boats_posts_custom_column', function($column_name, $post_id) {
	switch ($column_name) {
		case 'boat_name':
			echo MarineSync_Post_Type::get_boat_name($post_id);
			break;
		case 'vessel_lying':
			echo MarineSync_Post_Type::get_location($post_id);
			break;
		case 'asking_price':
			$price = MarineSync_Post_Type::get_asking_price($post_id);
			$currency = MarineSync_Post_Type::get_boat_field('currency', $post_id);
			echo esc_html($price ? $currency . number_format($price, 2) : '');
			break;
	}
}, 10, 2);

// Make columns sortable
add_filter('manage_edit-marinesync-boats_sortable_columns', function($columns) {
	$columns['boat_name'] = 'boat_name';
	$columns['vessel_lying'] = 'vessel_lying';
	$columns['asking_price'] = 'asking_price';
	return $columns;
});

add_action('init', function() {
	if (!empty($_GET['marinesync_pdf']) && is_numeric($_GET['marinesync_pdf'])) {
		$post_id = intval($_GET['marinesync_pdf']);
		// Safety check: optionally verify post type
		if (get_post_type($post_id) === 'marinesync-boats') {
			$pdf = new \MarineSync\PDF\MarineSync_PDF($post_id);
			$pdf->generatePdf();
			exit;
		} else {
			wp_die('Invalid or missing Boat ID.');
		}
	}
});

add_shortcode('marinesync_video', function($atts) {
	$atts = shortcode_atts([
		'id' => get_the_ID(),
		'index' => 1,
		'field' => 'videos',
		'subfield' => 'video_url',
		'class' => 'marinesync-video-embed'
	], $atts, 'marinesync_video');

	$post_id = intval($atts['id']);
	$index = max(1, intval($atts['index']));
	$field = $atts['field'];
	$subfield = $atts['subfield'];
	$class = esc_attr($atts['class']);

	if (!$post_id) return '';

	$videos = get_field($field, $post_id);
	if (empty($videos) || !is_array($videos)) return '';

	$video_index = $index - 1;
	if (empty($videos[$video_index][$subfield])) return '';

	$url = trim($videos[$video_index][$subfield]);
	$youtube_id = null;

	// YouTube ID extraction (matches youtu.be and youtube.com/watch?v=)
	if (preg_match('~(?:youtube\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([A-Za-z0-9_-]{11})~i', $url, $matches)) {
		$youtube_id = $matches[1];
	}

	if ($youtube_id) {
		$embed_url = "https://www.youtube.com/embed/" . esc_attr($youtube_id);
		return '<div class="' . $class . '" style="max-width:640px;margin:auto;"><iframe width="100%" height="360" src="' . esc_url($embed_url) . '" frameborder="0" allowfullscreen></iframe></div>';
	} else {
		// Fallback to HTML5 <video> tag
		return '<div class="' . $class . '" style="max-width:640px;margin:auto;"><video width="100%" height="360" controls preload="metadata"><source src="' . esc_url($url) . '" type="video/mp4">Your browser does not support the video tag.</video></div>';
	}
});

add_shortcode('enquire_button', function($atts) {
    $atts = shortcode_atts([
            'id' => get_the_ID(),
            'office_id' => ''
    ], $atts); //

    if(is_admin()) return null;

    $boat = get_post($atts['id']);

    $office_id = get_field('office_id', $boat);

    return '
    <div class="w-btn-wrapper align_center">
        <a class="w-btn us-btn-style_1 icon_atright" href="/contact-us?boat=' . urlencode($boat->post_title) .' &boat_id=' . $boat->ID . '&office_id=' . esc_attr($office_id) . '" target="_blank" rel="noopener">
            <span class="w-btn-label">Enquire</span> <i class="fas fa-paper-plane"></i>
        </a>
    </div>';
});

add_action('pre_get_posts', function($query) {
	if (!is_admin() || !$query->is_main_query()) return;
	if ($query->get('post_type') !== 'marinesync-boats') return;

	// LOG: Base pre_get_posts logic triggered
	error_log('MS100: pre_get_posts triggered');

	/* SEARCH ENHANCEMENT
	if (!empty($_GET['s'])) {
		error_log('MS106: Search enhancement triggered for marinesync-boats');

		// JOIN boat_name
		add_filter('posts_join', function($join) {
			global $wpdb;
			error_log('MS107: posts_join filter running');

			$join .= " LEFT JOIN {$wpdb->postmeta} AS mt1 ON ({$wpdb->posts}.ID = mt1.post_id AND mt1.meta_key = 'boat_name')";
			return $join;
		}, 5);

		// WHERE using CONCAT_WS
		add_filter('posts_where', function($where) {
			global $wpdb;
			error_log('MS111: posts_where filter running');

			$search = esc_sql($wpdb->esc_like($_GET['s']));
			error_log('MS112: Search term = ' . $search);

			$where .= " AND (";
			$where .= " CONCAT_WS(' ', {$wpdb->posts}.post_title, mt1.meta_value) LIKE '%{$search}%'";
			$where .= ")";
			error_log('MS113: WHERE clause built (coalesced)');
			return $where;
		}, 15);

		// GROUP BY
		add_filter('posts_groupby', function($groupby) {
			global $wpdb;
			error_log('MS114: posts_groupby filter running');
			return "{$wpdb->posts}.ID";
		}, 15);
	}*/

	// SORTING
	$orderby = $query->get('orderby');
	if ($orderby === 'boat_ref' || $orderby === 'boat_name' || $orderby === 'vessel_lying') {
		$query->set('meta_key', $orderby);
		$query->set('orderby', 'meta_value');
	}
	if ($orderby === 'year' || $orderby === 'loa' || $orderby === 'asking_price') {
		$query->set('meta_key', $orderby);
		$query->set('orderby', 'meta_value_num');
	}
	if ($orderby === 'featured_boat') {
		$query->set('tax_query', [[
			'taxonomy' => 'boat-cat',
			'field'    => 'slug',
			'terms'    => 'featured',
			'operator' => 'EXISTS'
		]]);
	}

	// DEFAULT SORT
	if (!$orderby && !$query->get('order')) {
		$query->set('meta_key', 'loa');
		$query->set('orderby', 'meta_value_num');
		$query->set('order', 'DESC');
	}

	// DEFAULT STATUS FILTER
	if (!isset($_GET['boat-status']) && !isset($_GET['s']) && !isset($_GET['post_status'])) {
		$tax_query = (array) $query->get('tax_query');
		$tax_query[] = [
			'taxonomy' => 'boat-status',
			'field'    => 'slug',
			'terms'    => ['active'],
			'operator' => 'IN'
		];
		$query->set('tax_query', $tax_query);
	}
}, 15);
