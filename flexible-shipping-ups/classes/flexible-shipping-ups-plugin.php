<?php

/**
 * Class Flexible_Shipping_UPS_Plugin
 */
class Flexible_Shipping_UPS_Plugin extends \WPDesk\PluginBuilder\Plugin\AbstractPlugin implements \WPDesk\PluginBuilder\Plugin\HookableCollection {

	use \WPDesk\PluginBuilder\Plugin\HookableParent;
	use \WPDesk\PluginBuilder\Plugin\TemplateLoad;


	const LOGGING_CONTEXT = 'flexible-shipping-ups';

	/**
	 * Scripts version.
	 *
	 * @var string
	 */
	private $scripts_version = '14';

	/**
	 * @var Flexible_Shipping_UPS_Checkout
	 */
	private $checkout;

	/**
	 * @var Flexible_Shipping_UPS_Access_Points_Helper
	 */
	private $access_points_helper;

	/**
	 * Flexible_Shipping_UPS_Plugin constructor.
	 *
	 * @param string $base_file
	 * @param array $plugin_data
	 *
	 */
	public function __construct( WPDesk_Plugin_Info $plugin_info ) {
		$this->plugin_info = $plugin_info;
		parent::__construct( $this->plugin_info );
	}

	/**
	 * Load dependencies.
	 */
	public function load_dependencies() {
	}

	/**
	 * Init plugin.
	 */
	public function init() {
		$this->init_base_variables();
		$this->load_dependencies();
		$this->checkout = new Flexible_Shipping_UPS_Checkout( $this );
		$this->checkout->hooks();

		$this->add_hookable( new Flexible_Shipping_UPS_Connect_Platform_Info( strtotime( '2018-10-26 23:59:59' ) ) );

		$this->add_hookable( new \WPDesk\Notice\AjaxHandler( trailingslashit( $this->get_plugin()->get_plugin_url() ) . 'vendor/wpdesk/wp-notice/assets' ) );

		$this->hooks();
	}

	/**
	 * Init base variables for plugin
	 */
	public function init_base_variables() {
		$this->plugin_url = $this->plugin_info->get_plugin_url();

		$this->plugin_path   = $this->plugin_info->get_plugin_dir();
		$this->template_path = $this->plugin_info->get_text_domain();

		$this->plugin_text_domain   = $this->plugin_info->get_text_domain();
		$this->plugin_namespace     = $this->plugin_info->get_text_domain();
		$this->template_path        = $this->plugin_info->get_text_domain();
		$this->default_settings_tab = 'main';

		$locale             = get_locale();
		$this->settings_url = admin_url( 'admin.php?page=wc-settings&tab=shipping&section=flexible_shipping_ups' );
		$this->docs_url     = 'pl_PL' === $locale ? 'https://www.wpdesk.pl/docs/ups-woocommerce-docs/' : 'https://www.wpdesk.net/docs/flexible-shipping-ups/';

		$this->default_view_args = array(
			'plugin_url' => $this->get_plugin_url()
		);

	}

	/**
	 * Hooks.
	 */
	public function hooks() {
		parent::hooks();
		add_filter( 'woocommerce_shipping_methods', array( $this, 'woocommerce_shipping_methods_filter' ), 20, 1 );
		add_action( 'wp_ajax_flexible_shipping_ups_api_status', array( $this, 'wp_ajax_flexible_shipping_ups_api_status' ) );
		add_filter( 'woocommerce_order_shipping_method', array( $this, 'woocommerce_order_shipping_method_filter' ), 10, 2 );
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded_action' ), 9 );
		add_action( 'admin_notices', array( $this, 'admin_notices_action' ) );
		$this->hooks_on_hookable_objects();
	}

	/**
	 * Enqueue admin scripts.
	 */
	public function admin_enqueue_scripts() {
		parent::admin_enqueue_scripts();
		$current_screen = get_current_screen();

		if ( 'woocommerce_page_wc-settings' === $current_screen->id ) {
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_register_style( 'ups_admin_css', $this->get_plugin_assets_url() . 'css/admin' . $suffix . '.css', array(), $this->scripts_version );
			wp_enqueue_style( 'ups_admin_css' );

			wp_enqueue_script( 'flexible_shipping_ups_admin', trailingslashit( $this->get_plugin_assets_url() ) . 'js/admin' . $suffix . '.js', array(), $this->scripts_version );
			wp_localize_script( 'flexible_shipping_ups_admin', 'flexible_shipping_ups_admin', array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'ajax_nonce' => wp_create_nonce( 'flexible_shipping_ups_api_status' ),
			) );
		}
	}

	/**
	 * Links on plugins list.
	 *
	 * @param array $links Links.
	 *
	 * @return array
	 */
	public function links_filter( $links ) {
		$locale       = get_locale();
		$docs_link    = $this->docs_url;
		$docs_link   .= '?utm_source=ups&utm_medium=quick-link&utm_campaign=docs-quick-link';
		$support_link = 'pl_PL' === $locale ? 'https://www.wpdesk.pl/support/' : 'https://www.wpdesk.net/support';

		$plugin_links = array(
			'<a href="' . $this->settings_url . '">' . __( 'Settings', 'flexible-shipping-ups' ) . '</a>',
			'<a href="' . $docs_link . '">' . __( 'Docs', 'flexible-shipping-ups' ) . '</a>',
			'<a href="' . $support_link . '">' . __( 'Support', 'flexible-shipping-ups' ) . '</a>',
		);
		return array_merge( $plugin_links, $links );
	}

	/**
	 * Return Access Points Helper.
	 *
	 * @return Flexible_Shipping_UPS_Access_Points_Helper
	 */
	public function get_access_points_helper() {
		if ( empty( $this->access_points_helper ) ) {
			$shipping_method            = $this->get_ups_shipping_method();
			$this->access_points_helper = new Flexible_Shipping_UPS_Access_Points_Helper(
				$shipping_method->settings['access_key'],
				$shipping_method->settings['user_id'],
				$shipping_method->settings['password']
			);
		}
		return $this->access_points_helper;
	}

	/**
	 * Get UPS shipping method.
	 *
	 * @return Flexible_Shipping_UPS_Shipping_Method
	 */
	public function get_ups_shipping_method() {
		$shipping_methods = WC()->shipping()->get_shipping_methods();
		/**
		 * IDE type hint.
		 *
		 * @var Flexible_Shipping_UPS_Shipping_Method $flexible_shipping_ups
		 */
		$flexible_shipping_ups = $shipping_methods['flexible_shipping_ups'];
		return $flexible_shipping_ups;
	}

	/**
	 * Check api connection status.
	 *
	 * @param bool $return
	 *
	 * @return mixed|string|void
	 */
	public function wp_ajax_flexible_shipping_ups_api_status( $return = false ) {
		check_ajax_referer( 'flexible_shipping_ups_api_status', 'security' );
		$flexible_shipping_ups = $this->get_ups_shipping_method();
		$json_response = array( 'connected' => true, 'status' => 'OK', 'class_name' => 'flexible_shipping_ups_api_status_ok' );
		$connection_errors = $flexible_shipping_ups->check_connection_error();
		if ( $connection_errors ) {
			$json_response = array( 'connected' => false, 'status' => $connection_errors, 'class_name' => 'flexible_shipping_ups_api_status_error' );
		}
		if ( !$return ) {
			echo json_encode( $json_response );
			die();
		}
		else {
			return json_encode( $json_response );
		}
	}

	/**
	 * Adds shipping method to Woocommerce.
	 * @param $methods
	 *
	 * @return mixed
	 */
	public function woocommerce_shipping_methods_filter( $methods ) {
		include_once( 'shipping-method.php' );
		$methods['flexible_shipping_ups'] = 'Flexible_Shipping_UPS_Shipping_Method';
		return $methods;
	}

	/**
	 * Add (Fallback) string on orders list when fallback action was taken for shipping.
	 *
	 * @param string $shipping_method_name
	 * @param WC_Order $order
	 * @return string
	 */
	public function woocommerce_order_shipping_method_filter( $shipping_method_name, $order ) {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return $shipping_method_name;
		}
		$current_screen = get_current_screen();
		if ( !is_object( $current_screen ) || $current_screen->id != 'edit-shop_order' || isset( $_GET['action'] ) ) {
			return $shipping_method_name;
		}
		$shipping_methods = $order->get_shipping_methods();
		/** @var WC_Order_Item_Shipping $shipping_method */
		$add_to_name = '';
		foreach ( $shipping_methods as $shipping_method ) {
			if ( version_compare( WC_VERSION, '2.7', '<' ) ) {
				if ( isset( $shipping_method['method_id'] ) ) {
					$method_id = $shipping_method['method_id'];
					$method_id_elements = explode( ':', $method_id );
					if ( isset( $method_id_elements[0] ) && isset( $method_id_elements[2] ) ) {
						if ( $method_id_elements[0] == 'flexible_shipping_ups' && $method_id_elements[2] == 'fallback' ) {
							$add_to_name = __( ' (Fallback)', 'flexible-shipping-ups' );
						}
					}
				}
			}
			else {
				$method_id = $shipping_method->get_method_id();
				$method_id_elements = explode( ':', $method_id );
				if ( isset( $method_id_elements[0] ) && isset( $method_id_elements[2] ) ) {
					if ( $method_id_elements[0] == 'flexible_shipping_ups' && $method_id_elements[2] == 'fallback' ) {
						$add_to_name = __( ' (Fallback)', 'flexible-shipping-ups' );
					}
				}
			}
		}
		$add_to_name = trim( $add_to_name, ',' );
		return $shipping_method_name . $add_to_name;
	}

	/**
	 * Plugins loaded hook.
	 */
	public function plugins_loaded_action() {
		if ( ! function_exists( 'should_enable_wpdesk_tracker' ) ) {
			function should_enable_wpdesk_tracker() {
				$tracker_enabled = true;
				if ( ! empty( $_SERVER['SERVER_ADDR'] ) && $_SERVER['SERVER_ADDR'] === '127.0.0.1' ) {
					$tracker_enabled = false;
				}

				return apply_filters( 'wpdesk_tracker_enabled', $tracker_enabled );
			}
		}

		$tracker_factory = new WPDesk_Tracker_Factory();
		$tracker_factory->create_tracker( basename( dirname( __FILE__ ) ) );
	}

	/**
	 * Display admin notice.
	 */
	public function admin_notices_action() {
		$wc_message = false;
		if ( !function_exists( 'WC' ) ) {
			$wc_message = true;
		}
		else {
			if ( version_compare( WC_VERSION, '2.6', '<' ) ) {
				$wc_message = true;
			}
		}
		if ( $wc_message ) {
			$class = 'notice notice-error';
			$message = __( 'Flexible Shipping UPS requires at least version 2.6 of WooCommerce plugin.', 'flexible-shipping-ups' );
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
		}
	}

}
