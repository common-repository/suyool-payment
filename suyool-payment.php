<?php
/*
 * Plugin Name: Suyool Payment
 * Description: Have your Suyool customers pay with their Mobile, without entering any confidential information.
 * Author: Suyool
 * Author URI: https://suyool.com
 * Version: 1.0.0
 * Text Domain: suyool-payment
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if WooCommerce is active
 **/
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('plugins_loaded', 'spfw_woocommerce_init', 0);

    function spfw_woocommerce_init()
    {
        if (!class_exists('WC_Payment_Gateway')) return;

        /**
         * Suyool Payment Gateway class
         */
        class SPFW_Woocommerce_Gateway extends WC_Payment_Gateway
        {

            public function __construct()
            {
                $this->id = 'suyool-payment'; // payment gateway plugin ID
                $this->icon = esc_url(plugin_dir_url(__FILE__) . 'suyool-logo.png');// URL of the icon that will be displayed on checkout page near your gateway name
                $this->has_fields = false; // in case you need a custom credit card form
                $this->method_title = 'Suyool Payment';
                $this->method_description = 'Have your Suyool customers pay with their Mobile, without entering any confidential information.'; // will be displayed on the options page
                // gateways can support subscriptions, refunds, saved payment methods
                $this->supports = array(
                    'products'
                );
                // Method with all the options fields
                $this->init_form_fields();
                // Load the settings.
                $this->init_settings();
                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');
                $this->enabled = $this->get_option('enabled');
                $this->currency = $this->get_option('currency');
                $this->certificate_key = $this->get_option('certificate_key');
                $this->test_certificate_key = $this->get_option('test_certificate_key');
                $this->test_merchant_id = $this->get_option('test_merchant_id');
                $this->live_merchant_id = $this->get_option('live_merchant_id');
                $this->suyool_iframe_mode = $this->get_option('suyool-iframe-mode');

                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_receipt_' . $this->id, array($this, 'spfw_woocommerce_receipt_page'), 10);
                add_action('woocommerce_thankyou_' . $this->id, array($this, 'spfw_woocommerce_thankyou_page'));
                add_action('woocommerce_api_' . $this->id, array($this, 'spfw_webhook'), 20);
            }


            function init_form_fields()
            {
                $this->form_fields = apply_filters('wc_offline_form_fields', array(
                    'enabled' => array(
                        'title' => esc_html('Enable/Disable'),
                        'label' => 'Enable Suyool Gateway',
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => 'no'
                    ),
                    'title' => array(
                        'title' => esc_html('Title'),
                        'type' => 'text',
                        'description' => esc_html('This controls the title which the user sees during checkout.'),
                        'default' => 'Suyool Method',
                        'desc_tip' => true,
                    ),
                    'description' => array(
                        'title' => esc_html('Description'),
                        'type' => 'textarea',
                        'description' => esc_html('This controls the description which the user sees during checkout.'),
                        'default' => 'Pay with Suyool',
                        'desc_tip' => true,
                    ),
                    'test_merchant_id' => array(
                        'title' => esc_html('Test Merchant Id'),
                        'type' => 'text',
                        'default' => $this->get_option('test_merchant_id')
                    ),

                    'test_certificate_key' => array(
                        'title' => esc_html('Suyool Certificate for Test'),
                        'type' => 'text',
                        'default' => $this->get_option('test_certificate_key')
                    ),

                    'live_mode' => array(
                        'title' => esc_html('Live mode'),
                        'label' => 'Enable Live Mode',
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => $this->get_option('live_mode'),
                        'desc_tip' => true,
                    ),


                    'live_merchant_id' => array(
                        'title' => esc_html('Live Merchant Id'),
                        'type' => 'text',
                        'default' => $this->get_option('live_merchant_id')
                    ),
                    'certificate_key' => array(
                        'title' => esc_html('Suyool Certificate for Live'),
                        'type' => 'text',
                        'default' => $this->get_option('certificate_key')
                    ),

                ));
            }

            function payment_fields()
            {
                echo esc_html($this->description);
            }

            function spfw_woocommerce_receipt_page($order)
            {
                global $woocommerce;
                $order = new WC_Order($order);
                $order_id = $order->get_id();
                include_once  'payment-process.php';

            }

            /**
             * Process the payment and return the result
             *
             * @access public
             * @param int $order_id
             * @return array
             */
            function process_payment($order_id)
            {
                global $woocommerce;
                $order = new WC_Order($order_id);

                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true)
                );
            }

            function spfw_woocommerce_thankyou_page($order_id)
            {

            }

            /**
             * Suyool Payment IPN webhook
             */
            public function spfw_webhook()
            {

            }

        }

        /**
         * Add the Gateway to WooCommerce
         */
        function spfw_woocommerce_add_gateway($methods)
        {
            $methods[] = 'SPFW_Woocommerce_Gateway';
            return $methods;
        }

        add_filter('woocommerce_payment_gateways', 'spfw_woocommerce_add_gateway');
    }

}

function spfw_window_secure_hash($order_id)
{
    global $woocommerce;
    $installed_payment_methods = WC()->payment_gateways->payment_gateways();
    $data = $installed_payment_methods['suyool-payment']->settings;
    $order = wc_get_order($order_id);
    $amount = empty($woocommerce->cart->total) ? $order->get_total() : $woocommerce->cart->total;
    $amount = number_format((float)$amount, 2, '.', '');
    $currency = $order->get_currency();
    $created_at = $order->get_date_created();
    $timestamp = strtotime($created_at) * 1000;
    $suyool_sandbox = $data['live_mode'];
    if ($suyool_sandbox == 'yes') {
        $certificate_key = $data['certificate_key'];
    } else {
        $certificate_key = $data['test_certificate_key'];
    }
    $window_secure = $order_id . $timestamp . $amount . $currency . $timestamp . $certificate_key;
    $WindowSecureHash = base64_encode(hash('sha512', $window_secure, true));

    return $WindowSecureHash;
}

function spfw_mobile_secure_hash($order_id)
{
    global $woocommerce;
    $order = wc_get_order($order_id);
    $installed_payment_methods = WC()->payment_gateways->payment_gateways();
    $data = $installed_payment_methods['suyool-payment']->settings;
    $amount = empty($woocommerce->cart->total) ? $order->get_total() : $woocommerce->cart->total;
    $amount = number_format((float)$amount, 2, '.', '');
    $currency = $order->get_currency();
    $created_at = $order->get_date_created();
    $timestamp = strtotime($created_at) * 1000;
    $suyool_sandbox = $data['live_mode'];
    if ($suyool_sandbox == 'yes') {
        $merchant_id = $data['live_merchant_id'];
        $certificate_key = $data['certificate_key'];
    } else {
        $merchant_id = $data['test_merchant_id'];
        $certificate_key = $data['test_certificate_key'];
    }
    $mobile_secure = $order_id . $merchant_id . $amount . $currency . $timestamp . $certificate_key;
    $MobileSecureHash = base64_encode(hash('sha512', $mobile_secure, true));
    return $MobileSecureHash;
}


function spfw_get_browser_type()
{
    $browser = "";

    // Sanitize and escape HTTP_USER_AGENT
    $user_agent = isset($_SERVER["HTTP_USER_AGENT"]) ? sanitize_text_field($_SERVER["HTTP_USER_AGENT"]) : '';

    // Validate and check for specific substrings in a case-insensitive manner
    if (stripos($user_agent, 'MSIE') !== false) {
        $browser = "IE";
    } else if (stripos($user_agent, 'Presto') !== false) {
        $browser = "opera";
    } else if (stripos($user_agent, 'CHROME') !== false) {
        $browser = "chrome";
    } else if (stripos($user_agent, 'SAFARI') !== false) {
        $browser = "safari";
    } else if (stripos($user_agent, 'FIREFOX') !== false) {
        $browser = "firefox";
    } else if (stripos($user_agent, 'Netscape') !== false) {
        $browser = "netscape";
    }

    return $browser;
}
add_action('rest_api_init', 'spfw_create_api_endpoint');

function spfw_create_api_endpoint()
{
    $namespace = 'wc/v3';
    $route = '/update-status/';
    $url = rest_url( $namespace . $route );

    register_rest_route($namespace, $route, array(
        'methods' => 'POST',
        'callback' => 'spfw_update_transaction_status',
    ));
}


function spfw_update_transaction_status($request)
{
    global $woocommerce;
    $installed_payment_methods = WC()->payment_gateways->payment_gateways();
    $data = $installed_payment_methods['suyool-payment']->settings;
    $suyool_sandbox = $data['live_mode'];
    if ($suyool_sandbox == 'yes') {
        $certificate_key = $data['certificate_key'];
    } else {
        $certificate_key = $data['test_certificate_key'];
    }
    $order_id = $request->get_param('TransactionID');
    $order = new WC_Order($order_id);
    $checkout_page_id = wc_get_page_id('checkout');
    $checkout_page_url = $checkout_page_id ? get_permalink($checkout_page_id) : '';
    $order_key = $order->get_order_key();
    $order_ref = $order_id . '&key=' . $order_key;
    $dataurl = add_query_arg('order-received', $order_ref, $checkout_page_url);

    $flag = $request->get_param('Flag');
    $response =array();
    if (isset($flag)) {
        if ($flag == '1') {
            $match_secure = $request->get_param('Flag') . $request->get_param('ReferenceNo') . $request->get_param('TransactionID') . $request->get_param('ReturnText') . $certificate_key;
            $SecureHash = base64_encode(hash('sha512', $match_secure, true));
            if ($SecureHash == $request->get_param('SecureHash')) {
                $order->update_status('completed', "Your order is Processing");
                $response['message'] ='Completed';
            }
        } else {
            $order->update_status('failed', $request->get_param('ReturnText'));
            $response['message'] ='Failed';
        }
        $response['success'] = true;
        $response['return_url'] = $dataurl;

        return $response;
    }
}

//an action that is triggered when an AJAX request is made by an authenticated user
add_action('wp_ajax_spfw_check_order_status', 'spfw_check_order_status');
//an action that is is triggered when an AJAX request is made by an unauthenticated user
add_action('wp_ajax_nopriv_spfw_check_order_status', 'spfw_check_order_status');

function spfw_check_order_status()
{
    check_ajax_referer('spfw_nonce', 'spfw_nonce');

    $order_id= sanitize_text_field($_POST['order_id']);
    $order = wc_get_order($order_id);
    $status = $order->get_status();

    echo esc_html($status);

    wp_die();
}

function spfw_send_request($params) {
    $url = $params['url'];
    $data = json_decode($params['data'], true);

    $response = wp_remote_post($url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Connection' => 'Keep-Alive',
        ),
        'body' => json_encode($data),
        'sslverify' => false,
    ));

    if (is_wp_error($response)) {
        return $response->get_error_message();
    }

    return wp_remote_retrieve_body($response);
}


function spfw_suyool_inline_script() {
    $params = array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'orderId' => '',
        'spfw_nonce' => wp_create_nonce('spfw_nonce') // Create and pass the nonce value

    );
    wp_localize_script('jquery', 'suyool_params', $params);
}
add_action('wp_enqueue_scripts', 'spfw_suyool_inline_script');
