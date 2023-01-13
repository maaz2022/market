<?php
/**
 * Plugin Name: Payment Gateway - 2Checkout for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/woo-2checkout/
 * Description: 2Checkout Payment Gateway for WooCommerce. Requires WooCommerce 5.5+
 * Author: Emran Ahmed
 * Version: 2.0.7
 * Domain Path: /languages
 * Requires PHP: 7.0
 * Requires at least: 5.5
 * Tested up to: 5.8
 * WC requires at least: 5.2
 * WC tested up to: 5.6
 * Text Domain: woo-2checkout
 * Author URI: https://getwooplugins.com/
 */

defined( 'ABSPATH' ) or die( 'Keep Silent' );

if ( ! class_exists( 'Woo_2Checkout' ) ):

	final class Woo_2Checkout {

		protected $_version = '2.0.7';
		protected static $_instance = null;

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		public function __construct() {
			$this->constants();
			$this->language();
			$this->includes();
			$this->hooks();
			do_action( 'woo_2checkout_loaded', $this );
		}

		public function define( $name, $value, $case_insensitive = false ) {
			if ( ! defined( $name ) ) {
				define( $name, $value, $case_insensitive );
			}
		}

		public function constants() {
			$this->define( 'WOO_2CO_PLUGIN_URI', plugin_dir_url( __FILE__ ) );
			$this->define( 'WOO_2CO_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
			$this->define( 'WOO_2CO_VERSION', $this->version() );
			$this->define( 'WOO_2CO_PLUGIN_INCLUDE_PATH', trailingslashit( plugin_dir_path( __FILE__ ) . 'includes' ) );
			$this->define( 'WOO_2CO_PLUGIN_DIRNAME', dirname( plugin_basename( __FILE__ ) ) );
			$this->define( 'WOO_2CO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
			$this->define( 'WOO_2CO_PLUGIN_FILE', __FILE__ );
			$this->define( 'WOO_2CO_IMAGES_URI', trailingslashit( plugin_dir_url( __FILE__ ) . 'images' ) );
			$this->define( 'WOO_2CO_ASSETS_URI', trailingslashit( plugin_dir_url( __FILE__ ) . 'assets' ) );
		}

		public function includes() {
			if ( $this->is_required_php_version() && $this->is_wc_active() ) {
				require_once $this->include_path( 'class-woo-2checkout-gateway.php' );
				require_once $this->include_path( 'class-woo-2checkout-gateway-api.php' );
				require_once $this->include_path( 'functions.php' );
			}
		}

		public function is_pro_active() {
			return class_exists( 'Woo_2Checkout_Pro' );
		}

		public function get_pro_link( $medium = 'go-pro' ) {

			$affiliate_id = apply_filters( 'gwp_affiliate_id', 0 );

			$link_args = array();

			if ( ! empty( $affiliate_id ) ) {
				$link_args['ref'] = esc_html( $affiliate_id );
			}

			$link_args['utm_source']   = 'wp-admin-plugins';
			$link_args['utm_medium']   = esc_attr( $medium );
			$link_args['utm_campaign'] = 'woo-2checkout';
			$link_args['utm_term']     = sanitize_title( $this->get_parent_theme_name() );

			$link_args = apply_filters( 'wvs_get_pro_link_args', $link_args );

			return esc_url( add_query_arg( $link_args, 'https://getwooplugins.com/plugins/woocommerce-2checkout/' ) );
		}

		public function include_path( $file ) {
			$file = ltrim( $file, '/' );

			return WOO_2CO_PLUGIN_INCLUDE_PATH . $file;
		}

		public function hooks() {
			add_action( 'admin_notices', array( $this, 'php_requirement_notice' ) );
			add_action( 'admin_notices', array( $this, 'wc_requirement_notice' ) );
			add_action( 'admin_notices', array( $this, 'wc_version_requirement_notice' ) );
			add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );

			if ( $this->is_required_php_version() && $this->is_wc_active() ) {

				add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );

				add_filter( 'plugin_action_links_' . $this->basename(), array( $this, 'plugin_action_links' ) );
			}
		}

		public function add_gateway( $methods ) {

			$methods[] = $this->get_gateway_class_name();

			return $methods;
		}

		public function get_gateway_class_name() {
			return apply_filters( 'woo_2checkout_get_gateway_class_name', 'Woo_2Checkout_Gateway', $this );
		}

		public function plugin_action_links( $links ) {

			$new_links = array();

			$settings_link = esc_url( add_query_arg( array(
				'page'    => 'wc-settings',
				'tab'     => 'checkout',
				'section' => 'woo-2checkout'
			), admin_url( 'admin.php' ) ) );

			$new_links['settings'] = sprintf( '<a href="%1$s" title="%2$s">%2$s</a>', $settings_link, esc_attr__( 'Settings', 'woo-2checkout' ) );

			return array_merge( $links, $new_links );
		}

		public function is_required_php_version() {
			return version_compare( PHP_VERSION, '5.6.0', '>=' );
		}

		public function is_required_wc_version() {
			return version_compare( WC_VERSION, '3.5', '>' );
		}

		public function wc_version_requirement_notice() {
			if ( $this->is_wc_active() && ! $this->is_required_wc_version() ) {
				$class   = 'notice notice-error';
				$message = sprintf( esc_html__( "Currently, you are using older version of WooCommerce. It's recommended to use latest version of WooCommerce to work with %s.", 'woo-2checkout' ), esc_html__( 'WooCommerce 2Checkout Payment Gateway', 'woo-2checkout' ) );
				printf( '<div class="%1$s"><p><strong>%2$s</strong></p></div>', $class, $message );
			}
		}

		public function php_requirement_notice() {
			if ( ! $this->is_required_php_version() ) {
				$class   = 'notice notice-error';
				$text    = esc_html__( 'Please check PHP version requirement.', 'woo-2checkout' );
				$link    = esc_url( 'https://docs.woocommerce.com/document/server-requirements/' );
				$message = wp_kses( __( "It's required to use latest version of PHP to use <strong>Payment Gateway - 2Checkout for WooCommerce</strong>.", 'woo-2checkout' ), array( 'strong' => array() ) );

				printf( '<div class="%1$s"><p>%2$s <a target="_blank" href="%3$s">%4$s</a></p></div>', $class, $message, $link, $text );
			}
		}

		public function wc_requirement_notice() {

			if ( ! $this->is_wc_active() ) {

				$class = 'notice notice-error';

				$text    = esc_html__( 'WooCommerce', 'woo-2checkout' );
				$link    = esc_url( add_query_arg( array(
					'tab'       => 'plugin-information',
					'plugin'    => 'woocommerce',
					'TB_iframe' => 'true',
					'width'     => '640',
					'height'    => '500',
				), admin_url( 'plugin-install.php' ) ) );
				$message = wp_kses( __( "<strong>Payment Gateway - 2Checkout for WooCommerce</strong> is a payment gateway plugin of ", 'woo-2checkout' ), array( 'strong' => array() ) );

				printf( '<div class="%1$s"><p>%2$s <a class="thickbox open-plugin-details-modal" href="%3$s"><strong>%4$s</strong></a></p></div>', $class, $message, $link, $text );
			}
		}

		public function language() {
			load_plugin_textdomain( 'woo-2checkout', false, trailingslashit( WOO_2CO_PLUGIN_DIRNAME ) . 'languages' );
		}

		public function is_wc_active() {
			return class_exists( 'WooCommerce' );
		}

		public function basename() {
			return WOO_2CO_PLUGIN_BASENAME;
		}

		public function dirname() {
			return WOO_2CO_PLUGIN_DIRNAME;
		}

		public function version() {
			return esc_attr( $this->_version );
		}

		public function plugin_path() {
			return untrailingslashit( plugin_dir_path( __FILE__ ) );
		}

		public function plugin_uri() {
			return untrailingslashit( plugins_url( '/', __FILE__ ) );
		}

		public function images_uri( $file ) {
			$file = ltrim( $file, '/' );

			return WOO_2CO_IMAGES_URI . $file;
		}

		public function assets_uri( $file ) {
			$file = ltrim( $file, '/' );

			return WOO_2CO_ASSETS_URI . $file;
		}

		public function plugin_row_meta( $links, $file ) {
			if ( $file == $this->basename() ) {

				$report_url = add_query_arg( array(
					'utm_source'   => 'wp-admin-plugins',
					'utm_medium'   => 'row-meta-link',
					'utm_campaign' => 'woo-2checkout'
				), 'https://getwooplugins.com/tickets/' );

				$documentation_url = add_query_arg( array(
					'utm_source'   => 'wp-admin-plugins',
					'utm_medium'   => 'row-meta-link',
					'utm_campaign' => 'woo-2checkout'
				), 'https://getwooplugins.com/documentation/woocommerce-2checkout/' );

				$row_meta['documentation'] = sprintf( '<a target="_blank" href="%1$s" title="%2$s">%2$s</a>', esc_url( $documentation_url ), esc_html__( 'Read Documentation', 'woo-2checkout' ) );
				$row_meta['issues']        = sprintf( '%2$s <a target="_blank" href="%1$s">%3$s</a>', esc_url( $report_url ), esc_html__( 'Facing issue?', 'woo-2checkout' ), '<span style="color: red">' . esc_html__( 'Please open a ticket.', 'woo-2checkout' ) . '</span>' );

				return array_merge( $links, $row_meta );
			}

			return (array) $links;
		}

		public function get_theme_name() {
			return wp_get_theme()->get( 'Name' );
		}

		public function get_theme_dir() {
			return strtolower( basename( get_template_directory() ) );
		}

		public function get_parent_theme_name() {
			return wp_get_theme( get_template() )->get( 'Name' );
		}

		public function get_parent_theme_dir() {
			return strtolower( basename( get_stylesheet_directory() ) );
		}
	}

	function woo_2checkout() {
		return Woo_2Checkout::instance();
	}

	add_action( 'plugins_loaded', 'woo_2checkout' );

endif;