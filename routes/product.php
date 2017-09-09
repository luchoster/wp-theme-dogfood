<?php

function _formatProduct($raw_product) {
  $product = new WC_Product_Variable($raw_product);

  return (object) [
    'ID'                => $raw_product->ID,
    'name'              => $product->get_name(),
    'status'            => $product->get_status(),
    'description'       => $product->get_description(),
    'short_description' => $product->get_short_description(),
    'image'             => get_the_post_thumbnail_url($raw_product->ID),
    'featured'          => $product->is_featured(),
    'category'          => get_the_terms($raw_product->ID, 'product_cat'),
    'variations'        => $product->get_available_variations(),
    'extra_fields'      => get_field('extra_fields', $raw_product->ID),
  ];
}

function searchProduct($request) {
  $args = array(
    'post_type' => 'product',
    's' => $request['starts_with'],
    'status' => 'publish',
    'posts_per_page' => $request['limit'],
    'offset' => ( $request['page'] - 1 ) * $request['limit'],
  );

  add_filter( 'posts_search', 'search_by_title', 20, 2 );
  $query = new WP_Query($args);
  $products = array_map('_formatProduct', $query->posts);

  $result = (object) [
    'query' => $request->get_query_params(),
    'count' => $query->found_posts,
    'rows'  => $products,
  ];

  return $result;
}

function search_by_title($search, $wp_query){

    global $wpdb;

    if(empty($search))
        return $search;

    $q = $wp_query->query_vars;
    $n = !empty($q['exact']) ? '' : '%';

    $search = $searchand = '';

    foreach((array)$q['search_terms'] as $term) :

        $term = esc_sql(like_escape($term));

        $search.= "{$searchand}($wpdb->posts.post_title REGEXP '[[:<:]]{$term}')";

        $searchand = ' AND ';

    endforeach;

    if(!empty($search)) :
        $search = " AND ({$search}) ";
    endif;

    return $search;

}

function getProductById($request) {
  $args = array(
    'post_type' => 'product',
    'status' => 'publish',
    'p' => $request['pid'],
  );

  $query_result = new WP_Query($args);
  $query_result_count = count($query_result->posts);

  return $query_result_count == 0 ?
    (object) array() : _formatProduct($query_result->post);
}

function product_routes() {
  register_rest_route(ENDPOINT_V1, '/product', array(
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'searchProduct',
    'args' => array(
      'page' => array(
        'default' => 1,
        'type' => 'integer',
        'sanitize_callback' => 'absint',
      ),
      'limit' => array(
        'default' => 12,
        'type' => 'integer',
        'sanitize_callback' => 'absint',
      ),
      'starts_with' => array(
        'default' => '',
        'type' => 'string',
      )
    )
  ));

  register_rest_route(ENDPOINT_V1, '/product/(?P<pid>\d+)', array(
    'method' => WP_REST_Server::READABLE,
    'callback' => 'getProductById',
    'args' => array(
      'pid' => array('validate_callback' => 'is_numeric')
    ),
  ));
}
