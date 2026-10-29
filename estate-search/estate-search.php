<?php
/**
 * Plugin Name: Estate Search
 * Description: Property and agent content, live search, and viewing requests for the Home Search theme.
 * Version: 1.0.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) { exit; }

function estate_register_content() {
    register_post_type('property', [
        'labels' => ['name' => 'Properties', 'singular_name' => 'Property'],
        'public' => true, 'has_archive' => true, 'rewrite' => ['slug' => 'properties'],
        'menu_icon' => 'dashicons-admin-home', 'show_in_rest' => true,
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
    ]);
    register_post_type('agent', [
        'labels' => ['name' => 'Agents', 'singular_name' => 'Agent'],
        'public' => true, 'has_archive' => true, 'rewrite' => ['slug' => 'agents'],
        'menu_icon' => 'dashicons-businessperson', 'show_in_rest' => true,
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
    ]);
    register_taxonomy('property_type', 'property', [
        'label' => 'Property types', 'public' => true, 'show_in_rest' => true,
        'hierarchical' => true, 'rewrite' => ['slug' => 'property-type'],
    ]);
}
add_action('init', 'estate_register_content');

register_activation_hook(__FILE__, function () {
    estate_register_content();
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

add_action('acf/init', function () {
    if (!function_exists('acf_add_local_field_group')) { return; }
    acf_add_local_field_group([
        'key' => 'group_estate_property', 'title' => 'Property details',
        'fields' => [
            ['key' => 'field_estate_price', 'label' => 'Price', 'name' => 'price', 'type' => 'number', 'required' => 1, 'min' => 0],
            ['key' => 'field_estate_location', 'label' => 'Location', 'name' => 'location', 'type' => 'text', 'required' => 1],
            ['key' => 'field_estate_address', 'label' => 'Street address', 'name' => 'address', 'type' => 'text'],
            ['key' => 'field_estate_beds', 'label' => 'Bedrooms', 'name' => 'bedrooms', 'type' => 'number', 'min' => 0],
            ['key' => 'field_estate_baths', 'label' => 'Bathrooms', 'name' => 'bathrooms', 'type' => 'number', 'min' => 0],
            ['key' => 'field_estate_area', 'label' => 'Area (sqm)', 'name' => 'area', 'type' => 'number', 'min' => 0],
            ['key' => 'field_estate_lat', 'label' => 'Latitude', 'name' => 'latitude', 'type' => 'number', 'min' => -90, 'max' => 90, 'step' => 'any'],
            ['key' => 'field_estate_lng', 'label' => 'Longitude', 'name' => 'longitude', 'type' => 'number', 'min' => -180, 'max' => 180, 'step' => 'any'],
            ['key' => 'field_estate_agent', 'label' => 'Assigned agent', 'name' => 'agent', 'type' => 'post_object', 'post_type' => ['agent'], 'return_format' => 'id'],
            ['key' => 'field_estate_featured', 'label' => 'Featured', 'name' => 'featured', 'type' => 'true_false'],
        ],
        'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'property']]],
    ]);
    acf_add_local_field_group([
        'key' => 'group_estate_agent', 'title' => 'Agent details',
        'fields' => [
            ['key' => 'field_agent_email', 'label' => 'Email', 'name' => 'agent_email', 'type' => 'email', 'required' => 1],
            ['key' => 'field_agent_phone', 'label' => 'Phone', 'name' => 'agent_phone', 'type' => 'text'],
            ['key' => 'field_agent_role', 'label' => 'Role', 'name' => 'agent_role', 'type' => 'text'],
        ],
        'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'agent']]],
    ]);
});

add_action('admin_notices', function () {
    if (current_user_can('activate_plugins') && !function_exists('acf_add_local_field_group')) {
        echo '<div class="notice notice-warning"><p>Estate Search needs Advanced Custom Fields to edit property and agent details.</p></div>';
    }
});

function estate_property_data($post_id) {
    $price = (float) get_post_meta($post_id, 'price', true);
    $lat = get_post_meta($post_id, 'latitude', true);
    $lng = get_post_meta($post_id, 'longitude', true);
    $types = get_the_terms($post_id, 'property_type');
    return [
        'id' => (int) $post_id,
        'title' => get_the_title($post_id),
        'url' => get_permalink($post_id),
        'image' => get_the_post_thumbnail_url($post_id, 'large') ?: '',
        'price' => $price,
        'location' => (string) get_post_meta($post_id, 'location', true),
        'beds' => (int) get_post_meta($post_id, 'bedrooms', true),
        'baths' => (int) get_post_meta($post_id, 'bathrooms', true),
        'area' => (int) get_post_meta($post_id, 'area', true),
        'type' => $types && !is_wp_error($types) ? $types[0]->name : 'Property',
        'lat' => is_numeric($lat) && $lat >= -90 && $lat <= 90 ? (float) $lat : null,
        'lng' => is_numeric($lng) && $lng >= -180 && $lng <= 180 ? (float) $lng : null,
    ];
}

function estate_request_value($request, $name) {
    $value = $request->get_param($name);
    return is_scalar($value) ? (string) $value : '';
}

add_action('rest_api_init', function () {
    register_rest_route('estate/v1', '/properties', [
        'methods' => 'GET', 'permission_callback' => '__return_true',
        'callback' => function (WP_REST_Request $request) {
            $location = sanitize_text_field(substr(estate_request_value($request, 'location'), 0, 120));
            $type = sanitize_title(estate_request_value($request, 'type'));
            $min = max(0, (int) estate_request_value($request, 'min_price'));
            $max = max(0, (int) estate_request_value($request, 'max_price'));
            $beds = max(0, (int) estate_request_value($request, 'bedrooms'));
            if ($max && $min > $max) { return new WP_Error('bad_range', 'Minimum price exceeds maximum price.', ['status' => 400]); }
            $meta = ['relation' => 'AND'];
            if ($location !== '') { $meta[] = ['key' => 'location', 'value' => $location, 'compare' => 'LIKE']; }
            if ($min) { $meta[] = ['key' => 'price', 'value' => $min, 'type' => 'NUMERIC', 'compare' => '>=']; }
            if ($max) { $meta[] = ['key' => 'price', 'value' => $max, 'type' => 'NUMERIC', 'compare' => '<=']; }
            if ($beds) { $meta[] = ['key' => 'bedrooms', 'value' => $beds, 'type' => 'NUMERIC', 'compare' => '>=']; }
            $page = max(1, (int) estate_request_value($request, 'page'));
            // shortcut: saved homes load up to 50 at once; batch requests if collections grow beyond that.
            $include = array_slice(array_filter(array_map('absint', explode(',', estate_request_value($request, 'include')))), 0, 50);
            $args = [
                'post_type' => 'property', 'post_status' => 'publish', 'posts_per_page' => 12,
                'paged' => $page, 'meta_query' => $meta,
                'tax_query' => $type ? [['taxonomy' => 'property_type', 'field' => 'slug', 'terms' => $type]] : [],
            ];
            if ($include) { $args['post__in'] = $include; $args['posts_per_page'] = 50; $args['orderby'] = 'post__in'; }
            $query = new WP_Query($args);
            return rest_ensure_response([
                'items' => array_map('estate_property_data', wp_list_pluck($query->posts, 'ID')),
                'total' => (int) $query->found_posts,
                'pages' => (int) $query->max_num_pages,
            ]);
        },
    ]);
});

add_action('admin_post_nopriv_estate_viewing', 'estate_handle_viewing');
add_action('admin_post_estate_viewing', 'estate_handle_viewing');
add_action('admin_post_nopriv_estate_contact', 'estate_handle_contact');
add_action('admin_post_estate_contact', 'estate_handle_contact');
function estate_handle_viewing() {
    $property_id = absint($_POST['property_id'] ?? 0);
    $redirect = $property_id && get_post_status($property_id) === 'publish' ? get_permalink($property_id) : home_url('/');
    if (!isset($_POST['estate_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['estate_nonce'])), 'estate_viewing_' . $property_id)) {
        wp_die('Your request could not be verified.', 'Request error', ['response' => 403]);
    }
    if (!empty($_POST['website'])) { wp_safe_redirect(add_query_arg('viewing', 'sent', $redirect)); exit; }
    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
    $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
    $agent_id = absint(get_post_meta($property_id, 'agent', true));
    $recipient = sanitize_email(get_post_meta($agent_id, 'agent_email', true));
    if (get_post_status($property_id) !== 'publish' || get_post_status($agent_id) !== 'publish' || !$name || strlen($name) > 120 || strlen($message) > 2000 || !is_email($email) || !$recipient || !is_email($recipient)) {
        wp_safe_redirect(add_query_arg('viewing', 'invalid', $redirect)); exit;
    }
    $limit_key = 'estate_view_' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? '') . $email);
    if (get_transient($limit_key)) { wp_safe_redirect(add_query_arg('viewing', 'error', $redirect)); exit; }
    set_transient($limit_key, 1, 30);
    $body = "Property: " . get_the_title($property_id) . "\nURL: " . get_permalink($property_id) . "\nName: $name\nEmail: $email\nPhone: $phone\n\n$message";
    $sent = wp_mail($recipient, 'Viewing request: ' . get_the_title($property_id), $body, ['Reply-To: ' . $email]);
    wp_safe_redirect(add_query_arg('viewing', $sent ? 'sent' : 'error', $redirect)); exit;
}

function estate_handle_contact() {
    $redirect = home_url('/contact/');
    if (!isset($_POST['estate_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['estate_nonce'])), 'estate_contact')) {
        wp_die('Your request could not be verified.', 'Request error', ['response' => 403]);
    }
    if (!empty($_POST['website'])) { wp_safe_redirect(add_query_arg('contact', 'sent', $redirect)); exit; }
    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
    if (!$name || strlen($name) > 120 || !is_email($email) || !$message || strlen($message) > 2000) { wp_safe_redirect(add_query_arg('contact', 'invalid', $redirect)); exit; }
    $limit_key = 'estate_contact_' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? '') . $email);
    if (get_transient($limit_key)) { wp_safe_redirect(add_query_arg('contact', 'error', $redirect)); exit; }
    set_transient($limit_key, 1, 30);
    $sent = wp_mail(get_option('admin_email'), 'Website enquiry from ' . $name, "Name: $name\nEmail: $email\n\n$message", ['Reply-To: ' . $email]);
    wp_safe_redirect(add_query_arg('contact', $sent ? 'sent' : 'error', $redirect)); exit;
}
