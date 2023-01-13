<?php

defined( 'ABSPATH' ) or die( 'Keep Silent' );

add_filter( 'woocommerce_no_available_payment_methods_message', function ( $no_gateways_message ) {

	$pro_url             = 'https://getwooplugins.com/plugins/woocommerce-2checkout/?utm_source=woo-2checkout-user&utm_medium=checkout-page&utm_campaign=woo-2checkout';
	$pro_text            = esc_html__( 'Payment Gateway - 2Checkout for WooCommerce - Pro ', 'woo-2checkout' );
	$pro_link            = sprintf( '<a target="_blank" href="%s">%s</a>', $pro_url, $pro_text );
	$no_gateways_message = '<strong>' . sprintf( esc_html__( 'Upgrade to %s to get WooCommerce Subscriptions payments, issue refunds from backend, inline popup checkout and more.', 'woo-2checkout' ), $pro_link ) . '</strong>';

	return $no_gateways_message;
} );
