<?php
/**
 * GenLog Plugin Setup
 *
 * @package WC_Rakuten_Log
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WC_Rakuten_Log
 */
class WC_Rakuten_Log
{
    /**
     * Instace of this class
     *
     * @var object
     */
    protected static $instance = null;

    /**
     * Initialize plugin public actions
     *
     * WC_Rakuten_Log constructor.
     */
    public static function init()
    {
        add_action( 'init', array( __CLASS__, 'load_plugin_textdomain' ), -1 );

        // Checks if Woocommerce is installed
        if(class_exists('WC_INTEGRATION')){
            self::includes();

            if ( is_admin() ){
                self::admin_includes();
            }

            add_filter( 'woocommerce_shipping_methods', array( __CLASS__, 'include_shipping_methods' ) );
            /* add_filter( 'woocommerce_checkout_fields', array(__CLASS__, 'add_address_fields') ); */
            add_filter( 'woocommerce_formatted_address_replacements', array(__CLASS__, 'add_address_replacement_fields'), 10, 2 );
//            add_filter( 'woocommerce_order_formatted_billing_address', array(__CLASS__, 'add_address_number_to_billing_address') , 10, 2 );
//            add_filter( 'woocommerce_order_formatted_shipping_address', array(__CLASS__, 'add_address_number_to_shipping_address'), 10, 2 );
            add_filter( 'woocommerce_email_classes', array(__CLASS__, 'include_emails'));
            add_filter( 'woocommerce_localisation_address_formats', array(__CLASS__, 'add_new_address_formats'), 10, 2 );
        }
    }

    /**
     * Includes
     */
    private static function includes()
    {
        include_once dirname( __FILE__ ) . '/wc-rakuten-log-functions.php';
        include_once dirname( __FILE__ ) . '/class-wc-rakuten-log-shipping.php';
        include_once dirname( __FILE__ ) . '/class-wc-rakuten-log-rest-client.php';
        include_once dirname( __FILE__ ) . '/class-wc-rakuten-log-order-details.php';
    }

    private static function admin_includes()
    {
        include_once dirname( __FILE__ ) . '/admin/class-wc-rakuten-log-admin-orders.php';
    }

    public static function include_emails($emails)
    {
        if (!isset($emails['WC_Rakuten_Log_Tracking_Email'])){
            $emails['WC_Rakuten_Log_Tracking_Email'] = include dirname(__FILE__) . '/emails/class-wc-rakuten-log-tracking-email.php';
        }

        return $emails;
    }


    /**
     * @param $methods
     *
     * Adds GenLog shipping method to the
     * array of shipping methods
     *
     * @return array
     */
    public static function include_shipping_methods($methods)
    {
        $methods['rakuten-log'] = 'WC_Rakuten_Log_Shipping';

        return $methods;
    }

    /**
     * Loads textdomain for GenLog
     */
    public static function load_plugin_textdomain()
    {
        load_plugin_textdomain( 'woocommerce-rakuten-log', false, dirname( plugin_basename( WC_RAKUTEN_LOG_PLUGIN_FILE ) ) . '/languages/' );
    }

    /**
     * WooCommerce fallback notice.
     */
    public static function woocommerce_missing_notice() {
        include_once dirname( __FILE__ ) . '/admin/views/html-admin-missing-dependencies.php';
    }

    /**
     * Get main file.
     *
     * @return string
     */
    public static function get_main_file() {
        return WC_RAKUTEN_LOG_PLUGIN_FILE;
    }
    /**
     * Get plugin path.
     *
     * @return string
     */
    public static function get_plugin_path() {
        return plugin_dir_path( WC_RAKUTEN_LOG_PLUGIN_FILE );
    }
    /**
     * Get templates path.
     *
     * @return string
     */
    public static function get_templates_path() {
        return self::get_plugin_path() . 'templates/';
    }

    public static function add_address_fields( $fields ) {
        $fields['billing']['billing_number'] = array(
            'label'       => __('Number', 'woocommerce-rakuten-log'),
            'placeholder' => _x('Number', 'placeholder', 'woocommerce-rakuten-log'),
            'required'    => true,
            'class'       => array('form-row-wide'),
            'clear'       => true
        );
        $fields['billing']['billing_neighborhood'] = array(
            'label'       => __('District', 'woocommerce-rakuten-log'),
            'placeholder' => _x('District', 'placeholder', 'woocommerce-rakuten-log'),
            'required'    => true,
            'class'       => array('form-row-first'),
            'clear'       => true
        );

        $fields['shipping']['shipping_number'] = array(
            'label'       => __('Number', 'woocommerce-rakuten-log'),
            'placeholder' => _x('Number', 'placeholder', 'woocommerce-rakuten-log'),
            'required'    => true,
            'class'       => array('form-row-first'),
            'clear'       => true
        );
        $fields['shipping']['shipping_neighborhood'] = array(
            'label'       => __('District', 'woocommerce-rakuten-log'),
            'placeholder' => _x('District', 'placeholder', 'woocommerce-rakuten-log'),
            'required'    => true,
            'class'       => array('form-row-first'),
            'clear'       => true
        );
        $fields['shipping']['shipping_number'] = array(
            'label'       => __('Phone number', 'woocommerce-rakuten-pay'),
            'placeholder' => _x('Número de telefone', 'placeholder', 'woocommerce-rakuten-pay'),
            'required'    => true,
            'class'       => array('form-row-first'),
            'clear'       => true
        );
        $fields['billing']['billing_address_2']['class'] = array('form-row-wide');
        $fields['billing']['billing_address_2']['label'] = __('Complement', 'woocommerce-rakuten-log');
        $fields['shipping']['shipping_address_2']['class'] = array('form-row-wide');
        $fields['shipping']['shipping_address_2']['label'] = __('Complement', 'woocommerce-rakuten-log');

        // Billing: Sort Fields

        $newfields['billing']['billing_first_name'] = $fields['billing']['billing_first_name'];
        $newfields['billing']['billing_last_name']  = $fields['billing']['billing_last_name'];
        $newfields['billing']['billing_company']    = $fields['billing']['billing_company'];
        $newfields['billing']['billing_email']      = $fields['billing']['billing_email'];
        $newfields['billing']['billing_phone']      = $fields['billing']['billing_phone'];
        $newfields['billing']['billing_country']    = $fields['billing']['billing_country'];
        $newfields['billing']['billing_address_1']  = $fields['billing']['billing_address_1'];
        $newfields['billing']['billing_number']    = $fields['billing']['billing_number'];
        $newfields['billing']['billing_neighborhood']  = $fields['billing']['billing_neighborhood'];
        $newfields['billing']['billing_address_2']  = $fields['billing']['billing_address_2'];
        $newfields['billing']['billing_city']       = $fields['billing']['billing_city'];
        $newfields['billing']['billing_postcode']   = $fields['billing']['billing_postcode'];
        $newfields['billing']['billing_state']      = $fields['billing']['billing_state'];

        // Shipping: Sort Fields

        $newfields['shipping']['shipping_first_name'] = $fields['shipping']['shipping_first_name'];
        $newfields['shipping']['shipping_last_name']  = $fields['shipping']['shipping_last_name'];
        $newfields['shipping']['shipping_company']    = $fields['shipping']['shipping_company'];
        $newfields['shipping']['shipping_number']    = $fields['shipping']['shipping_number'];
        $newfields['shipping']['shipping_country']    = $fields['shipping']['shipping_country'];
        $newfields['shipping']['shipping_address_1']  = $fields['shipping']['shipping_address_1'];
        $newfields['shipping']['shipping_number']    = $fields['shipping']['shipping_number'];
        $newfields['shipping']['shipping_neighborhood']  = $fields['shipping']['shipping_neighborhood'];
        $newfields['shipping']['shipping_address_2']  = $fields['shipping']['shipping_address_2'];
        $newfields['shipping']['shipping_city']       = $fields['shipping']['shipping_city'];
        $newfields['shipping']['shipping_state']      = $fields['shipping']['shipping_state'];
        $newfields['shipping']['shipping_postcode']   = $fields['shipping']['shipping_postcode'];

        $checkout_fields = array_merge( $fields, $newfields);
        return $checkout_fields;
    }

    public static function add_address_number_to_billing_address( $fields, $order ){
        $fields['billing_number'] = get_post_meta( $order->get_id(), '_billing_number', true );
        $fields['billing_neighborhood'] = get_post_meta( $order->get_id(), '_billing_neighborhood', true );
        return $fields;
    }

    public static function add_address_number_to_shipping_address( $fields, $order ){
        $fields['shipping_number'] = get_post_meta( $order->get_id(), '_shipping_number', true );
        $fields['shipping_neighborhood'] = get_post_meta( $order->get_id(), '_shipping_neighborhood', true );
        $fields['shipping_number'] = get_post_meta( $order->get_id(), '_shipping_number', true );
        return $fields;
    }

    public static function add_address_replacement_fields( $replacements, $address ){
        $replacements['{billing_number}'] = isset($address['billing_number']) ? $address['billing_number'] : '';
        $replacements['{billing_neighborhood}'] = isset($address['billing_neighborhood']) ? $address['billing_neighborhood'] : '';
        $replacements['{shipping_number}'] = isset($address['shipping_number']) ? $address['shipping_number'] : '';
        $replacements['{shipping_neighborhood}'] = isset($address['shipping_neighborhood']) ? $address['shipping_neighborhood'] : '';
        $replacements['{shipping_number}'] = isset($address['shipping_number']) ? $address['shipping_number'] : '';

        return $replacements;
    }

    public static function add_new_address_formats( $formats ) {
        $formats['BR'] = "{name}\n{address_1}\n{billing_number}\n{billing_neighborhood}\n{shipping_number}\n{shipping_neighborhood}\n{shipping_number}\n{city}\n{state}\n{postcode}\n{country}";
        return $formats;
    }
}

