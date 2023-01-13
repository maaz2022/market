<?php

defined( 'ABSPATH' ) or die( 'Keep Silent' );

if ( ! class_exists( 'Woo_2Checkout_Gateway' ) ):
	class Woo_2Checkout_Gateway extends WC_Payment_Gateway {

		protected $merchant_code;
		protected $secret_key;
		protected $buy_link_secret_word;
		protected $debug;
		protected $demo;
		protected $icon_style;
		protected $icon_width;
		protected $api;
		protected $log;

		public function __construct() {

			$this->id                 = 'woo-2checkout';
			$this->icon               = woo_2checkout()->images_uri( '2checkout-dark.svg' );
			$this->has_fields         = false;
			$this->method_title       = esc_html__( '2Checkout Payment Gateway', 'woo-2checkout' );
			$this->method_description = esc_html__( '2Checkout accept mobile and online payments from customers worldwide.', 'woo-2checkout' );

			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Define user set variables.
			$this->title                = $this->get_option( 'title', esc_html__( '2Checkout', 'woo-2checkout' ) );
			$this->description          = $this->get_option( 'description', esc_html__( 'Pay via 2Checkout. Accept Credit Cards, PayPal and Debit Cards', 'woo-2checkout' ) );
			$this->order_button_text    = $this->get_option( 'order_button_text', esc_html__( 'Proceed to 2Checkout', 'woo-2checkout' ) );
			$this->merchant_code        = $this->get_option( 'merchant_code' );
			$this->secret_key           = htmlspecialchars_decode( $this->get_option( 'secret_key' ) );
			$this->buy_link_secret_word = htmlspecialchars_decode( $this->get_option( 'buy_link_secret_word' ) );
			$this->debug                = wc_string_to_bool( $this->get_option( 'debug', 'no' ) );
			$this->demo                 = wc_string_to_bool( $this->get_option( 'demo', 'yes' ) );
			$this->icon_style           = $this->get_option( 'icon_style', 'dark' );
			$this->icon_width           = $this->get_option( 'icon_width', '50' );

			//  Init 2co api
			$this->load_api();

			$this->admin_notices();

			// Will return to site/?wc-api=woo-2checkout-gateway-return
			// Will return to site/?wc-api=woo-2checkout-ipn-response
			// Will return to site/?wc-api=woo-2checkout-lcn-response
			// Will return to site/?wc-api=woo-2checkout-ins-response

			add_action( 'woocommerce_api_woo-2checkout-gateway-return', array( $this, 'process_gateway_return' ) );
			add_action( 'woocommerce_api_woo-2checkout-ipn-response', array( $this, 'process_ipn_response' ) );
			add_action( 'woocommerce_api_woo-2checkout-ins-response', array( $this, 'process_ins_response' ) );
			add_action( 'woocommerce_update_options_payment_gateways_woo-2checkout', array(
				$this,
				'process_admin_options'
			) );

			if ( ! defined( 'WOO_2CO_CUSTOM_NOTIFICATION_KEY' ) ) {
				//	define( 'WOO_2CO_CUSTOM_NOTIFICATION', 'NOTIFICATION' );
			}
			if ( ! defined( 'WOO_2CO_CUSTOM_NOTIFICATION_SECRETE' ) ) {
				//	define( 'WOO_2CO_CUSTOM_NOTIFICATION_SECRETE', '4D_TRVhixL8yB#swCH2a' );
			}


			do_action( 'woo_2checkout_gateway_init', $this );
		}

		public function needs_setup() {
			return ( empty( $this->merchant_code ) || empty( $this->secret_key ) || empty( $this->buy_link_secret_word ) );
		}

		public function get_id() {
			return $this->id;
		}

		protected function load_api() {
			$this->api = new Woo_2Checkout_Gateway_API( $this->merchant_code, $this->secret_key );
		}

		public function get_api() {

			if ( is_object( $this->api ) ) {
				return $this->api;
			}

			$this->load_api();

			return $this->api;
		}

		public function get_icon() {

			// Override Icon
			$icon_url = apply_filters( 'woo_2checkout_icon', woo_2checkout()->images_uri( sprintf( '2checkout-%s.svg', $this->icon_style ) ) );

			$icons_str = sprintf( '<img  class="woo-2checkout-gateway-pay-image" alt="%s" src="%s" style="width: %d%%" />', esc_attr( $this->order_button_text ), esc_url( $icon_url ), absint( $this->icon_width ) );

			return apply_filters( 'woocommerce_gateway_icon', $icons_str, $this->get_id(), $this );
		}

		public function init_form_fields() {

			$this->form_fields = array();

			$this->form_fields['enabled'] = array(
				'title'   => esc_html__( 'Enable/Disable', 'woo-2checkout' ),
				'type'    => 'checkbox',
				'label'   => esc_html__( 'Enable 2Checkout Payment Gateway', 'woo-2checkout' ),
				'default' => 'yes'
			);

			$this->form_fields['title'] = array(
				'title'       => esc_html__( 'Title', 'woo-2checkout' ),
				'type'        => 'text',
				'description' => esc_html__( 'This controls the title which the user sees during checkout.', 'woo-2checkout' ),
				'default'     => esc_html__( '2Checkout', 'woo-2checkout' ),
				'desc_tip'    => true
			);

			$this->form_fields['description'] = array(
				'title'       => esc_html__( 'Description', 'woo-2checkout' ),
				'type'        => 'textarea',
				'description' => esc_html__( 'This controls the description which the user sees during checkout.', 'woo-2checkout' ),
				'default'     => esc_html__( 'Pay via 2Checkout. Accept Credit Cards, PayPal and Debit Cards', 'woo-2checkout' )
			);

			$this->form_fields['order_button_text'] = array(
				'title'       => esc_html__( 'Order button text', 'woo-2checkout' ),
				'type'        => 'text',
				'description' => esc_html__( 'Checkout order button text.', 'woo-2checkout' ),
				'default'     => esc_html__( 'Proceed to 2Checkout', 'woo-2checkout' ),
				'desc_tip'    => true
			);

			$this->form_fields['webhook'] = array(
				'title'       => sprintf( '<a href="https://getwooplugins.com/documentation/woocommerce-2checkout/" target="_blank">%s</a>', esc_html__( 'Read How to Setup', 'woo-2checkout' ) ),
				'type'        => 'title',
				/* translators: webhook URL */
				'description' => $this->display_admin_settings_webhook_description(),
			);

			$this->form_fields['merchant_code'] = array(
				'title'       => esc_html__( 'Merchant Code', 'woo-2checkout' ),
				'type'        => 'text',
				'default'     => '',
				'desc_tip'    => false,
				'description' => sprintf( __( 'Please enter 2Checkout <strong>Merchant Code</strong> from <a target="_blank" href="%s">Integrations > Webhooks &amp; API > API Section</a>.', 'woo-2checkout' ), 'https://secure.2checkout.com/cpanel/webhooks_api.php' )
			);

			$this->form_fields['secret_key'] = array(
				'title'       => esc_html__( 'Secret Key', 'woo-2checkout' ),
				'type'        => 'text',
				'description' => sprintf( __( 'Please enter 2Checkout <strong>Secret Key</strong> from <a target="_blank" href="%s">Integrations > Webhooks &amp; API > API Section</a>', 'woo-2checkout' ), 'https://secure.2checkout.com/cpanel/webhooks_api.php' ),
				'default'     => '',
				'desc_tip'    => false
			);

			$this->form_fields['buy_link_secret_word'] = array(
				'title'       => esc_html__( 'Buy Link Secret Word', 'woo-2checkout' ),
				'type'        => 'text',
				'description' => sprintf( __( 'Please enter 2Checkout <strong>Buy link secret word</strong> from <a target="_blank" href="%s">Integrations > Webhooks &amp; API > Secret word</a> section', 'woo-2checkout' ), 'https://secure.2checkout.com/cpanel/webhooks_api.php' ),
				'default'     => '',
				'desc_tip'    => false
			);

			$this->form_fields['demo'] = array(
				'title'       => esc_html__( 'Demo Mode', 'woo-2checkout' ),
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Enable Demo Mode', 'woo-2checkout' ),
				'default'     => 'yes',
				'description' => esc_html__( 'This mode allows you to test your setup to make sure everything works as expected without take real payment.', 'woo-2checkout' )
			);

			$this->form_fields['debug'] = array(
				'title'       => esc_html__( 'Debug Log', 'woo-2checkout' ),
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Enable Logging', 'woo-2checkout' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Log 2Checkout events, <strong>DON\'T ALWAYS ENABLE THIS.</strong> You can check this log in %s.', 'woo-2checkout' ), '<a target="_blank" href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->get_id() ) . '-' . sanitize_file_name( wp_hash( $this->get_id() ) ) . '.log' ) ) . '">' . esc_html__( 'System Status &gt; Logs', 'woo-2checkout' ) . '</a>' )
			);


			$this->form_fields['icon_style'] = array(
				'title'   => esc_html__( 'Gateway Icon Style', 'woo-2checkout' ),
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'label'   => esc_html__( 'Choose Gateway a Icon Style', 'woo-2checkout' ),
				'options' => array(
					'dark'  => esc_html__( 'Dark', 'woo-2checkout' ),
					'light' => esc_html__( 'Light', 'woo-2checkout' ),
				),
				'default' => 'dark'
			);

			$this->form_fields['icon_width'] = array(
				'title'             => esc_html__( 'Gateway Icon Width', 'woo-2checkout' ),
				'type'              => 'number',
				'description'       => esc_html__( 'Gateway Icon Width in %. Limit: 1-100', 'woo-2checkout' ),
				'default'           => '50',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'min'  => '1',
					'max'  => '100',
					'size' => '3',
				)
			);

			$this->form_fields['checkout_type'] = array(
				'title'    => esc_html__( 'Choose checkout type', 'woo-2checkout-pro' ),
				'type'     => 'select',
				'class'    => 'wc-enhanced-select',
				'label'    => esc_html__( 'Choose checkout type', 'woo-2checkout-pro' ),
				'options'  => array(
					'standard' => esc_html__( 'Standard Checkout ( Process on 2Checkout site )', 'woo-2checkout' ),
					'inline'   => esc_html__( 'Popup After Checkout - Inline Checkout - PRO FEATURE', 'woo-2checkout' ),
					'popup'    => esc_html__( 'Popup During Checkout - Inline Checkout - PRO FEATURE', 'woo-2checkout' ),
					'card'     => esc_html__( 'On Page Credit Card Only - PRO FEATURE', 'woo-2checkout' ),
				),
				'default'  => 'standard',
				'disabled' => true
			);

			$this->form_fields = apply_filters( 'woo_2checkout_admin_form_fields', $this->form_fields );
		}

		public function generate_select_html( $key, $data ) {
			$field_key = $this->get_field_key( $key );
			$defaults  = array(
				'title'             => '',
				'disabled'          => false,
				'class'             => '',
				'css'               => '',
				'placeholder'       => '',
				'type'              => 'text',
				'desc_tip'          => false,
				'description'       => '',
				'custom_attributes' => array(),
				'options'           => array(),
			);

			$data = wp_parse_args( $data, $defaults );

			ob_start();
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?><?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span>
						</legend>
						<select class="select <?php echo esc_attr( $data['class'] ); ?>" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?>>
							<?php foreach ( (array) $data['options'] as $option_key => $option_value ) : ?>
								<option <?php disabled( $data['disabled'], true ); ?> value="<?php echo esc_attr( $option_key ); ?>" <?php selected( (string) $option_key, esc_attr( $data['default'] ) ); ?>><?php echo esc_html( $option_value ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
					</fieldset>
				</td>
			</tr>
			<?php

			return ob_get_clean();
		}

		public function display_admin_settings_webhook_description() {
			return sprintf( __( '<strong>Webhook endpoint: </strong> <code style="background-color:#ddd;">%s</code> to your <a href="https://secure.2checkout.com/cpanel/ipn_settings.php" target="_blank">2Checkout IPN settings</a>', 'woo-2checkout' ), $this->get_ipn_response_url() );
		}

		public static function get_ipn_response_url() {
			return WC()->api_request_url( 'woo-2checkout-ipn-response' );
		}

		public static function get_ins_response_url() {
			return WC()->api_request_url( 'woo-2checkout-ins-response' );
		}

		public static function get_gateway_return_url() {
			return WC()->api_request_url( 'woo-2checkout-gateway-return' );
		}

		public function admin_notices() {
			if ( is_admin() ) {
				// Checks if account number and secret is not empty
				if ( wc_string_to_bool( $this->get_option( 'enabled' ) ) && ( empty( $this->merchant_code ) || empty( $this->secret_key ) || empty( $this->buy_link_secret_word ) ) ) {
					add_action( 'admin_notices', array( $this, 'plugin_not_configured_message' ) );
				}

				// Checks that the currency is supported
				if ( ! $this->using_supported_currency() ) {
					add_action( 'admin_notices', array( $this, 'currency_not_supported_message' ) );
				}
			}
		}

		public function using_supported_currency() {

			// https://knowledgecenter.2checkout.com/Documentation/07Commerce/Checkout-links-and-options/Order-interface-currencies

			$woocommerce_currency = get_woocommerce_currency();
			$supported_currencies = apply_filters( 'woo_2checkout_supported_currencies', array(
				'AED',
				'AFN',
				'ALL',
				'ARS',
				'AUD',
				'AZN',
				'BBD',
				'BDT',
				'BGN',
				'BHD',
				'BMD',
				'BND',
				'BOB',
				'BRL',
				'BSD',
				'BWP',
				'BYN',
				'BZD',
				'CAD',
				'CHF',
				'CLP',
				'CNY',
				'COP',
				'CRC',
				'CZK',
				'DKK',
				'DOP',
				'DZD',
				'EGP',
				'EUR',
				'FJD',
				'GBP',
				'GTQ',
				'HKD',
				'HNL',
				'HRK',
				'HTG',
				'HUF',
				'IDR',
				'ILS',
				'INR',
				'JMD',
				'JOD',
				'JPY',
				'KES',
				'KRW',
				'KWD',
				'KZT',
				'LAK',
				'LBP',
				'LKR',
				'LRD',
				'MAD',
				'MDL',
				'MMK',
				'MOP',
				'MRO',
				'MUR',
				'MVR',
				'MXN',
				'MYR',
				'NAD',
				'NGN',
				'NIO',
				'NOK',
				'NPR',
				'NZD',
				'OMR',
				'PAB',
				'PEN',
				'PGK',
				'PHP',
				'PKR',
				'PLN',
				'PYG',
				'QAR',
				'RON',
				'RSD',
				'RUB',
				'SAR',
				'SBD',
				'SCR',
				'SEK',
				'SGD',
				'SVC',
				'SYP',
				'THB',
				'TND',
				'TOP',
				'TRY',
				'TTD',
				'TWD',
				'UAH',
				'USD',
				'UYU',
				'VEF',
				'VND',
				'VUV',
				'WST',
				'XCD',
				'XOF',
				'YER',
				'ZAR',

			) );

			return ! ! in_array( $woocommerce_currency, $supported_currencies );

		}

		public function is_available() {
			// Test if is valid for use.
			return parent::is_available() && ! empty( $this->merchant_code ) && ! empty( $this->secret_key ) && ! empty( $this->buy_link_secret_word ) && $this->using_supported_currency();
		}

		public function get_checkout_payment_url( $args ) {
			return $this->get_api()->convertplus_buy_link( $args, $this->merchant_code, $this->buy_link_secret_word );
		}

		public function process_payment( $order_id ) {

			$order = wc_get_order( $order_id );
			$args  = $this->payment_args( $order );

			$this->log( "PROCESS PAYMENT ARGS:\n" . print_r( $args, true ) );
			// echo $this->get_checkout_payment_url( $args ); die;

			if ( $payment_url = $this->get_checkout_payment_url( $args ) ) {
				return array(
					'result'   => 'success',
					'redirect' => $payment_url
				);
			} else {
				return array(
					'result' => 'failure',
				);
			}
		}

		/** Log
		 *
		 * @param        $message
		 * @param string $level
		 *     'emergency': System is unusable.
		 *     'alert': Action must be taken immediately.
		 *     'critical': Critical conditions.
		 *     'error': Error conditions.
		 *     'warning': Warning conditions.
		 *     'notice': Normal but significant condition.
		 *     'info': Informational messages.
		 *     'debug': Debug-level messages.
		 */
		public function log( $message, $level = 'info' ) {
			if ( $this->debug ) {
				if ( is_null( $this->log ) ) {
					$this->log = wc_get_logger();
				}

				$context = array( 'source' => $this->get_id() );
				// $this->log->info( $message, $context );

				$this->log->log( $level, $message, $context );
			}
		}

		public function shop_language() {
			$lang = apply_filters( 'woo_2checkout_shop_language', '' );

			if ( '' !== $lang ) {
				return $lang;
			}

			$_lang = explode( '_', ( get_locale() ? get_locale() : get_option( 'WPLANG' ) ) );
			$lang  = $_lang[0];
			if ( 'es' == $lang ) {
				$lang = 'es_ib';
			}

			$available = array(
				'zh',
				'da',
				'nl',
				'fr',
				'gr',
				'el',
				'it',
				'jp',
				'no',
				'pt',
				'sl',
				'es_ib',
				'es_la',
				'sv',
				'en'
			);

			if ( ! in_array( $lang, $available ) ) {
				return 'en';
			}

			return $lang;
		}

		public function format_item_price( $price ) {
			return (float) number_format( (float) $price, wc_get_price_decimals(), wc_get_price_decimal_separator(), '' );
		}

		public function format_item_name( $item_name ) {
			return trim( html_entity_decode( wc_trim_string( $item_name ? wp_strip_all_tags( $item_name ) : esc_html__( 'Item', 'woo-2checkout' ), 127 ), ENT_NOQUOTES, 'UTF-8' ) );
		}

		// https://knowledgecenter.2checkout.com/Documentation/07Commerce/2Checkout-ConvertPlus/ConvertPlus_URL_parameters
		public function payment_args( $order ) {


			$ship_to_different_address = isset( $_POST['ship_to_different_address'] ) ? true : false;

			$ship_to_different_address = ! empty( $ship_to_different_address ) && ! wc_ship_to_billing_address_only();


			$args = array();

			$args['dynamic'] = true;

			if ( $this->demo ) {
				$args['test'] = true;
			}

			// Billing information

			$args['email'] = sanitize_email( $order->get_billing_email() );
			$args['name']  = esc_html( $order->get_formatted_billing_full_name() );

			if ( $order->get_billing_phone() ) {
				$args['phone'] = esc_html( $order->get_billing_phone() );
			}

			if ( $order->get_billing_country() ) {
				$args['country'] = esc_html( $order->get_billing_country() );
			}

			if ( $order->get_billing_city() ) {
				// $args['state'] = $order->get_billing_state();
				$args['city'] = esc_html( $order->get_billing_city() );
			}

			if ( $order->get_billing_state() ) {
				// $args['state'] = $order->get_billing_state();
				$args['state'] = esc_html( WC()->countries->get_states( $order->get_billing_country() )[ $order->get_billing_state() ] );
			}

			if ( $order->has_billing_address() ) {
				$args['address']  = esc_html( $order->get_billing_address_1() );
				$args['address2'] = esc_html( $order->get_billing_address_2() );
			}

			if ( $order->get_billing_postcode() ) {
				$args['zip'] = esc_html( $order->get_billing_postcode() );
			}

			if ( $order->get_billing_company() ) {
				$args['company-name'] = esc_html( $order->get_billing_company() );
			}

			// Delivery/Shipping information
			// $order->needs_shipping_address()  /  WC()->cart->needs_shipping_address()
			if ( wc_shipping_enabled() && $order->needs_shipping_address() ) {

				$args['ship-name']  = $ship_to_different_address ? esc_html( $order->get_formatted_shipping_full_name() ) : esc_html( $order->get_formatted_billing_full_name() );
				$args['ship-email'] = sanitize_email( $order->get_billing_email() );

				if ( $order->get_shipping_phone() || $order->get_billing_phone() ) {
					$args['ship-phone'] = ( $ship_to_different_address && $order->get_shipping_phone() ) ? esc_html( $order->get_shipping_phone() ) : esc_html( $order->get_billing_phone() );
				}

				if ( $order->get_shipping_country() || $order->get_billing_country() ) {
					$args['ship-country'] = $ship_to_different_address ? esc_html( $order->get_shipping_country() ) : esc_html( $order->get_billing_country() );
				}

				if ( $order->get_shipping_state() || $order->get_billing_state() ) {
					$ship_country       = $ship_to_different_address ? $order->get_shipping_country() : $order->get_billing_country();
					$ship_state         = $ship_to_different_address ? $order->get_shipping_state() : $order->get_billing_state();
					$args['ship-state'] = esc_html( WC()->countries->get_states( $ship_country )[ $ship_state ] );
				}

				if ( $order->get_shipping_city() || $order->get_billing_city() ) {
					$args['ship-city'] = $ship_to_different_address ? esc_html( $order->get_shipping_city() ) : esc_html( $order->get_billing_city() );
				}


				if ( $order->has_shipping_address() || $order->has_billing_address() ) {
					$args['ship-address']  = $ship_to_different_address ? esc_html( $order->get_shipping_address_1() ) : esc_html( $order->get_billing_address_1() );
					$args['ship-address2'] = $ship_to_different_address ? esc_html( $order->get_shipping_address_2() ) : esc_html( $order->get_billing_address_2() );
				}

				if ( $order->get_shipping_postcode() || $order->get_billing_postcode() ) {
					$args['ship-zip'] = $ship_to_different_address ? esc_html( $order->get_shipping_postcode() ) : esc_html( $order->get_billing_postcode() );
				}
			}

			// Product information
			$product_info                 = array();
			$product_info['prod']         = array();
			$product_info['opt']          = array();
			$product_info['price']        = array();
			$product_info['qty']          = array();
			$product_info['tangible']     = array();
			$product_info['type']         = array();
			$product_info['item-ext-ref'] = array();

			// Products
			if ( count( $order->get_items() ) > 0 ) {
				foreach ( $order->get_items() as $item ) {

					// $item = new WC_Order_Item_Product(); // WC_Order_Item

					$product = $item->get_product();

					if ( ! $product ) {
						continue;
					}

					$product_info['prod'][]  = $this->format_item_name( $item->get_name() );
					$product_info['price'][] = $this->format_item_price( $order->get_item_total( $item ) );
					$product_info['qty'][]   = $item->get_quantity(); // get_item_total

					if ( $product->is_downloadable() && $product->is_virtual() ) {
						$product_info['type'][] = 'digital';
					} else {
						$product_info['type'][] = 'physical';
					}

					$product_info['item-ext-ref'][] = $product->get_id();
				}
			}

			// Tax.
			if ( wc_tax_enabled() && 0 < $order->get_total_tax() ) {

				if ( get_option( 'woocommerce_tax_total_display' ) == 'itemized' ) {
					foreach ( $order->get_tax_totals() as $tax ) {
						$product_info['type'][]         = 'tax';
						$product_info['prod'][]         = esc_html( $tax->label );
						$product_info['price'][]        = $this->format_item_price( $tax->amount );
						$product_info['qty'][]          = 1;
						$product_info['item-ext-ref'][] = '';
					}
				} else {
					$product_info['type'][] = 'tax';
					//$product_info['__type'][]       = 'tax';
					$product_info['prod'][]  = esc_html( WC()->countries->tax_or_vat() );
					$product_info['price'][] = $this->format_item_price( $order->get_total_tax() );
					$product_info['qty'][]   = 1;
					//$product_info['tangible'][]     = '0';
					$product_info['item-ext-ref'][] = '';
				}
			}

			// Support Custom Fees. Add custom fee from "woocommerce_cart_calculate_fees" hook
			if ( 0 < count( $order->get_fees() ) ) {
				foreach ( $order->get_fees() as $item ) {

					//  new WC_Order_Item_Fee()
					$product_info['type'][]         = 'tax';
					$product_info['prod'][]         = $this->format_item_name( $item->get_name() );
					$product_info['price'][]        = $this->format_item_price( $item->get_total() );
					$product_info['qty'][]          = 1;
					$product_info['item-ext-ref'][] = '';
				}
			}

			// Shipping
			if ( wc_shipping_enabled() && 0 < $order->get_shipping_total() ) {

				$shipping_name = $this->format_item_name( sprintf( esc_html__( 'Shipping via %s', 'woo-2checkout' ), $order->get_shipping_method() ) );

				$product_info['type'][]         = 'shipping';
				$product_info['prod'][]         = $shipping_name;
				$product_info['price'][]        = $this->format_item_price( $order->get_shipping_total() );
				$product_info['qty'][]          = 1;
				$product_info['item-ext-ref'][] = '';
			}

			$args['return-url']       = $this->get_gateway_return_url();
			$args['return-type']      = 'redirect'; // link
			$args['currency']         = get_woocommerce_currency();
			$args['language']         = $this->shop_language();
			$args['customer-ext-ref'] = $order->get_customer_id();
			$args['order-ext-ref']    = $order->get_id();
			$args['tpl']              = 'default'; // default, one-column

			$args['prod']         = implode( ';', $product_info['prod'] );
			$args['price']        = implode( ';', $product_info['price'] );
			$args['qty']          = implode( ';', $product_info['qty'] );
			$args['type']         = implode( ';', $product_info['type'] );
			$args['item-ext-ref'] = implode( ';', $product_info['item-ext-ref'] );

			$this->log( "REQUEST PARAMS \n" . print_r( $args, true ), 'info' );

			return apply_filters( 'woo_2checkout_payment_args', $args, $product_info, $order, $this );
		}

		public function get_checkout_order_received_url( $order ) {
			$order_received_url = wc_get_endpoint_url( 'downloads', $order->get_id(), wc_get_page_permalink( 'my-account' ) );

			if ( 'yes' === get_option( 'woocommerce_force_ssl_checkout' ) || is_ssl() ) {
				$order_received_url = str_replace( 'http:', 'https:', $order_received_url );
			}

			$order_received_url = add_query_arg( 'key', $order->get_order_key(), $order_received_url );

			return apply_filters( 'woocommerce_get_checkout_order_received_url', $order_received_url, $order );
		}

		public function process_gateway_return() {

			$data = stripslashes_deep( $_GET ); // WPCS: CSRF ok, input var ok.

			do_action( 'woo_2checkout_gateway_process_gateway_return', $data, $this );

			status_header( 200 );
			nocache_headers();

			if ( isset( $data['refno'] ) && ! empty( $data['refno'] ) ) {

				$order_id       = absint( sanitize_text_field( $data['order-ext-ref'] ) );
				$order          = wc_get_order( $order_id );
				$transaction_id = sanitize_text_field( $data['refno'] );
				$this->log( "Gateway Return Response \n" . print_r( $data, true ) );

				if ( ! $order ) {
					wp_die( sprintf( 'Order# %d is not available.', $order_id ), '2Checkout Request', array( 'response' => 200 ) );
				}

				$this->log( "Gateway Return Signature: \n" . print_r( array(
						'wc generated' => $this->get_api()->generate_return_signature( $data, $this->buy_link_secret_word ),
						'2co returned' => $data['signature'],
					), true ) );

				if ( ! $this->get_api()->is_valid_return_signature( $data, $this->buy_link_secret_word ) ) {
					$order->update_status( 'failed', 'Order failed due to 2checkout signature mismatch.' );
					wc_add_notice( "Order failed due to 2Checkout return signature mismatch.", 'error' );
					do_action( 'woo_2checkout_payment_signature mismatch', $data, $this );
					// wp_redirect( $order->get_cancel_order_url_raw() );
					wp_redirect( wc_get_checkout_url() );
					exit;
				}

				// Order
				$order->update_status( 'on-hold', 'Payment received and waiting for 2Checkout IPN response.' );
				update_post_meta( $order->get_id(), '_transaction_id', $transaction_id );
				WC()->cart->empty_cart();
				do_action( 'woo_2checkout_payment_processing', $order, $data, $this );
				wp_redirect( $this->get_return_url( $order ) );
				exit;

			} else {
				wp_die( '2Checkout Gateway Return no refno', '2Checkout Response', array( 'response' => 200 ) );
			}
		}

		public function is_valid_ins_response() {
			$data = stripslashes_deep( $_POST ); // WPCS: CSRF ok, input var ok.
		}

		public function process_ins_response() {
			$data = stripslashes_deep( $_POST ); // WPCS: CSRF ok, input var ok.
			$this->log( "INS Response: \n" . print_r( $data, true ), 'info' );
			do_action( 'woo_2checkout_gateway_process_ins_response', $data, $this );
		}

		public function process_ipn_response() {

			if ( ! $_POST ) {
				return;
			}

			// Don't alter any value otherwise 2checkout hash won't be matched
			$data = stripslashes_deep( $_POST ); // WPCS: CSRF ok, input var ok.

			do_action( 'woo_2checkout_gateway_process_ipn_response', $data, $this );

			status_header( 200 );
			nocache_headers();

			$transaction_id       = sanitize_text_field( $data['REFNO'] );
			$base_string_for_hash = $this->get_api()->generate_base_string_for_hash( $data );
			$ipn_receipt          = $this->get_api()->ipn_receipt_response( $data );

			$this->log( "IPN Base String For Hash: \n" . print_r( $base_string_for_hash, true ) );
			$this->log( "IPN Response: \n" . print_r( $data, true ), 'info' );
			$this->log( "IPN receipt_response: \n" . print_r( $ipn_receipt, true ), 'info' );

			if ( $ipn_receipt ) {

				$order_id = absint( sanitize_text_field( $data['REFNOEXT'] ) );

				$order = wc_get_order( $order_id );

				if ( ! $order ) {
					echo $ipn_receipt;
					do_action( 'woo_2checkout_gateway_process_ipn_response_invalid_order', $data, $this );
					$this->log( sprintf( 'Order# %d is not available.', $order_id ), 'error' );
					exit;
				}

				$order_status = $order->get_status();

				// Test Payment
				if ( isset( $data['TEST_ORDER'] ) && $data['TEST_ORDER'] ) {
					$order->add_order_note( 'IPN Response Received as Test Order' );
				}

				if ( isset( $data['ORDERSTATUS'] ) ) {
					switch ( $data['ORDERSTATUS'] ) {

						// Payment Authorized
						case 'PAYMENT_AUTHORIZED':
							if ( ! in_array( $order_status, array( 'processing', 'completed' ) ) ) {
								$order->update_status( 'on-hold', 'Order PAYMENT AUTHORIZED by 2Checkout IPN.' );
								do_action( 'woo_2checkout_ipn_response_order_processing', $data['ORDERSTATUS'], $order, $data, $this );
							}
							break;

						// Completed Order
						case 'COMPLETE':
							if ( ! in_array( $order_status, array( 'processing', 'completed' ) ) ) {
								$order->payment_complete( $transaction_id );
								update_user_meta( $order->get_customer_id(), 'woo_2checkout_previous_order', $transaction_id );
								do_action( 'woo_2checkout_ipn_response_order_complete', $data['ORDERSTATUS'], $order, $data, $this );
							}
							break;

						// Cancel Order
						case 'CANCELED':
							$order->update_status( 'cancelled', 'Order CANCELED by 2Checkout IPN' );
							do_action( 'woocommerce_cancelled_order', $order->get_id() );
							do_action( 'woo_2checkout_ipn_response_order_canceled', $data['ORDERSTATUS'], $order, $data, $this );
							break;

						//  Refund Order
						case 'REFUND':
							if ( $order_status !== 'refunded' ) {
								$order->update_status( 'refunded', 'Order REFUND by 2Checkout IPN' );
								do_action( 'woo_2checkout_ipn_response_order_refund', $data['ORDERSTATUS'], $order, $data, $this );
							}
							break;

						default:
							$this->log( sprintf( "IPN Response: ORDERSTATUS = %s \n", $data['ORDERSTATUS'] ) . print_r( $data, true ), 'info' );
							break;
					}
				}

				echo $ipn_receipt;
				exit();
			} else {
				$this->log( 'No IPN Receipt Response Code Generated.', 'error' );
				echo 'No IPN Receipt Generated.';
				exit();
			}
		}

		public function get_transaction_url( $order ) {
			$this->view_transaction_url = 'https://secure.2checkout.com/cpanel/order_info.php?refno=%s';

			return parent::get_transaction_url( $order );
		}

		public function plugin_not_configured_message() {
			$id = 'woocommerce_woo-2checkout_';

			if ( isset( $_POST[ $id . 'merchant_code' ] ) && ! empty( $_POST[ $id . 'merchant_code' ] ) && isset( $_POST[ $id . 'secret_key' ] ) && ! empty( $_POST[ $id . 'secret_key' ] ) && isset( $_POST[ $id . 'buy_link_secret_word' ] ) && ! empty( $_POST[ $id . 'buy_link_secret_word' ] ) ) {
				return;
			}

			echo '<div class="error"><p><strong>' . esc_html__( 'Payment Gateway - 2Checkout for WooCommerce disabled', 'woo-2checkout' ) . '</strong>: ' . esc_html__( 'You must fill the "Merchant Code" and the "Secret Key" and "Buy Link Secret Word" fields.', 'woo-2checkout' ) . '</p></div>';
		}

		public function currency_not_supported_message() {
			echo '<div class="error"><p><strong>' . esc_html__( 'Payment Gateway - 2Checkout for WooCommerce disabled', 'woo-2checkout' ) . '</strong>: ' . esc_html__( '2Checkout does not support your store currency.', 'woo-2checkout' ) . '</p></div>';
		}

		public function payment_fields() {

			$description = $this->get_description();

			if ( $this->demo ) {
				$description .= '.<br />' . sprintf( __( '<strong>DEMO MODE ENABLED.</strong> Use a %s', 'woo-2checkout' ), '<a target="_blank" href="https://knowledgecenter.2checkout.com/Documentation/09Test_ordering_system/01Test_payment_methods">test payment cards</a>' );
			}
			if ( $description ) {
				echo wpautop( wptexturize( trim( $description ) ) );
			}
		}
	}
endif;
