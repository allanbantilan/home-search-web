<?php
// Capture mail only in the isolated Docker test stack.
add_filter('pre_wp_mail', function ($return, $args) {
    update_option('home_search_last_mail', $args);
    return true;
}, 10, 2);
