<?php
/**
 * Rakuten Log REST client implementation
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

    public function create_batch($batch_data)
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
    private function authorization_header() {
        // $document  = $this->shipping->owner_document;
        // $api_key   = $this->shipping->api_key;
        // $user_pass = $document . ':' . $api_key;
        // return 'Basic ' . base64_encode( $user_pass );
        $document_b  = get_option('woocommerce_rakuten-pay-banking-billet_settings')['document'];
        $api_key_b   = get_option('woocommerce_rakuten-pay-banking-billet_settings')['api_key'];
        $enabled_b   = get_option('woocommerce_rakuten-pay-banking-billet_settings')['enabled'];
        $document_c  = get_option('woocommerce_rakuten-pay-credit-card_settings')['document'];
        $api_key_c   = get_option('woocommerce_rakuten-pay-credit-card_settings')['api_key'];
        $enabled_c   = get_option('woocommerce_rakuten-pay-credit-card_settings')['enabled'];

        $user_pass = $document_b . ':' . $api_key_b;

        if ($enabled_b == 'no') {
            if ($enabled_c == 'no') {
                echo "<pre>nem ta</pre>";
            } else {
                return 'Basic ' . base64_encode( $user_pass );
            }
        } else {
            return 'Basic ' . base64_encode( $user_pass );
        }
    }
}
