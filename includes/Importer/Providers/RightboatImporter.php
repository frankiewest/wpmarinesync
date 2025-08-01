<?php

namespace MarineSync\Importer\Providers;

use InvalidArgumentException;
use MarineSync\Importer\ImporterInterface;
use RuntimeException;
use Exception;
use SimpleXMLElement;
use MarineSync\Importer\Helpers\MSACFHelpers;

class RightboatImporter implements ImporterInterface {
	/**
	 * Declare private properties
	 */
	private string $url;
	private array $auth = [];
	private int $timeout = 30;

	/**
	 * @param array $config
	 *
	 * @return void
	 */
	public function configure( array $config ): void {
		if(empty($config['url'])) throw new InvalidArgumentException('Feed URL is required');

		$this->url = $config['url'];
		$this->auth = $config['auth'] ?? [];
		$this->timeout = $config['timeout'] ?? 30;
	}

	public function fetch(): string {
		$args = [
			'timeout' => $this->timeout,
			'headers' => []
		];

		if(!empty($this->auth)) {
			$args['headers']['Authorization'] = 'Basic ' . base64_encode($this->auth['username'] . ':' . $this->auth['password']);
		}

		$response = wp_remote_get($this->url, $args);

		if(is_wp_error($response)) throw new RuntimeException('Failed to fetch feed: ' . $response->get_error_message());

		// Get HTTP status code
		$status_code = wp_remote_retrieve_response_code($response);
		if ($status_code !== 200) {
			throw new RuntimeException("Invalid HTTP status: $status_code");
		}

		// Check Content-Type header
		$content_type = wp_remote_retrieve_header($response, 'content-type');
		if (stripos($content_type, 'xml') === false) {
			throw new RuntimeException("Invalid content type: $content_type. Expected XML.");
		}

		// Get the response body
		$body = wp_remote_retrieve_body($response);
		if (empty($body)) {
			throw new RuntimeException('Empty feed response.');
		}

		if ( ! str_starts_with( $body, '<?xml' ) ) {
			throw new RuntimeException('Response does not appear to be valid XML.');
		}

		return $body;
	}

	public function parse( string $rawData ): array {
		try {
			$element = new SimpleXMLElement($rawData);
			return json_decode(json_encode($element), true);
		} catch (Exception $e) {
			throw new RuntimeException('Failed to parse XML: ' . $e->getMessage());
		}
	}

	public function process( array $parsedData ): array {
		$processed = [];

		if(!isset($parsedData['adverts']['advert'])) {
			return $processed;
		}

		$adverts = $parsedData['adverts']['advert'];
		if(isset($adverts['@attributes'])){
			$adverts = [$adverts];
		}

		foreach($adverts as $advert) {
			$features = $advert['advert_features'] ?? [];

			$processed[] = [
				'post_title' => trim(($features['manufacturer'] ?? '') . ' ' . ($features['model'] ?? '')),
				'taxonomies' => [
					'ms-boat-status-tax' => $advert['@attributes']['status'] ?? null,
					'ms-boat-categories' => $features['boat_category'] ?? null,
					'ms-boat-conditions' => $features['new_or_used'] ?? null,
					'ms-boat-types'      => $features['boat_type'] ?? null,
					'ms-boat-manufacturers' => $features['manufacturer'] ?? null,
					'ms-boat-designers'  => MSACFHelpers::get_item_value($advert['boat_features']['build'] ?? [], 'designer')
				],
				'acf' => [
					'ref' => $advert['@attributes']['ref'] ?? '0',
					'advert_media'    => $advert['advert_media']['media'] ?? [],
					'advert_features' => $features,
					'boat_features'   => MSACFHelpers::map_boat_features($advert['boat_features'] ?? []),
				]
			];
		}

		return $processed;
	}

	public function import(array $processedData): bool {
		foreach ($processedData as $boat) {
			$ref = $boat['acf']['advert_features']['ref'] ?? uniqid('boat-');

			$post_id = MSACFHelpers::create_or_update_boat_by_ref($ref, $boat);

			if (is_wp_error($post_id)) {
				error_log('Rightboat import failed: ' . $post_id->get_error_message());
				continue;
			}

			// Fire an action hook so others can attach functionality
			do_action('ms_boat_imported', $post_id, $boat);
		}

		return true;
	}
}