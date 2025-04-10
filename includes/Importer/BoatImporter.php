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
			'title', 'featured_image', 'boat_media', 'content', 'boat_ref', 'boat_type', 'new_or_used', 'manufacturer', 'model', 'year', 'asking_price',
			'vessel_lying', 'boat_category', 'loa', 'lwl', 'beam', 'draft', 'air_draft', 'displacement', 'ballast', 'hull_type', 'hull_colour',
			'hull_construction', 'super_structure_colour', 'super_structure_construction', 'deck_colour', 'deck_construction', 'keel_type', 'flybridge',
			'control_type', 'where', 'fuel', 'cruising_speed', 'max_speed', 'horse_power', 'engine_manufacturer', 'engine_location', 'gear_box',
			'cylinders', 'propeller_type', 'starting_type', 'drive_type', 'cooling_system', 'bow_thruster', 'stern_thruster', 'hours', 'engine_quality',
			'tankage', 'litres_per_hour', 'gallons_per_hour', 'range', 'last_serviced', 'passenger_capacity', 'cabins', 'berths', 'bath', 'shower',
			'toilet', 'fridge', 'freezer', 'oven', 'microwave', 'heating', 'air_conditioning', 'television', 'cd_player', 'dvd_player', 'cockpit_type',
			'generator', 'inverter', 'battery', 'battery_charger', 'navigation_lights', 'compass', 'depth_instrument', 'wind_instrument', 'autopilot',
			'gps', 'vhf', 'plotter', 'speed_instrument', 'radar', 'life_raft', 'epirb', 'bilge_pump', 'fire_extinguisher', 'mob_system', 'genoa',
			'spinnaker', 'tri_sail', 'storm_jib', 'main_sail', 'winches', 'anchor', 'spray_hood', 'bimimi', 'fenders', 'designer', 'known_defects',
			'reg_details', 'advert', 'open_marine', 'broker', 'owners_comments', 'external_url', 'company_name', 'office', 'office_name', 'office_id',
			'title_person', 'forename', 'surname', 'address', 'town', 'county', 'country', 'postcode', 'daytime_phone', 'evening_phone', 'fax', 'mobile',
			'website', 'email'
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

			// Add an example row with updated image URLs
			$example_row = array_fill(0, count($headers), '');
			$example_row[array_search('title', $headers)] = 'Example Boat';
			$example_row[array_search('manufacturer', $headers)] = 'Bavaria';
			$example_row[array_search('model', $headers)] = 'E 40 Sedan';
			$example_row[array_search('year', $headers)] = '2016';
			$example_row[array_search('asking_price', $headers)] = '219950';
			$example_row[array_search('featured_image', $headers)] = 'https://cdn.pixabay.com/photo/2023/08/29/18/46/yacht-8221925_1280.jpg';
			$example_row[array_search('boat_media', $headers)] = 'https://cdn.pixabay.com/photo/2023/08/29/18/46/yacht-8221926_1280.jpg,https://cdn.pixabay.com/photo/2023/08/29/18/46/yacht-8221927_1280.jpg,https://cdn.pixabay.com/photo/2023/08/29/18/46/yacht-8221928_1280.jpg';
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
			if ($headers === false) {
				error_log('MS106: Failed to read CSV headers from file: ' . $csv);
				add_settings_error('marinesync_importer', 'header_error', 'Failed to read CSV headers', 'error');
				fclose($handle);
				return;
			}

			$row_count = 0;

			while (($data = fgetcsv($handle)) !== false) {
				// Ensure the row has the same number of columns as the headers
				if (count($data) !== count($headers)) {
					error_log('MS107: Row ' . ($row_count + 2) . ' has ' . count($data) . ' columns, expected ' . count($headers));
					add_settings_error(
						'marinesync_importer',
						'row_column_mismatch',
						'Row ' . ($row_count + 2) . ' has an incorrect number of columns (' . count($data) . ', expected ' . count($headers) . ')',
						'warning'
					);
					// Pad or trim the data array to match the headers
					$data = array_pad($data, count($headers), '');
					if (count($data) > count($headers)) {
						$data = array_slice($data, 0, count($headers));
					}
				}

				try {
					$row = array_combine($headers, $data);
					self::process_row($row);
					$row_count++;
				} catch (\Exception $e) {
					error_log('MS101: Error processing row ' . ($row_count + 2) . ': ' . $e->getMessage());
					add_settings_error(
						'marinesync_importer',
						'row_error',
						'Error in row ' . ($row_count + 2) . ': ' . $e->getMessage(),
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
				$error_message = is_wp_error($featured_image_id) ? $featured_image_id->get_error_message() : 'Unknown error';
				error_log('MS102: Failed to set featured image for post ID ' . $post_id . ': ' . $error_message);
				add_settings_error(
					'marinesync_importer',
					'featured_image_error_' . $post_id,
					"Failed to set featured image for '{$row['title']}' (Post ID: $post_id): $error_message",
					'warning'
				);
			}
		}

		// Handle gallery images
		if (!empty($row['boat_media'])) {
			$gallery_urls = array_filter(array_map('trim', explode(',', $row['boat_media'])));
			$gallery_image_ids = [];

			foreach ($gallery_urls as $index => $url) {
				$image_id = self::download_and_attach_image($url, $post_id, $row['title'] . ' Gallery Image ' . ($index + 1));
				if ($image_id && !is_wp_error($image_id)) {
					$gallery_image_ids[] = $image_id;
				} else {
					$error_message = is_wp_error($image_id) ? $image_id->get_error_message() : 'Unknown error';
					error_log('MS103: Failed to add gallery image ' . $url . ' for post ID ' . $post_id . ': ' . $error_message);
					add_settings_error(
						'marinesync_importer',
						'gallery_image_error_' . $post_id . '_' . $index,
						"Failed to add gallery image '$url' for '{$row['title']}' (Post ID: $post_id): $error_message",
						'warning'
					);
				}
			}

			// Update ACF field 'boat_media' with array of image IDs
			if (!empty($gallery_image_ids) && function_exists('update_field')) {
				update_field('boat_media', $gallery_image_ids, $post_id);
			}
		}

		// Store all other meta fields
		foreach ($row as $key => $value) {
			if (!empty($value) && $key !== 'title' && $key !== 'content' && $key !== 'featured_image' && $key !== 'boat_media') {
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

		// Set a custom user-agent to avoid being blocked by servers
		$args = [
			'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
			'timeout' => 30,
		];

		// Download the image with additional debugging
		$response = wp_remote_get($url, $args);
		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			error_log('MS104: Failed to download image from ' . $url . ': ' . $error_message);
			return new \WP_Error('download_failed', $error_message);
		}

		$http_code = wp_remote_retrieve_response_code($response);
		if ($http_code !== 200) {
			$error_message = "HTTP $http_code - " . wp_remote_retrieve_response_message($response);
			$headers = wp_remote_retrieve_headers($response);
			$headers_str = print_r($headers, true);
			error_log('MS104: Failed to download image from ' . $url . ': ' . $error_message . ' Headers: ' . $headers_str);
			return new \WP_Error('download_failed', "Failed to download image (HTTP $http_code): $error_message");
		}

		$body = wp_remote_retrieve_body($response);
		$temp_file = wp_tempnam(basename($url));
		if (!file_put_contents($temp_file, $body)) {
			error_log('MS104: Failed to write image to temp file: ' . $temp_file);
			return new \WP_Error('download_failed', 'Failed to write image to temporary file');
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