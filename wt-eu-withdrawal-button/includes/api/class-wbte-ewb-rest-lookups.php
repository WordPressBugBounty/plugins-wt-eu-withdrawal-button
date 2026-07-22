<?php
/**
 * REST controller for admin lookup data (categories, products).
 *
 * @package Wbte_Eu_Withdrawal_Button
 * @since   1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wbte_Ewb_REST_Lookups
 *
 * @since 1.0.1
 */
class Wbte_Ewb_REST_Lookups extends Wbte_Ewb_REST_Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'lookups';

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/product-categories',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_product_categories' ),
					'permission_callback' => array( $this, 'admin_permission_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/products',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'search_products' ),
					'permission_callback' => array( $this, 'admin_permission_check' ),
					'args'                => array(
						'search'  => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'include' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Return product categories for exclusion settings.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_product_categories() {
		$categories = array();
		$terms      = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$categories[] = array(
					'id'   => (int) $term->term_id,
					'name' => $term->name,
				);
			}
		}

		return $this->success_response( $categories );
	}

	/**
	 * Search products or resolve saved product IDs.
	 *
	 * Uses the same WooCommerce product data store search as wc-product-search.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function search_products( $request ) {
		if ( ! class_exists( 'WC_Data_Store' ) ) {
			return $this->error_response(
				'wbte_ewb_missing_dependency',
				__( 'WooCommerce is not available.', 'wt-eu-withdrawal-button' ),
				500
			);
		}

		$include = sanitize_text_field( (string) $request->get_param( 'include' ) );
		$results = array();

		if ( '' !== $include ) {
			$ids = array_filter( array_map( 'absint', explode( ',', $include ) ) );

			foreach ( $ids as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$results[] = $this->format_product_result( $product );
				}
			}

			return $this->success_response( $results );
		}

		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		if ( strlen( $search ) < 2 ) {
			return $this->success_response( array() );
		}

		$data_store = WC_Data_Store::load( 'product' );
		$product_ids = $data_store->search_products( $search, '', true, false, 20 );

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$results[] = $this->format_product_result( $product );
			}
		}

		return $this->success_response( $results );
	}

	/**
	 * Format a product for lookup responses.
	 *
	 * @param WC_Product $product Product object.
	 * @return array{id:int,name:string}
	 */
	private function format_product_result( $product ) {
		return array(
			'id'   => $product->get_id(),
			'name' => wp_strip_all_tags(
				html_entity_decode( $product->get_formatted_name(), ENT_QUOTES, get_bloginfo( 'charset' ) )
			),
		);
	}
}
