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

require_once(SP_RIGHTBOAT_PATH . 'vendor/autoload.php');

require_once(SP_RIGHTBOAT_PATH . 'Libraries/Media/MediaImporter.php');
use \Libraries\Media\MediaImporter;

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
	// XML Feed URL
	$xml_feed_url = get_option('xml_feed_url');

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
		$boat_id = MarineSync_Post_Type::get_boat_by_ref($sku);

		// Initialize all attributes
		$all_attributes = array();

		if ($boat_id) {
			// Boat exists, update it
			$boat = get_post($boat_id);
            $boat = $boat->ID;
			error_log('Boat (ID: ' . $boat_id . ') updating...');
		} else {
			// Product doesn't exist, create a new one
			$boat = wp_insert_post([
                    'post_title' => $title,
                    'post_content' => $description_result,
                    'post_status' => 'publish'
            ]);
			MarineSync_Post_Type::set_boat_field(
				    $boat,
                    'boat_ref',
                    $sku
            ); // Set the ref for the new boat
			error_log('Creating Boat: ' . $boat . ' ...');
		}

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
		$media_importer = new MediaImporter($boat_images, $boat, 'marinesync-media-importer');

		try {
			// Import images
			error_log("update_woocommerce_products_event - Processing images manually.");
			$media_importer->processImages();
			error_log("update_woocommerce_products_event - Finished processing images.");
		} catch (\Exception $e) {
			error_log("update_woocommerce_products_event - Image processing ERROR: " . $e->getMessage());
		}

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
			update_post_meta($product->get_id(), 'vat_type', $vat_type);
		}

		// Add Vessel Lying custom field
		$vessel_lying = $advert_features->vessel_lying;
		$vessel_lying_attribute = '';
		if(!empty($vessel_lying)) {
			// Set meta value
			$location = (string) $vessel_lying;
			update_post_meta($product->get_id(), 'location', $location);
			error_log($location . " now set as location meta value.");

			// Set product attribute
			$vessel_lying_attribute = new WC_Product_Attribute();
			$vessel_lying_attribute->set_name('Location');
			$vessel_lying_attribute->set_visible(true);
			$vessel_lying_attribute->set_variation(false);
			$vessel_lying_attribute->set_options([$location]);
		}

		// Add boat category custom field
		if(!empty($boat_category)){
			update_post_meta($product->get_id(), 'boat_category', $boat_category);
			error_log($boat_category . " now set as boat category.");
		}

		// Add boat type custom field
		if(!empty($boat_type)){
			update_post_meta($product->get_id(), 'boat_type', $boat_type);
			error_log($boat_type . " now set as boat type.");
		}

		// Add LOA custom field
		$loa_item = $boat_features->dimensions->xpath('item[@name="loa"]');
		if (!empty($loa_item)) {
			$loa = (float) $loa_item[0];
			update_post_meta($product->get_id(), 'loa', $loa);
			error_log($loa . " now set as loa meta value.");
		}

		// Add Beam custom field
		$beam_item = $boat_features->dimensions->xpath('item[@name="beam"]');
		if (!empty($beam_item)) {
			$beam = (float) $beam_item[0];
			update_post_meta($product->get_id(), 'beam', $beam);
			error_log($beam . " now set as beam meta value.");
		}

		// Add Year custom field
		$year_item = $boat_features->build->xpath('item[@name="year"]');
		if (!empty($year_item)) {
			$year = (int) $year_item[0];
			update_post_meta($product->get_id(), 'year', $year);
			error_log($year . " now set as year meta value.");
		}

		// Add hull material
		try {
			$hull_material = (string) $boat_features->xpath('rb:additional/rb:item[@name="hull_material"]')[0];
			$hull_material_value = ucfirst($hull_material);
			if(!empty($hull_material_value)){
				update_post_meta($product->get_id(), '_rightboat_metadata_rb_additional_hull_material', $hull_material_value);
				error_log("Hull material meta set to: " . $hull_material_value);
			}
		} catch (\Exception $e) {
			error_log("Hull material error: " . $e->getMessage());
		}


		// Clear the field first
		update_post_meta($product->get_id(), '_rightboat_metadata_all_additional', '');

		// Initialize array fresh for this product
		$additional_items = [];
		$additional_nodes = $advert->boat_features->xpath('rb:additional/rb:item');
		if(!empty($additional_nodes)){
			foreach($additional_nodes as $item){
				$name = (string) $item['name'];
				$value = (string) $item;
				$additional_items[] = str_replace("_", " ", ucfirst($name)) . ': ' . ucfirst($value);
			}


			$all_additional_info = implode("\n", $additional_items);
			update_post_meta($product->get_id(), '_rightboat_metadata_all_additional', $all_additional_info);
			error_log("All additional info saved: " . $all_additional_info);
		}

		// Add Price custom field
		if($poa === "true"){
			update_post_meta($product->get_id(), 'asking_price', "POA");
			error_log("POA " . $poa . " set as post meta.");
		}else{
			MarineSync_Post_Type::set_asking_price($boat, (int) $price);

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
				$concat_currency_price = number_format($price) . ' ' . $currency;
				MarineSync_Post_Type::set_boat_field($boat, 'currency', $currency_symbol);
			}
		}

		error_log("Boat price set to: " . $price);

		$build_attributes = extract_items_from_parent($boat_features->build, $product);
		$all_attributes = array_merge($all_attributes, $build_attributes);
		error_log(print_r($build_attributes) . " added to all attributes.");

		$dimensions_attributes = extract_items_from_parent($boat_features->dimensions, $product);
		$all_attributes = array_merge($all_attributes, $dimensions_attributes);
		error_log(print_r($dimensions_attributes) . " added to all attributes.");

		$navigation_attributes = extract_items_from_parent($boat_features->navigation, $product);
		$all_attributes = array_merge($all_attributes, $navigation_attributes);
		error_log(print_r($navigation_attributes) . " added to all attributes.");

		$engine_attributes = extract_items_from_parent($boat_features->engine, $product);
		$all_attributes = array_merge($all_attributes, $engine_attributes);
		error_log(print_r($engine_attributes) . " added to all attributes.");

		$rigSails_attributes = extract_items_from_parent($boat_features->rig_sails, $product);
		$all_attributes = array_merge($all_attributes, $rigSails_attributes);
		error_log(print_r($rigSails_attributes) . " added to all attributes.");

		$electronics_attributes = extract_items_from_parent($boat_features->electronics, $product);
		$all_attributes = array_merge($all_attributes, $electronics_attributes);
		error_log(print_r($electronics_attributes) . " added to all attributes.");

		$galley_attributes = extract_items_from_parent($boat_features->galley, $product);
		$all_attributes = array_merge($all_attributes, $galley_attributes);
		error_log(print_r($galley_attributes) . " added to all attributes.");

		$safety_features_attributes = extract_items_from_parent($boat_features->safety_equipment, $product);
		$all_attributes = array_merge($all_attributes, $safety_features_attributes);
		error_log(print_r($safety_features_attributes) . " added to all attributes.");

		$accommodation_attributes = extract_items_from_parent($boat_features->accommodation, $product);
		$all_attributes = array_merge($all_attributes, $accommodation_attributes);
		error_log(print_r($accommodation_attributes) . " added to all attributes.");

		$general_attributes = extract_items_from_parent($boat_features->general, $product);
		$all_attributes = array_merge($all_attributes, $general_attributes);
		error_log(print_r($general_attributes) . " added to all attributes.");

		$equipment_attributes = extract_items_from_parent($boat_features->equipment, $product);
		$all_attributes = array_merge($all_attributes, $equipment_attributes);
		error_log(print_r($equipment_attributes) . " added to all attributes.");

		array_unshift($all_attributes, $vessel_lying_attribute);

		$product->set_attributes($all_attributes);
		error_log(print_r($all_attributes));

		$product->save();
		error_log("Product completed.");
	}

	error_log('Loop has finished.');
}

// Extract items from parent nodes
function extract_items_from_parent($xmlSelector, $product) {
	$attributes = array();

	foreach ($xmlSelector as $parentNode) {
		$attribute = new WC_Product_Attribute();
		$attribute_name = str_replace("_", " ", $parentNode->getName());
		$attribute->set_name(ucwords($attribute_name));
		$attribute->set_visible(true);
		$attribute->set_variation(false);

		$options = array();
		foreach ($parentNode->children() as $item) {
			// Continue configuring attribute
			$name = (string) str_replace("_", " ", ucwords($item->attributes()->name));
			$value = (string) $item;
			$options[] = "{$name}: {$value}";

			// Set attribute as post meta for shortcode
			try {
				$no_us = str_replace(" ", "_", $name);

				update_post_meta($product->get_id(), "_rightboat_metadata_" . $no_us, $value);
			} catch (\Exception $e) {
				error_log("extract_items_from_parent - Error: " . $e->getMessage());
			}
		}

		if (!empty($options)) {
			$attribute->set_options($options);
			$attribute->set_position(count($attributes));
			$attributes[] = $attribute;
		}

		error_log("$attribute_name imported");
	}

	return $attributes;
}

function get_attachment_id_from_url($url) {
	global $wpdb;

	// Check if the image already exists in the media library based on the URL
	$attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid='%s'", $url));

	return $attachment ? $attachment[0] : false;
}

// Check if image exists by URL
function get_uploaded_image_urls() {
	$uploaded_images = get_option('uploaded_image_urls', array());
	return $uploaded_images;
}

function add_uploaded_image_url($url) {
	$uploaded_images = get_uploaded_image_urls();
	$uploaded_images[] = $url;
	update_option('uploaded_image_urls', array_unique($uploaded_images));
}
function deletion($adverts) {
	// Initialise SKUs array
	$current_skus = [];
	$existing_skus = [];

	// Retrieve adverts
	foreach ($adverts as $advert) {
		// Define sku
		$sku = (string) $advert['ref'];
		$current_skus[] = $sku;

		error_log("deletion() - Feed Item REF: $sku");
	}

	// Build WP Query
	$category_slug = 'rightboat';

	// Define arguments for query
	$args = array(
		'post_type' => 'product',
		'posts_per_page' => -1,
		'post_status' => 'publish',
		'tax_query' => array(
			array(
				'taxonomy' => 'product_cat',
				'field' => 'slug',
				'terms' => $category_slug
			)
		),
		'fields' => 'ids'
	);

	// Create new products query instance
	$products_query = new WP_Query($args);

	// Get the product IDs
	$product_ids = $products_query->posts;

	// Loop through product IDs and get the SKU for each product
	foreach ($product_ids as $product_id) {
		$product = wc_get_product($product_id);
		if ($product) {
			$sku = $product->get_sku();
			$existing_skus[] = $sku;

			error_log("deletion() - Product SKU: $sku");
		}
	}

	// Compare two arrays to find outliers
	$sku_outliers = array_diff($existing_skus, $current_skus);

	// Loop through SKUs to delete corresponding products
	foreach ($sku_outliers as $outlier) {
		// Get product ID by SKU
		$product_id = wc_get_product_id_by_sku($outlier);
		if ($product_id) {
			$product = wc_get_product($product_id);
			$sold_boats_process = get_option('sold_boats_process');

			if ($sold_boats_process === "delete") {
				$product->delete(true);
			} elseif ($sold_boats_process === "draft") {
				$product->set_status('draft');
				$product->save();
			} elseif ($sold_boats_process === "hide") {
				$product->set_catalog_visibility('hidden');
				$product->save();
			} elseif ($sold_boats_process === "sold") {
				$product->set_stock_status('outofstock');
				update_post_meta($product_id, 'current_status', "Sold"); // set status meta value to Sold rather than Available

				wp_set_object_terms($product_id, 'Sold', 'product_tag');

				// Remove the "Available" tag if it exists
				$available_tag = get_term_by('name', 'Available', 'product_tag');
				if ($available_tag) {
					wp_remove_object_terms($product->get_id(), $available_tag->term_id, 'product_tag');
					error_log("Available tag removed from the product.");
				}

				$product->save();
			}

			error_log('Product ' . $outlier . ' no longer for sale, status set to ' . get_option('sold_boats_process') . '.');
		}
	}
}

// Custom http request timeout
add_filter('http_request_timeout', 'custom_http_request_timeout');
function custom_http_request_timeout($timeout) {
	return 20; // Increase the timeout to 20 seconds
}

// Bypass https ssl verification
add_filter('https_ssl_verify', '__return_false');