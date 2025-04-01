<?php

namespace MarineSync\Importers;

use MarineSync\PostType\MarineSync_Post_Type;

/**
 * Class BoatImporter
 *
 * Handles the import of boat data from an XML/JSON file.
 */

class MarineSync_Boat_Importer implements BoatImporter {
	/**
	 * @var array Options for the importer.
	 */
	private $options;

	/**
	 * Singleton instance of the class.
	 *
	 * @return MarineSync_Boat_Importer
	 */
	public static function getInstance(): MarineSync_Boat_Importer {
		static $instance = null;

		if ( is_null( $instance ) ) {
			$instance = new self();
		}

		return $instance;
	}

	private function __construct(){
		// Private constructor to prevent instantiation
		// Load the options from the database
		$this->options = get_option('marinesync_feed_settings');
	}

	public function importBoatData( $path ): array {
		// Open wordpress request
		$response = wp_remote_get( $path );

		// Check for errors
		if ( is_wp_error( $response ) ) {
			// Handle error
			return [];
		}

		// Get the body of the response
		$body = wp_remote_retrieve_body( $response );

		// Skip if the body is empty
		if ( empty( $body ) ) {
			// Handle error
			error_log('MarineSync_Boat_Importer->importBoatData :=: Empty response body');
			return [];
		}

		// Detect content type
		$trimmed_body = trim( $body );

		// Check if it looks like XML
		if ( str_starts_with( $trimmed_body, '<' ) && str_contains( $trimmed_body, '>' ) ) {
			// Parse XML
			$format = 'xml';
			$data = $this->parseXML( $body );
		} elseif ( str_starts_with( $trimmed_body, '{' ) && str_contains( $trimmed_body, '}' ) ) {
			// Parse JSON
			$format = 'json';
			$data = $this->parseJSON( $body );
		} else {
			// Handle error
			error_log('MarineSync_Boat_Importer->importBoatData :=: Invalid data format');
			return [];
		}

		// Check if data is empty
		if ( empty( $data ) ) {
			// Handle error
			error_log('MarineSync_Boat_Importer->importBoatData :=: Empty data after parsing');
			return [];
		}

		// Return data
		return [
			'data' => $data,
			'format' => $format
		];
	}

	/**
	 * Parse XML data.
	 *
	 * @param string $xml The XML data to parse.
	 *
	 * @return array The parsed data.
	 */
	private function parseXML( string $xml ): array {
		// Parse the XML data
		$xml_data = simplexml_load_string( $xml );

		if ( $xml_data === false ) {
			// Handle error
			return [];
		}

		return json_decode( json_encode( $xml_data ), true );
	}

	/**
	 * Parse JSON data.
	 *
	 * @param string $json The JSON data to parse.
	 *
	 * @return array The parsed data.
	 */
	private function parseJSON( string $json ): array {
		// Parse the JSON data
		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			// Handle error
			return [];
		}

		return $data;
	}

	/**
	 * Process the boat data.
	 *
	 * @param array $data The boat data to process.
	 * @return void
	 */
	public function processBoatData( $data ): void {
		// Process boat data
		$boatData = $this->importBoatData($this->options['feed_url']);
		$format = $boatData['format'] ?? 'xml';

		// Check if boat data is empty as a safety check
		if ( empty( $boatData['data'] ) ) {
			// Handle error
			error_log('MarineSync_Boat_Importer->processBoatData :=: Empty boat data');
			return;
		}

		// Process each boat
		foreach ( $boatData['data'] as $boat ) {
			// Process each boat
			$this->processSingleBoat( $boat, $format );
		}
	}

	/**
	 * Process a single boat.
	 *
	 * @param array $boat The boat data to process.
	 *
	 * @return void
	 */
	private function processSingleBoat( array $boat, string $format ): void {
		// Check if boat exists in the database
		$ref = $boat['ref'] ?? null;
		if ( $ref ) {
			$existingBoat = MarineSync_Post_Type::get_boat_by_ref( $ref );

			if ( $existingBoat ) {
				// Update existing boat
				$this->updateBoat( $existingBoat, $boat );
			} else {
				// Insert new boat
				$this->createBoat( $boat, $format );
			}
		} else {
			error_log('MarineSync_Boat_Importer->processSingleBoat :=: Boat reference not found');
		}
	}

	/**
	 * Create a new boat.
	 *
	 * @param array $boat The new boat data.
	 *
	 * @return void
	 */
	private function createBoat(array $boat, string $format): void {
		// Create a new boat post
		$post_data = array(
			'post_title'   => $boat['name'],
			'post_content' => $boat['description'],
			'post_status'  => 'publish',
			'post_type'    => 'boat',
		);

		// Insert the post into the database
		$post_id = wp_insert_post( $post_data );

		if ( ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, 'boat_ref', $boat['ref'] );
			update_post_meta( $post_id, 'boat_data', $boat );
		} else {
			error_log('MarineSync_Boat_Importer->createBoat :=: Error creating boat post');
		}
	}
}