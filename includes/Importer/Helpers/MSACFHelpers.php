<?php

namespace MarineSync\Importer\Helpers;

class MSACFHelpers {
	public static function get_item_value($section, string $name): ?string {
		if(!isset($section['item'])) return null;
		foreach((array) $section['item'] as $item){
			if(($item['@attributes']['name'] ?? '') === $name) {
				return $item[0] ?? null;
			}
		}
		return null;
	}

	public static function map_boat_features(array $boat_features): array {
		return [
			// General boat info
			'general' => [
				'boat_name'         => self::get_item_value($boat_features, 'boat_name'),
				'owners_comment'    => self::get_item_value($boat_features, 'owners_comment'),
				'reg_details'       => self::get_item_value($boat_features, 'reg_details'),
				'known_defects'     => self::get_item_value($boat_features, 'known_defects'),
				'range'             => self::get_item_value($boat_features, 'range'),
				'last_serviced'     => self::get_item_value($boat_features, 'last_serviced'),
				'passenger_capacity'=> self::get_item_value($boat_features, 'passenger_capacity'),
				'television' => self::get_item_value($boat_features['general'] ?? [], 'television'),
				'cd_player'  => self::get_item_value($boat_features['general'] ?? [], 'cd_player'),
				'dvd_player' => self::get_item_value($boat_features['general'] ?? [], 'dvd_player'),
			],

			// Dimensions
			'dimensions' => [
				'beam'      => self::get_item_value($boat_features['dimensions'] ?? [], 'beam'),
				'draft'     => self::get_item_value($boat_features['dimensions'] ?? [], 'draft'),
				'loa'       => self::get_item_value($boat_features['dimensions'] ?? [], 'loa'),
				'lwl'       => self::get_item_value($boat_features['dimensions'] ?? [], 'lwl'),
				'air_draft' => self::get_item_value($boat_features['dimensions'] ?? [], 'air_draft'),
			],

			// Build
			'build' => [
				'designer'                 => self::get_item_value($boat_features['build'] ?? [], 'designer'),
				'builder'                  => self::get_item_value($boat_features['build'] ?? [], 'builder'),
				'where'                    => self::get_item_value($boat_features['build'] ?? [], 'where'),
				'year'                     => self::get_item_value($boat_features['build'] ?? [], 'year'),
				'hull_colour'              => self::get_item_value($boat_features['build'] ?? [], 'hull_colour'),
				'hull_construction'        => self::get_item_value($boat_features['build'] ?? [], 'hull_construction'),
				'hull_number'              => self::get_item_value($boat_features['build'] ?? [], 'hull_number'),
				'hull_type'                => self::get_item_value($boat_features['build'] ?? [], 'hull_type'),
				'super_structure_colour'   => self::get_item_value($boat_features['build'] ?? [], 'super_structure_colour'),
				'super_structure_construction' => self::get_item_value($boat_features['build'] ?? [], 'super_structure_construction'),
				'deck_colour'              => self::get_item_value($boat_features['build'] ?? [], 'deck_colour'),
				'deck_construction'        => self::get_item_value($boat_features['build'] ?? [], 'deck_construction'),
				'cockpit_type'             => self::get_item_value($boat_features['build'] ?? [], 'cockpit_type'),
				'control_type'             => self::get_item_value($boat_features['build'] ?? [], 'control_type'),
				'flybridge'                => self::get_item_value($boat_features['build'] ?? [], 'flybridge'),
				'keel_type'                => self::get_item_value($boat_features['build'] ?? [], 'keel_type'),
				'ballast'                  => self::get_item_value($boat_features['build'] ?? [], 'ballast'),
				'displacement'             => self::get_item_value($boat_features['build'] ?? [], 'displacement'),
			],

			// Galley
			'galley' => [
				'oven'             => self::get_item_value($boat_features['galley'] ?? [], 'oven'),
				'microwave'        => self::get_item_value($boat_features['galley'] ?? [], 'microwave'),
				'fridge'           => self::get_item_value($boat_features['galley'] ?? [], 'fridge'),
				'freezer'          => self::get_item_value($boat_features['galley'] ?? [], 'freezer'),
				'heating'          => self::get_item_value($boat_features['galley'] ?? [], 'heating'),
				'air_conditioning' => self::get_item_value($boat_features['galley'] ?? [], 'air_conditioning'),
			],

			// Engine
			'engine' => [
				'stern_thruster'       => self::get_item_value($boat_features['engine'] ?? [], 'stern_thruster'),
				'bow_thruster'         => self::get_item_value($boat_features['engine'] ?? [], 'bow_thruster'),
				'fuel'                 => self::get_item_value($boat_features['engine'] ?? [], 'fuel'),
				'hours'                => self::get_item_value($boat_features['engine'] ?? [], 'hours'),
				'cruising_speed'       => self::get_item_value($boat_features['engine'] ?? [], 'cruising_speed'),
				'max_speed'            => self::get_item_value($boat_features['engine'] ?? [], 'max_speed'),
				'horse_power'          => self::get_item_value($boat_features['engine'] ?? [], 'horse_power'),
				'engine_manufacturer'  => self::get_item_value($boat_features['engine'] ?? [], 'engine_manufacturer'),
				'engine_quantity'      => self::get_item_value($boat_features['engine'] ?? [], 'engine_quantity'),
				'tankage'              => self::get_item_value($boat_features['engine'] ?? [], 'tankage'),
				'gallons_per_hour'     => self::get_item_value($boat_features['engine'] ?? [], 'gallons_per_hour'),
				'litres_per_hour'      => self::get_item_value($boat_features['engine'] ?? [], 'litres_per_hour'),
				'engine_location'      => self::get_item_value($boat_features['engine'] ?? [], 'engine_location'),
				'gearbox'              => self::get_item_value($boat_features['engine'] ?? [], 'gearbox'),
				'cylinders'            => self::get_item_value($boat_features['engine'] ?? [], 'cylinders'),
				'propeller_type'       => self::get_item_value($boat_features['engine'] ?? [], 'propeller_type'),
				'starting_type'        => self::get_item_value($boat_features['engine'] ?? [], 'starting_type'),
				'drive_type'           => self::get_item_value($boat_features['engine'] ?? [], 'drive_type'),
				'cooling_system'       => self::get_item_value($boat_features['engine'] ?? [], 'cooling_system'),
			],

			// Navigation
			'navigation' => [
				'navigation_lights' => self::get_item_value($boat_features['navigation'] ?? [], 'navigation_lights'),
				'compass'           => self::get_item_value($boat_features['navigation'] ?? [], 'compass'),
				'depth_instrument'  => self::get_item_value($boat_features['navigation'] ?? [], 'depth_instrument'),
				'wind_instrument'   => self::get_item_value($boat_features['navigation'] ?? [], 'wind_instrument'),
				'autopilot'         => self::get_item_value($boat_features['navigation'] ?? [], 'autopilot'),
				'gps'               => self::get_item_value($boat_features['navigation'] ?? [], 'gps'),
				'vhf'               => self::get_item_value($boat_features['navigation'] ?? [], 'vhf'),
				'plotter'           => self::get_item_value($boat_features['navigation'] ?? [], 'plotter'),
				'speed_instrument'  => self::get_item_value($boat_features['navigation'] ?? [], 'speed_instrument'),
				'radar'             => self::get_item_value($boat_features['navigation'] ?? [], 'radar'),
			],

			// Accommodation
			'accommodation' => [
				'cabins'  => self::get_item_value($boat_features['accommodation'] ?? [], 'cabins'),
				'berths'  => self::get_item_value($boat_features['accommodation'] ?? [], 'berths'),
				'toilet'  => self::get_item_value($boat_features['accommodation'] ?? [], 'toilet'),
				'shower'  => self::get_item_value($boat_features['accommodation'] ?? [], 'shower'),
				'bath'    => self::get_item_value($boat_features['accommodation'] ?? [], 'bath'),
			],

			// Safety Equipment
			'safety_equipment' => [
				'life_raft'       => self::get_item_value($boat_features['safety_equipment'] ?? [], 'life_raft'),
				'epirb'           => self::get_item_value($boat_features['safety_equipment'] ?? [], 'epirb'),
				'bilge_pump'      => self::get_item_value($boat_features['safety_equipment'] ?? [], 'bilge_pump'),
				'fire_extinguisher'=> self::get_item_value($boat_features['safety_equipment'] ?? [], 'fire_extinguisher'),
				'mob_system'      => self::get_item_value($boat_features['safety_equipment'] ?? [], 'mob_system'),
			],

			// Rig & Sails
			'rig_sails' => [
				'genoa'       => self::get_item_value($boat_features['rig_sails'] ?? [], 'genoa'),
				'spinnaker'   => self::get_item_value($boat_features['rig_sails'] ?? [], 'spinnaker'),
				'tri_sail'    => self::get_item_value($boat_features['rig_sails'] ?? [], 'tri_sail'),
				'storm_jib'   => self::get_item_value($boat_features['rig_sails'] ?? [], 'storm_jib'),
				'main_sail'   => self::get_item_value($boat_features['rig_sails'] ?? [], 'main_sail'),
				'winches'     => self::get_item_value($boat_features['rig_sails'] ?? [], 'winches'),
			],

			// Electronics
			'electronics' => [
				'battery'          => self::get_item_value($boat_features['electronics'] ?? [], 'battery'),
				'battery_charger'  => self::get_item_value($boat_features['electronics'] ?? [], 'battery_charger'),
				'generator'        => self::get_item_value($boat_features['electronics'] ?? [], 'generator'),
				'inverter'         => self::get_item_value($boat_features['electronics'] ?? [], 'inverter'),
			],

			// Equipment
			'equipment' => [
				'anchor'     => self::get_item_value($boat_features['equipment'] ?? [], 'anchor'),
				'spray_hood' => self::get_item_value($boat_features['equipment'] ?? [], 'spray_hood'),
				'bimini'     => self::get_item_value($boat_features['equipment'] ?? [], 'bimini'),
				'fenders'    => self::get_item_value($boat_features['equipment'] ?? [], 'fenders'),
			],
		];
	}

	/**
	 * Get a boat by its ms_ref value.
	 *
	 * @param string $ref
	 * @return \WP_Post|int|null
	 */
	public static function get_boat_by_ref(string $ref) {
		$query = new \WP_Query([
			'post_type'  => 'ms_boat',
			'meta_query' => [
				[
					'key'   => 'ms_ref',
					'value' => $ref,
				],
			],
			'posts_per_page' => 1,
			'post_status'    => 'any',
		]);

		return $query->have_posts() ? $query->posts[0] : null;
	}

	/**
	 * Get a boat by any ACF/meta field.
	 *
	 * @param string $field
	 * @param string $value
	 * @return \WP_Post|int|null
	 */
	public static function get_boat_by_field(string $field, string $value) {
		$query = new \WP_Query([
			'post_type'  => 'ms_boat',
			'meta_query' => [
				[
					'key'   => $field,
					'value' => $value,
				],
			],
			'posts_per_page' => 1,
			'post_status'    => 'any',
		]);

		return $query->have_posts() ? $query->posts[0] : null;
	}

	/**
	 * Set a custom ACF/meta field value for a boat.
	 *
	 * @param int $post_id
	 * @param string $field
	 * @param mixed $value
	 * @return void
	 */
	public static function set_boat_field(int $post_id, string $field, $value): void {
		if (function_exists('update_field')) {
			// Use ACF if available
			update_field($field, $value, $post_id);
		} else {
			// Fallback to standard WordPress meta
			update_post_meta($post_id, $field, $value);
		}
	}

	/**
	 * Create or update a boat by its ms_ref.
	 *
	 * @param string $ref
	 * @param array $data (expects keys: post_title, taxonomies[], acf[])
	 * @return int|\WP_Error Post ID or error
	 */
	public static function create_or_update_boat_by_ref(string $ref, array $data) {
		$boat = self::get_boat_by_ref($ref);
		$post_id = $boat ? $boat->ID : 0;

		$postarr = [
			'post_type'   => 'ms_boat',
			'post_title'  => $data['post_title'] ?? 'Untitled Boat',
			'post_status' => 'publish',
		];

		if ($post_id) {
			$postarr['ID'] = $post_id;
			$result = wp_update_post($postarr, true);
		} else {
			$result = wp_insert_post($postarr, true);
		}

		if (is_wp_error($result)) {
			return $result;
		}

		$post_id = $post_id ?: $result;

		// Save ms_ref
		self::set_boat_field($post_id, 'ms_ref', $ref);

		// Taxonomies
		if (!empty($data['taxonomies'])) {
			foreach ($data['taxonomies'] as $taxonomy => $termName) {
				if (empty($termName) || !taxonomy_exists($taxonomy)) {
					continue;
				}

				$term = term_exists($termName, $taxonomy);
				if (!$term) {
					$term = wp_insert_term($termName, $taxonomy);
				}

				if (!is_wp_error($term)) {
					wp_set_object_terms($post_id, intval($term['term_id']), $taxonomy, false);
				}
			}
		}

		// ACF fields
		if (!empty($data['acf'])) {
			foreach ($data['acf'] as $tab => $fields) {
				foreach ($fields as $field_key => $value) {
					if (is_array($value)) {
						foreach ($value as $sub_field_key => $sub_value) {
							self::set_boat_field($post_id, $sub_field_key, $sub_value);
						}
					} else {
						self::set_boat_field($post_id, $field_key, $value);
					}
				}
			}
		}

		return $post_id;
	}
}