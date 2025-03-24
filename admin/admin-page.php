<?php
/**
 * Admin interface for MarineSync feed settings
 */

namespace MarineSync;

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
        add_action('wp_ajax_marinesync_run_feed', array($this, 'ajax_run_feed'));
        add_action('wp_ajax_marinesync_check_feed_status', array($this, 'ajax_check_feed_status'));
    }

    public function add_admin_menu() {
        add_menu_page(
            __('MarineSync', 'marinesync'),
            __('MarineSync', 'marinesync'),
            'manage_options',
            'marinesync',
            array($this, 'render_admin_page'),
            'dashicons-ship',
            30
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

    public function enqueue_admin_scripts($hook) {
	    error_log('MS300: Admin scripts hook: ' . $hook);

	    if ('toplevel_page_marinesync' !== $hook) {
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

	    wp_enqueue_style('marinesync-admin-css', MARINESYNC_PLUGIN_URL . 'assets/css/admin.css', array(), time());
	    wp_enqueue_script('marinesync-admin-js', MARINESYNC_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), time(), true);

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

    public function render_admin_page() {
        $this->options = get_option('marinesync_feed_settings', array(
            'feed_format' => 'auto',
            'feed_url' => '',
            'feed_provider' => '',
            'feed_frequency' => 24
        ));

        $this->feed_running = get_transient('marinesync_feed_running');
        ?>
        <div class="wrap marinesync-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
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