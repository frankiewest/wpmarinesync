<?php

namespace MarineSync\Importer;

final class BoatImporter {
	/**
	 * Generates a CSV template file for boat imports
	 * @return string Path to the generated template file or empty string on failure
	 */
	public static function generate_csv_template(): string {
		// Define all fields from your XML export
		$headers = [
			'title',                // Post title (boat name/model)
			'featured_image',       // Featured image URL
			'boat_media',              // Gallery images (comma-separated URLs)
			'content',             // Main description
			'boat_ref',            // Reference number
			'boat_type',           // Power/Sail
			'new_or_used',         // New/Used
			'manufacturer',        // Brand
			'model',              // Model
			'year',               // Build year
			'asking_price',       // Price
			'vessel_lying',       // Location
			'boat_category',      // Category (Sports Cruiser Boats, etc.)
			'loa',                // Length Overall
			'lwl',                // Length Waterline
			'beam',              // Width
			'draft',             // Draft
			'air_draft',         // Air draft
			'displacement',      // Displacement
			'ballast',           // Ballast
			'hull_type',         // Hull type
			'hull_colour',       // Hull color
			'hull_construction', // Hull construction
			'super_structure_colour',      // Superstructure color
			'super_structure_construction', // Superstructure construction
			'deck_colour',         // Deck color
			'deck_construction',   // Deck construction
			'keel_type',           // Keel type
			'flybridge',           // Yes/No
			'control_type',        // Control type
			'where',              // Additional location info
			'fuel',               // Fuel type
			'cruising_speed',     // Knots
			'max_speed',          // Knots
			'horse_power',        // Engine HP
			'engine_manufacturer', // Engine brand
			'engine_location',     // Engine location
			'gear_box',           // Gearbox type
			'cylinders',          // Number of cylinders
			'propeller_type',     // Propeller type
			'starting_type',      // Starting type
			'drive_type',         // Drive type
			'cooling_system',     // Cooling system
			'bow_thruster',       // On/Off
			'stern_thruster',     // On/Off
			'hours',              // Engine hours
			'engine_quality',     // Engine condition
			'tankage',            // Number of tanks
			'litres_per_hour',    // Fuel consumption
			'gallons_per_hour',   // Fuel consumption
			'range',              // Range
			'last_serviced',      // Last service date
			'passenger_capacity', // Passenger capacity
			'cabins',            // Yes/No or count
			'berths',            // Number of berths
			'bath',              // Yes/No
			'shower',            // Yes/No
			'toilet',            // Yes/No
			'fridge',            // Yes/No
			'freezer',           // Yes/No
			'oven',              // Yes/No
			'microwave',         // Yes/No
			'heating',           // Yes/No
			'air_conditioning',  // Yes/No
			'television',        // Yes/No
			'cd_player',         // Yes/No
			'dvd_player',        // Yes/No
			'cockpit_type',      // Cockpit type
			'generator',         // Yes/No
			'inverter',          // Yes/No
			'battery',           // Yes/No
			'battery_charger',   // Yes/No
			'navigation_lights', // Yes/No
			'compass',           // Yes/No
			'depth_instrument',  // Yes/No
			'wind_instrument',   // Yes/No
			'autopilot',         // Yes/No
			'gps',              // Yes/No
			'vhf',              // Yes/No
			'plotter',           // Yes/No
			'speed_instrument',  // Yes/No
			'radar',            // Yes/No
			'life_raft',         // Yes/No
			'epirb',             // Yes/No
			'bilge_pump',        // Yes/No
			'fire_extinguisher', // Yes/No
			'mob_system',        // Yes/No
			'genoa',             // Yes/No
			'spinnaker',         // Yes/No
			'tri_sail',          // Yes/No
			'storm_jib',         // Yes/No
			'main_sail',         // Yes/No
			'winches',           // Yes/No
			'anchor',            // Yes/No
			'spray_hood',        // Yes/No
			'bimimi',            // Yes/No (probably meant to be "bimini")
			'fenders',           // Yes/No
			'designer',          // Designer name
			'known_defects',     // Known issues
			'reg_details',       // Registration details
			'advert',            // Advertisement info
			'open_marine',       // Open marine info
			'broker',            // Broker info
			'owners_comments',   // Owner comments
			'external_url',      // External URL
			// Contact/office fields
			'company_name',
			'office',
			'office_name',
			'office_id',
			'title_person',      // Using "title" as title_person to avoid conflict
			'forename',
			'surname',
			'address',
			'town',
			'county',
			'country',
			'postcode',
			'daytime_phone',
			'evening_phone',
			'fax',
			'mobile',
			'website',
			'email'
		];

		// Create temporary file
		$temp_file = wp_tempnam('boat_import_template');

		if (!$temp_file) {
			return '';
		}

		// Open file for writing
		if (($handle = fopen($temp_file, 'w')) !== false) {
			// Write CSV headers
			fputcsv($handle, $headers);

			// Add an example row (optional)
			$example_row = array_fill(0, count($headers), '');
			// Fill some example values
			$example_row[array_search('title', $headers)] = 'Example Boat';
			$example_row[array_search('manufacturer', $headers)] = 'Bavaria';
			$example_row[array_search('model', $headers)] = 'E 40 Sedan';
			$example_row[array_search('year', $headers)] = '2016';
			$example_row[array_search('asking_price', $headers)] = '219950';
			fputcsv($handle, $example_row);

			fclose($handle);
			return $temp_file;
		}

		return '';
	}
	public static function process_csv($csv): void {
		if (!file_exists($csv) || !is_readable($csv)) {
			error_log('MS100: Cannot read CSV file: ' . $csv);
			add_settings_error('marinesync_importer', 'file_error', 'Cannot read CSV file', 'error');
			return;
		}

		if (($handle = fopen($csv, 'r')) !== false) {
			$headers = fgetcsv($handle);
			$row_count = 0;

			while (($data = fgetcsv($handle)) !== false) {
				try {
					$row = array_combine($headers, $data);
					self::process_row($row);
					$row_count++;
				} catch (\Exception $e) {
					error_log('MS101: Error processing row ' . $row_count . ': ' . $e->getMessage());
					add_settings_error(
						'marinesync_importer',
						'row_error',
						'Error in row ' . $row_count . ': ' . $e->getMessage(),
						'warning'
					);
				}
			}

			fclose($handle);
			add_settings_error(
				'marinesync_importer',
				'import_success',
				"Successfully processed $row_count rows",
				'success'
			);
		}
	}

	private static function process_row(array $row): void {
		$post_data = [
			'post_title' => $row['title'] ?? '',
			'post_content' => $row['content'] ?? '',
			'post_type' => 'marinesync-boats',
			'post_status' => 'publish'
		];

		$post_id = wp_insert_post($post_data);

		if (is_wp_error($post_id)) {
			throw new \Exception('Failed to create boat post: ' . $post_id->get_error_message());
		}

		// Handle featured image
		if (!empty($row['featured_image'])) {
			$featured_image_id = self::download_and_attach_image($row['featured_image'], $post_id, $row['title'] . ' Featured Image');
			if ($featured_image_id && !is_wp_error($featured_image_id)) {
				set_post_thumbnail($post_id, $featured_image_id);
			} else {
				error_log('MS102: Failed to set featured image for post ID ' . $post_id);
			}
		}

		// Handle gallery images
		if (!empty($row['gallery'])) {
			$gallery_urls = array_filter(array_map('trim', explode(',', $row['gallery'])));
			$gallery_image_ids = [];

			foreach ($gallery_urls as $index => $url) {
				$image_id = self::download_and_attach_image($url, $post_id, $row['title'] . ' Gallery Image ' . ($index + 1));
				if ($image_id && !is_wp_error($image_id)) {
					$gallery_image_ids[] = $image_id;
				} else {
					error_log('MS103: Failed to add gallery image ' . $url . ' for post ID ' . $post_id);
				}
			}

			// Update ACF field 'boat_media' with array of image IDs
			if (!empty($gallery_image_ids) && function_exists('update_field')) {
				update_field('boat_media', $gallery_image_ids, $post_id);
			}
		}

		// Store all other meta fields
		foreach ($row as $key => $value) {
			if (!empty($value) && $key !== 'title' && $key !== 'content' && $key !== 'featured_image' && $key !== 'gallery') {
				update_post_meta($post_id, $key, sanitize_text_field($value));
			}
		}
	}

	/**
	 * Downloads an image from a URL and attaches it to a post
	 *
	 * @param string $url Image URL
	 * @param int $post_id Post ID to attach the image to
	 * @param string $description Image description
	 * @return int|WP_Error Image attachment ID or WP_Error on failure
	 */
	private static function download_and_attach_image(string $url, int $post_id, string $description) {
		// Ensure WordPress media handling functions are available
		require_once(ABSPATH . 'wp-admin/includes/media.php');
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/image.php');

		// Download the image
		$temp_file = download_url($url);
		if (is_wp_error($temp_file)) {
			error_log('MS104: Failed to download image from ' . $url . ': ' . $temp_file->get_error_message());
			return $temp_file;
		}

		// Prepare file array for upload
		$file_array = [
			'name' => basename($url),
			'tmp_name' => $temp_file
		];

		// Determine file type
		$file_type = wp_check_filetype($file_array['name']);
		if (empty($file_type['type'])) {
			$file_array['name'] .= '.jpg'; // Fallback to .jpg if type can't be determined
		}

		// Upload the image to the media library
		$attachment_id = media_handle_sideload($file_array, $post_id, $description);

		// Clean up temporary file
		@unlink($temp_file);

		if (is_wp_error($attachment_id)) {
			error_log('MS105: Failed to upload image from ' . $url . ': ' . $attachment_id->get_error_message());
			return $attachment_id;
		}

		return $attachment_id;
	}
}