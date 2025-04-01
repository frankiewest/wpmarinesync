<?php

namespace MarineSync\Importers;

use AllowDynamicProperties;

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
			$data = $this->parseXML( $body );
		} elseif ( str_starts_with( $trimmed_body, '{' ) && str_contains( $trimmed_body, '}' ) ) {
			// Parse JSON
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
		return $data;
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

		// Check if boat data is empty as a safety check
		if ( empty( $boatData ) ) {
			// Handle error
			error_log('MarineSync_Boat_Importer->processBoatData :=: Empty boat data');
			return;
		}

		// Process each boat
		foreach ( $boatData as $boat ) {
			// Process each boat
			$this->processSingleBoat( $boat );
		}
	}

	/**
	 * Process a single boat.
	 *
	 * @param array $boat The boat data to process.
	 *
	 * @return void
	 */
	private function processSingleBoat( array $boat ): void {
		// Check if boat exists in the database

	}
}