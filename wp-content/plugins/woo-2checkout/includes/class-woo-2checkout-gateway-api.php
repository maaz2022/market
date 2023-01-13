<?php

defined( 'ABSPATH' ) or die( 'Keep Silent' );

if ( ! class_exists( 'Woo_2Checkout_Gateway_API' ) ):

	class Woo_2Checkout_Gateway_API {

		const POST = 'POST';
		const GET = 'GET';
		const PUT = 'PUT';
		const DELETE = 'DELETE';

		protected $merchant_code;

		protected $secret_key;

		public function __construct( $merchant_code, $secret_key ) {
			$this->merchant_code = $merchant_code;
			$this->secret_key    = $secret_key;
		}


		public function generate_jwt_token( $merchant_id, $iat, $exp, $buy_link_secret_word ) {

			$header    = $this->encode( json_encode( array( 'alg' => 'HS512', 'typ' => 'JWT' ) ) );
			$payload   = $this->encode( json_encode( array( 'sub' => $merchant_id, 'iat' => $iat, 'exp' => $exp ) ) );
			$signature = $this->encode(
				hash_hmac( 'sha512', "$header.$payload", $buy_link_secret_word, true )
			);

			return implode( '.', array(
				$header,
				$payload,
				$signature
			) );
		}

		/**
		 * @param $data
		 *
		 * @return string|string[]
		 */
		private function encode( $data ) {
			return str_replace( '=', '', strtr( base64_encode( $data ), '+/', '-_' ) );
		}


		// https://knowledgecenter.2checkout.com/Documentation/07Commerce/2Checkout-ConvertPlus/ConvertPlus_URL_parameters
		// https://knowledgecenter.2checkout.com/Documentation/07Commerce/2Checkout-ConvertPlus/ConvertPlus_Buy-Links_Signature
		public function convertplus_buy_link_signature( $params, $buy_link_secret_word ) {

			$signature_params = array(
				'return-url',
				'return-type',
				'expiration',
				'order-ext-ref',
				'item-ext-ref',
				'customer-ref',
				'customer-ref',
				'customer-ext-ref',
				// 'lock',
				'currency',
				'prod',
				'price',
				'qty',
				'type',
				'opt',
				'description',
				'recurrence',
				'duration',
				'renewal-price'
			);

			$filtered_params = array_filter( $params, function ( $key ) use ( $signature_params ) {
				return in_array( $key, $signature_params );
			}, ARRAY_FILTER_USE_KEY );

			$serialize_string = $this->convertplus_serialize( $filtered_params );


			//$serialize_string = $this->convertplus_serialize( $params );

			return hash_hmac( 'sha256', $serialize_string, $buy_link_secret_word );
		}

		public function convertplus_buy_link( $data, $merchant_code, $buy_link_secret_word ) {

			$data = array( 'merchant' => $merchant_code ) + $data;

			if ( ! isset( $data['expiration'] ) ) {
				$data['expiration'] = absint( time() + ( HOUR_IN_SECONDS * 5 ) ); // 5 hours; 60 mins; 60 secs
			}

			$data['signature'] = $this->convertplus_buy_link_signature( $data, $buy_link_secret_word );

			return 'https://secure.2checkout.com/checkout/buy/?' . http_build_query( $data );
		}

		// https://knowledgecenter.2checkout.com/Documentation/07Commerce/2Checkout-ConvertPlus/How-to-use-2Checkout-Signature-Generation-API-Endpoint#PHP_23
		public function get_signature( $params, $buy_link_secret_word ) {

			$jwt_token = $this->generate_jwt_token(
				$this->merchant_code,
				time(),
				time() + 3600,
				$buy_link_secret_word
			);

			$response = wp_remote_post( "https://secure.2checkout.com/checkout/api/encrypt/generate/signature", array(
				'headers' => array(
					"content-type"   => "application/json",
					"cache-control"  => "no-cache",
					"merchant-token" => $jwt_token,
				),
				'body'    => wp_json_encode( $params )

			) );

			$response_body = wp_remote_retrieve_body( $response );
			$response_data = json_decode( $response_body );

			/*if ( is_wp_error( $response ) ) {
				return $response;
			}*/

			if ( isset( $response_data->signature ) ) {
				return $response_data->signature;
			}

			if ( isset( $response_data->error_code ) ) {
				wc_add_notice( $response_data->message, 'error' );

				// return new WP_Error( $response_data->error_code, $response_data->message );
				return false;
			}

			wc_add_notice( '2Checkout: Unable to get signature response from signature generation API.', 'error' );

			return false;

		}

		public function convertplus_serialize( $params ) {

			ksort( $params );

			$map_data = array_map( function ( $value ) {
				// return strlen( stripslashes( $value ) ) . stripslashes( $value );
				return strlen( $value ) . $value;
			}, $params );

			// print_r($map_data); die;

			return implode( '', $map_data );
		}

		// https://knowledgecenter.2checkout.com/API-Integration/Webhooks/06Instant_Payment_Notification_(IPN)/Calculate-the-IPN-HASH-signature
		public function is_valid_ipn_lcn_hash( $post_data, $secret_key ) {

			$ipn_hash = $post_data["HASH"];

			$generate_string = $this->generate_base_string_for_hash( $post_data );

			$server_hash = hash_hmac( 'md5', $generate_string, $secret_key );

			return $server_hash == $ipn_hash;
		}

		public function generate_base_string_for_hash( $params ) {

			$string = "";

			unset( $params['HASH'] );

			foreach ( $params as $value ) {

				if ( is_array( $value ) ) {
					$string .= $this->generate_base_string_for_hash( $value );
				} else {
					$string .= strlen( $value ) . $value;
				}
			}

			return $string;
		}


		/*public function generate_base_string_for_hash( $post_data ) {
			$generate_string = '';
			array_walk_recursive( $post_data, function ( $value, $key ) use ( &$generate_string ) {
				if ( $key != 'HASH' ) {
					//$generate_string .= strlen( stripslashes( $value ) ) . stripslashes( $value );
					$generate_string .= strlen( $value ) . $value;
				}
			} );

			return $generate_string;
		}*/


		// https://knowledgecenter.2checkout.com/API-Integration/Webhooks/06Instant_Payment_Notification_(IPN)/Read-receipt-response-for-2Checkout
		// https://knowledgecenter.2checkout.com/API-Integration/Webhooks/IPN_and_LCN_URL_settings
		public function ipn_receipt_response( $post_data, $secret_key = false ) {
			// <EPAYMENT>DATE|HASH</EPAYMENT>

			if ( ! isset( $post_data["IPN_PID"] ) || ! isset( $post_data["IPN_PNAME"] ) ) {
				return false;
			}

			$receipt_date = gmdate( 'YmdHis' );

			$ipn_receipt = array(
				$post_data["IPN_PID"][0],
				$post_data["IPN_PNAME"][0],
				$post_data["IPN_DATE"],
				$receipt_date
			);

			// CUSTOM IPN AND LCN CONFIGURATIONS
			if ( ! $secret_key ) {
				$secret_key = $this->secret_key;
			}

			$receipt_return = implode( '', array_map( function ( $value ) {
				return strlen( stripslashes( $value ) ) . stripslashes( $value );
			}, $ipn_receipt ) );

			$receipt_hash = hash_hmac( 'md5', $receipt_return, $secret_key );

			if ( $this->is_valid_ipn_lcn_hash( $post_data, $secret_key ) ) {
				return "<EPAYMENT>{$receipt_date}|{$receipt_hash}</EPAYMENT>";
			} else {
				return false;
			}
		}

		// https://knowledgecenter.2checkout.com/Documentation/07Commerce/2Checkout-ConvertPlus/Signature_validation_for_return_URL_via_ConvertPlus
		// https://knowledgecenter.2checkout.com/Documentation/07Commerce/InLine-Checkout-Guide/Signature_validation_for_return_URL_via_InLine_checkout

		public function generate_return_signature( $params, $buy_link_secret_word ) {

			if ( empty( $params ) || ! isset( $params['signature'] ) || empty( $params['signature'] ) ) {
				return false;
			}

			// Remove signature key from params list.
			unset( $params['signature'], $params['wc-api'] );
			$serialize_string = $this->convertplus_serialize( $params );

			return hash_hmac( 'sha256', $serialize_string, $buy_link_secret_word );
		}

		public function is_valid_return_signature( $params, $buy_link_secret_word ) {

			if ( empty( $params ) || ! isset( $params['signature'] ) || empty( $params['signature'] ) ) {
				return false;
			}

			$return_signature = sanitize_text_field( $params['signature'] );

			// Remove signature key from params list.
			unset( $params['signature'], $params['wc-api'] );
			$serialize_string    = $this->convertplus_serialize( $params );
			$generated_signature = hash_hmac( 'sha256', $serialize_string, $buy_link_secret_word );

			return $generated_signature == $return_signature;
		}
	}
endif;