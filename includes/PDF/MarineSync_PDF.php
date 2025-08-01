<?php

namespace MarineSync\PDF;

require_once MARINESYNC_PLUGIN_DIR . 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use MarineSync\PostType\MarineSync_Post_Type;

final class MarineSync_PDF {
	private $boat_id;

	public function __construct($boat_id){
		$this->boat_id = $boat_id;
		error_log("MSPDF001: Constructed MarineSync_PDF for boat_id={$boat_id}");
	}

	/**
	 * Parse boat meta
	 *
	 * @return array
	 */
	private function parseBoatMeta(): array {
		error_log("MSPDF002: Starting parseBoatMeta for boat_id={$this->boat_id}");

		// Search for boat using MS methods
		$boat = $this->boat_id ?? 0;

		// Check if ID returns a boat
		if (!$boat) {
			error_log("MSPDF003: Boat not found with this ID: {$this->boat_id}");
			throw new \Exception('Boat not found with this ID: ' . $this->boat_id);
		}

		// Get featured image URL
		$featured_image_id = get_post_thumbnail_id($this->boat_id);
		$featured_image_url = wp_get_attachment_image_url($featured_image_id, 'full');
		error_log("MSPDF004: Featured image URL for boat_id={$this->boat_id} is " . ($featured_image_url ?: 'NOT FOUND'));

		// Get all ACF fields
		$acf_fields = get_fields($this->boat_id) ?: [];
		error_log("MSPDF005: Fetched " . count($acf_fields) . " ACF fields for boat_id={$this->boat_id}");

		// Get all meta fields (as fallback)
		$meta_fields = get_post_meta($this->boat_id);
		error_log("MSPDF006: Fetched " . count($meta_fields) . " meta fields for boat_id={$this->boat_id}");

		// Merge meta fields that are missing from ACF fields
		$merged = 0;
		foreach ($meta_fields as $key => $values) {
			if (!array_key_exists($key, $acf_fields)) {
				$acf_fields[$key] = (count($values) === 1) ? $values[0] : $values;
				$merged++;
			}
		}
		error_log("MSPDF007: Merged {$merged} meta fields into ACF fields for boat_id={$this->boat_id}");

		// add featured image
		$acf_fields['featured_image_url'] = $featured_image_url;

		// add company logo if needed
		$acf_fields['company_logo_url'] = content_url('uploads/2025/07/companylogo.png');

		error_log("MSPDF008: Returning combined meta for boat_id={$this->boat_id} with " . count($acf_fields) . " fields.");

		error_log(print_r($acf_fields, true));

		return $acf_fields;
	}

	/**
	 * Get Office details by office_id
	 * @param string|int $office_id
	 * @return array|null
	 */
	private function getOfficeDetails($office_id) {
		error_log("MSPDF016: Looking up office details for office_id={$office_id}");
		if (!$office_id) return null;

		$offices = get_field('offices', 'option');
		if (empty($offices) || !is_array($offices)) {
			error_log("MSPDF017: No offices found in ACF options");
			return null;
		}

		foreach ($offices as $office) {
			if (!empty($office['id']) && strval($office['id']) === strval($office_id)) {
				error_log("MSPDF018: Match found for office_id={$office_id}");
				return $office;
			}
		}

		error_log("MSPDF019: No match for office_id={$office_id}");
		return null;
	}

	/**
	 * Gather all ACF details fields and return as a single HTML string.
	 * @param int $post_id
	 * @return string
	 */
	private function getDetailsSectionsHtml($post_id) {
		// Get the main post content
		$post = get_post($post_id);
		$post_content = $post ? apply_filters('the_content', $post->post_content) : '';

		// The list of detail ACF fields
		$acf_fields = [
			'construction_details',
			'machinery_details',
			'electrics_details',
			'tankage_details',
			'accommodation_details',
			'domestic_details',
			'deck_details',
			'navigation_details',
			'tenders_details',
			'safety_details',
		];

		$html = '';
		// Add post content at the top
		if (!empty($post_content)) {
			$html .= $post_content . "\n";
		}

		// Loop through ACF detail fields and append if not empty
		foreach ($acf_fields as $acf_field) {
			$acf_value = get_field($acf_field, $post_id);
			if (!empty($acf_value)) {
				$label = ucwords(str_replace('_', ' ', str_replace('_details', '', $acf_field)));
				$html .= "<h3>{$label}</h3>\n" . $acf_value . "\n";
			}
		}

		return $html;
	}

	/**
	 * Generate HTML
	 *
	 * @return mixed
	 */
	private function generateHtml() {
		error_log("MSPDF009: Starting generateHtml for boat_id={$this->boat_id}");
		$boat_data = $this->parseBoatMeta();
		error_log("MSPDF010: Parsed boat data for HTML generation for boat_id={$this->boat_id}");

		$images = array_slice($boat_data['boat_media'], 0, 2);

		$office_id = $boat_data['office_id'] ?? '';
		$office = $this->getOfficeDetails($office_id);

		$office_tel = $office['daytime_phone'] ?? '';
		$office_email = $office['office_email'] ?? '';
		$office_address = trim(
			($office['address'] ?? '') . ', ' .
			($office['town'] ?? '') . ', ' .
			($office['county'] ?? '') . ', ' .
			($office['postcode'] ?? '') . ', ' .
			($office['country'] ?? '')
			, ', ');

		$details_html = $this->getDetailsSectionsHtml($this->boat_id);

		$post_title = get_post_field('post_title', $this->boat_id) ?? '';

		$additional_fields = [
			'control_type' => 'Control Type',
			'range' => 'Range',
			'last_serviced' => 'Last Serviced',
			'passenger_capacity' => 'Passenger Capacity',
			'where' => 'Where',
			'super_structure_colour' => 'Super Structure Colour',
			'super_structure_construction' => 'Super Structure Construction',
			'ballast' => 'Ballast',
			'displacement' => 'Displacement',
			'hours' => 'Hours',
			'epirb' => 'Epirb',
			'bilge_pump' => 'Bilge Pump',
			'fire_extinguisher' => 'Fire Extinguisher',
			'berths' => 'Berths',
			'known_defects' => 'Known Defects',
			'reg_details' => 'Reg Details',
			'owners_comments' => 'Owners Comments',
			'external_url' => 'External URL',
			'battery' => 'Battery',
			'mob_system' => 'Mob System',
			'open_marine' => 'Open Marine',
			'broker' => 'Broker'
		];


		// Gather additional info fields
		$additional_info_html = '';
		foreach ( $additional_fields as $field => $label ) {
			if ( ! empty( $boat_data[ $field ] ) ) {
				$additional_info_html .= "<p><strong>" . esc_html( $label ) . ":</strong> " . esc_html( $boat_data[ $field ] ) . "</p>";
			}
		}

		return "
		    <style>
		        @page {
		            margin: 0;
		            padding: 0;
		        }
		        body {
		            margin: 1cm 1.5cm;
		            position: relative;
		        }
		        .first-page {
		            page-break-after: always;
		            position: relative;
		        }
		        .page-break {
		            page-break-before: always;
		        }
		        .boat-details-table th, .boat-details-table td {
		            font-size: 13px;
		        }
		        .content-wrapper {
		            position: relative;
		            min-height: 100%;
		            padding-bottom: 1.4cm;
		        }
		    </style>
		    <div class='content-wrapper'>
		        <div style='font-family: Arial, sans-serif;color:#2E5274;'>
		            <div class='first-page'>
		                <table style='width: 100%; margin-bottom: 20px;'>
		                    <tr>
		                        <td style='width: 70%;line-height: 0.7em'>
		                            <h2 style='margin: 0;'>" . $post_title . "</h2>
		                            <span style='display: block; font-size: 20px; margin: 10px 0;'>£" . esc_html(number_format($boat_data['price'])) . " " . esc_html($boat_data['vat_type'])."</span>
		                            <span style='display: block;'>" . esc_html($boat_data['vessel_lying']) . "</span>
		                        </td>
		                        <td style='width: 30%; text-align: right;'>
		                            <img src='" . $boat_data['company_logo_url'] . "' style='width: 200px;'><br>
		                            <span>Tel: " . esc_html($office_tel) . "</span><br>
									<span>Email: " . esc_html($office_email) . "</span>
		                        </td>
		                    </tr>
		                </table>
		                <img src='" . esc_url($boat_data['featured_image_url']) . "' style='width: 100%; display: block;'>
		         

		                <div>
		                    <h2>Boat Details</h2>
		                    <hr style='border: 1px solid #000; margin: 10px 0 20px 0;'>
		                    <table class='boat-details-table' style='width: 100%; border-collapse: collapse; border: 1px solid #000;'>
		                        <tr>
		                            <td style='border: 1px solid #000; padding: 8px; width: 20%; color: #888;'>Make:</td>
		                            <td style='border: 1px solid #000; padding: 8px; width: 30%; color: #336699;'><strong>" . esc_html($boat_data['manufacturer']) . "</strong></td>
		                            <td style='border: 1px solid #000; padding: 8px; width: 20%; color: #888;'>Model:</td>
		                            <td style='border: 1px solid #000; padding: 8px; width: 30%; color: #336699;'><strong>" . esc_html($boat_data['model']) . "</strong></td>
		                        </tr>
		                        <tr>
		                            <td style='border: 1px solid #000; padding: 8px; color: #888;'>Hull Material:</td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['hull_type']) . "</strong></td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #888;'>Year:</td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['year']) . "</strong></td>
		                        </tr>
		                        <tr>
		                            <td style='border: 1px solid #000; padding: 8px; color: #888;'>Beam:</td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['beam']) . "</strong></td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #888;'>Fuel Type:</td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html(ucfirst($boat_data['fuel'])) . "</strong></td>
		                        </tr>
		                        <tr>
		                            <td style='border: 1px solid #000; padding: 8px; color: #888;'>Length:</td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['loa']) . "</strong></td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #888;'>Boat Location:</td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['vessel_lying']) . "</strong></td>
		                        </tr>
		                        <tr>
		                            <td style='border: 1px solid #000; padding: 8px; color: #888;'>Price:</td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>£" . esc_html(number_format($boat_data['price'])) . "</strong></td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #888;'>Max Speed:</td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['max_speed']) . "</strong></td>
		                        </tr>
		                        <tr>
		                            <td style='border: 1px solid #000; padding: 8px; color: #888;'>Name:</td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['boat_name'] ?? $post_title) . "</strong></td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #888;'>Max Draft:</td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['max_draft'] ?? ($boat_data['draft'] ?? '')) . "</strong></td>
		                        </tr>
		                        <tr>
		                            <td style='border: 1px solid #000; padding: 8px; color: #888;'>Condition:</td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['new_or_used']) . "</strong></td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #888;'>Cabins:</td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['cabins']) . "</strong></td>
		                        </tr>
		                    </table>
		                </div>
		            </div>
		            <div style='color: black;'>
		                <h2 style='color:#2E5274;'>Boat Description</h2>
		                <hr style='border: 1px solid #000; margin: 10px 0 20px 0;'>
		                ". $details_html . "
		            </div>
		            <div>
		                <h2>Information & Features</h2>
		                <hr style='border: 1px solid #000; margin: 10px 0 20px 0;'>
		                <table style='width: 100%; border-collapse: collapse; border: 1px solid #000;'>
		                    <tr>
		                        <td style='border: 1px solid #000; padding: 8px; width: 20%; color: #888;'>Engine Make:</td>
		                        <td style='border: 1px solid #000; padding: 8px; width: 30%; color: #336699;'><strong>" . esc_html($boat_data['engine_manufacturer']) . "</strong></td>
		                        <td style='border: 1px solid #000; padding: 8px; width: 20%; color: #888;'>Engine Model:</td>
		                        <td style='border: 1px solid #000; padding: 8px; width: 30%; color: #336699;'><strong>" . esc_html($boat_data['engine_model']) . "</strong></td>
		                    </tr>
		                    <tr>
		                        <td style='border: 1px solid #000; padding: 8px; color: #888;'>Engine Quantity:</td>
		                        <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['engine_quantity']) . "</strong></td>
		                        <td style='border: 1px solid #000; padding: 8px; color: #888;'>Horse Power:</td>
		                        <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['horse_power']) . "</strong></td>
		                    </tr>
		                    <tr>
		                        <td style='border: 1px solid #000; padding: 8px; color: #888;'>Cruising Speed:</td>
		                        <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['cruising_speed']) . "</strong></td>
		                        <td style='border: 1px solid #000; padding: 8px; color: #888;'>Drive Type:</td>
		                        <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['drive_type']) . "</strong></td>
		                    </tr>
		                    <tr>
		                        <td style='border: 1px solid #000; padding: 8px; color: #888;'>Min Draft:</td>
		                        <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['min_draft']) . "</strong></td>
		                        <td style='border: 1px solid #000; padding: 8px; color: #888;'>Air Draft:</td>
		                        <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['air_draft']) . "</strong></td>
		                    </tr>
		                    <tr>
		                        <td style='border: 1px solid #000; padding: 8px; color: #888;'>Bow Thruster:</td>
		                        <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['bow_thruster']) . "</strong></td>
		                        <td style='border: 1px solid #000; padding: 8px; color: #888;'>Stern Thruster:</td>
		                        <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['stern_thruster']) . "</strong></td>
		                    </tr>
		                    <tr>
		                        <td style='border: 1px solid #000; padding: 8px; color: #888;'>Generator:</td>
		                        <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['generator']) . "</strong></td>
		                        <td style='border: 1px solid #000; padding: 8px; color: #888;'>Warranty:</td>
		                        <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['warranty']) . "</strong></td>
		                    </tr>
		                </table>
		            </div>
		            <div class='page-break'>
		                <h2>Additional Information</h2>
		                <hr style='border: 1px solid #000; margin: 10px 0 20px 0;'>
		                ".$additional_info_html."
		            </div>
		        </div>
		    </div>
		</div>";
	}

	/**
	 * Generate PDF
	 *
	 * @return void
	 */
	public function generatePdf(): void {
		error_log("MSPDF011: Called generatePdf for boat_id={$this->boat_id}");

		try {
			// Pass in boat meta to retrieve data
			$boat_data = $this->parseBoatMeta() ?? [];
			$boat_name = $boat_data['boat_name'] ?? get_post_field('post_title', $this->boat_id);

			// Return nothing if boat data is an empty array
			if (empty($boat_data)) {
				error_log("MSPDF012: Empty boat_data array for boat_id={$this->boat_id}");
				return;
			}

			// Configure options
			$options = new Options();
			$options->set('isHtml5ParserEnabled', true);
			$options->set('isPhpEnabled', true);
			$options->set('isRemoteEnabled', true);

			$dompdf = new Dompdf($options);

			$html = $this->generateHtml();
			error_log("MSPDF013: Generated HTML for boat_id={$this->boat_id} (" . strlen($html) . " chars)");

			$dompdf->loadHtml($html);
			$dompdf->setPaper('A4', 'portrait');
			$dompdf->render();

			error_log("MSPDF014: PDF rendered for boat_id={$this->boat_id}");

			header('Content-Type: application/pdf');
			header('Content-Disposition: inline; filename="'.$boat_name.'.pdf"');

			$dompdf->stream("{$boat_name}.pdf", ["Attachment" => false]);
			error_log("MSPDF015: PDF stream sent for boat_id={$this->boat_id}");
		} catch (\Exception $e) {
			error_log("MSPDF099: Exception in generatePdf for boat_id={$this->boat_id}: " . $e->getMessage());
			wp_die('Error generating PDF: ' . esc_html($e->getMessage()));
		}
	}
}
