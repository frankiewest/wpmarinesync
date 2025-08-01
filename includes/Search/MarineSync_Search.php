<?php

namespace MarineSync\Search;

use WP_Error;

class MarineSync_Search {
	/**
	 * Search postmeta by meta_key and meta_value
	 *
	 * @param string $meta_key
	 * @param string $meta_value
	 *
	 * @return array|null
	 */
	public static function search_meta_value(string $meta_key, string $meta_value = '', string $type = 'meta'): array|null {
		if ($meta_key === '' && $meta_value === '') {
			return null;
		}

		global $wpdb;

		// Initialize results
		$results = [];

		try {
			if ($type === 'meta') {
				// Prepare the base query with placeholder
				$query = $wpdb->prepare(
					"SELECT DISTINCT meta_value
                FROM {$wpdb->postmeta}
                WHERE meta_key = %s",
					$meta_key
				);

				// Add meta_value condition if provided
				if ($meta_value !== '') {
					$query = $wpdb->prepare(
						$query . " AND meta_value = %s",
						$meta_value
					);
				}

				$query .= " ORDER BY meta_value ASC";

			} elseif ($type === 'tax') {
				// Prepare the base query with placeholder
				$query = $wpdb->prepare(
					"SELECT DISTINCT terms.name
                FROM {$wpdb->terms} AS terms
                INNER JOIN {$wpdb->term_taxonomy} AS taxonomy 
                    ON terms.term_id = taxonomy.term_id
                INNER JOIN {$wpdb->term_relationships} AS relationships 
                    ON taxonomy.term_taxonomy_id = relationships.term_taxonomy_id
                INNER JOIN {$wpdb->posts} AS posts 
                    ON relationships.object_id = posts.ID
                WHERE taxonomy.taxonomy = %s",
					$meta_key
				);

				// Add name condition if meta_value provided
				if ($meta_value !== '') {
					$query = $wpdb->prepare(
						$query . " AND terms.name = %s",
						$meta_value
					);
				}

				$query .= " ORDER BY terms.name ASC";

			} else {
				return null;
			}

			// Execute the query
			$results = $wpdb->get_col($query);

			// Check for database errors
			if ($wpdb->last_error) {
				error_log('Database error in search_meta_value: ' . $wpdb->last_error);
				return null;
			}

			return array_filter($results, 'strlen');

		} catch (Exception $e) {
			error_log('Exception in search_meta_value: ' . $e->getMessage());
			return null;
		}
	}

	/**
	 * Search years range
	 *
	 * @param int $min
	 * @param int $max
	 *
	 * @return array|null|WP_Error
	 */
	public static function search_years(int $min = 0 , int $max = 9999): array|null|WP_Error {
		if($min < 0 || $max < 0) {
			return null;
		}

		if($min > $max) {
			return new WP_Error('invalid_year_range', 'Invalid year range');
		}

		// Get global wpdb
		global $wpdb;

		// Begin forming query
		$query = "
		SELECT DISTINCT meta_value
		FROM {$wpdb->postmeta}
		WHERE meta_key = 'year'
		";

		$query .= "
		ORDER BY meta_value ASC
		";

		$results = $wpdb->get_col($query);

		$filtered_results = array_filter($results, function($year) use ($min, $max) {
			return $year >= $min && $year <= $max;
		});

		return array_filter($filtered_results, 'strlen');
	}

	/**
	 * @return false|string
	 */
	public static function render_search_form(): bool|string {
		if(is_admin()) return "in admin interface";

		ob_start();
		include MARINESYNC_PLUGIN_DIR . 'includes/custom-boat-search.php';
		return ob_get_clean();
	}

	/**
	 * @param $query
	 *
	 * @return void
	 */
	public static function custom_search_query($query): void {
		$meta_query = [];
		$tax_query = [];

		// Exclude sold
		if($query->is_search() && !(is_page('sold-boats'))){
			$tax_query[] = [
				'taxonomy' => 'boat-status',
				'field'    => 'slug',
				'terms'    => [ 'sold' ],
				'operator' => 'NOT IN'
			];
		}

		// Add manufacturer filter
		if(isset($_GET['manufacturer']) && !empty($_GET['manufacturer'])){
			$manufacturer = sanitize_text_field(str_replace(' ', '_', $_GET['manufacturer']));
			$tax_query[] = [
				[
					'taxonomy' => 'manufacturer',
					'terms' => $manufacturer,
					'field' => 'name',
					'operator' => 'IN'
				]
			];
		}

		// Add price range filter
		if(isset($_GET['price_range']) && !empty($_GET['price_range'])){
			$price_range = sanitize_text_field($_GET['price_range']);
			switch($price_range){
				case 'up-to-30k':
					$meta_query[] = [
						'key' => 'asking_price',
						'value' => 30000,
						'compare' => '<',
						'type' => 'NUMERIC'
					];
					break;
				case '30k-50k':
					$meta_query[] = [
						'key' => 'asking_price',
						'value' => [30000, 50000],
						'compare' => 'BETWEEN',
						'type' => 'NUMERIC'
					];
					break;
				case '50k-100k':
					$meta_query[] = [
						'key' => 'asking_price',
						'value' => [50000, 100000],
						'compare' => 'BETWEEN',
						'type' => 'NUMERIC'
					];
					break;
				case '100k-200k':
					$meta_query[] = [
						'key' => 'asking_price',
						'value' => [100000, 200000],
						'compare' => 'BETWEEN',
						'type' => 'NUMERIC'
					];
					break;
				case '200k-300k':
					$meta_query[] = [
						'key' => 'asking_price',
						'value' => [200000, 300000],
						'compare' => 'BETWEEN',
						'type' => 'NUMERIC'
					];
					break;
				case 'over-300k':
					$meta_query[] = [
						'key' => 'asking_price',
						'value' => 300000,
						'compare' => '>=',
						'type' => 'NUMERIC'
					];
					break;
				default:
					// Do nothing
					break;
			}
		}

		if(isset($_GET['boat_type']) && !empty($_GET['boat_type'])){
			$boat_type = sanitize_text_field($_GET['boat_type']);
			$meta_query[] = [
				'key' => 'boat_type',
				'value' => $boat_type,
				'compare' => '='
			];
		}

		// Add price range filter
		if(isset($_GET['loa']) && !empty($_GET['loa'])){
			$loa = sanitize_text_field($_GET['loa']);
			switch($loa){
				case 'up-to-10m':
					$meta_query[] = [
						'key' => 'loa',
						'value' => 10,
						'compare' => '<',
						'type' => 'NUMERIC'
					];
					break;
				case '10m-15m':
					$meta_query[] = [
						'key' => 'loa',
						'value' => [10, 15],
						'compare' => 'BETWEEN',
						'type' => 'NUMERIC'
					];
					break;
				case '15m-20m':
					$meta_query[] = [
						'key' => 'loa',
						'value' => [15, 20],
						'compare' => 'BETWEEN',
						'type' => 'NUMERIC'
					];
					break;
				case 'over-20m':
					$meta_query[] = [
						'key' => 'loa',
						'value' => 20,
						'compare' => '>',
						'type' => 'NUMERIC'
					];
					break;
				default:
					// Do nothing
					break;
			}
		}
		// Add year range filter
		if (isset($_GET['year_range']) && !empty($_GET['year_range'])) {
			$year_range = sanitize_text_field($_GET['year_range']);
			switch ($year_range) {
				case 'pre-1980':
					$meta_query[] = [
						'key' => 'year',
						'value' => 1980,
						'compare' => '<',
						'type' => 'NUMERIC'
					];
					break;
				case '1980-1990':
					$meta_query[] = [
						'key' => 'year',
						'value' => [1980, 1990],
						'compare' => 'BETWEEN',
						'type' => 'NUMERIC'
					];
					break;
				case '1990-2000':
					$meta_query[] = [
						'key' => 'year',
						'value' => [1990, 2000],
						'compare' => 'BETWEEN',
						'type' => 'NUMERIC'
					];
					break;
				case '2000-2010':
					$meta_query[] = [
						'key' => 'year',
						'value' => [2000, 2010],
						'compare' => 'BETWEEN',
						'type' => 'NUMERIC'
					];
					break;
				case '2010-2020':
					$meta_query[] = [
						'key' => 'year',
						'value' => [2010, 2020],
						'compare' => 'BETWEEN',
						'type' => 'NUMERIC'
					];
					break;
				case '2020-plus':
					$meta_query[] = [
						'key' => 'year',
						'value' => 2020,
						'compare' => '>=',
						'type' => 'NUMERIC'
					];
					break;
				default:
					// Do nothing
					break;
			}
		}

		// Add currency filter
		if (isset($_GET['currency']) && !empty($_GET['currency'])) {
			$currency = sanitize_text_field($_GET['currency']);
			$meta_query[] = [
				'key' => 'currency',
				'value' => $currency,
				'compare' => '='
			];
		}

		// Add sort by
		if(isset($_GET['sortby_field']) && !empty($_GET['sortby_field'])) {
			$sortby_field = sanitize_text_field($_GET['sortby_field']);
			switch($sortby_field){
				case 'price-high-low':
					$query->set('meta_key', 'asking_price');
					$query->set('orderby', 'meta_value_num');
					$query->set('order', 'DESC');
					break;
				case 'price-low-high':
					$query->set('meta_key', 'asking_price');
					$query->set('orderby', 'meta_value_num');
					$query->set('order', 'ASC');
					break;
				case 'loa-high-low':
					$query->set('meta_key', 'loa');
					$query->set('orderby', 'meta_value_num');
					$query->set('order', 'DESC');
					break;
				case 'loa-low-high':
					$query->set('meta_key', 'loa');
					$query->set('orderby', 'meta_value_num');
					$query->set('order', 'ASC');
					break;
				default:
					$query->set('meta_key', 'asking_price');
					$query->set('orderby', 'meta_value_num');
					$query->set('order', 'DESC');
					break;
			}
		}

		// Add post_type
		$query->set('post_type', 'marinesync-boats');

		// Add tax_query and meta_query to query
		if (!empty($tax_query)) {
			$query->set( 'tax_query', $tax_query );
		}

		if (!empty($meta_query)) {
			$query->set( 'meta_query', $meta_query );
		}
	}
}