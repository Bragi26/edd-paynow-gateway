<?php

namespace EDD\Payments\Paynow;

use Paynow\Client;
use Paynow\Environment;
use Paynow\Exception\PaynowException;
use Paynow\Service\Payment;

/*
  Easy Digital Downloads Paynow Payment Gateway Plugin is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  any later version.

  Easy Digital Downloads Paynow Payment Gateway Plugin is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with Easy Digital Downloads Paynow Payment Gateway Plugin. If not, see http://www.gnu.org/licenses/gpl-3.0.html.
 */

/**
 * Paynow Payment Gateway for Easy Digital Downloads
 *
 * @author Piotr Włoch 
 */
class Paynow {

	protected static $instance;
	public $gateway_id = 'paynow';
	public $is_setup;

	public function __construct() {
		$this->requiedFiles();
		$this->initActions();
	}

	/**
	 * Returns instance 
	 * @return class
	 */
	public static function getInstance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Add required files
	 */
	public function requiedFiles() {
		require_once EDD_PAYNOW_PAYMENTS_DIR . '/vendor/autoload.php';
	}

	/**
	 * Add actions and filters
	 */
	public function initActions() {
		add_filter( 'edd_accepted_payment_icons', array( $this, 'registerPaymentIcon' ), 10, 1 );
		add_filter( 'edd_payment_gateways', array( $this, 'registerGateway' ), 10, 1 );
		add_action( 'edd_pre_process_purchase', array( $this, 'checkConfig' ), 1 );
		add_action( 'edd_paynow_cc_form', '__return_false' );
		add_action( 'edd_gateway_paynow', array( $this, 'processPayment' ) );
		add_action( 'init', array( $this, 'listenForPaynowIPN' ) );
		if ( is_admin() ) {
			add_filter( 'edd_settings_sections_gateways', array( $this, 'registerGatewaySection' ), 10, 1 );
			add_filter( 'edd_settings_gateways', array( $this, 'registerGatewaySettings' ) );
		}
	}

	/**
	 * Register Paynow gateway in EDD 
	 * @param array $gateways
	 * @return array 
	 */
	public function registerGateway( $gateways ) {
		$default_paynow_info = array(
			$this->gateway_id => array(
				'admin_label' => __( 'Paynow', 'edd-paynow-gateway' ),
				'checkout_label' => __( 'Paynow', 'edd-paynow-gateway' ),
				'supports' => array(),
			),
		);
		$gateways_return = array_merge( $gateways, apply_filters( 'edd_register_paynow_gateway', $default_paynow_info ) );

		return $gateways_return;
	}

	/**
	 * Register Paynow gateway section in EDD
	 * @param array $gateway_section
	 * @return array
	 */
	public function registerGatewaySection( $gateway_section ) {
		$gateway_section['paynow'] = __( 'Paynow Payments', 'edd-paynow-gateway' );
		return $gateway_section;
	}

	/**
	 * Register Paynow gateway settings in Edd section
	 * @param array $gateway_settings
	 * @return array
	 */
	public function registerGatewaySettings( $gateway_settings ) {
		$default_paynow_settings = array(
			'paynow' => array(
				'id' => 'paynow',
				'name' => '<strong>' . __( 'Paynow Payment Settings', 'edd-paynow-gateway' ) . '</strong>',
				'type' => 'header',
			),
			'paynow-api-key' => array(
				'id' => 'paynow_api_key',
				'name' => __( 'Api Key', 'edd-paynow-gateway' ),
				'des' => __( '', 'edd-paynow-gateway' ),
				'type' => 'text',
				'size' => 'regular',
			),
			'paynow-secret-key' => array(
				'id' => 'paynow_secret_key',
				'name' => __( 'Secret Key', 'edd-paynow-gateway' ),
				'des' => __( '', 'edd-paynow-gateway' ),
				'type' => 'text',
				'size' => 'regular',
			),
		);
		$gateway_settings['paynow'] = apply_filters( 'edd_default_paynow_settings', $default_paynow_settings );
		return $gateway_settings;
	}

	/**
	 * Register Paynow payment icon 
	 * @param array $payment_icons
	 * @return array
	 */
	public function registerPaymentIcon( $payment_icons ) {
		$url = EDD_PAYNOW_PAYMENTS_URL . '/assets/images/logo-paynow.png';
		$payment_icons[$url] = __( 'Paynow', 'edd-paynow=payments' );
		return $payment_icons;
	}

	/**
	 * Check if Paynow settings are setup
	 * @return boolean
	 */
	public function isSetup() {
		if ( null !== $this->is_setup ) {
			return $this->is_setup;
		}
		$requirder_items = array( 'api_key', 'secret_key' );
		$current_values = array(
			'api_key' => edd_get_option( 'paynow_api_key' ),
			'secret_key' => edd_get_option( 'paynow_secret_key' ),
		);
		$this->is_setup = true;
		foreach ( $requirder_items as $key ) {
			if ( empty( $current_values[$key] ) ) {
				$this->is_setup = false;
			}
		}
		return $this->is_setup;
	}

	/**
	 * Retrieve Paynow payment gateway credentials
	 * @return array
	 */
	public function getCredentials() {
		$paynow_credentials = array(
			'api_key' => edd_get_option( 'paynow_api_key' ),
			'secret_key' => edd_get_option( 'paynow_secret_key' ),
		);
		return $paynow_credentials;
	}

	/**
	 * Check if gateway is eanbled and credentials setup, if not it show admin error notice
	 */
	public function checkConfig() {
		$is_enabled = edd_is_gateway_active( $this->gateway_id );
		if ( (!$is_enabled || $this->isSetup() === false) && 'paynow' == edd_get_chosen_gateway() ) {
			edd_set_error( 'paynow_gateway_not_configured', __( 'There is an error with Paynow Payments configuration.', 'edd-paynow-gateway' ) );
		}
	}

	/**
	 * Process Paynow payment data and redirects to Paynow site
	 * @param array $purchase_data
	 */
	public function processPayment( $purchase_data ) {
		if ( !wp_verify_nonce( $purchase_data['gateway_nonce'], 'edd-gateway' ) ) {
			wp_die( __( 'Nonce verification has failed', 'edd-paynow-gateway' ), __( 'Error', 'edd-paynow-gateway' ), array( 'response' => 403 ) );
		}
		$payment_data = array(
			'price' => $purchase_data['price'],
			'date' => $purchase_data['date'],
			'user_email' => $purchase_data['user_email'],
			'purchase_key' => $purchase_data['purchase_key'],
			'currency' => edd_get_currency(),
			'downloads' => $purchase_data['downloads'],
			'user_info' => $purchase_data['user_info'],
			'cart_details' => $purchase_data['cart_details'],
			'gateway' => 'paynow',
			'status' => 'pending',
		);

		$payment_id = edd_insert_payment( $payment_data );
		if ( !$payment_id ) {
			edd_record_gateway_error( __( 'Payment Error', 'edd-paynow-gateway' ), sprintf( __( 'Payment creation failed before sending buyer to Paynow. Payment data: %s', 'edd-paynow-gateway' ), json_encode( $payment_data ) ), $payment_id );
			edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
		} else {
			$credentials = $this->getCredentials();
			$idempotencyKey = uniqid( $payment_id . '_' );
			$continue_url = add_query_arg( array(
				'payment-confirmation' => 'paynow',
				'payment-id' => $payment_id,
				'paynow-listener' => 'ipn',
					), get_permalink( edd_get_option( 'success_page', false ) ) );

			$paynow_payment = [
				'amount' => $purchase_data['price'] * 100,
				'currency' => edd_get_currency(),
				'externalId' => $payment_id,
				'description' => __( 'Order: ', 'edd-paynow-gateway' ) . $payment_id,
				'buyer' => [
					'email' => $payment_data['user_info']['email'],
					'firstName' => $payment_data['user_info']['first_name'],
					'lastName' => $payment_data['user_info']['last_name'],
				],
				'continueUrl' => $continue_url
			];

			if ( edd_is_test_mode() ) {
				$enviroment = Environment::SANDBOX;
			} else {
				$enviroment = Environment::PRODUCTION;
			}
			try {
				$client = new Client( $credentials['api_key'], $credentials['secret_key'], $enviroment );
				$payment = new Payment( $client );
				$result = $payment->authorize( $paynow_payment, $idempotencyKey );

				$paynow_status = $result->getStatus();
				if ( !empty( $paynow_status ) ) {
					edd_update_payment_meta( $payment_id, '_edd_paynow_payment_status', $paynow_status );
				}
				$paynow_payment_id = $result->getPaymentId();
				if ( !empty( $paynow_payment_id ) ) {
					edd_update_payment_meta( $payment_id, '_edd_paynow_payment_id', $paynow_payment_id );
				}
				$paynow_redirect = $result->getRedirectUrl();
				if ( !empty( $paynow_redirect ) ) {
					wp_redirect( $paynow_redirect );
					exit;
				}
			} catch ( PaynowException $ex ) {
				edd_record_gateway_error( __( 'Payment Error', 'edd-paynow-gateway' ), sprintf( __( 'Payment creation failed while processing a Paynow payment gateway purchase. Payment data: %s', 'edd-paynow-gateway' ), json_encode( $payment_data ) ) );
				edd_send_back_to_checkout( '?payment-mode=' . $this->gateway_id );
			}
		}
	}

	/**
	 * Listener IPN for Paynow return information about payment status
	 */
	public function listenForPaynowIPN() {
		$paynow_listener = filter_input( INPUT_GET, 'paynow-listener' );
		if ( isset( $paynow_listener ) && strtolower( esc_attr( $paynow_listener ) ) == strtolower( 'ipn' ) ) {
			$this->processPaynowIPN();
		}
	}

	/**
	 * Process Paynow return IPN information about payment status
	 * @return null
	 */
	public function processPaynowIPN() {
		$tmp_payment_id = filter_input( INPUT_GET, 'payment-id' );
		$status = null;
		$payment_paynow_id = null;
		$payment_id = null;
		if ( isset( $tmp_payment_id ) && !empty( $tmp_payment_id ) && $tmp_payment_id != '' && !is_null( $tmp_payment_id ) ) {
			$payment_id = $tmp_payment_id;
		}
		$tmp_payment_paynow_id = filter_input( INPUT_GET, 'paymentId' );
		if ( isset( $tmp_payment_paynow_id ) && !empty( $tmp_payment_id ) && $tmp_payment_paynow_id != '' && !is_null( $tmp_payment_paynow_id ) ) {
			$payment_paynow_id = $tmp_payment_paynow_id;
		}
		$tmp_status = filter_input( INPUT_GET, 'paymentStatus' );
		if ( isset( $tmp_status ) && !empty( $tmp_status ) && $tmp_status != '' && !is_null( $tmp_status ) ) {
			$status = $tmp_status;
		}
		if ( is_null( $payment_id ) && is_null( $payment_paynow_id ) ) {
			return;
		}
		if ( strtolower( $status ) == strtolower( 'CONFIRMED' ) ) {
			edd_update_payment_status( $payment_id, 'publish' );
			edd_insert_payment_note( $payment_id, __( 'Payment done via Paynow with transaction id ' . $payment_paynow_id, 'edd-paynow-gateway' ) );
		}
		if ( strtolower( $status ) == strtolower( 'PENDING' ) || strtolower( $status ) == strtolower( 'NEW' ) ) {
			edd_update_payment_status( $payment_id, 'pending' );
			edd_insert_payment_note( $payment_id, __( 'Paynow payment pending with transaction id ' . $payment_paynow_id, 'edd-paynow-gateway' ) );
		}
		if ( strtolower( $status ) == strtolower( 'REJECTED' ) ) {
			edd_update_payment_status( $payment_id, 'failed' );
			edd_insert_payment_note( $payment_id, __( 'Paynow payment failed with transaction id ' . $payment_paynow_id, 'edd-paynow-gateway' ) );
		}
		if ( strtolower( $status ) == strtolower( 'ERROR' ) ) {
			edd_update_payment_status( $payment_id, 'failed' );
			edd_insert_payment_note( $payment_id, __( 'Error occured during Paynow payment with transaction id ' . $payment_paynow_id, 'edd-paynow-gateway' ) );
		}
	}

}
