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
		$featured_image_url = wp_get_attachment_image_url($featured_image_id);
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
		$acf_fields['company_logo_url'] = MARINESYNC_PLUGIN_URL . 'assets/images/company-logo.png';

		error_log("MSPDF008: Returning combined meta for boat_id={$this->boat_id} with " . count($acf_fields) . " fields.");
		return $acf_fields;
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
		                            <h2 style='margin: 0;'>" . esc_html($boat_data['name']) . "</h2>
		                            <span style='display: block; font-size: 20px; margin: 10px 0;'>£" . esc_html(number_format($boat_data['price'])) . " " . esc_html($boat_data['vat_type'])."</span>
		                            <span style='display: block;'>" . esc_html($boat_data['vessel_lying']) . "</span>
		                        </td>
		                        <td style='width: 30%; text-align: right;'>
		                            <img src='" . $boat_data['company_logo_url'] . "' style='width: 200px;'>
		                            <span>Tel: " . esc_html($boat_data['contact_no']) . "</span>
		                            <span>Email: " . esc_html($boat_data['contact_email']) . "</span>
		                        </td>
		                    </tr>
		                </table>
		                <div style='margin-bottom: 20px;'>
		                    <img src='" . $boat_data['featured_image'] . "' style='width: 100%;'>
		                </div>
		                <div>
		                    <h2>Boat Details</h2>
		                    <hr style='border: 1px solid #000; margin: 10px 0 20px 0;'>
		                    <table class='boat-details-table' style='width: 100%; border-collapse: collapse; border: 1px solid #000;'>
		                        <tr>
		                            <td style='border: 1px solid #000; padding: 8px; width: 20%; color: #888;'>Make:</td>
		                            <td style='border: 1px solid #000; padding: 8px; width: 30%; color: #336699;'><strong>" . esc_html($boat_data['make']) . "</strong></td>
		                            <td style='border: 1px solid #000; padding: 8px; width: 20%; color: #888;'>Model:</td>
		                            <td style='border: 1px solid #000; padding: 8px; width: 30%; color: #336699;'><strong>" . esc_html($boat_data['model']) . "</strong></td>
		                        </tr>
		                        <tr>
		                            <td style='border: 1px solid #000; padding: 8px; color: #888;'>Hull Material:</td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['hull_material']) . "</strong></td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #888;'>Year:</td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['year']) . "</strong></td>
		                        </tr>
		                        <tr>
		                            <td style='border: 1px solid #000; padding: 8px; color: #888;'>Beam:</td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['beam']) . "</strong></td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #888;'>Fuel Type:</td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html(ucfirst($boat_data['fuel_type'])) . "</strong></td>
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
		                            <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['name']) . "</strong></td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #888;'>Max Draft:</td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['max_draft']) . "</strong></td>
		                        </tr>
		                        <tr>
		                            <td style='border: 1px solid #000; padding: 8px; color: #888;'>Condition:</td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['current_status']) . "</strong></td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #888;'>Cabins:</td>
		                            <td style='border: 1px solid #000; padding: 8px; color: #336699;'><strong>" . esc_html($boat_data['cabins']) . "</strong></td>
		                        </tr>
		                    </table>
		                </div>
		            </div>
		            <div style='color: black;'>
		                <h2 style='color:#2E5274;'>Boat Description</h2>
		                <hr style='border: 1px solid #000; margin: 10px 0 20px 0;'>
		                ". $boat_data['description'] . "
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
		                ".nl2br($boat_data['additional_info'])."
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
