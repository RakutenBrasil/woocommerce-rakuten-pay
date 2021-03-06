<?php
/**
 * GenLog REST client implementation
 *
 * @package WC_Rakuten_Log
 */

if (!defined( 'ABSPATH' )) {
    exit;
}


class WC_Rakuten_Log_REST_Client extends WC_Payment_Gateway {
    protected $shipping;

    public function __construct($shipping)
    {
        $this->shipping = $shipping;
    }

    protected function do_post_request( $endpoint, $data = array(), $headers = array() ){
        $params = array(
            'timeout' => 60,
            'method'  => 'POST'
        );

        if(!empty($data)){
            $params['body'] = $data;
        }

        if(!empty($headers)){
            $params['headers'] = $headers;
        }

        return wp_remote_post($this->get_api_base_url() . $endpoint, $params);
    }

    protected function do_get_request( $endpoint, $headers = array() ){
        $params = array(
            'timeout' => 60,
            'method'  => 'GET'
        );

        if(!empty($headers)){
            $params['headers'] = $headers;
        }

        return wp_remote_get($this->get_api_base_url() . $endpoint, $params);
    }

    public function create_calculation($calculation_data)
    {
        $endpoint = 'calculation';
        $body = json_encode($calculation_data, JSON_PRESERVE_ZERO_FRACTION);
        $headers = array(
            'Authorization' => $this->authorization_header(),
            'Signature'     => $this->get_signature($body),
            'Content-Type'  => 'application/json',
            'Cache-Control' => 'no-cache'
        );
        $response = $this->do_post_request($endpoint, $body, $headers);

        if (is_wp_error($response)){
            return array('result' => 'fail');
        }

        $response_body = json_decode( $response['body'], true );
        if ( $response['response']['code'] != 200 ) {
            return array( 'result' => 'fail' );
        }
        if ( isset($response_body['result']) && $response_body['result'] == 'failure' ) {
            return array( 'result' => 'fail' );
        }
        return $response_body;
    }

    public function create_batch($batch_data, $order_id, $order_ids)
    {
        $endpoint = 'batch';
        $body = json_encode($batch_data, JSON_PRESERVE_ZERO_FRACTION);
        $headers = array(
            'Authorization' => $this->authorization_header(),
            'Signature'     => $this->get_signature($body),
            'Content-Type'  => 'application/json',
            'Cache-Control' => 'no-cache'
        );
        $response = $this->do_post_request($endpoint, $body, $headers);

        if (is_wp_error($response)){
            return array('result' => 'fail');
        }

        $response_body = json_decode( $response['body'], true );

        $this->get_batch($batch_data, $order_id, $order_ids);

        if ( $response['response']['code'] != 200 ) {
            return array(
                'result' => 'fail',
                'errors' => $response_body['messages']
            );
        }
        if ( $response_body['status'] == 'ERROR' ) {
            return array( 'result' => 'fail' );
        }
        return $response_body;
    }

    public function get_api_base_url() {
        if ('production' === $this->shipping->environment){
            return WC_RAKUTEN_LOG_PRODUCTION_API_URL;
        } else {
            return WC_RAKUTEN_LOG_SANDBOX_API_URL;
        }
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
            $this->shipping->signature_key,
            true
        );
        return base64_encode( $signature );
    }

    /**
     * @return string
     */
    private function authorization_header()
    {
        $document_billet = get_option('woocommerce_rakuten-pay-banking-billet_settings')['document'];
        $api_key_billet = get_option('woocommerce_rakuten-pay-banking-billet_settings')['api_key'];
        $enabled_billet = get_option('woocommerce_rakuten-pay-banking-billet_settings')['enabled'];
        $document_credit_card = get_option('woocommerce_rakuten-pay-credit-card_settings')['document'];
        $api_key_credit_card = get_option('woocommerce_rakuten-pay-credit-card_settings')['api_key'];
        $enabled_credit_card = get_option('woocommerce_rakuten-pay-credit-card_settings')['enabled'];

        if ( $enabled_billet == 'yes' ) {

            $user_pass = $document_billet . ':' . $api_key_billet;
            return 'Basic ' . base64_encode( $user_pass );

        } else if ( $enabled_credit_card == 'yes' ) {

            $user_pass = $document_credit_card . ':' . $api_key_credit_card;
            return 'Basic ' . base64_encode( $user_pass );

        } else {
	        echo "<script>console.log('Configure as chaves da API e Assinatura GenComm')</script>";
        }
        return "<script>console.log('ERRO: Configure as chaves da API e Assinatura GenComm')</script>";
    }

	public function get_batch( $batch_data, $order_id, $order_ids )
	{
		$this->log = new WC_Logger();
		$this->log_admin = new WC_Rakuten_Log_Admin_Orders();

		$endpoint = 'order/' . $order_id;

		$headers = array(
			'Authorization' => $this->authorization_header(),
			'Content-Type'  => 'application/json',
			'Cache-Control' => 'no-cache'
		);
		$response = $this->do_get_request($endpoint, $headers);
		$response_body = json_decode( $response['body'], true );
		$this->log->add('LOG', 'endpoint: ' . print_r($endpoint, true));

		if ( $response_body['status'] == 'OK' ) {

			$errors = $this->log_admin->valid_rakuten_log_batch_orders($order_ids);
			$count_error = count($errors);

			if( $count_error != 0 ) {
				return false;
			} else {

			update_post_meta( $order_id, '_rakuten_log_tracking_code', $response_body['content']['trackings_number'][0] );
			update_post_meta( $order_id, '_rakuten_log_tracking_url', $response_body['content']['tracking_print_url'] );
			update_post_meta( $order_id, '_rakuten_log_print_url', $response_body['content']['batch_print_url'] );
			update_post_meta( $order_id, '_rakuten_log_batch_print_url', $response_body['content']['batch_print_url'] );
			update_post_meta( $order_id, '_rakuten_log_batch_code', $response_body['content']['batch_code'] );
			update_post_meta( $order_id, '_rakuten_log_volume', $response_body['content']['shipping_option']['volumes'][0]['number'] );

			}
		} else {

			$errors = $this->log_admin->valid_rakuten_log_batch_orders($order_ids);
			$count_error = count($errors);

			if ( $count_error != 0 ) {
				return false;
			} else {
				return $batch_data;
			}
		}
		return $response_body;
	}
}
