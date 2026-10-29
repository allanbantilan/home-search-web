<?php
if (!defined('ABSPATH')) { exit; }

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'gallery', 'caption']);
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('home-search', get_stylesheet_uri(), [], '1.0.0');
    if (is_post_type_archive('property')) {
        wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
        wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);
    }
    wp_enqueue_script('home-search', get_template_directory_uri() . '/assets/js/site.js', is_post_type_archive('property') ? ['leaflet'] : [], '1.0.0', true);
    wp_add_inline_script('home-search', 'window.HomeSearch=' . wp_json_encode([
        'searchUrl' => esc_url_raw(rest_url('estate/v1/properties')),
    ]) . ';', 'before');
});

add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive('property')) { return; }
    $meta = ['relation' => 'AND'];
    $location = sanitize_text_field(home_search_query_value('location'));
    $min = absint(home_search_query_value('min_price'));
    $max = absint(home_search_query_value('max_price'));
    $beds = absint(home_search_query_value('bedrooms'));
    if ($location !== '') { $meta[] = ['key' => 'location', 'value' => $location, 'compare' => 'LIKE']; }
    if ($min) { $meta[] = ['key' => 'price', 'value' => $min, 'compare' => '>=', 'type' => 'NUMERIC']; }
    if ($max) { $meta[] = ['key' => 'price', 'value' => $max, 'compare' => '<=', 'type' => 'NUMERIC']; }
    if ($beds) { $meta[] = ['key' => 'bedrooms', 'value' => $beds, 'compare' => '>=', 'type' => 'NUMERIC']; }
    $query->set('meta_query', $meta);
    $type = sanitize_title(home_search_query_value('type'));
    if ($type) { $query->set('tax_query', [['taxonomy' => 'property_type', 'field' => 'slug', 'terms' => $type]]); }
    $query->set('posts_per_page', 12);
});

function home_search_money($amount) { return '₱' . number_format((float) $amount); }
function home_search_query_value($name) {
    $value = $_GET[$name] ?? '';
    return is_scalar($value) ? wp_unslash((string) $value) : '';
}
function home_search_archive_url($type = '') {
    $url = get_post_type_archive_link('property');
    return $type ? add_query_arg('type', $type, $url) : $url;
}
function home_search_contact_url() { return home_url('/contact/'); }
function home_search_compare_url() { return home_url('/compare/'); }
function home_search_property_types() {
    if (!taxonomy_exists('property_type')) { return []; }
    $terms = get_terms(['taxonomy' => 'property_type', 'hide_empty' => true]);
    return is_wp_error($terms) ? [] : $terms;
}

add_action('after_switch_theme', function () {
    foreach (['compare' => 'Compare homes', 'favorites' => 'Saved homes', 'contact' => 'Contact'] as $slug => $title) {
        if (!get_page_by_path($slug)) { wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title]); }
    }
});
