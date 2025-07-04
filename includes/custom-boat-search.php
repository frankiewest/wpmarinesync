<?php
// Use search class
use MarineSync\Search\MarineSync_Search;

// Get manufacturers, location
$manufacturers = MarineSync_Search::search_meta_value(meta_key: 'manufacturer', type: 'tax');
$locations = MarineSync_Search::search_meta_value(meta_key: 'vessel_lying', type: 'meta');

?>

<form id="boat-search-form" role="search" method="get" action="/">
	<div class="custom_search_form_column">
		<select name="manufacturer" class="custom_search_form_column">
			<option value="">All Manufacturers</option>
			<?php foreach($manufacturers as $manufacturer): ?>
				<option value="<?= esc_attr($manufacturer) ?>" <?= selected(isset($_GET['manufacturer']) ? $_GET['manufacturer'] : '', $manufacturer) ?>><?= esc_html($manufacturer) ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div class="custom_search_form_column">
		<select name="price_range">
			<option value="">Any Price (£)</option>
			<option value="up-to-30k" <?= selected(isset($_GET['price_range']) ? $_GET['price_range'] : '', 'up-to-30k') ?>>Up to £30K</option>
			<option value="30k-50k" <?= selected(isset($_GET['price_range']) ? $_GET['price_range'] : '', '30k-50k') ?>>£30K to £50K</option>
			<option value="50k-100k" <?= selected(isset($_GET['price_range']) ? $_GET['price_range'] : '', '50k-100k') ?>>£50K to £100K</option>
			<option value="100k-200k" <?= selected(isset($_GET['price_range']) ? $_GET['price_range'] : '', '100k-200k') ?>>£100K to £200K</option>
			<option value="200k-300k" <?= selected(isset($_GET['price_range']) ? $_GET['price_range'] : '', '200k-300k') ?>>£200K to £300K</option>
			<option value="over-300k" <?= selected(isset($_GET['price_range']) ? $_GET['price_range'] : '', 'over-300k') ?>>Over £300K</option>
		</select>
	</div>
	<div class="custom_search_form_column">
		<select name="boat_type" class="custom_search_form_column">
			<option value="">Power/Sail</option>
			<option value="Power" <?= selected(isset($_GET['boat_type']) ? $_GET['boat_type'] : '', 'Power') ?>>Power</option>
			<option value="Sail" <?= selected(isset($_GET['boat_type']) ? $_GET['boat_type'] : '', 'Sail') ?>>Sail</option>
		</select>
	</div>
	<div class="custom_search_form_column">
		<select name="loa" class="custom_search_form_column">
			<option value="">Any LOA (m)</option>
			<option value="up-to-10m" <?= selected(isset($_GET['loa']) ? $_GET['loa'] : '', 'up-to-10m') ?>>Up to 10m</option>
			<option value="10m-15m" <?= selected(isset($_GET['loa']) ? $_GET['loa'] : '', '10m-15m') ?>>10m - 15m</option>
			<option value="15m-20m" <?= selected(isset($_GET['loa']) ? $_GET['loa'] : '', '15m-20m') ?>>15m - 20m</option>
			<option value="over-20m" <?= selected(isset($_GET['loa']) ? $_GET['loa'] : '', 'over-20m') ?>>Over 20m</option>
		</select>
	</div>
	<div class="custom_search_form_column">
		<select name="year_range" class="custom_search_form_column">
			<option value="">Any Year</option>
			<option value="pre-1980" <?= selected(isset($_GET['year_range']) ? $_GET['year_range'] : '', 'pre-1980') ?>>Up to 1980</option>
			<option value="1980-1990" <?= selected(isset($_GET['year_range']) ? $_GET['year_range'] : '', '1980-1990') ?>>1980 - 1990</option>
			<option value="1990-2000" <?= selected(isset($_GET['year_range']) ? $_GET['year_range'] : '', '1990-2000') ?>>1990 - 2000</option>
			<option value="2000-2010" <?= selected(isset($_GET['year_range']) ? $_GET['year_range'] : '', '2000-2010') ?>>2000 - 2010</option>
			<option value="2010-2020" <?= selected(isset($_GET['year_range']) ? $_GET['year_range'] : '', '2010-2020') ?>>2010 - 2020</option>
			<option value="2020-plus" <?= selected(isset($_GET['year_range']) ? $_GET['year_range'] : '', '2020-plus') ?>>2020 to Present</option>
		</select>
	</div>
	<div class="custom_search_form_column">
		<select name="sortby_field" class="custom_search_form_column">
			<option value="">Sort By</option>
			<option value="price-high-low" <?= selected(isset($_GET['sortby_field']) ? $_GET['sortby_field'] : '', 'price-high-low') ?>>Price High > Low</option>
			<option value="price-low-high" <?= selected(isset($_GET['sortby_field']) ? $_GET['sortby_field'] : '', 'price-low-high') ?>>Price Low > High</option>
			<option value="loa-high-low" <?= selected(isset($_GET['sortby_field']) ? $_GET['sortby_field'] : '', 'loa-high-low') ?>>LOA High > Low</option>
			<option value="loa-low-high" <?= selected(isset($_GET['sortby_field']) ? $_GET['sortby_field'] : '', 'loa-low-high') ?>>LOA Low > High</option>
		</select>
	</div>
    <div class="custom_search_form_column">
        <select name="vessel_lying" class="custom_search_form_column">
            <option value="">All Locations</option>
			<?php foreach($locations as $location): ?>
                <option value="<?= esc_attr($location) ?>" <?= selected(isset($_GET['vessel_lying']) ? $_GET['vessel_lying'] : '', $location) ?>><?= esc_html($location) ?></option>
			<?php endforeach; ?>
        </select>
    </div>
    <div class="custom_search_form_column">
        <input type="text" name="s" value="<?php echo get_search_query(); ?>">
    </div>

	<div class="custom_search_form_column">
		<input type="submit" value="Search">
	</div>
</form>

<?php
add_action('pre_get_posts', ['\\MarineSync\\Search\\MarineSync_Search', 'custom_search_query']);
?>