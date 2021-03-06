<?php
/**
 * GenPay API
 *
 * @package WooCommerce_Rakuten_Pay/API
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WC_Rakuten_Pay_API class.
 */
class WC_Rakuten_Pay_API {

    /**
     * PRODUCTION API URL.
     */
    const PRODUCTION_API_URL = 'https://api.gencomm.com.br/rpay/v1/';

    /**
     * SANDBOX API URL.
     */
    const SANDBOX_API_URL = 'http://oneapi-sandbox.genpay.com.br/rpay/v1/';

    /**
     * Gateway class.
     *
     * @var WC_Rakuten_Pay_Gateway
     */
    protected $gateway;

	/**
	 * PRODUCTION_JS_URL
	 */
	const PRODUCTION_JS_URL = 'https://static.genpay.com.br/rpayjs/rpay-latest.min.js';

	/**
	 * SANDBOX_JS_URL
	 */
    const SANDBOX_JS_URL = 'https://static.genpay.com.br/rpayjs/rpay-latest.dev.min.js';

    /**
     * Constructor.
     *
     * @param WC_Payment_Gateway $gateway Gateway instance.
     */
    public function __construct( $gateway = null ) {
        $this->gateway = $gateway;
    }

    /**
     * Get API URL.
     *
     * @return string
     */
    public function get_api_url() {
        if ( 'production' === $this->gateway->environment ) {
            return self::PRODUCTION_API_URL;
        } else {
            return self::SANDBOX_API_URL;
        }
    }

    /**
     * Get JS Library URL.
     *
     * @return string
     */
    public function get_js_url() {
	    if ( 'production' === $this->gateway->environment ) {
		    return self::PRODUCTION_JS_URL;
	    } else {
    		return self::SANDBOX_JS_URL;
	    }
    }

    /**
     * Returns a bool that indicates if currency is amongst the supported ones.
     *
     * @return bool
     */
    public function using_supported_currency() {
        return 'BRL' === get_woocommerce_currency();
    }

    /**
     * Only numbers.
     *
     * @param  string|int $string String to convert.
     *
     * @return string|int
     */
    protected function only_numbers( $string ) {
        return preg_replace( '([^0-9])', '', $string );
    }

    /**
     * Get the smallest installment amount.
     *
     * @return int
     */
    public function get_smallest_installment() {
        return wc_format_decimal( $this->gateway->smallest_installment );
    }

    /**
     * Do POST requests in the GenPay API.
     *
     * @param  string $endpoint API Endpoint.
     * @param  array  $data     Request data.
     * @param  array  $headers  Request headers.
     *
     * @return array            Request response.
     */
    protected function do_post_request( $endpoint, $data = array(), $headers = array() ) {
        $params = array(
            'timeout' => 60,
            'method' => 'POST'
        );

        if ( ! empty( $data ) ) {
            $params['body'] = $data;
        }

        if ( ! empty( $headers ) ) {
            $params['headers'] = $headers;
        }

        return wp_remote_post( $this->get_api_url() . $endpoint, $params );
    }

    /**
     * Do GET requests in the GenPay API.
     *
     * @param  string $endpoint API Endpoint.
     * @param  array  $headers  Request headers.
     *
     * @return array            Request response.
     */
    protected function do_get_request( $endpoint, $headers = array() ) {
        $params = array(
            'timeout' => 60,
            'method'  => 'GET'
        );

        if ( ! empty( $headers ) ) {
            $params['headers'] = $headers;
        }

        return wp_remote_get( $this->get_api_url() . $endpoint, $params );
    }

    /**
     * Generate the charge data.
     *
     * @param  WC_Order $order           Order data.
     * @param  string   $payment_method  Payment method.
     * @param  array    $posted          Form posted data.
     * @param  array    $installments     In case of not free installment
     *
     * @return array            Charge data.
     */
    public function generate_charge_data( $order, $payment_method, $posted, $installments ) {
        // WC_Order class intance to get calculation_code and postage_service_code
        $shipping_methods = $order->get_shipping_methods();
        $shipping_data = reset($shipping_methods);
        $shipping_method = $shipping_data->get_method_id();
        $total_amount = (float) $order->get_total() + $installments['interest_amount'];

        $customer_name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

        // Root
        $data = array(
            'reference'   => $order->get_order_number(),
            'amount'      => $total_amount,
            'currency'    => get_woocommerce_currency(),
            'webhook_url' => WC()->api_request_url( get_class( $this->gateway ) ),
            'fingerprint' => $posted['rakuten_pay_fingerprint'],
            'payments'    => array(),
            'customer'    => array(
                'document'      => $this->only_numbers($posted['billing_cpf']),
                'name'          => $customer_name,
                'business_name' => $posted['billing_company'] ?: $customer_name,
                'email'         => $order->get_billing_email(),
                'birth_date'    => '1999-01-01',
                'kind'          => 'personal',
                'addresses'     => array(),
                'phones'        => array(
                    array(
                        'kind'         => 'billing',
                        'reference'    => 'others',
                        'number'       => array(
                            'country_code' => '55',
                            'area_code'    => preg_replace(
                                '/\((\d{2})\)\s(\d{4,5})-(\d{4})/',
                                '${1}',
                                $order->get_billing_phone()
                            ),
                            'number' => preg_replace(
                                '/\((\d{2})\)\s(\d{4,5})-(\d{4})/',
                                '${2}${3}',
                                $order->get_billing_phone()
                            )
                        )
                    ),
                    array(
                        'kind'         => 'shipping',
                        'reference'    => 'others',
                        'number'       => array(
                            'country_code' => '55',
                            'area_code'    => preg_replace(
                                '/\((\d{2})\)\s(\d{4,5})-(\d{4})/',
                                '${1}',
                                $order->get_billing_phone()
                            ),
                            'number' => preg_replace(
                                '/\((\d{2})\)\s(\d{4,5})-(\d{4})/',
                                '${2}${3}',
                                $order->get_billing_phone()
                            )
                        )
                    )
                )
            ),
            'order' => array(
                'reference'       => (string) $order->get_id(),
                'payer_ip'        => $this->customer_ip_address( $order ),
                'items_amount'    => (float) $order->get_subtotal(),
                'shipping_amount' => (float) $order->get_shipping_total(),
                'taxes_amount'    => (float) $order->get_total_tax() + $installments['interest_amount'],
                'discount_amount' => (float) $order->get_total_discount(),
                'items' => $this->getItems($order),
            ),
        );

        //Commissionings
        if ( $shipping_method == 'rakuten-log' ) {
            $commissionings = array(

                    'reference'                 => (string) $order->get_id(),
                    'kind'                      => 'rakuten_logistics',
                    'amount'                    => (float) $order->get_shipping_total(),
                    'calculation_code'          => $shipping_data->get_meta('calculation_code'),
                    'postage_service_code'      => $shipping_data->get_meta('postage_service_code'),

            );

            $data['commissionings'][] = $commissionings;
        }

        //Billing Address.
        if ( ! empty( $order->get_billing_address_1() ) ) {
            $billing_address = array(
                'kind'          => 'billing',
                'contact'       => $customer_name,
                'street'        => $order->get_billing_address_1(),
                'complement'    => $order->get_billing_address_2(),
                'city'          => $order->get_billing_city(),
                'state'         => $order->get_billing_state(),
                'country'       => $order->get_billing_country(),
                'zipcode'       => $this->only_numbers( $order->get_billing_postcode() ),
            );

            // Non-WooCommerce default address fields.
            if ( ! empty( $posted['billing_number'] ) ) {
                $billing_address['number'] = $posted['billing_number'];
            }
            if ( ! empty( $posted['billing_neighborhood'] ) ) {
                $billing_address['district'] = $posted['billing_neighborhood'];
            }

            $data['customer']['addresses'][] = $billing_address;
        }

        if ( $payment_method == 'credit_card' ) {
            $payment = array(
                'reference'                => '1',
                'method'                   => $payment_method,
                'amount'                   => $total_amount,
                'installments_quantity'    => (integer) $posted['rakuten_pay_installments'],
                'brand'                    => strtolower( $posted['rakuten_pay_card_brand'] ),
                'token'                    => $posted['rakuten_pay_token'],
                'cvv'                      => $posted['rakuten_pay_card_cvc'],
                'holder_name'              => $posted['rakuten_pay_card_holder_name'],
                'holder_document'          => $posted['rakuten_pay_card_holder_document'],
                'options'                  => array(
                    'save_card'   => false,
                    'new_card'    => false,
                    'recurrency'  => false
                )
            );
            if ( isset( $installments ) ) {
                $payment['installments'] = $installments;
            }
        } else {
            $payment = array(
                'method'     => $payment_method,
                'expires_on' => date( 'Y-m-d', $this->strtotime( '+3 day' ) ),
                'amount'     => (float) $order->get_total()
            );
        }

        $data['payments'][] = $payment;

        // Shipping Address
        if ( ! empty( $_POST['ship_to_different_address'] ) ) {
            $shipping_address = array(
                'kind'       => 'shipping',
                'contact'    => $customer_name,
                'street'     => $order->get_shipping_address_1(),
                'complement' => $order->get_shipping_address_2(),
                'zipcode'    => $this->only_numbers( $order->get_shipping_postcode() ),
                'city'       => $order->get_shipping_city(),
                'state'      => $order->get_shipping_state(),
                'country'    => $order->get_shipping_country(),
            );

            // Non-WooCommerce default address fields.
            if ( ! empty( $posted['shipping_number'] ) ) {
                $shipping_address['number'] = $posted['shipping_number'];
            }
            if ( ! empty( $posted['shipping_neighborhood'] ) ) {
                $shipping_address['district'] = $posted['shipping_neighborhood'];
            }

            $data['customer']['addresses'][] = $shipping_address;
        } else {
            $shipping_address                = $billing_address;
            $shipping_address['kind']        = 'shipping';
            $data['customer']['addresses'][] = $shipping_address;
        }

        $current_user = wp_get_current_user();
        $current_user_id = $current_user->ID;
        //TODO verificar
//        update_user_meta( $current_user_id, 'billing_birthdate', $posted['billing_birthdate']);

        return $data;
    }

    /**
     * Generate the charge data.
     *
     * @param  WC_Order $order             Order data.
     * @param  array    $posted            Form posted data.
     * @param  array    $transaction_data  Transaction data.
     *
     * @return array                       [kind: total|partial, Charge data].
     */
    public function generate_refund_data( $order, $payment_method, $posted, $transaction_data ) {
        $customer_name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        $transaction_id = get_post_meta( $order->get_id(), '_wc_rakuten_pay_transaction_id', true );
        $refund_reason  = $posted['refund_reason'];
        $paid_value     = (float) $order->get_total();
        $refund_value   = (float) $posted['refund_amount'];

        if ( $paid_value === $refund_value ) {
            $kind = 'total';
        } else {
            $kind = 'partial';
        }

        // Root
        $data = array(
            'requesters'  => 'merchant',
            'reason'      => $refund_reason,
            'amount'      => $refund_value,
            'payments'    => array(
                array(
                    'id'     => $transaction_data['payments'][0]['id'],
                    'amount' => (float) $refund_value
                )
            )
        );

        // Billet refund data
        if ( 'billet' === $payment_method ) {
            $banking_account_data = array(
                'document'    => $this->only_numbers( $posted['refund_customer_document'] ),
                'bank_code'   => $this->only_numbers( $posted['refund_bank_code'] ),
                'bank_agency' => $this->only_numbers( $posted['refund_bank_agency'] ),
                'bank_number' => $posted['refund_bank_number']
            );

            $data['payments'][0]['bank_account'] = $banking_account_data;
        }

        return [$kind, $data];
    }

    /**
     * Do the charge transaction.
     *
     * @param  WC_Order $order Order data.
     * @param  array    $args  Transaction args.
     *
     * @return array Response data.
     *   array( 'result' => 'fail' ) for general request failures
     *   array( 'result' => 'failure', 'errors' => errors[] ) for Genpay errors
     *   array( 'result' => 'authorized', ... ) for authorized Genpay transactions
     */
    public function charge_transaction( $order, $charge_data) {
        if ( 'yes' === $this->gateway->debug ) {
            $this->gateway->log->add( $this->gateway->id, 'Doing a charge charge_transaction for order ' . $order->get_order_number() . '...' );
        }

        $endpoint = 'charges';
        $body     = $this->getJson( $charge_data);
        $headers  = array(
            'Authorization' => $this->authorization_header(),
            'Signature' => $this->get_signature( $body ),
            'Content-Type' => 'application/json'
        );
        $response = $this->do_post_request( $endpoint, $body, $headers );

        if ( is_wp_error( $response ) ) {
            if ( 'yes' === $this->gateway->debug ) {
                $this->gateway->log->add( $this->gateway->id, 'WP_Error in doing the charge_transaction: ' . print_r($response, true) . $response->get_error_message() );
            }
            return array( 'result' => 'fail' );
        }

        $response_body = json_decode( $response['body'], true );

        if ( $response['response']['code'] != 200 ) {
            $error_message = '';
            if ( isset( $response_body['errors'] ) ) {
                foreach ( $response_body['errors'] as $error ) {
                    $error_message .= $error['description'] . '\n';
                }
            }
            if ( 'yes' === $this->gateway->debug ) {
                $this->gateway->log->add( $this->gateway->id, 'Fail in doing the charge_transaction: ' . print_r( $response_body, true ) );
                $this->gateway->log->add( $this->gateway->id, 'Result: ' . print_r( $response_body['result'], true ) );
                $this->gateway->log->add( $this->gateway->id, 'URL: ' . print_r( $this->gateway->get_return_url( $order ), true ) );
            }

            $result_messages = $response_body['result_messages'][0];
            update_post_meta( $order_id, '_wc_rakuten_pay_transaction_data', $payment_data );
            $this->save_order_meta_fields( $order_id, $payment_data, $payments );

            // Change the order status.
            $this->process_order_status( $order, $response_body['result'], $response_body, $result_messages );

            return array( 'result' => 'fail' );
        }

        if ( $response_body['result'] == 'failure' ) {
            if ( 'yes' === $this->gateway->debug ) {
                $this->gateway->log->add( $this->gateway->id, 'Failed to make the transaction: ' . print_r( $response, true ) );
            }
            return $response_body;
        }

        if ( 'yes' === $this->gateway->debug ) {
            $this->gateway->log->add( $this->gateway->id, 'Transaction completed successfully! The charge_transaction response is: ' . print_r( $response_body, true ) );
        }
        return $response_body;
    }

    public function charge_authorization( $api_key, $document, $environment ) {
        if ( 'yes' === $this->gateway->debug ) {
            $this->gateway->log->add( $this->gateway->id, 'Doing a charge charge_authorization' );
        }

        $endpoint = self::PRODUCTION_API_URL . 'charges';
        if ( 'sandbox' === $environment ) {
            $endpoint = self::SANDBOX_API_URL . 'charges';
        }
        $user_pass = $document . ':' . $api_key;

        $headers  = array(
            'Authorization' => 'Basic ' . base64_encode( $user_pass ),
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'application/json'
        );

        $params = array(
            'timeout' => 60,
            'method'  => 'GET',
            'headers' => $headers,
        );

        $response = wp_remote_get( $endpoint, $params );
        if ( 'yes' === $this->gateway->debug ) {
            $this->gateway->log->add( $this->gateway->id, 'Response HTTP Code: ' . $response['response']['code'] );
        }

        return $response['response']['code'];
    }

    /**
     * Cancels the transaction.
     *
     * @param  WC_Order $order Order data.
     * @param  string   $token Checkout token.
     *
     * @return array           Response data.
     */
    public function cancel_transaction( $order ) {
        if ( 'yes' === $this->gateway->debug ) {
            $this->gateway->log->add( $this->gateway->id, 'Cancelling payment for order ' . $order->get_order_number() . '...' );
        }

        $body           = $this->getJson( array(), JSON_PRESERVE_ZERO_FRACTION );
        $transaction_id = get_post_meta( $order->get_id(), '_wc_rakuten_pay_transaction_id', true );
        $headers        = array(
            'Authorization' => $this->authorization_header(),
            'Signature'     => $this->get_signature( $body ),
            'Content-Type' => 'application/json'
        );
        $endpoint       = 'charges/' . $transaction_id . '/cancel';
        $response       = $this->do_post_request( $endpoint, $body, $headers );

        if ( is_wp_error( $response ) ) {
            if ( 'yes' === $this->gateway->debug ) {
                $this->gateway->log->add( $this->gateway->id, 'WP_Error in doing the transaction: ' . $response->get_error_message() );
            }
            $transaction_url = '<a href="https://dashboard.genpay.com.br/sales/' . intval( $transaction_id ) . '">https://dashboard.genpay.com.br/sales/' . intval( $transaction_id ) . '</a>';
            $this->send_email(
                sprintf( esc_html__( 'The cancel transaction for order %s has failed.', 'woocommerce-rakuten-pay' ), $order->get_order_number() ),
                esc_html__( 'Transaction failed', 'woocommerce-rakuten-pay' ),
                sprintf( esc_html__( 'In order to cancel this transaction access the GenPay dashboard:  %1$s.', 'woocommerce-rakuten-pay' ), $transaction_url )
            );
            $order->add_order_note( __('GenPay: Order could not be cancelled due to an error. You must access the GenPay Dashboard to complete the cancel operation', 'woocommerce-rakuten-pay' ) );

            return;
        }

        $data = json_decode( $response['body'], true );

        if ( $data['result'] == 'failure' ) {
            if ( 'yes' === $this->gateway->debug ) {
                $this->gateway->log->add( $this->gateway->id, 'Failed to make the transaction: ' . print_r( $response, true ) );
            }
            return;
        }

        if ( 'yes' === $this->gateway->debug ) {
            $this->gateway->log->add( $this->gateway->id, 'Transaction completed successfully! The transaction response is: ' . print_r( $data, true ) );
        }

        update_post_meta( $order->get_id(), '_wc_rakuten_pay_order_cancelled', 'yes' );

        return;
    }

    /**
     * Do the refund transaction.
     *
     * @param  WC_Order $order        Order data.
     * @param  array    $refund_data  Refund transaction args.
     *
     * @return array Response data.
     *   array( 'result' => 'fail' ) for general request failures
     *   array( 'result' => 'failure', 'errors' => errors[] ) for GenPay errors
     *   array( 'result' => 'authorized', ... ) for authorized GenPay transactions
     */
    public function refund_transaction( $order, $refund_kind, $refund_data ) {
        $body           = $this->getJson( $refund_data, JSON_PRESERVE_ZERO_FRACTION );
        $transaction_id = get_post_meta( $order->get_id(), '_wc_rakuten_pay_transaction_id', true );
        $headers        = array(
            'Authorization' => $this->authorization_header(),
            'Signature'     => $this->get_signature( $body ),
            'Content-Type' => 'application/json'
        );
        if ( 'total' === $refund_kind ) {
            $refund_route = '/refund';
        } else {
            $refund_route = '/refund_partial';
        }
        $endpoint       = 'charges/' . $transaction_id . $refund_route;
        $response       = $this->do_post_request( $endpoint, $body, $headers );

        if ( is_wp_error( $response ) ) {
            if ( 'yes' === $this->gateway->debug ) {
                $this->gateway->log->add( $this->gateway->id, 'WP_Error in doing the refund_transaction: ' . $response->get_error_message() );
            }
            $transaction_url = '<a href="https://dashboard.genpay.com.br/sales/' . intval( $transaction_id ) . '">https://dashboard.genpay.com.br/sales/' . intval( $transaction_id ) . '</a>';
            $this->send_email(
                sprintf( esc_html__( 'The refund transaction for order %s has failed.', 'woocommerce-rakuten-pay' ), $order->get_order_number() ),
                esc_html__( 'Transaction failed', 'woocommerce-rakuten-pay' ),
                sprintf( esc_html__( 'In order to refund this transaction access the GenPay dashboard:  %1$s.', 'woocommerce-rakuten-pay' ), $transaction_url )
            );
            $order->add_order_note( __('Genpay: Order could not be refunded due to an error. You must access the Genpay Dashboard to complete the cancel operation', 'woocommerce-rakuten-pay' ) );
            return false;
        }

        $response_body = json_decode( $response['body'], true );

        if ( $response['response']['code'] != 200 ) {
            $error_message = '';
            if ( isset( $response_body['errors'] ) ) {
                foreach ( $response_body['errors'] as $error ) {
                    $error_message .= $error['description'] . '\n';
                }
            }
            if ( 'yes' === $this->gateway->debug ) {
                $this->gateway->log->add( $this->gateway->id, 'Fail in doing the refund_transaction: \n' . $error_message );
            }
            return false;
        }

        if ( $response_body['result'] == 'failure' ) {
            if ( 'yes' === $this->gateway->debug ) {
                $this->gateway->log->add( $this->gateway->id, 'Failed to make the refund_transaction: ' . print_r( $response, true ) );
            }
            return false;
        }

        if ( 'yes' === $this->gateway->debug ) {
            $this->gateway->log->add( $this->gateway->id, 'Transaction completed successfully! The refund_transaction response is: ' . print_r( $response_body, true ) );
        }

        $refunded_ids   = get_post_meta( $order->get_id(), '_wc_rakuten_pay_order_refunded_ids', true );
        $refunded_ids   = $refunded_ids ?: array();
        $refund_id      = $response_body['refunds'][0]['id'];
        $refunded_ids[] = $refund_id;

        update_post_meta( $order->get_id(), '_wc_rakuten_pay_order_refunded_ids', $refunded_ids );
        return true;
    }

    /**
     * Get transaction data.
     *
     * @param  WC_Order $order        Order data.
     *
     * @return array Response data.
     *   false for general request failures
     *   array( 'result' => 'data', ... ) with data from Genpay transaction
     */
    public function get_transaction( $order ) {
        $transaction_id = get_post_meta( $order->get_id(), '_wc_rakuten_pay_transaction_id', true );
        $headers        = array(
            'Authorization' => $this->authorization_header(),
            'Content-Type' => 'application/json'
        );
        $endpoint       = 'charges/' . $transaction_id;
        $response       = $this->do_get_request( $endpoint, $headers );

        if ( is_wp_error( $response ) ) {
            if ( 'yes' === $this->gateway->debug ) {
                $this->gateway->log->add( $this->gateway->id, 'WP_Error in doing the get_transaction: ' . $response->get_error_message() );
            }
            return false;
        }

        $response_body = json_decode( $response['body'], true );

        if ( $response['response']['code'] != 200 ) {
            $error_message = '';
            if ( isset( $response_body['errors'] ) ) {
                foreach ( $response_body['errors'] as $error ) {
                    $error_message .= $error['description'] . '\n';
                }
            }
            if ( 'yes' === $this->gateway->debug ) {
                $this->gateway->log->add( $this->gateway->id, 'Fail in doing the get_transaction: \n' . $error_message );
            }
            return false;
        }

        if ( 'yes' === $this->gateway->debug ) {
            $this->gateway->log->add( $this->gateway->id, 'Failed to make the get_transaction: ' . print_r( $response, true ) );
        }

        return $response_body;
    }

    /**
     * Get installments
     *
     * @param  float           $amount   Amount
     * @return array | false   $result   Installments or false for errors
     */
    public function get_installments( $amount ) {
        $headers        = array(
            'Authorization' => $this->authorization_header(),
            'Content-Type' => 'application/json'
        );
        $endpoint       = add_query_arg( array(
            'amount' => $amount
        ), 'checkout' );
        $response       = $this->do_get_request( $endpoint, $headers );

        if ( is_wp_error( $response ) ) {
            if ( 'yes' === $this->gateway->debug ) {
                $this->gateway->log->add( $this->gateway->id, 'WP_Error in doing the get_installments: ' . $response->get_error_message() );
            }
            return false;
        }

        $response_body = json_decode( $response['body'], true );
        $installments  = array_filter( $response_body['payments'], function( $p ) {
            return $p['method'] == 'credit_card';
        } );
        return $installments[0]['installments'];
    }

    /**
     * Get card brand name.
     *
     * @param string $brand Card brand.
     * @return string
     */
    protected function get_card_brand_name( $brand ) {
        $names = array(
            'visa'       => __( 'Visa', 'woocommerce-rakuten-pay' ),
            'mastercard' => __( 'MasterCard', 'woocommerce-rakuten-pay' ),
            'amex'       => __( 'American Express', 'woocommerce-rakuten-pay' ),
            'aura'       => __( 'Aura', 'woocommerce-rakuten-pay' ),
            'jcb'        => __( 'JCB', 'woocommerce-rakuten-pay' ),
            'diners'     => __( 'Diners', 'woocommerce-rakuten-pay' ),
            'elo'        => __( 'Elo', 'woocommerce-rakuten-pay' ),
            'hipercard'  => __( 'Hipercard', 'woocommerce-rakuten-pay' ),
            'discover'   => __( 'Discover', 'woocommerce-rakuten-pay' ),
        );

        return isset( $names[ $brand ] ) ? $names[ $brand ] : $brand;
    }

    /**
     * Get payment method.
     *
     * @param WC_Order $order WooCommerce Order.
     * @return string
     */
    protected function get_payment_method( $order ) {
        $payment_method = $order->get_payment_method( $order );

        switch ( $payment_method ) {
            case 'rakuten-pay-credit-card':
                return 'credit_card';
            case 'rakuten-pay-banking-billet':
                return 'billet';
        }
    }

    /**
     * Is Credit Card payment method.
     *
     * @param WC_Order $order WooCommerce Order.
     * @return boolean  Returns true if is Credit Card Payment.
     */
    public function is_credit_card_payment_method( $order ) {
        return 'credit_card' === $this->get_payment_method( $order );
    }

    /**
     * Is Billet payment method.
     *
     * @param WC_Order $order WooCommerce Order.
     * @return boolean  Returns true if is Credit Card Payment.
     */
    public function is_banking_billet_payment_method( $order ) {
        return 'billet' === $this->get_payment_method( $order );
    }

    /**
     * Save order meta fields for credid card payment type.
     * Save fields as meta data to display on order's admin screen.
     *
     * @param int    $id Order ID.
     * @param array  $data Order data.
     */
    protected function save_order_meta_fields( $id, $data, $payments ) {
        if ( ! empty( $data['card_brand'] ) ) {
            update_post_meta( $id, __( 'Credit Card', 'woocommerce-rakuten-pay' ), $this->get_card_brand_name( sanitize_text_field( $data['card_brand'] ) ) );
        }
        if ( ! empty( $data['installments'] ) ) {
            update_post_meta( $id, __( 'Installments', 'woocommerce-rakuten-pay' ), sanitize_text_field( $data['installments'] ) );
        }
        if ( ! empty( $data['amount'] ) ) {
            update_post_meta( $id, __( 'Total paid', 'woocommerce-rakuten-pay' ), number_format( intval( $data['amount'] ), wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() ) );
        }
        if ( ! empty( $data['billet_url'] ) ) {
            update_post_meta( $id, __( 'Banking Ticket URL', 'woocommerce-rakuten-pay' ), sanitize_text_field( $data['billet_url'] ) );
        }
        if ( ! empty( $payments['credit_card']['number'] ) ) {
            update_post_meta( $id, __( 'Card Number', 'woocommerce-rakuten-pay' ), sanitize_text_field( $payments['credit_card']['number'] ) );
        }
    }

    /**
     * Process payment by method.
     *
     * @param int $order_id Order ID.
     *
     * @return array Redirect data.
     */
    public function process_regular_payment( $order_id ) {
        $order          = wc_get_order( $order_id );
        $payment_method = $this->get_payment_method( $order );
        $buyer_interest = $this->get_buyer_interest();

        $installments_qty = (integer) $_POST['rakuten_pay_installments'];
        $amount           = (float) $order->get_total();
        $installment      = null;
        if ( $installments_qty > $this->gateway->free_installments ) {
            $installments = $this->get_installments( $amount );
            if ( $installments === false ) {
                return array(
                    'result' => 'fail'
                );
            }
            foreach ($installments as $i) {
                if ( $i['quantity'] == $installments_qty ) {
                    $installment = $i;
                    break;
                }
            }
        }

        if ( $buyer_interest == 'yes' ) {
            $data = $this->generate_charge_data($order, $payment_method, $_POST, $installment);
        } else {
            $installment = array(
                'total' => $amount,
                'quantity' => $installments_qty,
                'interest_percent' => (float) '0',
                'interest_amount' => (float) '0',
            );
            $installment['installment_amount'] = $installment['total'] / $installment['quantity'];

            $data = $this->generate_charge_data($order, $payment_method, $_POST, $installment);
        }

        // Save customer data at database, CPF, birthdate and Phone
	    update_post_meta( $order_id, '_billing_cpf', $data['customer']['document'] );
        $current_user = wp_get_current_user();
        $current_user_id = $current_user->ID;
	    update_user_meta( $current_user_id, 'billing_cpf', $data['customer']['document'] );
	    update_user_meta( $current_user_id, 'billing_phone', $order->get_billing_phone() );

        $transaction    = $this->charge_transaction( $order, $data );
        $payments = reset($transaction['payments']);

        // Status cancelled and redirect to thank you page.
        if ( isset( $transaction['result'] ) && $transaction['result'] === 'fail' ) {
            return array(
                'result'   => 'success',
                'redirect' => $this->gateway->get_return_url( $order ),
            );
        }

        if ( isset( $transaction['errors'] ) ) {
            foreach ( $transaction['errors'] as $error ) {
                $error_msg = $error['code'] . ', ' . $error['description'];
                wc_add_notice( $error_msg, 'error' );
            }

            return array(
                'result' => 'fail',
            );
        }

        if ( ! isset( $transaction['charge_uuid'] ) ) {
            if ( 'yes' === $this->gateway->debug ) {
                $this->gateway->log->add( $this->gateway->id, 'Transaction data does not contain id or charge url for order ' . $order->get_order_number() . '...' );
            }

            return array(
                'result' => 'fail',
            );
        }

        // Save transaction data.
        update_post_meta( $order_id, '_wc_rakuten_pay_transaction_id', $transaction['charge_uuid'] );
        update_post_meta( $order_id, '_transaction_id', $transaction['charge_uuid'] );

        if ( $payment_method === 'credit_card' ) {
            $payment_data = array(
                'payment_method'  => $payment_method,
                'installments'    => $_POST['rakuten_pay_installments'],
                'card_brand'      => $this->get_card_brand_name( $_POST['rakuten_pay_card_brand'] ),
                'amount'          => $data['amount'],
                'number'          => $payments['credit_card']['number']
            );
        } else {
            $payment_data = array(
                'payment_method'  => $payment_method,
                'billet_url'      => $payments['billet']['url'],
                'amount'          => $data['amount']
            );
        }

        $payment_data = array_map(
            'sanitize_text_field',
            $payment_data
        );

        update_post_meta( $order_id, '_wc_rakuten_pay_transaction_data', $payment_data );
        $this->save_order_meta_fields( $order_id, $payment_data, $payments );

        // Change the order status.
        $result_messages = implode(' - ', $transaction['payments'][0]['result_messages']);

        $this->process_order_status( $order, $transaction['result'], $transaction, $result_messages );

        // Empty the cart.
        WC()->cart->empty_cart();

        // Redirect to thanks page.
        return array(
            'result'   => 'success',
            'redirect' => $this->gateway->get_return_url( $order ),
        );
    }

    /**
     * Process refund.
     *
     * @param int    $order_id  Order ID.
     * @param float  $amount    Amount to refund.
     * @param string $reason    Reason whereby the refund has been done.
     *
     * @return array Redirect data.
     *
     */
    public function process_refund( $order_id, $amount, $reason ) {
        $order            = wc_get_order( $order_id );
        $payment_method   = $this->get_payment_method( $order );
        $transaction_data = $this->get_transaction( $order );

        if ( ! $transaction_data ) {
            return false;
        }

        $refund_result  = $this->generate_refund_data( $order, $payment_method, $_POST, $transaction_data );
        $refund_kind    = $refund_result[0];
        $refund_data    = $refund_result[1];
        $result         = $this->refund_transaction( $order, $refund_kind, $refund_data );

        return $result;
    }

    /**
     * Check if Genpay response is valid.
     *
     * @param  string $body  IPN body.
     * @param  string $token IPN signature token
     *
     * @return bool
     */
    public function verify_signature( $body, $token ) {
        $signature  = $this->get_signature( $body );
        error_log(print_r($signature, true));
        return $token === $signature;
    }

    /**
     * Send email notification to admin.
     *
     * @param string $subject Email subject.
     * @param string $title   Email title.
     * @param string $message Email message.
     */
    protected function send_email( $subject, $title, $message ) {
        $mailer = WC()->mailer();
        $mailer->send( get_option( 'admin_email' ), $subject, $mailer->wrap_message( $title, $message ) );
    }

    /**
     * Send email notification to customer.
     *
     * @param string $subject Email subject.
     * @param string $title   Email title.
     * @param string $message Email message.
     */
    protected function send_email_customer( $subject, $title, $message ) {
        $to = $_POST['billing_email'];
        $mailer = WC()->mailer();
        $mailer->send( $to, $subject, $mailer->wrap_message( $title, $message ) );
    }

    /**
     * Process banking billet.
     *
     * @param string $billet  Billet id number.
     */
    public function process_banking_billet( $billet ) {
        @ob_clean();

        $response = $this->do_get_request( 'charges/' . $billet . '/billet/download', array(
            'Authorization' => $this->authorization_header(),
            'Content-Type' => 'application/json'
        ) );

        $data = json_decode( $response['body'], true );

        echo $data['html'];
        exit;
    }

    /**
     * IPN handler.
     */
    public function ipn_handler() {
        @ob_clean();

        $raw_response = file_get_contents( 'php://input' );

        if ( empty( $raw_response ) ) {
            return $this->ipn_handler_fail();
        }

        $token = $_SERVER['HTTP_SIGNATURE'];
        if ( ! $this->verify_signature( $raw_response, $token ) ) {
            return $this->ipn_handler_fail();
        }

        $decoded_response = json_decode( $raw_response, true );
        $ipn_result = $this->process_ipn( $decoded_response );

        if ( ! $ipn_result ) {
            return $this->ipn_handler_fail();
        }

        header( 'HTTP/1.1 200 OK' );

        // Deprecated action since 2.0.0.
        do_action( 'wc_rakuten_pay_valid_ipn_request', $decoded_response );

        exit;
    }

    protected function ipn_handler_fail() {
        wp_die( esc_html__( 'Genpay Request Failure', 'woocommerce-rakuten-pay' ), '', array( 'response' => 401 ) );
    }

    /**
     * Process IPN requests.
     *
     * @param array    $posted Posted data.
     *
     * @return boolean $result Result of ipn process
     */
    public function process_ipn( $posted ) {
        global $wpdb;

        $posted   = wp_unslash( $posted );
        $order_id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wc_rakuten_pay_transaction_id' AND meta_value = %s", $posted['uuid'] ) ) );
        $order    = wc_get_order( $order_id );
        $status   = $this->normalize_status( $posted['status'] );

        if ( ! $order ) {
            return false;
        }

        if ( $order->get_id() !== $order_id ) {
            return false;
        }

        $result_messages = null;
        $order_status_result = $this->process_order_status( $order, $status, $posted, $result_messages );

        return $order_status_result;
    }

    /**
     * Process the order status.
     *
     * @param  WC_Order $order  Order data.
     * @param  string   $status Transaction status.
     *
     * @return boolean  $result Order status result
     */
    public function process_order_status( $order, $status, $data, $result_messages ) {
        if ( 'yes' === $this->gateway->debug ) {
            $this->gateway->log->add( $this->gateway->id, 'Payment status for order ' . $order->get_order_number() . ' is now: ' . $status );
        }

        switch ( $status ) {
            case 'pending' :
                $order->update_status( 'pending', __( 'Genpay: The transaction is being processed.', 'woocommerce-rakuten-pay' ) );

                $transaction_id  = get_post_meta( $order->get_id(), '_wc_rakuten_pay_transaction_id', true );
                $transaction_url = '<a href="https://dashboard.genpay.com.br/sales/' . intval( $transaction_id ) . '">https://dashboard.genpay.com.br/sales/' . intval( $transaction_id ) . '</a>';
                $this->send_email_customer(
                    sprintf( esc_html__( 'The transaction for order %s was recieved', 'woocommerce-rakuten-pay' ), $order->get_order_number() ),
                    esc_html__( 'Transaction recieved', 'woocommerce-rakuten-pay' ),
                    sprintf( esc_html__( 'Order %1$s has been marked as pending payment, for more details, see %2$s.', 'woocommerce-rakuten-pay' ), $order->get_order_number(), $transaction_url )
                );

                $order->add_order_note( __( 'Genpay: The transaction is Pending for payment.', 'woocommerce-rakuten-pay' ) );

                break;
            case 'authorized' :
                if ( in_array( $order->get_status(), array( 'approved', 'completed' ), true ) ) {
                    break;
                }

                $order->update_status( 'on-hold', __( 'Genpay: The transaction was authorized.', 'woocommerce-rakuten-pay' ) );

                break;
            case 'approved' :
                if ( in_array( $order->get_status(), array( 'completed' ), true ) ) {
                    break;
                }

                $order->add_order_note( __( 'Genpay: Transaction paid.', 'woocommerce-rakuten-pay' ) );

                // Changing the order for processing and reduces the stock.
                $order->payment_complete();

                break;
            case 'cancelled' :
                if ( in_array( $order->get_status(), array( 'approved', 'completed' ), true ) ) {
                    break;
                }
                if ( get_post_meta( $order->get_id(), '_wc_rakuten_pay_order_cancelled', true ) ) {
                    break;
                }

                update_post_meta( $order->get_id(), '_wc_rakuten_pay_order_cancelled', 'yes' );
                $order->update_status( 'cancelled' );

                $transaction_id  = get_post_meta( $order->get_id(), '_wc_rakuten_pay_transaction_id', true );
                $transaction_url = '<a href="https://dashboard.genpay.com.br/sales/' . intval( $transaction_id ) . '">https://dashboard.genpay.com.br/sales/' . intval( $transaction_id ) . '</a>';
                $this->send_email(
                    sprintf( esc_html__( 'The transaction for order %s was cancelled', 'woocommerce-rakuten-pay' ), $order->get_order_number() ),
                    esc_html__( 'Transaction failed', 'woocommerce-rakuten-pay' ),
                    sprintf( esc_html__( 'Order %1$s has been marked as cancelled, because the transaction was cancelled on Genpay, for more details, see %2$s.', 'woocommerce-rakuten-pay' ), $order->get_order_number(), $transaction_url )
                );
                $this->send_email_customer(
                    sprintf( esc_html__( 'The transaction for order %s was cancelled', 'woocommerce-rakuten-pay' ), $order->get_order_number() ),
                    esc_html__( 'Transaction failed', 'woocommerce-rakuten-pay' ),
                    sprintf( esc_html__( 'Order %1$s has been marked as cancelled, because the transaction was cancelled on Genpay, for more details, see %2$s.', 'woocommerce-rakuten-pay' ), $order->get_order_number(), $transaction_url )
                );

                $order->add_order_note( __( 'Genpay: The transaction was cancelled.', 'woocommerce-rakuten-pay' ) );

                break;
            case 'failure' :
                update_post_meta( $order->get_id(), '_wc_rakuten_pay_order_failure', 'yes' );
                $order->update_status( 'cancelled' );

                $transaction_id  = get_post_meta( $order->get_id(), '_wc_rakuten_pay_transaction_id', true );
                $transaction_url = '<a href="https://dashboard.genpay.com.br/sales/' . intval( $transaction_id ) . '">https://dashboard.genpay.com.br/sales/' . intval( $transaction_id ) . '</a>';

                $this->send_email_customer(
                    sprintf( esc_html__( 'The transaction for order %s was cancelled', 'woocommerce-rakuten-pay' ), $order->get_order_number() ),
                    esc_html__( 'Transaction failed', 'woocommerce-rakuten-pay' ),
                    sprintf( esc_html__( 'Order %1$s has been marked as cancelled, because the transaction was cancelled on Genpay, for more details, see %2$s.', 'woocommerce-rakuten-pay' ), $order->get_order_number(), $transaction_url )
                );

                $order->add_order_note( __( 'Genpay: The transaction was cancelled because ' . $result_messages  , 'woocommerce-rakuten-pay' ) );

                break;
            case 'declined' :
                update_post_meta( $order->get_id(), '_wc_rakuten_pay_order_declined', 'yes' );
                $order->update_status( 'failed' );

                $transaction_id  = get_post_meta( $order->get_id(), '_wc_rakuten_pay_transaction_id', true );
                $transaction_url = '<a href="https://dashboard.genpay.com.br/sales/' . intval( $transaction_id ) . '">https://dashboard.genpay.com.br/sales/' . intval( $transaction_id ) . '</a>';

                $this->send_email_customer(
                    sprintf( esc_html__( 'The transaction for order %s was declined', 'woocommerce-rakuten-pay' ), $order->get_order_number() ),
                    esc_html__( 'Transaction failed', 'woocommerce-rakuten-pay' ),
                    sprintf( esc_html__( 'Order %1$s has been marked as declined, because the transaction was declined on Genpay, for more details, see %2$s.', 'woocommerce-rakuten-pay' ), $order->get_order_number(), $transaction_url )
                );

                $order->add_order_note( 'Genpay: A transação foi declinada. <br /> ' . print_r($result_messages, true) );

                break;
            case 'refunded' :
                if ( in_array( $order->get_status(), array( 'on-hold' ), true ) ) {
                    break;
                }
                if ( (float) $order->get_total() === (float) $order->get_total_refunded() ) {
                    break;
                }

                $refunded_ids = get_post_meta( $order->get_id(), '_wc_rakuten_pay_order_refunded_ids', true );
                $refunded_ids = $refunded_ids ?: array();

                if ( isset( $data['refunds'] ) ) {
                    foreach ( $data['refunds'] as $refund_data ) {

                        $next_refund = false;
                        foreach ( $refunded_ids as $refunded_id ) {
                            if ( $refunded_id === $refund_data['id'] ) {
                                $next_refund = true;
                            }
                        }

                        if ( $next_refund ) {
                            continue;
                        }

                        $refunded_ids[] = $refund_data['id'];
                        update_post_meta( $order->get_id(), '_wc_rakuten_pay_order_refunded_ids', $refunded_ids );

                        $refund_amount          = $refund_data['amount'];
                        $refund_reason          = $refund_data['reason'];
                        $order_id               = $order->get_id();
                        $api_refund             = 0; // via ipn
                        $restock_refunded_items = 1;

                        $refund = wc_create_refund( array(
                            'amount'         => $refund_amount,
                            'reason'         => $refund_reason,
                            'order_id'       => $order_id,
                            'line_items'     => array(),
                            'refund_payment' => $api_refund,
                            'restock_items'  => $restock_refunded_items,
                        ) );

                        if ( is_wp_error( $refund ) ) {
                            if ( 'yes' === $this->gateway->debug ) {
                                $this->gateway->log->add( $this->gateway->id, 'WP_Error in refund status processing: ' . $response->get_error_message() . '...' );
                            }
                            return false;
                        }
                    }
                } else {
                    $refund_amount          = wc_format_decimal( $order->get_total() - $order->get_total_refunded() );
                    $refund_reason          = __( 'Order fully refunded', 'woocommerce' );
                    $order_id               = $order->get_id();
                    $api_refund             = 1; // via ipn
                    $restock_refunded_items = 1;

                    $refund = wc_create_refund( array(
                        'amount'         => $refund_amount,
                        'reason'         => $refund_reason,
                        'order_id'       => $order_id,
                        'line_items'     => array(),
                        'refund_payment' => $api_refund,
                        'restock_items'  => $restock_refunded_items,
                    ) );

                    if ( is_wp_error( $refund ) ) {
                        if ( 'yes' === $this->gateway->debug ) {
                            $this->gateway->log->add( $this->gateway->id, 'WP_Error in refund status processing: ' . $response->get_error_message() . '...' );
                        }
                        return false;
                    }
                }

                if ( (float) $order->get_total() === (float) $order->get_total_refunded() ) {
                    $order->add_order_note( __( 'Genpay: The transaction fully refunded.', 'woocommerce-rakuten-pay' ) );
                    // $order->update_status( 'refunded', __( 'Genpay: The transaction was fully refunded.', 'woocommerce-rakuten-pay' ) );
                } else {
                    $order->add_order_note( __( 'Genpay: The transaction received a partial refund.', 'woocommerce-rakuten-pay' ) );
                }

                $transaction_id  = get_post_meta( $order->get_id(), '_wc_rakuten_pay_transaction_id', true );
                $transaction_url = '<a href="https://dashboard.genpay.com.br/sales/' . intval( $transaction_id ) . '">https://dashboard.genpay.com.br/sales/' . intval( $transaction_id ) . '</a>';
                $this->send_email(
                    sprintf( esc_html__( 'The transaction for order %s refunded', 'woocommerce-rakuten-pay' ), $order->get_order_number() ),
                    esc_html__( 'Transaction refunded', 'woocommerce-rakuten-pay' ),
                    sprintf( esc_html__( 'Order %1$s has been marked as refunded by Genpay, for more details, see %2$s.', 'woocommerce-rakuten-pay' ), $order->get_order_number(), $transaction_url )
                );

                break;

            default :
                break;
        }

        return true;
    }

    /**
     * get signature of requested data.
     *
     * @param   string  $data  Data.
     * @return  string  base64 signature.
     */
    private function get_signature( $data ) {
        $signature = hash_hmac(
            'sha256',
            $data,
            $this->gateway->signature_key,
            true
        );
        return base64_encode( $signature );
    }

    /**
     * Base64 encoding without padding
     *
     * @param   string   $data   Data to encode
     * @return  string           Base64 encoded data
     */
    private function base64_encode_url( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_'), '=' );
    }

    /**
     * Customer IP address.
     *
     * @param  WC_Order   Current Order
     * @return string     Customer ip address
     */
    private function customer_ip_address( $order ) {
        return get_post_meta( $order->get_id(), '_customer_ip_address', true );
    }

    /**
     * Banking Billet URL
     *
     * @param  string $billet  Billet id number.
     * @return string          Billet URL.
     */
    public function banking_billet_url( $billet ) {
        $scheme = parse_url( home_url(), PHP_URL_SCHEME );
        $query = array(
            'wc-api' => get_class( $this->gateway ),
            'billet' => $billet
        );
        $api_request_url = add_query_arg( $query, trailingslashit( home_url( '', $scheme ) ) );
        return $api_request_url;
    }

    /**
     * strtotime considering the wp timezone
     * @param string  $time date time format like defined on std strtotime
     * @return int    unix timestamp
     */
    public function strtotime( $time ) {
        $tz_string = get_option('timezone_string');
        $tz_offset = get_option('gmt_offset', 0);

        if ( !empty( $tz_string ) ) {
            // If site timezone option string exists, use it
            $timezone = $tz_string;

        } elseif ( $tz_offset == 0 ) {
            // get UTC offset, if it isn’t set then return UTC
            $timezone = 'UTC';

        } else {
            $timezone = $tz_offset;

            if( substr( $tz_offset, 0, 1 ) != "-" && substr( $tz_offset, 0, 1 ) != "+" && substr( $tz_offset, 0, 1 ) != "U" ) {
                $timezone = "+" . $tz_offset;
            }
        }

        $datetime = new DateTime($time, new DateTimeZone($timezone));
        return $datetime->format('U');
    }

    private function normalize_status( $status ) {
        $status = sanitize_text_field( $status );

        switch ( $status ) {
            case 'partial_refunded':
                return 'refunded';
            default:
                return $status;
        }
    }

    private function authorization_header() {
        $document  = $this->gateway->document;
        $api_key   = $this->gateway->api_key;
        $user_pass = $document . ':' . $api_key;
        return 'Basic ' . base64_encode( $user_pass );
    }

    public function get_buyer_interest() {
        $buyer_interest   = $this->gateway->settings['buyer_interest'];
        return $buyer_interest;
    }

    public function get_installments_buyer_interest() {
        $free_installments = $this->gateway->free_installments;
        if ( $free_installments == 12 ) {
            return $free_installments;
        }
    }

    /**
     * @var array
     */
    private static $installmentsToFloat = [
        'interest_percent',
        'interest_amount',
        'installment_amount',
        'total',
    ];

    /**
     * Json encode and Check if const exists based from PHP Version
     *
     * @param array $data
     * @return mixed|string
     */
    public static function getJson(array $data)
    {
        if (defined('JSON_PRESERVE_ZERO_FRACTION')) {

            return  json_encode($data, JSON_PRESERVE_ZERO_FRACTION);
        }

        /** For PHP Version < 5.6 */
        return self::preserveZeroFractionInstallments($data);
    }

    /**
     * @param array $data
     * @return mixed|string
     */
    private static function preserveZeroFractionInstallments(array $data)
    {
        $jsonData = json_encode($data);
        try {
            $payments = $data['payments'];
            foreach ($payments as $item) {
                if (!array_key_exists('installments', $item)) {
                    break;
                }
                $jsonData = self::installmentsToFloat($item['installments'], $jsonData);
            }

            return $jsonData;
        } catch (\Exception $e) {

            return $jsonData;
        }
    }

    /**
     * @param array $installments
     * @param $jsonData
     * @return mixed|string
     */
    private static function installmentsToFloat(array $installments, $jsonData)
    {
        foreach (self::$installmentsToFloat as $field) {
            if (array_key_exists($field, $installments)) {
                $jsonData = str_replace('"' . $field . '":'. $installments[$field], '"' . $field . '":'. number_format($installments[$field], 2, ".", "") . '', $jsonData);
            }
        }

        return $jsonData;
    }

	/**
	 * Get the items with the order params and set the array to the payload.
	 *
	 * @param $order
	 *
	 * @return array
	 */
	public function getItems($order) {
		$items = $order->get_items();
		$data = [];

		foreach ( $items as $item ) {

			if (empty($sku) || is_null($sku) ) {

				$data[] = [
					'reference'    => (string) $this->removeAccentuation($this->getSku($item)),
					'description'  => substr( $item['name'], 0, 255 ),
					'amount'       => (float) $item->get_product()->get_price(),
					'quantity'     => $item['quantity'],
					'total_amount' => (float) $item['total'],
					'categories'   => $this->getCategories( $order, $item['product_id'] ),
				];
			}
		}

		return $data;

	}

	/**
	 * Get the categories IDs and title.
	 *
	 * @param $order
	 * @param $product_id
	 *
	 * @return array
	 */
	private function getCategories($order, $product_id) {

		$categories = wp_get_post_terms( $product_id, 'product_cat');
		$category_id = [];
		foreach ($categories as $category) {

			$category_id[] = [
				'name' => (string) $category->name,
				'id' => (string) $category->term_id
			];
		}
		return $category_id;
	}

	/**
	 * Get sku or product ID
	 *
	 * @param $item
	 *
	 * @return string
	 */
	private function getSku($item) {
		if (empty($item->get_product()->get_sku()) || is_null($item->get_product()->get_sku()) ) {
			return $item['product_id'];
		}

		return $item->get_product()->get_sku();
  }

  	/**
	 * Recieves an string and take off the accentuation
	 *
	 * @param $string
     * @return string
	 */
	private function removeAccentuation($str) {
		$map = [
			'á' => 'a',
			'à' => 'a',
			'ã' => 'a',
			'â' => 'a',
			'é' => 'e',
			'ê' => 'e',
			'í' => 'i',
			'ó' => 'o',
			'ô' => 'o',
			'õ' => 'o',
			'ú' => 'u',
			'ü' => 'u',
			'ç' => 'c',
			'Á' => 'A',
			'À' => 'A',
			'Ã' => 'A',
			'Â' => 'A',
			'É' => 'E',
			'Ê' => 'E',
			'Í' => 'I',
			'Ó' => 'O',
			'Ô' => 'O',
			'Õ' => 'O',
			'Ú' => 'U',
			'Ü' => 'U',
			'Ç' => 'C'
		];
		return strtr($str, $map);
	}
}
