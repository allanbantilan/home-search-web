<?php
$agent = get_page_by_title('Ana Reyes', OBJECT, 'agent');
if (!$agent) {
    $agent_id = wp_insert_post(['post_type' => 'agent', 'post_status' => 'publish', 'post_title' => 'Ana Reyes', 'post_content' => 'Local property advisor.']);
    update_post_meta($agent_id, 'agent_email', 'ana@example.test');
    update_post_meta($agent_id, 'agent_role', 'Senior property advisor');
} else {
    $agent_id = $agent->ID;
}
$type = term_exists('House', 'property_type');
if (!$type) { $type = wp_insert_term('House', 'property_type'); }
$properties = [
    ['Makati Garden Residence', 18000000, 'Makati', 3, 2, 210, 14.5547, 121.0244],
    ['Tagaytay Hillside Retreat', 12000000, 'Tagaytay', 2, 2, 165, 14.1153, 120.9621],
    ['Cebu City Modern Loft', 8000000, 'Cebu City', 2, 1, 95, 10.3157, 123.8854],
];
foreach ($properties as [$title, $price, $location, $beds, $baths, $area, $lat, $lng]) {
    $existing = get_page_by_title($title, OBJECT, 'property');
    if ($existing) { continue; }
    $id = wp_insert_post(['post_type' => 'property', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => 'A considered home with comfortable living spaces and a convenient setting.']);
    foreach (['price' => $price, 'location' => $location, 'bedrooms' => $beds, 'bathrooms' => $baths, 'area' => $area, 'latitude' => $lat, 'longitude' => $lng, 'agent' => $agent_id, 'featured' => 1] as $key => $value) {
        update_post_meta($id, $key, $value);
    }
    wp_set_post_terms($id, [(int) $type['term_id']], 'property_type');
    echo "$id: $title\n";
}
echo "ACF field groups: " . count(acf_get_field_groups()) . "\n";
