<?php

require_once ('product.php');
require_once ('page.php');
require_once ('category.php');
require_once ('brand.php');

add_action('rest_api_init', 'product_routes');
add_action('rest_api_init', 'registerPageRoutes');
add_action('rest_api_init', 'registerCategoryRoutes');
add_action('rest_api_init', 'registerBrandRoutes');
