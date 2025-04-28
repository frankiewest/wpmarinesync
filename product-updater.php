<?php
/**
 * Plugin Name: Rightboat Product XML Feed Import
 * Description: Process for importing products via data from XML feed.
 * Version: 3.1.2
 * Author: Hampshire Web Design
 */

// Define constants
define('SP_RIGHTBOAT_PATH', plugin_dir_path(__FILE__));
define('SP_RIGHTBOAT_URL', plugin_dir_url(__FILE__));

require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');

// Get global marinesync post type
use \MarineSync\PostType\MarineSync_Post_Type;

// Schedule WP Cron
add_action('init', 'schedule_woocommerce_xml_updater');
function schedule_woocommerce_xml_updater() {
	// Schedule the event if it's not already scheduled
	if (!wp_next_scheduled('update_woocommerce_products_event')) {
		wp_schedule_event(time(), 'hourly', 'update_woocommerce_products_event');
	}

	// Hook the update function to the scheduled event
	add_action('update_woocommerce_products_event', 'update_woocommerce_products_from_xml');
}

// Register and define the settings
add_action('admin_init', 'woocommerce_xml_updater_settings');
function woocommerce_xml_updater_settings() {
	register_setting('woocommerce-xml-updater-settings-group', 'xml_feed_url');
	register_setting('woocommerce-xml-updater-settings-group', 'license_key');
	register_setting('woocommerce-xml-updater-settings-group', 'last_checked');
	register_setting('woocommerce-xml-updater-settings-group', 'feed_updated');
	register_setting('woocommerce-xml-updater-settings-group', 'status');
	register_setting('woocommerce-xml-updater-settings-group', 'sold_boats_process');
}

// Cron callback function
function update_woocommerce_products_from_xml() {
	error_log('=== Rightboat XML Import: Cron started at ' . current_time('mysql') . ' ===');

	// XML Feed URL
	$xml_feed_url = "https://import.rightboat.com/exports/mark-williamsandsmithells-com-openmarine-e51131675d.xml?version=1744751307";

	// Validate XML Feed URL
	if (!$xml_feed_url || !is_string($xml_feed_url) || !preg_match('/^http(s)?:\/\/[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(\/.*)?$/i', $xml_feed_url)) {
		error_log('WooCommerce XML Update: Please enter a valid URL');
		return false;
	}

	// Fetch XML data from the feed URL
	$xml_data = wp_remote_get($xml_feed_url);

	if (is_wp_error($xml_data)) {
		error_log("Failed to fetch XML data from $xml_feed_url. Error: " . $xml_data->get_error_message());
		return;
	}

	// Parse XML data
	$xml = simplexml_load_string(wp_remote_retrieve_body($xml_data));
	$adverts = $xml->xpath('//advert');

	// Initialize processed media URLs array
	$processed_media_urls = get_option('processed_media_urls', array());

	// Loop through each advert node
	foreach ($adverts as $advert) {
		// Define baseline nodes
		$advert_features = $advert->advert_features;
		$boat_features = $advert->boat_features;
		$sku = (string) $advert['ref'];

		error_log('Rightboat XML Import: Processing boat ref ' . $sku);

		// Extract advert_features
		$title = (string) $advert_features->manufacturer . ' ' . (string) $advert_features->model;
		$boat_type = (string) $advert_features->boat_type;
		$boat_category = (string) $advert_features->boat_category;
		$price = (int) $advert_features->asking_price;
		$patterns = array("&lt;p&gt;", "&lt;br&gt;");
		$replacements = array("", "");
		$description = (string) $advert_features->marketing_descs->marketing_desc[0];
		$description_result = str_replace($patterns, $replacements, $description);

		$currency = (string) $advert_features->asking_price['currency'];
		$poa = (string) $advert_features->asking_price['poa'];

		// Check if a product with the same SKU already exists
		$boat = MarineSync_Post_Type::get_boat_by_ref($sku)->ID;

		$boat_id = ($boat instanceof WP_Post) ? $boat->ID : $boat;

		// Initialize all attributes
		$all_attributes = array();

		if ($boat_id) {
			// Boat exists
			error_log('Boat (ID: ' . $boat_id . ') found, updating...');

			$boat = get_post($boat_id);
			if (!$boat || $boat->post_type !== 'marinesync-boats') {
				error_log('Post exists but is not a marinesync-boats type. Skipping.');
				continue; // Skip this loop iteration
			}
		} else {
			// Boat does not exist, create a new one
			$boat_id = wp_insert_post([
				'post_title'   => $title,
				'post_content' => $description_result,
				'post_status'  => 'publish',
				'post_type'    => 'marinesync-boats' // You MUST set the correct post type here
			]);

			if (is_wp_error($boat_id)) {
				error_log('Failed to create new boat: ' . $boat_id->get_error_message());
				continue;
			}

			// Set the reference (custom field or meta)
			MarineSync_Post_Type::set_boat_field($boat_id, 'boat_ref', $sku);
			error_log('Created new Boat (ID: ' . $boat_id . ')');
		}

		// Always work with the ID
		$boat = $boat_id;

		// Add Status custom field
		$status = (string) $advert['status'];
		error_log("Status: " . $status);
		if(!empty($status)) {
			if ($status === "Sold") {
				// Remove the "Available" tag if it exists
				$available_status = get_term_by('slug', 'active', 'boat-status');
				if ($available_status) {
					wp_remove_object_terms($boat, $available_status->term_id, 'boat-status');
					error_log("Available status removed from the boat.");
					wp_set_object_terms($boat, 'sold', 'boat-status', true);
				} else {
					wp_set_object_terms($boat, 'sold', 'boat-status', true);
				}
			} else {  // Changed from "Sold" to "Available"
				// Remove the "Sold" tag if it exists
				$sold_status = get_term_by('slug', 'sold', 'boat-status');
				if ($sold_status) {
					wp_remove_object_terms($boat, $sold_status->term_id, 'boat-status');  // Changed from $available_tag to $sold_tag
					error_log("Sold status removed from the boat.");  // Updated log message
					wp_set_object_terms($boat, 'active', 'boat-status', true);
				}
				wp_set_object_terms($boat, 'active', 'boat-status', true);
			}
		}

		$boat_images = []; // Get only images
		foreach ($advert->advert_media->media as $media) {
			$type = (string) $media['type'];
			if (strpos($type, 'image/') === 0) {
				$boat_images[] = $media;
			}
		}

		// Image processing
		$boat_images = []; // Get only images
		foreach ($advert->advert_media->media as $media) {
			$type = (string) $media['type'];
			$url = (string) $media;
			if (strpos($type, 'image/') === 0 && !empty($url)) {
				$boat_images[] = $url; // Store the URL directly
			}
		}
		error_log('Rightboat XML Import: Found ' . count($boat_images) . ' images for boat ref ' . $sku . ': ' . print_r($boat_images, true));

// Image processing class
		$media_importer = new class($boat_images, $boat_id, 'marinesync-media-importer') {
			private $images, $boat_id, $term;

			function __construct($images, $boat_id, $term) {
				$this->images = $images;
				$this->boat_id = $boat_id;
				$this->term = $term;
			}

			private function getAttachmentIdByFilename($url) {
				global $wpdb;

				$filename_path_parts = pathinfo($url);
				$filename_basename = $filename_path_parts['basename'];
				$filename_no_scaled = preg_replace('/-scaled\.[^.]*$|\.[^.]*$/', '', sanitize_file_name($filename_basename));

				$query = $wpdb->prepare(
					"SELECT p.ID, pm.meta_value AS file_path
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id)
             WHERE p.post_type = 'attachment'
             AND pm.meta_key = '_wp_attached_file'
             AND pm.meta_value LIKE %s",
					'%' . $wpdb->esc_like($filename_no_scaled) . '%'
				);

				$results = $wpdb->get_results($query);

				if (empty($results)) {
					error_log("MediaImporter::getAttachmentIdByFilename - No existing attachment found for $filename_basename");
					return false;
				}

				$match = reset($results);
				error_log("MediaImporter::getAttachmentIdByFilename - Found attachment ID: {$match->ID} for $filename_basename");
				return $match->ID;
			}

			private function uploadImage($url, $boat_id) {
				$response = wp_remote_get($url, ['timeout' => 20]);
				if (is_wp_error($response)) {
					error_log("MediaImporter::uploadImage - Failed to fetch image $url: " . $response->get_error_message());
					return false;
				}

				$image_data = wp_remote_retrieve_body($response);
				$upload = wp_upload_bits(basename($url), null, $image_data);
				if (!empty($upload['error'])) {
					error_log("MediaImporter::uploadImage - Upload failed for $url: " . $upload['error']);
					return false;
				}

				$wp_filetype = wp_check_filetype(basename($upload['file']));
				$attachment = [
					'post_mime_type' => $wp_filetype['type'],
					'post_title' => sanitize_file_name(basename($upload['file'])),
					'post_content' => '',
					'post_status' => 'inherit'
				];
				$attachment_id = wp_insert_attachment($attachment, $upload['file'], $boat_id);

				if (is_wp_error($attachment_id)) {
					error_log("MediaImporter::uploadImage - Failed to insert attachment for $url: " . $attachment_id->get_error_message());
					return false;
				}

				$attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
				wp_update_attachment_metadata($attachment_id, $attachment_data);

				wp_set_object_terms($attachment_id, $this->term, 'us_media_category');
				error_log("MediaImporter::uploadImage - New attachment uploaded. ID: $attachment_id for $url");
				return $attachment_id;
			}

			private function getOrUploadImage($url, $boat_id) {
				$attachment_id = $this->getAttachmentIdByFilename($url);
				if ($attachment_id) {
					wp_set_object_terms($attachment_id, $this->term, 'us_media_category');
					error_log("MediaImporter::getOrUploadImage - Using existing attachment ID: $attachment_id for $url");
					return $attachment_id;
				}

				return $this->uploadImage($url, $boat_id);
			}

			public function processImages() {
				if (empty($this->images) || !is_array($this->images)) {
					error_log("MediaImporter::processImages - No images to process");
					return;
				}

				$post_id = $this->boat_id;
				$gallery_attachment_ids = [];

				// Process first image as featured image
				$first_image = array_shift($this->images);
				if ($first_image) {
					$thumbnail_id = $this->getOrUploadImage($first_image, $post_id);
					if ($thumbnail_id) {
						set_post_thumbnail($post_id, $thumbnail_id);
						error_log("MediaImporter::processImages - Set featured image ID: $thumbnail_id for post $post_id");
					} else {
						error_log("MediaImporter::processImages - Failed to set featured image for $first_image");
					}
				}

				// Process remaining images for gallery
				foreach ($this->images as $image_url) {
					$attachment_id = $this->getOrUploadImage($image_url, $post_id);
					if ($attachment_id) {
						$gallery_attachment_ids[] = $attachment_id;
						error_log("MediaImporter::processImages - Added gallery image ID: $attachment_id for $image_url");
					} else {
						error_log("MediaImporter::processImages - Failed to process gallery image $image_url");
					}
				}

				// Update ACF gallery field
				if (!empty($gallery_attachment_ids)) {
					update_field('boat_media', $gallery_attachment_ids, $post_id);
					error_log("MediaImporter::processImages - Updated ACF gallery field with " . count($gallery_attachment_ids) . " images");
				}
			}
		};

		// Import images
		error_log("update_woocommerce_products_event - Starting image processing for boat ID: $boat_id");
		try {
			$media_importer->processImages();
			error_log("update_woocommerce_products_event - Finished processing images for boat ID: $boat_id");
		} catch (\Exception $e) {
			error_log("update_woocommerce_products_event - Image processing ERROR: " . $e->getMessage());
		}

		// Clear videos field first if it exists
		if (get_field('videos', $boat)) delete_field('videos', $boat);

		foreach ($advert->advert_media->media as $media) {
			// Initialise string parts of url
			$video_url = (string) $media;
			$type = (string) $media['type'];

			// Initialise file path
			$video_url_pathinfo = pathinfo($video_url);

			$video_url_dirname = $video_url_pathinfo['dirname'];
			$video_url_basename = $video_url_pathinfo['basename'];
			$video_url_extension = $video_url_pathinfo['extension'];
			$video_url_filename = $video_url_pathinfo['filename'];

			if (strpos($type, 'image/') === 0) {
				// FLV files not accepted
				error_log('Media is an image, not a video');
				continue;
			}

			if (strpos($type, 'video/flv') === 0) {
				// FLV files not accepted
				error_log('FLV videos not accepted');
				continue;
			}

			if(strpos($type, 'video/mp4') === 0 && $video_url_extension !== 'mp4'){
				// Video must be youtube
				add_row('videos', ['video_url' => $video_url], $boat);
				if(!in_array($video_url, $processed_media_urls)){
					// Add to processed URLs
					$processed_media_urls[] = $video_url;
					update_option('processed_media_urls', $processed_media_urls);
				}
			}

			if(strpos($type, 'video/mp4') === 0 && $video_url_extension === 'mp4'){
				/**
				 * Existing video
				 */
				if(in_array($video_url, $processed_media_urls)){
					global $wpdb;

					$query = $wpdb->prepare(
						"SELECT post_id
                        FROM {$wpdb->postmeta}
                        WHERE meta_key = '_wp_attached_file'
                        AND meta_value LIKE %s",
						$video_url_basename);

					$attachment_id = $wpdb->get_var($query);

					if($attachment_id){
						add_row('videos', ['video_url' => $video_url], $boat);
						wp_update_post([
							'ID' => $attachment_id,
							'post_parent' => $boat
						]);
					}
					continue;
				}

				/**
				 * New video
				 */
				$video_file_contents = file_get_contents($video_url);
				if($video_file_contents){
					$upload = wp_upload_bits($video_url_basename, null, $video_file_contents);
					if(!$upload['error']){
						$attachment = [
							'post_mime_type' => 'video/mp4',
							'post_title' => $video_url_basename,
							'post_content' => '',
							'post_status' => 'inherit'
						];

						$attachment_id = wp_insert_attachment($attachment, $upload['file'], $boat);
						add_row('videos', ['video_url' => $video_url], $boat);

						// Add to processed URLs
						$processed_media_urls[] = $video_url;
						update_option('processed_media_urls', $processed_media_urls);
					}
				}
			}
		}

		// Add manufacturer as a product tag
		$manufacturer = (string) $advert_features->manufacturer;

		if(!empty($manufacturer)){
			wp_set_object_terms($boat, $manufacturer, 'manufacturer', true);
			MarineSync_Post_Type::set_manufacturer($boat, $manufacturer);
		}

		// Add new or used status as a product tag
		$new_or_used = (string) $advert_features->new_or_used;

		if(!empty($new_or_used)){
			wp_set_object_terms($boat, $new_or_used, 'boat-condition', true);
			MarineSync_Post_Type::set_boat_field($boat, 'new_or_used', ucwords($new_or_used));
		}

		// Add Vat included
		$vat_included = (string) $advert_features->asking_price['vat_included'];
		if(!empty($vat_included)){
			if($vat_included === 'true'){
				$vat_label = "incl. VAT";
				MarineSync_Post_Type::set_boat_field($boat, 'vat_included', $vat_label);
			}else if($vat_included === 'false'){
				$vat_label = "excl. VAT";
				MarineSync_Post_Type::set_boat_field($boat, 'vat_included', $vat_label);
			}else{
				$vat_label = "";
				MarineSync_Post_Type::set_boat_field($boat, 'vat_included', $vat_label);
			}
		}

		// Add tax paid
		$vat_type = (string) $advert_features->asking_price['vat_type'];
		if(!empty($vat_type)){
			MarineSync_Post_Type::set_boat_field($boat, 'vat_type', $vat_type);
		}

		// Add boat category custom field
		if(!empty($boat_category)){
			MarineSync_Post_Type::set_boat_field($boat, 'boat_category', $boat_category);
			error_log($boat_category . " now set as boat category.");
		}

		// Add boat type custom field
		if(!empty($boat_type)){
			MarineSync_Post_Type::set_boat_field($boat, 'boat_type', $boat_type);
			error_log($boat_type . " now set as boat type.");
		}

		// Add LOA custom field
		$loa_item = $boat_features->dimensions->xpath('item[@name="loa"]');
		if (!empty($loa_item)) {
			$loa = (float) $loa_item[0];
			MarineSync_Post_Type::set_boat_field($boat, 'loa', $loa);
			error_log($loa . " now set as loa meta value.");
		}

		// Add Beam custom field
		$beam_item = $boat_features->dimensions->xpath('item[@name="beam"]');
		if (!empty($beam_item)) {
			$beam = (float) $beam_item[0];
			MarineSync_Post_Type::set_boat_field($boat, 'beam', $beam);
			error_log($beam . " now set as beam meta value.");
		}

		// Add Year custom field
		$year_item = $boat_features->build->xpath('item[@name="year"]');
		if (!empty($year_item)) {
			$year = (int) $year_item[0];
			MarineSync_Post_Type::set_boat_field($boat, 'year', $year);
			error_log($year . " now set as year meta value.");
		}

		// Add Hull material custom field
		$hull_material = (string) $boat_features->xpath('rb:additional/rb:item[@name="hull_material"]')[0];
		$hull_material_value = ucfirst($hull_material);
		if(!empty($hull_material_value)){
			MarineSync_Post_Type::set_boat_field($boat, 'hull_type', $hull_material_value);
			error_log("Hull material meta set to: " . $hull_material_value);
		}

		// Add Price custom field
		if($poa === "true"){
			MarineSync_Post_Type::set_asking_price($boat, 0);
			error_log("POA " . $poa . " set as post meta.");
		}else{
			MarineSync_Post_Type::set_asking_price($boat, $price);

			if($currency == 'GBP'){
				$currency_symbol = "£";
				MarineSync_Post_Type::set_boat_field($boat, 'currency', $currency_symbol);
			}else if($currency == 'EUR'){
				$currency_symbol = "€";
				MarineSync_Post_Type::set_boat_field($boat, 'currency', $currency_symbol);
			}else if($currency == 'USD'){
				$currency_symbol = "€";
				MarineSync_Post_Type::set_boat_field($boat, 'currency', $currency_symbol);
			}else{
				MarineSync_Post_Type::set_boat_field($boat, 'currency', $currency);
			}
		}

		error_log("Boat price set to: " . $price);

		$build_attributes = extract_items_from_parent($boat_features->build, $boat_id);

		$all_attributes = array_merge($all_attributes, $build_attributes);
		error_log(print_r($build_attributes) . " added to all attributes.");

		$dimensions_attributes = extract_items_from_parent($boat_features->dimensions, $boat_id);
		$all_attributes = array_merge($all_attributes, $dimensions_attributes);
		error_log(print_r($dimensions_attributes) . " added to all attributes.");

		$navigation_attributes = extract_items_from_parent($boat_features->navigation, $boat_id);
		$all_attributes = array_merge($all_attributes, $navigation_attributes);
		error_log(print_r($navigation_attributes) . " added to all attributes.");

		$engine_attributes = extract_items_from_parent($boat_features->engine, $boat_id);
		$all_attributes = array_merge($all_attributes, $engine_attributes);
		error_log(print_r($engine_attributes) . " added to all attributes.");

		$rigSails_attributes = extract_items_from_parent($boat_features->rig_sails, $boat_id);
		$all_attributes = array_merge($all_attributes, $rigSails_attributes);
		error_log(print_r($rigSails_attributes) . " added to all attributes.");

		$electronics_attributes = extract_items_from_parent($boat_features->electronics, $boat_id);
		$all_attributes = array_merge($all_attributes, $electronics_attributes);
		error_log(print_r($electronics_attributes) . " added to all attributes.");

		$galley_attributes = extract_items_from_parent($boat_features->galley, $boat_id);
		$all_attributes = array_merge($all_attributes, $galley_attributes);
		error_log(print_r($galley_attributes) . " added to all attributes.");

		$safety_features_attributes = extract_items_from_parent($boat_features->safety_equipment, $boat_id);
		$all_attributes = array_merge($all_attributes, $safety_features_attributes);
		error_log(print_r($safety_features_attributes) . " added to all attributes.");

		$accommodation_attributes = extract_items_from_parent($boat_features->accommodation, $boat_id);
		$all_attributes = array_merge($all_attributes, $accommodation_attributes);
		error_log(print_r($accommodation_attributes) . " added to all attributes.");

		$general_attributes = extract_items_from_parent($boat_features->general, $boat_id);
		$all_attributes = array_merge($all_attributes, $general_attributes);
		error_log(print_r($general_attributes) . " added to all attributes.");

		$equipment_attributes = extract_items_from_parent($boat_features->equipment, $boat_id);
		$all_attributes = array_merge($all_attributes, $equipment_attributes);
		error_log(print_r($equipment_attributes) . " added to all attributes.");

		MarineSync_Post_Type::save_boat($boat_id);
		error_log("Product completed.");
	}

	error_log('Loop has finished.');
}

// Extract items from parent nodes
function extract_items_from_parent($xmlSelector, $boat) {
	$attributes = array();

	// Get the boat ID (should already be an ID)
	$boat_id = $boat;

	foreach ($xmlSelector as $parentNode) {
		$attribute_name = str_replace("_", " ", $parentNode->getName());

		$options = array();
		foreach ($parentNode->children() as $item) {
			$name = (string) str_replace("_", " ", ucwords($item->attributes()->name));
			$value = (string) $item;
			$options[] = "{$name}: {$value}";

			// Set attribute as post meta for shortcode
			try {
				$field_key = str_replace(" ", "_", strtolower($name));

				// Check if this field exists as an ACF field
				if (function_exists('get_field_object') && get_field_object($field_key, $boat_id)) {
					// Field exists in ACF, use the MarineSync method
					MarineSync_Post_Type::set_boat_field($boat_id, $field_key, $value);
					error_log("ACF field found for {$field_key}, using set_boat_field method");
				} else {
					// Field doesn't exist in ACF, use regular post meta
					error_log("No ACF field found for {$field_key}, using regular post meta");
				}
			} catch (\Exception $e) {
				error_log("extract_items_from_parent - Error: " . $e->getMessage());
			}
		}

		error_log("$attribute_name imported");
	}

	return $attributes;
}

// Custom http request timeout
add_filter('http_request_timeout', 'custom_http_request_timeout');
function custom_http_request_timeout($timeout) {
	return 20; // Increase the timeout to 20 seconds
}

// Bypass https ssl verification
add_filter('https_ssl_verify', '__return_false');