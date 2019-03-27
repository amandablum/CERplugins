<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Flexible_Shipping_UPS_Checkout' ) ) {

	class Flexible_Shipping_UPS_Checkout {

		/**
		 * @var Flexible_Shipping_UPS_Plugin
		 */
		private $plugin;

		/**
		 * Flexible_Shipping_UPS_Checkout constructor.
		 *
		 * @param Flexible_Shipping_UPS_Plugin $plugin
		 */
		public function __construct( $plugin ) {
			$this->plugin = $plugin;
		}

		public function hooks() {
			add_action( 'woocommerce_review_order_after_shipping', array( $this, 'woocommerce_review_order_after_shipping_action' ) );
			add_action( 'woocommerce_checkout_update_order_review', array( $this, 'woocommerce_checkout_update_order_review_action' ) );

			add_action( 'woocommerce_checkout_process', array( $this, 'woocommerce_checkout_process_action' ) );

			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'woocommerce_checkout_update_order_meta_action' ), 10, 2 );

			add_action( 'woocommerce_checkout_create_order', array( $this, 'woocommerce_checkout_create_order_action' ), 10, 2 );
		}

		/**
		 * On create order - Woocommerce 3.0 and later.
		 *
		 * @param WC_Order $order
		 * @param array $data
		 */
		public function woocommerce_checkout_create_order_action( $order, $data ) {
			if ( $this->is_ups_access_point_in_selected_shipping_method() ) {
				if ( isset( $_POST['ups_access_point'] ) ) {
					$order->add_meta_data( '_ups_access_point', $_POST['ups_access_point'] );
				}
			}
		}

		/**
		 * On update order meta - Woocommerce before 3.0.
		 *
		 * @param int $order_id
		 * @param array $data
		 */
		public function woocommerce_checkout_update_order_meta_action( $order_id, $data ) {
			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				if ( $this->is_ups_access_point_in_selected_shipping_method() ) {
					if ( isset( $data['ups_access_point'] ) ) {
						update_post_meta( $order_id, '_ups_access_point', $data['ups_access_point'] );
					}
				}
			}
		}

		/**
		 * Validate checkout for selected access point.
		 */
		public function woocommerce_checkout_process_action() {
			if ( WC()->cart->needs_shipping() ) {
				if ( $this->is_ups_access_point_in_selected_shipping_method() ) {
					if ( empty( $_POST['ups_access_point'] ) ) {
						wc_add_notice( __( 'Please select UPS Access Point', 'flexible-shipping-ups' ), 'error' );
					}
				}
			}
		}

		/**
		 * Force shipping recalculation.
		 *
		 * @param array $post_data Post data.
		 */
		public function woocommerce_checkout_update_order_review_action( $post_data ) {
			/*
			 * Force shipping recalculation!
			 * https://stackoverflow.com/a/45763102
			 */
			foreach ( WC()->cart->get_cart() as $key => $value ) {
				WC()->cart->set_quantity( $key, $value['quantity'] + 1 );
				WC()->cart->set_quantity( $key, $value['quantity'] );
				break;
			}
		}

		/**
		 * Check if UPS shipping method with access point is selected in checkout.
		 *
		 * @return bool
		 */
		private function is_ups_access_point_in_selected_shipping_method() {
			$ups_access_point_in_selected_shipping_method = false;

			$shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
			foreach ( $shipping_methods as $shipping_method ) {
				if ( strpos( $shipping_method, 'flexible_shipping_ups' ) === 0 ) {
					if ( strpos( $shipping_method, '_access_point' ) !== false ) {
						$ups_access_point_in_selected_shipping_method = true;
					}
				}
			}
			return $ups_access_point_in_selected_shipping_method;
		}

		/**
		 * Displays select item with UPS access points.
		 *
		 * @throws Exception
		 */
		public function woocommerce_review_order_after_shipping_action() {
			if ( ! $this->is_ups_access_point_in_selected_shipping_method() ) {
				return;
			}
			if ( !empty( $_REQUEST['post_data'] ) ) {
				parse_str( $_REQUEST['post_data'], $post_data );
			}
			else {
				$post_data = array();
			}
			if ( empty( $_REQUEST['s_country'] ) ) {
				$country = WC()->countries->get_base_country();
			}
			else {
				$country = $_REQUEST['s_country'];
			}
			if ( empty( $_REQUEST['s_postcode'] ) ) {
				$postcode = get_option( 'woocommerce_store_postcode', '' );
			}
			else {
				$postcode = $_REQUEST['s_postcode'];
			}

			$args = array();

			$access_points = $this->plugin->get_access_points_helper();

			$args['nearest_location'] = $access_points->get_nearest_access_point_for_postcode( $country, $postcode );

			$locations = $access_points->get_access_points_for_postcode( $country, $postcode, 50 );

			$args['select_options'] = $access_points->prepare_items_for_select_field( $locations );

			$args['select_options'] = array( '0' => __( 'Select access point', 'flexible-shipping-ups' ) ) + $args['select_options'];

			$args['selected_access_point'] = '0';
			if ( isset( $post_data['ups_access_point'] ) && array_key_exists( $post_data['ups_access_point'], $args['select_options'] ) ) {
				$args['selected_access_point'] = $post_data['ups_access_point'];
			}
			echo $this->plugin->load_template( 'shipping-method-after', '', $args );

		}
	}

}
