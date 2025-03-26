<?php
/**
 * Admin interface for MarineSync feed settings
 */

namespace MarineSync;

use MarineSync\PostType\MarineSync_Post_Type;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class MarineSync_Admin_Page {
	private static $instance = null;
	private $options;
	private $feed_running = false;

	public static function get_instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action('admin_menu', array($this, 'add_admin_menu'));
		add_action('admin_init', array($this, 'register_settings'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

		// Register AJAX handlers
		add_action('wp_ajax_marinesync_run_feed', array($this, 'ajax_run_feed'));
		add_action('wp_ajax_marinesync_check_feed_status', array($this, 'ajax_check_feed_status'));
		add_action('wp_ajax_marinesync_export_boats', array($this, 'ajax_export_boats'));
		add_action('wp_ajax_marinesync_save_settings', array($this, 'ajax_save_settings'));

		// Always register the hook handler
		add_action('marinesync_scheduled_export', array($this, 'handle_scheduled_export'));

		// Explicitly call setup on init after everything is loaded
		add_action('init', array($this, 'setup_scheduled_exports'));

		// Initialize options
		$this->options = get_option('marinesync_feed_settings', array(
			'feed_format' => 'auto',
			'feed_url' => '',
			'feed_provider' => '',
			'feed_frequency' => 24,
			'export_feed_frequency' => 24
		));
        
        // Setup scheduled events if needed
        $this->setup_scheduled_exports();
	}

	/**
	 * Debug function to display all scheduled cron jobs
	 * Add this to your admin page for troubleshooting
	 */
	public function display_cron_debug_info() {
		$crons = _get_cron_array();
		$schedules = wp_get_schedules();
		$date_format = get_option('date_format') . ' ' . get_option('time_format');

		echo '<div class="marinesync-card">';
		echo '<h3>' . __('Cron Debug Information', 'marinesync') . '</h3>';

		if (empty($crons)) {
			echo '<p>' . __('No cron events scheduled.', 'marinesync') . '</p>';
		} else {
			echo '<table class="widefat">';
			echo '<thead><tr><th>Hook</th><th>Arguments</th><th>Schedule</th><th>Next Run</th></tr></thead>';
			echo '<tbody>';

			foreach ($crons as $timestamp => $cronhooks) {
				foreach ($cronhooks as $hook => $events) {
					foreach ($events as $key => $event) {
						$schedule = isset($event['schedule']) ? $event['schedule'] : 'once';
						$schedule_display = isset($schedules[$schedule]['display']) ? $schedules[$schedule]['display'] : $schedule;

						echo '<tr>';
						echo '<td>' . esc_html($hook) . '</td>';
						echo '<td>' . (empty($event['args']) ? 'None' : print_r($event['args'], true)) . '</td>';
						echo '<td>' . esc_html($schedule_display) . '</td>';
						echo '<td>' . date_i18n($date_format, $timestamp) . '</td>';
						echo '</tr>';
					}
				}
			}

			echo '</tbody></table>';
		}

		// Show registered schedules
		echo '<h4>' . __('Registered Schedules', 'marinesync') . '</h4>';
		echo '<table class="widefat">';
		echo '<thead><tr><th>Name</th><th>Display</th><th>Interval (seconds)</th></tr></thead>';
		echo '<tbody>';

		foreach ($schedules as $name => $schedule) {
			echo '<tr>';
			echo '<td>' . esc_html($name) . '</td>';
			echo '<td>' . esc_html($schedule['display']) . '</td>';
			echo '<td>' . esc_html($schedule['interval']) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		// Add a manual control for the cron
		echo '<h4>' . __('Cron Controls', 'marinesync') . '</h4>';
		echo '<form method="post" action="">';
		wp_nonce_field('marinesync_cron_control', 'cron_control_nonce');
		echo '<button type="submit" name="clear_all_cron" class="button button-secondary">' . __('Clear All MarineSync Cron Events', 'marinesync') . '</button> ';
		echo '<button type="submit" name="reschedule_cron" class="button button-primary">' . __('Reschedule Export Cron', 'marinesync') . '</button>';
		echo '</form>';

		echo '</div>';
	}

    /**
     * Safely adds a child element to an XML element
     *
     * @param \SimpleXMLElement $parent The parent element
     * @param string $name The name of the child element
     * @param mixed $value The value of the child element
     * @return \SimpleXMLElement|null The created child element or null if value was empty
     */
    private function safe_add_child($parent, $name, $value) {
        if (empty($value) && $value !== '0') {
            return null;
        }
        return $parent->addChild($name, htmlspecialchars((string)$value));
    }

    /**
     * Safely adds an attribute to an XML element
     *
     * @param \SimpleXMLElement $element The element to add the attribute to
     * @param string $name The name of the attribute
     * @param mixed $value The value of the attribute
     * @return bool True if attribute was added, false otherwise
     */
    private function safe_add_attribute($element, $name, $value) {
        if (empty($value) && $value !== '0') {
            return false;
        }
        $element->addAttribute($name, (string)$value);
        return true;
    }

    /**
     * Safely adds an item element with value attribute to an XML parent
     *
     * @param \SimpleXMLElement $parent The parent element
     * @param string $name The name of the item
     * @param mixed $value The value of the item
     * @return \SimpleXMLElement|null The created element or null if value was empty
     */
    private function safe_add_item($parent, $name, $value) {
        if (empty($value) && $value !== '0') {
            return null;
        }
        $item = $parent->addChild('item');
        $item->addAttribute('name', $name);
        $item->addAttribute('value', (string)$value);
        return $item;
    }

	// Enqueue admin scripts
	public function enqueue_admin_scripts($hook) {
		error_log('MS300: Admin scripts hook: ' . $hook);

		// Updated condition to check for both pages
		if (!in_array($hook, ['toplevel_page_marinesync', 'marinesync_page_marinesync-import', 'marinesync_page_marinesync-export'])) {
			error_log('MS301: Not loading scripts - wrong hook');
			return;
		}

		error_log('MS302: Enqueuing admin styles from: ' . MARINESYNC_PLUGIN_URL . 'assets/css/admin.css');

		// Check if constant is defined
		if (!defined('MARINESYNC_PLUGIN_URL')) {
			error_log('MS303: MARINESYNC_PLUGIN_URL constant not defined!');
		}

		// Check if file exists
		$file_path = MARINESYNC_PLUGIN_DIR . 'assets/css/admin.css';
		if (!file_exists($file_path)) {
			error_log('MS304: CSS file not found at: ' . $file_path);
		} else {
			error_log('MS305: CSS file exists at: ' . $file_path);
		}

		wp_enqueue_style('marinesync-admin-css',
			MARINESYNC_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			MARINESYNC_PLUGIN_VERSION
		);
		error_log('MS311: Is style enqueued? ' . (wp_style_is('marinesync-admin-css', 'enqueued') ? 'Yes' : 'No'));
		wp_enqueue_script('marinesync-admin-js', MARINESYNC_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), MARINESYNC_PLUGIN_VERSION, true);

		wp_localize_script('marinesync-admin-js', 'marinesyncAdmin', array(
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('marinesync_admin_nonce'),
			'feedRunning' => get_transient('marinesync_feed_running'),
			'i18n' => array(
				'feedRunning' => __('Feed is currently running. Please wait...', 'marinesync'),
				'feedComplete' => __('Feed completed successfully!', 'marinesync'),
				'feedError' => __('Error running feed. Please check the logs.', 'marinesync'),
				'confirmRun' => __('Are you sure you want to run the feed manually?', 'marinesync')
			)
		));
	}

	public function add_admin_menu() {
		// Add main menu page
		add_menu_page(
			__('MarineSync', 'marinesync'),
			__('MarineSync', 'marinesync'),
			'manage_options',
			'marinesync',
			array($this, 'render_overview_page'),
			'dashicons-ship',
			30
		);

		// Add Import subpage (previously the main admin page)
		add_submenu_page(
			'marinesync',
			__('Import Boats', 'marinesync'),
			__('Import', 'marinesync'),
			'manage_options',
			'marinesync-import',
			array($this, 'render_import_page')
		);

		// Add Export subpage
		add_submenu_page(
			'marinesync',
			__('Export Boats', 'marinesync'),
			__('Export', 'marinesync'),
			'manage_options',
			'marinesync-export',
			array($this, 'render_export_page')
		);
	}

	public function register_settings() {
		register_setting('marinesync_options', 'marinesync_feed_settings', array($this, 'sanitize_settings'));
	}

	public function sanitize_settings($input) {
		$sanitized = array();
		$sanitized['feed_format'] = sanitize_text_field($input['feed_format']);
		$sanitized['feed_url'] = esc_url_raw($input['feed_url']);
		$sanitized['feed_provider'] = sanitize_text_field($input['feed_provider']);
		$sanitized['feed_frequency'] = absint($input['feed_frequency']);
		$sanitized['export_feed_frequency'] = absint($input['export_feed_frequency']);
		return $sanitized;
	}

	// Overview/Dashboard page
	public function render_overview_page() {
        // Make sure options are loaded
		if (empty($this->options)) {
			$this->options = \get_option('marinesync_feed_settings', array(
				'feed_format' => 'auto',
				'feed_url' => '',
				'feed_provider' => '',
				'feed_frequency' => 24
			));
		}
		?>
        <div class="wrap marinesync-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="marinesync-admin-container">
                <div class="marinesync-card">
                    <h2><?php _e('Welcome to MarineSync', 'marinesync'); ?></h2>
                    <p><?php _e('Use the navigation links to manage your boat inventory.', 'marinesync'); ?></p>

                    <div class="marinesync-dashboard-links">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=marinesync-import')); ?>" class="button button-primary">
							<?php _e('Import Boats', 'marinesync'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=marinesync-export')); ?>" class="button button-secondary">
							<?php _e('Export Boats', 'marinesync'); ?>
                        </a>
                    </div>
                </div>

                <div class="marinesync-card">
                    <h3><?php _e('Feed Status', 'marinesync'); ?></h3>
                    <ul class="marinesync-status-list">
                        <li>
                            <strong><?php _e('Last Run:', 'marinesync'); ?></strong>
                            <span id="last-run"><?php echo esc_html(get_option('marinesync_last_run', __('Never', 'marinesync'))); ?></span>
                        </li>
                        <li>
                            <strong><?php _e('Total Boats:', 'marinesync'); ?></strong>
                            <span id="total-boats"><?php echo esc_html($this->get_total_boats()); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
		<?php
	}

	// Import page (previously the main admin page)
	public function render_import_page() {
		$this->options = get_option('marinesync_feed_settings', array(
			'feed_format' => 'auto',
			'feed_url' => '',
			'feed_provider' => '',
			'feed_frequency' => 24
		));

		$this->feed_running = get_transient('marinesync_feed_running');
		?>
        <div class="wrap marinesync-admin">
            <h1><?php _e('Import Boats', 'marinesync'); ?></h1>

			<?php settings_errors(); ?>

            <div class="marinesync-admin-container">
                <div class="marinesync-admin-main">
                    <form method="post" action="options.php" class="marinesync-settings-form">
						<?php settings_fields('marinesync_options'); ?>

                        <div class="marinesync-form-section">
                            <h2><?php _e('Feed Settings', 'marinesync'); ?></h2>

                            <div class="form-field">
                                <label for="feed_format"><?php _e('Feed Format', 'marinesync'); ?></label>
                                <select name="marinesync_feed_settings[feed_format]" id="feed_format">
                                    <option value="auto" <?php selected($this->options['feed_format'], 'auto'); ?>><?php _e('Auto Detect', 'marinesync'); ?></option>
                                    <option value="xml" <?php selected($this->options['feed_format'], 'xml'); ?>><?php _e('XML', 'marinesync'); ?></option>
                                    <option value="json" <?php selected($this->options['feed_format'], 'json'); ?>><?php _e('JSON', 'marinesync'); ?></option>
                                </select>
                                <p class="description"><?php _e('Select the feed format or let the system auto-detect it.', 'marinesync'); ?></p>
                            </div>

                            <div class="form-field">
                                <label for="feed_url"><?php _e('Feed URL', 'marinesync'); ?></label>
                                <input type="url" name="marinesync_feed_settings[feed_url]" id="feed_url"
                                       value="<?php echo esc_attr($this->options['feed_url']); ?>" class="regular-text">
                                <p class="description"><?php _e('Enter the URL of your boat feed.', 'marinesync'); ?></p>
                            </div>

                            <div class="form-field">
                                <label for="feed_provider"><?php _e('Feed Provider', 'marinesync'); ?></label>
                                <select name="marinesync_feed_settings[feed_provider]" id="feed_provider">
                                    <option value=""><?php _e('Select Provider', 'marinesync'); ?></option>
                                    <option value="boats" <?php selected($this->options['feed_provider'], 'boats'); ?>><?php _e('Boats.com', 'marinesync'); ?></option>
                                    <option value="yachtworld" <?php selected($this->options['feed_provider'], 'yachtworld'); ?>><?php _e('YachtWorld', 'marinesync'); ?></option>
                                    <option value="boatshop" <?php selected($this->options['feed_provider'], 'boatshop'); ?>><?php _e('BoatShop', 'marinesync'); ?></option>
                                    <option value="rightboat" <?php selected($this->options['feed_provider'], 'rightboat'); ?>><?php _e('Rightboat', 'marinesync'); ?></option>
                                </select>
                                <p class="description"><?php _e('Select the provider of your boat feed.', 'marinesync'); ?></p>
                            </div>

                            <div class="form-field">
                                <label for="feed_frequency"><?php _e('Feed Run Frequency', 'marinesync'); ?></label>
                                <select name="marinesync_feed_settings[feed_frequency]" id="feed_frequency">
                                    <option value="1" <?php selected($this->options['feed_frequency'], 1); ?>><?php _e('Every Hour', 'marinesync'); ?></option>
                                    <option value="2" <?php selected($this->options['feed_frequency'], 2); ?>><?php _e('Every 2 Hours', 'marinesync'); ?></option>
                                    <option value="4" <?php selected($this->options['feed_frequency'], 4); ?>><?php _e('Every 4 Hours', 'marinesync'); ?></option>
                                    <option value="8" <?php selected($this->options['feed_frequency'], 8); ?>><?php _e('Every 8 Hours', 'marinesync'); ?></option>
                                    <option value="12" <?php selected($this->options['feed_frequency'], 12); ?>><?php _e('Every 12 Hours', 'marinesync'); ?></option>
                                    <option value="18" <?php selected($this->options['feed_frequency'], 18); ?>><?php _e('Every 18 Hours', 'marinesync'); ?></option>
                                    <option value="24" <?php selected($this->options['feed_frequency'], 24); ?>><?php _e('Every 24 Hours', 'marinesync'); ?></option>
                                </select>
                                <p class="description"><?php _e('How often should the feed be processed?', 'marinesync'); ?></p>
                            </div>

							<?php submit_button(__('Save Settings', 'marinesync')); ?>
                        </div>
                    </form>
                </div>

                <div class="marinesync-admin-sidebar">
                    <div class="marinesync-card">
                        <h3><?php _e('Manual Feed Run', 'marinesync'); ?></h3>
                        <p><?php _e('Click the button below to manually trigger the feed import process.', 'marinesync'); ?></p>

                        <div class="marinesync-feed-status">
							<?php if ($this->feed_running): ?>
                                <div class="notice notice-warning">
                                    <p><?php _e('Feed is currently running. Please wait...', 'marinesync'); ?></p>
                                </div>
							<?php endif; ?>
                        </div>

                        <button type="button" id="run-feed" class="button button-primary" <?php disabled($this->feed_running); ?>>
							<?php _e('Run Feed Now', 'marinesync'); ?>
                        </button>
                    </div>

                    <div class="marinesync-card">
                        <h3><?php _e('Feed Status', 'marinesync'); ?></h3>
                        <ul class="marinesync-status-list">
                            <li>
                                <strong><?php _e('Last Run:', 'marinesync'); ?></strong>
                                <span id="last-run"><?php echo esc_html(get_option('marinesync_last_run', __('Never', 'marinesync'))); ?></span>
                            </li>
                            <li>
                                <strong><?php _e('Next Run:', 'marinesync'); ?></strong>
                                <span id="next-run"><?php echo esc_html($this->get_next_run_time()); ?></span>
                            </li>
                            <li>
                                <strong><?php _e('Total Boats:', 'marinesync'); ?></strong>
                                <span id="total-boats"><?php echo esc_html($this->get_total_boats()); ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
		<?php
	}

	// Export page
	public function render_export_page() {
		// Make sure options are loaded
		if (empty($this->options)) {
			$this->options = get_option('marinesync_feed_settings', array(
				'export_feed_frequency' => 24
			));
		}

		$export_schedule_updated = false;
		
		// Handle form submission for export frequency
		if (isset($_POST['update_export_frequency']) && isset($_POST['export_feed_frequency'])) {
		    check_admin_referer('marinesync_export_frequency', 'export_frequency_nonce');
		    
		    $frequency = absint($_POST['export_feed_frequency']);
		    if ($frequency >= 1 && $frequency <= 24) {
		        // Update the options
		        $this->options['export_feed_frequency'] = $frequency;
		        update_option('marinesync_feed_settings', $this->options);
		        
		        // Clear any existing scheduled export events
		        $timestamp = wp_next_scheduled('marinesync_scheduled_export');
		        if ($timestamp) {
		            wp_unschedule_event($timestamp, 'marinesync_scheduled_export');
		        }
		        
		        // Calculate how many seconds based on frequency (in hours)
		        $interval = $frequency * HOUR_IN_SECONDS;
		        
		        // Schedule the new event
		        wp_schedule_event(time(), $frequency . 'hours', 'marinesync_scheduled_export');
		        
		        $export_schedule_updated = true;
		    }
		}
		?>
        <div class="wrap marinesync-admin">
            <h1><?php _e('Export Boats', 'marinesync'); ?></h1>
            
            <?php if ($export_schedule_updated): ?>
            <div class="notice notice-success">
                <p><?php _e('Export schedule has been updated successfully.', 'marinesync'); ?></p>
            </div>
            <?php endif; ?>

            <div class="marinesync-admin-container">
                <div class="marinesync-admin-main">
                    <div class="marinesync-card">
                        <h2><?php _e('Export Options', 'marinesync'); ?></h2>
                        <p><?php _e('Export your boat listings as an Open Marine compliant XML file.', 'marinesync'); ?></p>

                        <div id="export-message"></div>

                        <form id="export-form" method="post">
			                <?php wp_nonce_field('marinesync_admin_nonce', 'nonce'); ?>

                            <p>
                                <button type="button" id="export-boats" class="button button-primary">
					                <?php _e('Run Boat Export', 'marinesync'); ?>
                                </button>
                            </p>
                        </form>
                    </div>

                    <div class="marinesync-card">
                        <h2><?php _e('Export URL', 'marinesync'); ?></h2>
                        <p><?php _e('Your XML export file is available at this URL:', 'marinesync'); ?></p>

                        <div class="form-field">
                            <input type="text" readonly value="<?php echo esc_url(site_url('/wp-content/uploads/marinesync-exports/marinesync-export-' . sanitize_title(get_bloginfo('name')) . '.xml')); ?>" class="regular-text"
                                   onclick="this.select();" style="width: 100%;">
                        </div>

                        <p class="description"><?php _e('This URL can be used in third-party systems that need to access your boat data.', 'marinesync'); ?></p>
                    </div>

                    <div class="marinesync-card">
                        <h2><?php _e('Export Frequency', 'marinesync'); ?></h2>
                        <p><?php _e('Set how often the export file should be automatically updated:', 'marinesync'); ?></p>

                        <form method="post" action="">
                            <?php wp_nonce_field('marinesync_export_frequency', 'export_frequency_nonce'); ?>
                            <div class="form-field">
                                <label for="export_feed_frequency"><?php _e('Update Frequency', 'marinesync'); ?></label>
                                <select name="export_feed_frequency" id="export_feed_frequency">
                                    <option value="1" <?php selected($this->options['export_feed_frequency'], 1); ?>><?php _e('Every Hour', 'marinesync'); ?></option>
                                    <option value="12" <?php selected($this->options['export_feed_frequency'], 12); ?>><?php _e('Every 12 Hours', 'marinesync'); ?></option>
                                    <option value="24" <?php selected($this->options['export_feed_frequency'], 24); ?>><?php _e('Every 24 Hours', 'marinesync'); ?></option>
                                </select>
                                <p class="description"><?php _e('Choose how frequently the XML export should be regenerated.', 'marinesync'); ?></p>
                            </div>
                            
                            <p>
                                <input type="submit" name="update_export_frequency" class="button button-primary" value="<?php _e('Save Schedule', 'marinesync'); ?>">
                            </p>
                        </form>
                        
                        <p>
                            <?php 
                            $timestamp = wp_next_scheduled('marinesync_scheduled_export');
                            if ($timestamp) {
                                echo __('Next scheduled export: ', 'marinesync') . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
                            } else {
                                echo __('No scheduled exports found. Save the schedule to enable automatic exports.', 'marinesync');
                            }
                            ?>
                        </p>
                    </div>
                </div>

                <div class="marinesync-admin-sidebar">
                    <div class="marinesync-card">
                        <h3><?php _e('Export Information', 'marinesync'); ?></h3>
                        <p><?php _e('The export file will contain all boat listings with their complete metadata.', 'marinesync'); ?></p>
                        <ul class="marinesync-status-list">
                            <li>
                                <strong><?php _e('Total Boats:', 'marinesync'); ?></strong>
                                <span><?php echo esc_html($this->get_total_boats()); ?></span>
                            </li>
                            <li>
                                <strong><?php _e('Last Export:', 'marinesync'); ?></strong>
                                <span><?php echo esc_html(get_option('marinesync_last_export', __('Never', 'marinesync'))); ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <?php // $this->display_cron_debug_info(); ?>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#export-boats').on('click', function() {
                    var $button = $(this);
                    var $message = $('#export-message');

                    $button.prop('disabled', true);
                    $message.html('<div class="notice notice-info"><p><?php _e('Generating export file, please wait...', 'marinesync'); ?></p></div>');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'marinesync_export_boats',
                            nonce: $('#nonce').val()
                        },
                        success: function(response) {
                            if(response.success) {
                                $message.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                                // Create download link
                                if(response.data.url) {
                                    $message.append('<p><a href="' + response.data.url + '" class="button" target="_blank"><?php _e('Download Export File', 'marinesync'); ?></a></p>');
                                }
                            } else {
                                $message.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                            }
                        },
                        error: function() {
                            $message.html('<div class="notice notice-error"><p><?php _e('Export failed. Please try again.', 'marinesync'); ?></p></div>');
                        },
                        complete: function() {
                            $button.prop('disabled', false);
                        }
                    });
                });
            });
        </script>
		<?php
	}

	/**
	 * Handle AJAX settings save
	 */
	public function ajax_save_settings() {
		check_ajax_referer('marinesync_admin_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(__('Unauthorized access', 'marinesync'));
		}

		// Parse settings from serialized form data
		parse_str($_POST['settings'], $settings);

		// Handle export frequency settings
		if (isset($settings['export_feed_frequency'])) {
			$frequency = absint($settings['export_feed_frequency']);
			if ($frequency >= 1 && $frequency <= 24) {
				// Update options
				$this->options['export_feed_frequency'] = $frequency;
				update_option('marinesync_feed_settings', $this->options);

				// Update cron schedule
				$timestamp = wp_next_scheduled('marinesync_scheduled_export');
				if ($timestamp) {
					wp_unschedule_event($timestamp, 'marinesync_scheduled_export');
				}

				// Schedule using standard WordPress schedules
				$recurrence = $this->get_wp_schedule_for_frequency($frequency);
				wp_schedule_event(time(), $recurrence, 'marinesync_scheduled_export');

				wp_send_json_success(__('Export schedule updated successfully', 'marinesync'));
			} else {
				wp_send_json_error(__('Invalid frequency value', 'marinesync'));
			}
		}

		// Handle other settings
		if (isset($settings['marinesync_feed_settings'])) {
			$new_settings = $this->sanitize_settings($settings['marinesync_feed_settings']);
			update_option('marinesync_feed_settings', $new_settings);
			$this->options = $new_settings;

			wp_send_json_success(__('Settings saved successfully', 'marinesync'));
		}

		wp_send_json_error(__('No valid settings found', 'marinesync'));
	}

	private function get_next_run_time() {
		$last_run = get_option('marinesync_last_run', 0);
		$frequency = $this->options['feed_frequency'];

		if (!$last_run) {
			return __('Not scheduled', 'marinesync');
		}

		$next_run = strtotime("+{$frequency} hours", strtotime($last_run));
		return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_run);
	}

	private function get_total_boats() {
		$count = wp_count_posts('marinesync-boats');
		return $count->publish;
	}

	// Handle AJAX actions for export and delete
	public function ajax_export_boats($ajax_request = true) {
		// Only verify nonce for AJAX requests
		if ($ajax_request) {
			check_ajax_referer('marinesync_admin_nonce', 'nonce');
		}

		error_log('MS025: Starting boat export process' . ($ajax_request ? ' via AJAX' : ' via cron'));

		// Check for admin capabilities if called from AJAX
		if ($ajax_request && !current_user_can('manage_options')) {
			error_log('MS026: Unauthorized access attempt to export boats');
			wp_die('Unauthorized access');
		}

		global $wpdb;

		// Get all boat posts
		$boat_posts = get_posts(array(
			'post_type' => 'marinesync-boats',
			'posts_per_page' => -1,
			'post_status' => 'publish',
		));

		$count = count($boat_posts);
		error_log('MS027: Found ' . $count . ' boat posts to export');

		try {
			// Create XML document
			$xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><open_marine version="1.7" language="en" origin="'.esc_attr(get_bloginfo('name')).'" date="'.date('Y-m-d\TH:i:s').'"></open_marine>');

			// Add broker information
			$broker = $xml->addChild('broker');
			// Use a default broker code if not set
			$broker_code = isset($this->options['broker_code']) ? $this->options['broker_code'] : 'default';
			$broker->addAttribute('code', $broker_code);
			error_log('MS032: Added broker information');

			// Add offices information
			$offices = $xml->addChild('offices');

			// Check if ACF is active before using get_field
			if (function_exists('get_field')) {
				// Get office option ACF field
				$office_data = get_field('offices', 'option');
				if(!empty($office_data)) {
					error_log('MS033: Processing ' . count($office_data) . ' offices');
					foreach($office_data as $item) {
						try {
							// Add office information
							$office = $offices->addChild('office');
							$office->addAttribute('id', isset($item['id']) ? $item['id'] : '');
							$office->addChild('office_name', isset($item['office_name']) ? $item['office_name'] : '');
							$office->addChild('email', isset($item['office_email']) ? $item['office_email'] : '');

							// Add name information
							$name = $office->addChild('name');
							$name->addChild('title', isset($item['title']) ? $item['title'] : '');
							$name->addChild('forename', isset($item['forename']) ? $item['forename'] : '');
							$name->addChild('surname', isset($item['surname']) ? $item['surname'] : '');

							// Add address
							$office->addChild('address', isset($item['address']) ? $item['address'] : '');
							$office->addChild('town', isset($item['town']) ? $item['town'] : '');
							$office->addChild('county', isset($item['county']) ? $item['county'] : '');
							$office->addChild('postcode', isset($item['postcode']) ? $item['postcode'] : '');
							$office->addChild('country', isset($item['country']) ? $item['country'] : '');

							// add daytime phone and evening phone
							$office->addChild('daytime_phone', isset($item['daytime_phone']) ? $item['daytime_phone'] : '');
							$office->addChild('evening_phone', isset($item['evening_phone']) ? $item['evening_phone'] : '');

							// Add mobile phone and fax
							$office->addChild('mobile', isset($item['mobile']) ? $item['mobile'] : '');
							$office->addChild('fax', isset($item['fax']) ? $item['fax'] : '');

							// Add website
							$office->addChild('website', isset($item['website']) ? $item['website'] : '');
						} catch (\Exception $e) {
							error_log('MS034: Error adding office: ' . $e->getMessage());
						}
					}
				} else {
					error_log('MS035: No office data found in ACF fields');
				}
			} else {
				error_log('MS036: ACF function get_field not available');
			}

			// Create <adverts>
			$adverts = $xml->addChild('adverts');
			error_log('MS037: Starting boat entries processing');

			foreach ($boat_posts as $index => $post) {
				try {
					error_log('MS038: Processing boat post ID: ' . $post->ID . ' (' . ($index + 1) . '/' . $count . ')');

					// Add boat element
					$boat = $adverts->addChild('advert');
					$boat->addAttribute('ref', $post->ID);

					// Check if MarineSync_Post_Type class and method exist
					if (class_exists('MarineSync\\PostType\\MarineSync_Post_Type') &&
					    method_exists('MarineSync\\PostType\\MarineSync_Post_Type', 'get_boat_field')) {

						// Add advert attr - check each field exists before adding
						$status = MarineSync_Post_Type::get_boat_field('status', $post->ID);
						if ($status) {
							$boat->addAttribute('status', $status);
						}

						$boat->addAttribute('last_modified', get_the_modified_date('Y-m-d\TH:i:s', $post->ID));

						$office_id = MarineSync_Post_Type::get_boat_field('office_id', $post->ID);
						if ($office_id) {
							$boat->addAttribute('office_id', $office_id);
						}

						// Add advert_media
						$advert_media = $boat->addChild('advert_media');

						// Get primary image
						$post_thumbnail = get_post_thumbnail_id($post->ID);
						if ($post_thumbnail) {
							// Get the full image URL
							$primary_url = get_the_post_thumbnail_url($post->ID, 'full');

							// Add the primary image to XML
							$primary_image = $advert_media->addChild('media', $primary_url);

							// Get file extension for MIME type
							$file_ext = pathinfo($primary_url, PATHINFO_EXTENSION);
							$mime_type = 'image/' . ($file_ext ? $file_ext : 'jpeg');

							$primary_image->addAttribute('type', $mime_type);
							$primary_image->addAttribute('primary', 'true');

							// Get attachment metadata for caption
							$attachment = get_post($post_thumbnail);
							$caption = $attachment ? $attachment->post_excerpt : '';
							$primary_image->addAttribute('caption', $caption);

							// Get file modified time
							$file_path = get_attached_file($post_thumbnail);
							$primary_mtime = $file_path && file_exists($file_path) ? filemtime($file_path) : time();
							$primary_image->addAttribute('file_mtime', date('Y-m-d\TH:i:s', $primary_mtime));
						}

						if (function_exists('get_field')) {
							$images = get_field('boat_media', $post->ID);
							if (!empty($images)) {
								foreach ($images as $image) {
									// Skip if the image is the same as the primary image
									if (isset($image['ID']) && $image['ID'] == $post_thumbnail) {
										continue;
									}

									// Get image URL
									$url = isset($image['url']) ? $image['url'] : '';

									// Extract file extension for the correct MIME type
									$file_ext = pathinfo($url, PATHINFO_EXTENSION);
									$mime_type = 'image/' . ($file_ext ? $file_ext : 'jpeg');

									// Create media element
									$media = $advert_media->addChild('media', $url);
									$media->addAttribute('type', $mime_type);
									$media->addAttribute('primary', 'false'); // Explicitly mark as not primary
									$media->addAttribute('caption', isset($image['caption']) ? $image['caption'] : '');

									// Get file modified time if available, or use current time
									$file_path = isset($image['path']) ? $image['path'] : '';
									$mtime = $file_path && file_exists($file_path) ? filemtime($file_path) : time();
									$media->addAttribute('file_mtime', date('Y-m-d\TH:i:s', $mtime));
								}
							}
						}

						// Add advert features
						$advert_features = $boat->addChild('advert_features');

						// Add child elements directly
						$advert_features->addChild('title', htmlspecialchars((string)MarineSync_Post_Type::get_boat_field('title', $post->ID)));
						$advert_features->addChild('boat_type', htmlspecialchars((string)MarineSync_Post_Type::get_boat_field('boat_type', $post->ID)));
						$advert_features->addChild('boat_category', htmlspecialchars((string)MarineSync_Post_Type::get_boat_field('boat_category', $post->ID)));
						$advert_features->addChild('new_or_used', htmlspecialchars((string)MarineSync_Post_Type::get_boat_field('new_or_used', $post->ID)));

						// Vessel lying
						$vessel_lying = $advert_features->addChild('vessel_lying',
							htmlspecialchars((string)MarineSync_Post_Type::get_boat_field('vessel_lying', $post->ID)));
						$country = MarineSync_Post_Type::get_boat_field('vessel_lying_country', $post->ID);
						if ($country) {
							$vessel_lying->addAttribute('country', $country);
						}

						// Add asking price
						$asking_price = $advert_features->addChild('asking_price',
							htmlspecialchars((string)MarineSync_Post_Type::get_boat_field('asking_price', $post->ID)));

						$hide_price = MarineSync_Post_Type::get_boat_field('hide_price', $post->ID);
						if ($hide_price) {
							$asking_price->addAttribute('hide_price', $hide_price);
						}

						$currency = MarineSync_Post_Type::get_boat_field('currency', $post->ID);
						if ($currency) {
							$asking_price->addAttribute('currency', $currency);
						}

						$vat_included = MarineSync_Post_Type::get_boat_field('vat_included', $post->ID);
						if ($vat_included) {
							$asking_price->addAttribute('vat_included', $vat_included);
						}

						$vat_type = MarineSync_Post_Type::get_boat_field('vat_type', $post->ID);
						if ($vat_type) {
							$asking_price->addAttribute('vat_type', $vat_type);
						}

						$vat_country = MarineSync_Post_Type::get_boat_field('vat_country', $post->ID);
						if ($vat_country) {
							$asking_price->addAttribute( 'vat_country', $vat_country );
						}

						// Add marketing desc
						$marketing_descs = $advert_features->addChild('marketing_descs');

						$desc = get_post_field('post_content', $post->ID);
						$marketing_desc = $marketing_descs->addChild('marketing_desc');
						$marketing_desc_node = dom_import_simplexml($marketing_desc);
						$cdata = $marketing_desc_node->ownerDocument->createCDATASection($desc);
						$marketing_desc_node->appendChild($cdata);

						$lang = MarineSync_Post_Type::get_boat_field('marketing_desc_language', $post->ID);
						if ($lang) {
							$marketing_desc->addAttribute('language', $lang);
						}

						$short_desc = MarineSync_Post_Type::get_boat_field('marketing_short_desc', $post->ID);
						$marketing_short_desc = $marketing_descs->addChild('marketing_short_desc');
						$marketing_short_desc_node = dom_import_simplexml($marketing_short_desc);
						$short_cdata = $marketing_short_desc_node->ownerDocument->createCDATASection($short_desc);
						$marketing_short_desc_node->appendChild($short_cdata);

						$short_lang = MarineSync_Post_Type::get_boat_field('marketing_short_desc_language', $post->ID);
						if ($short_lang) {
							$marketing_short_desc->addAttribute('language', $short_lang);
						}

						// Add manufacturer and model
						$advert_features->addChild('manufacturer',
							htmlspecialchars((string)MarineSync_Post_Type::get_boat_field('manufacturer', $post->ID)));
						$advert_features->addChild('model',
							htmlspecialchars((string)MarineSync_Post_Type::get_boat_field('model', $post->ID)));

						// Add boat features
						$boat_features = $boat->addChild('boat_features');

						// Add dimensions
						$dimensions = $boat_features->addChild('dimensions');

						// Add dimension items directly
						$beam_item = $dimensions->addChild('item', (string)MarineSync_Post_Type::get_boat_field('beam', $post->ID));
						$beam_item->addAttribute('name', 'beam');

						$draft_item = $dimensions->addChild('item', (string)MarineSync_Post_Type::get_boat_field('draft', $post->ID));
						$draft_item->addAttribute('name', 'draft');

						$loa_item = $dimensions->addChild('item', (string)MarineSync_Post_Type::get_boat_field('loa', $post->ID));
						$loa_item->addAttribute('name', 'loa');

						$engine_power_item = $dimensions->addChild('item', (string)MarineSync_Post_Type::get_boat_field('engine_power', $post->ID));
						$engine_power_item->addAttribute('name', 'engine_power');

						// Add build
						$build = $boat_features->addChild('build');

						// Add build items
						$year_item = $build->addChild('item', (string)MarineSync_Post_Type::get_boat_field('year', $post->ID));
						$year_item->addAttribute('name', 'year');

						$keel_type_item = $build->addChild('item', (string)MarineSync_Post_Type::get_boat_field('keel_type', $post->ID));
						$keel_type_item->addAttribute('name', 'keel_type');

						$hin_item = $build->addChild('item', (string)MarineSync_Post_Type::get_boat_field('hin', $post->ID));
						$hin_item->addAttribute('name', 'hin');

						// Add engine
						$engine = $boat_features->addChild('engine');

						// Add engine items
						$engine_manufacturer_item = $engine->addChild('item', (string)MarineSync_Post_Type::get_boat_field('engine_manufacturer', $post->ID));
						$engine_manufacturer_item->addAttribute('name', 'engine_manufacturer');

						$engine_model_item = $engine->addChild('item', (string)MarineSync_Post_Type::get_boat_field('engine_model', $post->ID));
						$engine_model_item->addAttribute('name', 'engine_model');

						$horse_power_item = $engine->addChild('item', (string)MarineSync_Post_Type::get_boat_field('horse_power', $post->ID));
						$horse_power_item->addAttribute('name', 'horse_power');

						$fuel_item = $engine->addChild('item', (string)MarineSync_Post_Type::get_boat_field('fuel', $post->ID));
						$fuel_item->addAttribute('name', 'fuel');

						$hours_item = $engine->addChild('item', (string)MarineSync_Post_Type::get_boat_field('hours', $post->ID));
						$hours_item->addAttribute('name', 'hours');

						// Add additional
						$additional = $boat_features->addChild('additional');

						// Add additional items
						$dry_weight_item = $additional->addChild('item', (string)MarineSync_Post_Type::get_boat_field('dry_weight', $post->ID));
						$dry_weight_item->addAttribute('name', 'dry_weight');

						$fuel_tanks_item = $additional->addChild('item', (string)MarineSync_Post_Type::get_boat_field('fuel_tanks_capacity', $post->ID));
						$fuel_tanks_item->addAttribute('name', 'fuel_tanks_capacity');

						$hull_material_item = $additional->addChild('item', (string)MarineSync_Post_Type::get_boat_field('hull_material', $post->ID));
						$hull_material_item->addAttribute('name', 'hull_material');

						$water_tanks_item = $additional->addChild('item', (string)MarineSync_Post_Type::get_boat_field('water_tanks_capacity', $post->ID));
						$water_tanks_item->addAttribute('name', 'water_tanks_capacity');
					} else {
						error_log('MS039: MarineSync_Post_Type class or method not found');
					}
				} catch (\Exception $e) {
					error_log('MS040: Error processing boat ID ' . $post->ID . ': ' . $e->getMessage());
				}
			}

			// Create the uploads directory if it doesn't exist
			$upload_dir = wp_upload_dir();
			$export_dir = $upload_dir['basedir'] . '/marinesync-exports/';

			if (!file_exists($export_dir)) {
				wp_mkdir_p($export_dir);
				error_log('MS041: Created export directory: ' . $export_dir);
			}

			// Create an .htaccess file to ensure the directory is publicly accessible
			$htaccess_file = $export_dir . '.htaccess';
			if (!file_exists($htaccess_file)) {
				file_put_contents($htaccess_file, "Allow from all\n");
				error_log('MS042: Created .htaccess in export directory');
			}

			// Fixed filename for consistent access
			$filename = 'marinesync-export-' . sanitize_title(get_bloginfo('name')) . '.xml';
			$filepath = $export_dir . $filename;
			$public_url = $upload_dir['baseurl'] . '/marinesync-exports/' . $filename;

			// Save the XML file
			$dom = new \DOMDocument('1.0');
			$dom->preserveWhiteSpace = false;
			$dom->formatOutput = true;
			$dom->loadXML($xml->asXML());

			$result = file_put_contents($filepath, $dom->saveXML());

			if ($result === false) {
				error_log('MS043: Error writing XML file to: ' . $filepath);
				throw new \Exception('Could not write to file: ' . $filepath);
			}

			error_log('MS044: Generated XML file at: ' . $filepath);

			// Store the public URL in an option for easy access
			update_option('marinesync_export_url', $public_url);
			update_option('marinesync_last_export', current_time('mysql'));

			// Handle response based on request source
			if ($ajax_request) {
				// This is a regular AJAX call from the admin interface
				if (wp_doing_ajax()) {
					wp_send_json_success(array(
						'message' => __('Export completed successfully', 'marinesync'),
						'url' => $public_url
					));
				} elseif (isset($_GET['then']) && $_GET['then'] == 'deactivate' && isset($_GET['redirect'])) {
					?>
					<script type="text/javascript">
						window.location.href = <?php echo json_encode(esc_url_raw($_GET['redirect'])); ?>;
					</script>
					<?php
				} else {
					// Redirect to admin page with success message
					wp_redirect(admin_url('admin.php?page=marinesync-export&export=success&url=' . urlencode($public_url)));
				}
			} else {
				// This is a cron job - just log the success
				error_log('MS046: Cron export process completed successfully. File saved to: ' . $filepath);
				return true;
			}

			error_log('MS046: Export process completed');
			exit;

		} catch (\Exception $e) {
			error_log('MS047: Critical error in export process: ' . $e->getMessage());

			if ($ajax_request) {
				if (wp_doing_ajax()) {
					wp_send_json_error(array(
						'message' => __('Export failed: ', 'marinesync') . $e->getMessage()
					));
				} else {
					wp_die('Export Error: ' . $e->getMessage());
				}
			} else {
				// Cron job failed
				error_log('MS048: Cron export failed with error: ' . $e->getMessage());
				return false;
			}
		}
	}

	public function ajax_run_feed() {
		check_ajax_referer('marinesync_admin_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(__('Unauthorized access', 'marinesync'));
		}

		if (get_transient('marinesync_feed_running')) {
			wp_send_json_error(__('Feed is already running', 'marinesync'));
		}

		// Set transient to prevent multiple runs
		set_transient('marinesync_feed_running', true, 10 * MINUTE_IN_SECONDS);

		// Trigger feed process
		do_action('marinesync_process_feed');

		wp_send_json_success(__('Feed process started', 'marinesync'));
	}

	public function ajax_check_feed_status() {
		check_ajax_referer('marinesync_admin_nonce', 'nonce');

		$feed_running = get_transient('marinesync_feed_running');
		$response = array(
			'running' => (bool) $feed_running,
			'last_run' => get_option('marinesync_last_run', __('Never', 'marinesync')),
			'next_run' => $this->get_next_run_time(),
			'total_boats' => $this->get_total_boats()
		);

		wp_send_json_success($response);
	}

	/**
	 * Maps hour frequency to WordPress standard schedules
	 *
	 * @param int $hours Frequency in hours
	 * @return string WordPress schedule name
	 */
	private function get_wp_schedule_for_frequency($hours) {
		// Map to standard WordPress schedules
		if ($hours <= 1) {
			return 'hourly';
		} else if ($hours <= 12) {
			return 'twicedaily';
		} else {
			return 'daily';
		}
	}

	/**
	 * Clear all MarineSync cron events
	 */
	private function clear_all_cron_events() {
		$crons = _get_cron_array();
		$marinesync_hooks = ['marinesync_scheduled_export'];

		foreach ($crons as $timestamp => $cronhooks) {
			foreach ($marinesync_hooks as $hook) {
				if (isset($cronhooks[$hook])) {
					foreach ($cronhooks[$hook] as $key => $event) {
						wp_unschedule_event($timestamp, $hook, $event['args']);
						error_log("MS100: Unscheduled cron event: $hook at timestamp $timestamp");
					}
				}
			}
		}
	}

	/**
	 * Schedule the export cron with standard schedules
	 */
	private function schedule_export_cron() {
		// Get frequency from options
		$frequency = isset($this->options['export_feed_frequency']) ?
			absint($this->options['export_feed_frequency']) : 24;

		// Make sure we have a valid frequency
		if ($frequency < 1) $frequency = 24;

		// Map to WP standard schedules
		if ($frequency <= 1) {
			$recurrence = 'hourly';
		} else if ($frequency <= 12) {
			$recurrence = 'twicedaily';
		} else {
			$recurrence = 'daily';
		}

		// Schedule the event
		$timestamp = time() + 120; // Start in 2 minutes, not immediately
		$scheduled = wp_schedule_event($timestamp, $recurrence, 'marinesync_scheduled_export');

		if ($scheduled) {
			error_log("MS101: Successfully scheduled export cron using $recurrence schedule starting at " . date('Y-m-d H:i:s', $timestamp));
		} else {
			error_log("MS102: Failed to schedule export cron");
		}
	}

	/**
	 * Improved setup of scheduled exports
	 */
	public function setup_scheduled_exports() {
		// Check if we need to handle cron control actions
		if (isset($_POST['clear_all_cron']) && isset($_POST['cron_control_nonce']) &&
		    wp_verify_nonce($_POST['cron_control_nonce'], 'marinesync_cron_control')) {

			$this->clear_all_cron_events();
			add_action('admin_notices', function() {
				echo '<div class="notice notice-success"><p>' . __('All MarineSync cron events cleared.', 'marinesync') . '</p></div>';
			});
		}

		if (isset($_POST['reschedule_cron']) && isset($_POST['cron_control_nonce']) &&
		    wp_verify_nonce($_POST['cron_control_nonce'], 'marinesync_cron_control')) {

			$this->clear_all_cron_events();
			$this->schedule_export_cron();
			add_action('admin_notices', function() {
				echo '<div class="notice notice-success"><p>' . __('Export cron rescheduled.', 'marinesync') . '</p></div>';
			});
		}

		// Only set up scheduled exports if they don't already exist
		$timestamp = wp_next_scheduled('marinesync_scheduled_export');
		if (!$timestamp) {
			$this->schedule_export_cron();
		}
	}
    
    /**
     * Adds custom intervals for WP Cron
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function add_custom_cron_intervals($schedules) {
        // Add custom intervals based on options
        if (isset($this->options['export_feed_frequency'])) {
            $frequency = absint($this->options['export_feed_frequency']);
            $seconds = $frequency * HOUR_IN_SECONDS;
            
            // Only add if it's a valid interval
            if ($frequency >= 1 && $frequency <= 24) {
                $schedules[$frequency . 'hours'] = array(
                    'interval' => $seconds,
                    'display' => sprintf(__('Every %d Hours', 'marinesync'), $frequency)
                );
            }
        }
        
        return $schedules;
    }
    
    /**
     * Handles the scheduled export process
     * Called by WordPress cron
     */
    public function handle_scheduled_export() {
        error_log('MS060: Starting scheduled export from cron job');
        
        // Run the export without requiring AJAX
        $this->ajax_export_boats(false);
    }

	/**
	 * Register plugin activation hooks
	 */
	public static function register_activation() {
		// Get instance to use methods
		$instance = self::get_instance();

		// Get options
		$options = get_option('marinesync_feed_settings', array(
			'export_feed_frequency' => 24
		));

		// Clear any existing scheduled events
		$timestamp = wp_next_scheduled('marinesync_scheduled_export');
		if ($timestamp) {
			wp_unschedule_event($timestamp, 'marinesync_scheduled_export');
		}

		// Schedule the event if we have a frequency
		if (isset($options['export_feed_frequency'])) {
			$frequency = absint($options['export_feed_frequency']);
			if ($frequency >= 1) {
				// Schedule using standard WordPress schedules
				$recurrence = $instance->get_wp_schedule_for_frequency($frequency);
				wp_schedule_event(time(), $recurrence, 'marinesync_scheduled_export');
				error_log('MS051: Scheduled export on activation using WP schedule: ' . $recurrence);
			}
		}
	}
    
    /**
     * Register plugin deactivation hooks to clean up
     */
    public static function register_deactivation() {
        // Clear scheduled events
        $timestamp = wp_next_scheduled('marinesync_scheduled_export');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'marinesync_scheduled_export');
        }
    }
}