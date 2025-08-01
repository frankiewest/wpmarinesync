<?php

namespace MarineSync\Importer;

use InvalidArgumentException;
use RuntimeException;

interface ImporterInterface {
	/**
	 * Configures the importer with necessary settings (e.g., feed URL, authentication details).
	 *
	 * @param array $config Configuration array (e.g., ['url' => 'https://example.com/feed', 'auth' => ['username' => 'user', 'password' => 'pass']]).
	 * @return void
	 * @throws InvalidArgumentException If the configuration is invalid (e.g., missing required fields).
	 */
	public function configure(array $config): void;

	/**
	 * Connects to the external feed and fetches raw data.
	 *
	 * @return string Raw feed data (e.g., XML or JSON string).
	 * @throws RuntimeException If the connection fails or the response is invalid.
	 */
	public function fetch(): string;

	/**
	 * Parses the raw feed data into a structured format (e.g., array or object).
	 *
	 * @param string $rawData The raw feed data (XML or JSON string).
	 * @return array Parsed data in a standardized format.
	 * @throws RuntimeException If parsing fails (e.g., malformed XML/JSON).
	 */
	public function parse(string $rawData): array;

	/**
	 * Processes the parsed data (e.g., transforms or validates it for import).
	 *
	 * @param array $parsedData The parsed feed data.
	 * @return array Processed data ready for import.
	 * @throws RuntimeException If processing fails (e.g., invalid data structure).
	 */
	public function process(array $parsedData): array;

	/**
	 * Imports the processed data (e.g., saves to WordPress database or performs other actions).
	 *
	 * @param array $processedData The processed data to import.
	 * @return bool True on successful import, false otherwise.
	 * @throws RuntimeException If the import fails (e.g., database errors).
	 */
	public function import(array $processedData): bool;
}