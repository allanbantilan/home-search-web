<?php if (!defined('ABSPATH')) { exit; } ?><!doctype html>
<html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="<?php echo esc_attr(is_front_page() ? 'Search homes by location, price and bedrooms. Compare properties and arrange a viewing.' : wp_get_document_title() . ' — property listings, details and viewing information.'); ?>"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?><a class="skip-link" href="#content">Skip to content</a>
<header class="site-header"><div class="wrap header-inner">
<a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><span class="brand-mark" aria-hidden="true"></span><span>Home Search</span></a>
<nav class="main-nav" id="site-nav" aria-label="Primary navigation">
<a href="<?php echo esc_url(home_url('/')); ?>">Home</a><a href="<?php echo esc_url(home_search_archive_url()); ?>">Properties</a><a href="<?php echo esc_url(get_post_type_archive_link('agent')); ?>">Our agents</a><a href="<?php echo esc_url(home_search_contact_url()); ?>">Contact</a>
</nav>
<div class="header-actions"><a class="count-link" href="<?php echo esc_url(home_url('/favorites/')); ?>">Saved <span data-favorite-count>0</span></a><a class="count-link" href="<?php echo esc_url(home_search_compare_url()); ?>">Compare <span data-compare-count>0</span></a><a class="button button-small" href="<?php echo esc_url(home_search_contact_url()); ?>">Let's talk <span aria-hidden="true">↗</span></a><button class="menu-toggle" type="button" aria-controls="site-nav" aria-expanded="false" aria-label="Open menu">☰</button></div>
</div></header><main id="content">
