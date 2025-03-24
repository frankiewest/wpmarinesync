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

		// Initialize options
		$this->options = get_option('marinesync_feed_settings', array(
			'feed_format' => 'auto',
			'feed_url' => '',
			'feed_provider' => '',
			'feed_frequency' => 24
		));
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
		return $sanitized;
	}

	// Overview/Dashboard page
	public function render_overview_page() {
        // Make sure options are loaded
		if (empty($this->options)) {
			$this->options = get_option('marinesync_feed_settings', array(
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
		?>
        <div class="wrap marinesync-admin">
            <h1><?php _e('Export Boats', 'marinesync'); ?></h1>

            <div class="marinesync-admin-container">
                <div class="marinesync-admin-main">
                    <div class="marinesync-card">
                        <h2><?php _e('Export Options', 'marinesync'); ?></h2>
                        <p><?php _e('Export your boat listings as an Open Marine compliant XML file.', 'marinesync'); ?></p>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-ajax.php?action=marinesync_export_boats')); ?>">
							<?php wp_nonce_field('marinesync_export_nonce', 'export_nonce'); ?>

                            <p>
                                <button type="submit" class="button button-primary">
									<?php _e('Export All Boats', 'marinesync'); ?>
                                </button>
                            </p>
                        </form>
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
                        </ul>
                    </div>
                </div>
            </div>
        </div>
		<?php
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
	public function ajax_export_boats() {
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
							$name = $office->addChild('name', isset($item['name']) ? $item['name'] : '');
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
						if (function_exists('get_field')) {
							$images = get_field('images', $post->ID);
							if (!empty($images)) {
								foreach ($images as $image) {
									$media = $advert_media->addChild('media', isset($image['url']) ? $image['url'] : '');
									$media->addAttribute('type', 'image/' . (isset($image['type']) ? $image['type'] : 'jpeg'));
									$media->addAttribute('caption', isset($image['caption']) ? $image['caption'] : '');
									$media->addAttribute('primary', isset($image['primary']) ? $image['primary'] : '0');
									$media->addAttribute('file_mtime', isset($image['file_mtime']) ? $image['file_mtime'] : '');
								}
							}
						}

						// Add advert features
						$advert_features = $boat->addChild('advert_features');

						// Safely add child elements - check each field
						$this->safe_add_child($advert_features, 'title', MarineSync_Post_Type::get_boat_field('title', $post->ID));
						$this->safe_add_child($advert_features, 'boat_type', MarineSync_Post_Type::get_boat_field('boat_type', $post->ID));
						$this->safe_add_child($advert_features, 'boat_category', MarineSync_Post_Type::get_boat_field('boat_category', $post->ID));
						$this->safe_add_child($advert_features, 'new_or_used', MarineSync_Post_Type::get_boat_field('new_or_used', $post->ID));

						// Vessel lying
						$vessel_lying = $this->safe_add_child($advert_features, 'vessel_lying',
							MarineSync_Post_Type::get_boat_field('vessel_lying', $post->ID));

						if ($vessel_lying) {
							$country = MarineSync_Post_Type::get_boat_field('vessel_lying_country', $post->ID);
							if ($country) {
								$vessel_lying->addAttribute('country', $country);
							}
						}

						// Add asking price
						$price = MarineSync_Post_Type::get_boat_field('asking_price', $post->ID);
						$asking_price = $this->safe_add_child($advert_features, 'asking_price', $price);

						if ($asking_price) {
							$this->safe_add_attribute($asking_price, 'hide_price',
								MarineSync_Post_Type::get_boat_field('hide_price', $post->ID));
							$this->safe_add_attribute($asking_price, 'currency',
								MarineSync_Post_Type::get_boat_field('currency', $post->ID));
							$this->safe_add_attribute($asking_price, 'vat_included',
								MarineSync_Post_Type::get_boat_field('vat_included', $post->ID));
							$this->safe_add_attribute($asking_price, 'vat_type',
								MarineSync_Post_Type::get_boat_field('vat_type', $post->ID));
							$this->safe_add_attribute($asking_price, 'vat_country',
								MarineSync_Post_Type::get_boat_field('vat_country', $post->ID));
						}

						// Add marketing desc
						$marketing_descs = $boat->addChild('marketing_descs');

						$desc = MarineSync_Post_Type::get_boat_field('marketing_desc', $post->ID);
						$marketing_desc = $this->safe_add_child($marketing_descs, 'marketing_desc', $desc);

						if ($marketing_desc) {
							$lang = MarineSync_Post_Type::get_boat_field('marketing_desc_language', $post->ID);
							if ($lang) {
								$marketing_desc->addAttribute('language', $lang);
							}
						}

						$short_desc = MarineSync_Post_Type::get_boat_field('marketing_short_desc', $post->ID);
						$marketing_short_desc = $this->safe_add_child($marketing_descs, 'marketing_short_desc', $short_desc);

						if ($marketing_short_desc) {
							$lang = MarineSync_Post_Type::get_boat_field('marketing_short_desc_language', $post->ID);
							if ($lang) {
								$marketing_short_desc->addAttribute('language', $lang);
							}
						}

						// Add manufacturer and model
						$this->safe_add_child($advert_features, 'manufacturer',
							MarineSync_Post_Type::get_boat_field('manufacturer', $post->ID));
						$this->safe_add_child($advert_features, 'model',
							MarineSync_Post_Type::get_boat_field('model', $post->ID));

						// Add boat features
						$boat_features = $advert_features->addChild('boat_features');

						// Add dimensions
						$dimensions = $boat_features->addChild('dimensions');

						// Add dimension items
						$this->safe_add_item($dimensions, 'beam', MarineSync_Post_Type::get_boat_field('beam', $post->ID));
						$this->safe_add_item($dimensions, 'draft', MarineSync_Post_Type::get_boat_field('draft', $post->ID));
						$this->safe_add_item($dimensions, 'loa', MarineSync_Post_Type::get_boat_field('loa', $post->ID));
						$this->safe_add_item($dimensions, 'engine_power', MarineSync_Post_Type::get_boat_field('engine_power', $post->ID));

						// Add build
						$build = $boat_features->addChild('build');

						// Add build items
						$this->safe_add_item($build, 'year', MarineSync_Post_Type::get_boat_field('year', $post->ID));
						$this->safe_add_item($build, 'keel_type', MarineSync_Post_Type::get_boat_field('keel_type', $post->ID));
						$this->safe_add_item($build, 'hin', MarineSync_Post_Type::get_boat_field('hin', $post->ID));

						// Add engine
						$engine = $boat_features->addChild('engine');

						// Add engine items
						$this->safe_add_item($engine, 'engine_manufacturer',
							MarineSync_Post_Type::get_boat_field('engine_manufacturer', $post->ID));
						$this->safe_add_item($engine, 'engine_model',
							MarineSync_Post_Type::get_boat_field('engine_model', $post->ID));
						$this->safe_add_item($engine, 'horse_power',
							MarineSync_Post_Type::get_boat_field('horse_power', $post->ID));
						$this->safe_add_item($engine, 'fuel',
							MarineSync_Post_Type::get_boat_field('fuel', $post->ID));
						$this->safe_add_item($engine, 'hours',
							MarineSync_Post_Type::get_boat_field('hours', $post->ID));

						// Add additional
						$additional = $boat_features->addChild('additional');

						// Add additional items
						$this->safe_add_item($additional, 'dry_weight',
							MarineSync_Post_Type::get_boat_field('dry_weight', $post->ID));
						$this->safe_add_item($additional, 'fuel_tanks_capacity',
							MarineSync_Post_Type::get_boat_field('fuel_tanks_capacity', $post->ID));
						$this->safe_add_item($additional, 'hull_material',
							MarineSync_Post_Type::get_boat_field('hull_material', $post->ID));
						$this->safe_add_item($additional, 'water_tanks_capacity',
							MarineSync_Post_Type::get_boat_field('water_tanks_capacity', $post->ID));
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
			$filename = 'marinesync-export-' . sanitize_title(get_bloginfo('name')) . '-' . uniqid() . '.xml';
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

			// If accessed via AJAX, return success with the URL
			if (wp_doing_ajax()) {
				wp_send_json_success(array(
					'message' => __('Export completed successfully', 'marinesync'),
					'url' => $public_url
				));
			}

			// Check if we should deactivate after export
			if (isset($_GET['then']) && $_GET['then'] === 'deactivate' && isset($_GET['redirect'])) {
				error_log('MS045: Export completed, proceeding with deactivation');
				?>
                <script type="text/javascript">
                    window.location.href = <?php echo json_encode(esc_url_raw($_GET['redirect'])); ?>;
                </script>
				<?php
			} else {
				// Redirect to admin page with success message
				wp_redirect(admin_url('admin.php?page=marinesync-export&export=success&url=' . urlencode($public_url)));
			}

			error_log('MS046: Export process completed');
			exit;

		} catch (\Exception $e) {
			error_log('MS047: Critical error in export process: ' . $e->getMessage());

			if (wp_doing_ajax()) {
				wp_send_json_error(array(
					'message' => __('Export failed: ', 'marinesync') . $e->getMessage()
				));
			} else {
				wp_die('Export Error: ' . $e->getMessage());
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
}