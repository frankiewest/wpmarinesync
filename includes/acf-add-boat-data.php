<?php
namespace MarineSync\ACF;

class Acf_add_boat_data {
    public static function add_boat_data() {
        error_log('MS020: Starting ACF field group creation');
        
        if (!function_exists('acf_add_local_field_group')) {
            error_log('MS021: ACF functions not available');
            return;
        }
        
        $fields = array(
            array(
                'key' => 'field_boat_dimensions',
                'label' => \__('Dimensions', 'marinesync'),
                'name' => 'dimensions',
                'type' => 'group',
                'instructions' => \__('Enter the boat dimensions', 'marinesync'),
                'required' => 0,
                'layout' => 'block',
                'sub_fields' => array(
                    array(
                        'key' => 'field_boat_loa',
                        'label' => \__('Length Overall (LOA)', 'marinesync'),
                        'name' => 'loa',
                        'type' => 'text',
                        'instructions' => \__('Enter the length overall in feet', 'marinesync'),
                        'required' => 0,
                    ),
                    array(
                        'key' => 'field_boat_beam',
                        'label' => \__('Beam', 'marinesync'),
                        'name' => 'beam',
                        'type' => 'text',
                        'instructions' => \__('Enter the beam in feet', 'marinesync'),
                        'required' => 0,
                    ),
                    array(
                        'key' => 'field_boat_draft',
                        'label' => \__('Draft', 'marinesync'),
                        'name' => 'draft',
                        'type' => 'text',
                        'instructions' => \__('Enter the draft in feet', 'marinesync'),
                        'required' => 0,
                    ),
                ),
            ),
            array(
                'key' => 'field_boat_engine',
                'label' => \__('Engine Details', 'marinesync'),
                'name' => 'engine',
                'type' => 'group',
                'instructions' => \__('Enter the engine details', 'marinesync'),
                'required' => 0,
                'layout' => 'block',
                'sub_fields' => array(
                    array(
                        'key' => 'field_boat_engine_make',
                        'label' => \__('Engine Make', 'marinesync'),
                        'name' => 'make',
                        'type' => 'text',
                        'instructions' => \__('Enter the engine manufacturer', 'marinesync'),
                        'required' => 0,
                    ),
                    array(
                        'key' => 'field_boat_engine_model',
                        'label' => \__('Engine Model', 'marinesync'),
                        'name' => 'model',
                        'type' => 'text',
                        'instructions' => \__('Enter the engine model', 'marinesync'),
                        'required' => 0,
                    ),
                    array(
                        'key' => 'field_boat_engine_year',
                        'label' => \__('Engine Year', 'marinesync'),
                        'name' => 'year',
                        'type' => 'number',
                        'instructions' => \__('Enter the engine year', 'marinesync'),
                        'required' => 0,
                    ),
                    array(
                        'key' => 'field_boat_engine_hours',
                        'label' => \__('Engine Hours', 'marinesync'),
                        'name' => 'hours',
                        'type' => 'number',
                        'instructions' => \__('Enter the engine hours', 'marinesync'),
                        'required' => 0,
                    ),
                ),
            ),
            array(
                'key' => 'field_boat_tanks',
                'label' => \__('Tank Capacities', 'marinesync'),
                'name' => 'tanks',
                'type' => 'group',
                'instructions' => \__('Enter the tank capacities', 'marinesync'),
                'required' => 0,
                'layout' => 'block',
                'sub_fields' => array(
                    array(
                        'key' => 'field_boat_fuel_tank',
                        'label' => \__('Fuel Tank', 'marinesync'),
                        'name' => 'fuel',
                        'type' => 'text',
                        'instructions' => \__('Enter the fuel tank capacity in gallons', 'marinesync'),
                        'required' => 0,
                    ),
                    array(
                        'key' => 'field_boat_water_tank',
                        'label' => \__('Water Tank', 'marinesync'),
                        'name' => 'water',
                        'type' => 'text',
                        'instructions' => \__('Enter the water tank capacity in gallons', 'marinesync'),
                        'required' => 0,
                    ),
                    array(
                        'key' => 'field_boat_holding_tank',
                        'label' => \__('Holding Tank', 'marinesync'),
                        'name' => 'holding',
                        'type' => 'text',
                        'instructions' => \__('Enter the holding tank capacity in gallons', 'marinesync'),
                        'required' => 0,
                    ),
                ),
            ),
        );
        
        $location = array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'marinesync-boats',
                ),
            ),
        );
        
        $args = array(
            'key' => 'group_boat_data',
            'title' => \__('Boat Data', 'marinesync'),
            'fields' => $fields,
            'location' => $location,
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => \__('Boat listing data fields', 'marinesync'),
            'local' => 'php',
            'modified' => time(),
            'parent' => 0,
            'is_sync' => false,
        );
        
        acf_add_local_field_group($args);
        
        error_log('MS022: ACF field group created successfully');
    }
}

