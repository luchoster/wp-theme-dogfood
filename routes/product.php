<?php

function get_yoast_products($post_id){
  $yoastMeta = array(
    'yoast_wpseo_focuskw'               => get_post_meta( $post_id, '_yoast_wpseo_focuskw', true  ),
    'yoast_wpseo_title'                 => get_post_meta( $post_id, '_yoast_wpseo_title', true  ),
    'yoast_wpseo_metadesc'              => get_post_meta( $post_id, '_yoast_wpseo_metadesc', true  ),
    'yoast_wpseo_linkdex'               => get_post_meta( $post_id, '_yoast_wpseo_linkdex', true  ),
    'yoast_wpseo_metakeywords'          => get_post_meta( $post_id, '_yoast_wpseo_metakeywords', true  ),
    'yoast_wpseo_meta_robots_noindex'   => get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true  ),
    'yoast_wpseo_meta_robots_nofollow'  => get_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', true  ),
    'yoast_wpseo_meta_robots_adv'       => get_post_meta( $post_id, '_yoast_wpseo_meta-robots-adv', true  ),
    'yoast_wpseo_canonical'             => get_post_meta( $post_id, '_yoast_wpseo_canonical', true  ),
    'yoast_wpseo_redirect'              => get_post_meta( $post_id, '_yoast_wpseo_redirect', true  ),
    'yoast_wpseo_opengraph_title'       => get_post_meta( $post_id, '_yoast_wpseo_opengraph-title', true  ),
    'yoast_wpseo_opengraph_description' => get_post_meta( $post_id, '_yoast_wpseo_opengraph-description', true  ),
    'yoast_wpseo_opengraph_image'       => get_post_meta( $post_id, '_yoast_wpseo_opengraph-image', true  ),
    'yoast_wpseo_twitter_title'         => get_post_meta( $post_id, '_yoast_wpseo_twitter-title', true  ),
    'yoast_wpseo_twitter_description'   => get_post_meta( $post_id, '_yoast_wpseo_twitter-description', true  ),
    'yoast_wpseo_twitter_image'         => get_post_meta( $post_id, '_yoast_wpseo_twitter-image', true  )
  );

    return $yoastMeta;
}

function _formatProduct($raw_product) {
  $product = new WC_Product_Variable($raw_product);


  return (object) [
    'ID'                      => $raw_product->ID,
    'name'                    => $product->get_name(),
    'meta'                    => get_yoast_products($post_id),
    'status'                  => $product->get_status(),
    'description'             => $product->get_description(),
    'short_description'       => $product->get_short_description(),
    'image'                   => get_the_post_thumbnail_url($raw_product->ID),
    'thumbnail'               => get_the_post_thumbnail_url($raw_product->ID, 'thumbnail'),
    'featured'                => $product->is_featured(),
    'category'                => get_the_terms($raw_product->ID, 'product_cat'),
    'variations'              => $product->get_available_variations(),
    'slug'                    => $product->get_slug(),
    'additional_descriptions' => get_field('additional_descriptions', $raw_product->ID),
    'brand'                   => get_field('brand', $raw_product->ID),
    'nutritional_info'        => get_field('nutritional_info', $raw_product->ID),
    'feeding_instructions'    => get_field('feeding_instructions', $raw_product->ID)
  ];
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

// @param array('cat' => string, 'featured' => int)
//
// @return tax_query array map
function _getSearchProductTaxQuery($request) {
  $query_count = count(array_filter($request, function($val, $key) {
    return $val != null;
  }, ARRAY_FILTER_USE_BOTH));

  if ($query_count == 0)
    return null;

  $tax_query = $query_count > 1 ? array('relation' => 'AND') : array();

  if ($request['featured'] == 1)
    array_push($tax_query, array(
      'taxonomy' => 'product_visibility',
      'field'    => 'name',
      'terms'    => 'featured',
    ));

  if ($request['cat'])
    array_push($tax_query, array(
      'taxonomy' => 'product_cat',
      'field' => 'slug',
      'terms' => explode(',', $request['cat']),
    ));

  return $tax_query;
}

function searchProduct($request) {
  $tax_query = _getSearchProductTaxQuery(array(
    'cat' => $request['cat'],
    'featured' => $request['featured'],
  ));
  $meta_query = is_null($request['brand']) ? null : array(
    array(
      'key' => 'brand',
      'compare' => 'IN',
      'value' => explode(',', $request['brand']),
    )
  );

  $args = array(
    'post_type' => 'product',
    's' => $request['starts_with'],
    'status' => 'publish',
    'posts_per_page' => $request['limit'],
    'offset' => ( $request['page'] - 1 ) * $request['limit'],
    'tax_query' => $tax_query,
    'meta_query' => $meta_query
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

function getProductBySlug($request) {
  $args = array(
    'post_type' => 'product',
    'status' => 'publish',
    'name' => $request['slug'],
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
      ),
      'featured' => array(
        'type' => 'integer',
      ),
      'cat' => array(
        'type' => 'string',
      ),
      'brand' => array(
        'type' => 'string',
      )
    )
  ));

  register_rest_route(ENDPOINT_V1, '/product/(?P<slug>[a-zA-Z0-9-]+)', array(
    'method' => WP_REST_Server::READABLE,
    'callback' => 'getProductBySlug',
  ));
}
