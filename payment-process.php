<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $woocommerce;

$order = wc_get_order($order_id);
if (empty($order)) {
    return '';
}

$installed_payment_methods = WC()->payment_gateways->payment_gateways();
$data = $installed_payment_methods['suyool-payment']->settings;
$order_key = $order->get_order_key();
$amount = empty($woocommerce->cart->total) ? $order->get_total() : $woocommerce->cart->total;
$amount = number_format((float)$amount, 2, '.', '');
$checkout_page_id = wc_get_page_id('checkout');
$checkout_page_url = $checkout_page_id ? get_permalink($checkout_page_id) : '/checkout';
$currency = $order->get_currency();
$created_at = $order->get_date_created();
$timestamp = strtotime($created_at) * 1000;
$suyool_sandbox = $data['live_mode'];
if ($suyool_sandbox == 'yes') {
    $merchant_id = $data['live_merchant_id'];
    $params['url'] = 'https://externalservices.nicebeach-895ccbf8.francecentral.azurecontainerapps.io/api/OnlinePayment/PayQR';

} else {
    $merchant_id = $data['test_merchant_id'];
    $params['url'] = 'https://online.suyool.money/PayQR';

}
$AdditionalInfo = '';
$order_ref = $order_id . '&key=' . $order_key;
$dataUrl = add_query_arg('order-received', $order_ref, $checkout_page_url);

if (wp_is_mobile()) {
    $MobileSecureHash = spfw_mobile_secure_hash($order_id);
    $browser = spfw_get_browser_type();
    $order_ref = $order_id . '&key=' . $order_key;
    $data = add_query_arg('order-received', $order_ref, $checkout_page_url);
    $json = [
        "strTranID" => $order_id,
        "MerchantID" => $merchant_id,
        "Amount" => $amount,
        "Currency" => $currency,
        "CallBackURL" => "",
        "TS" => (string)$timestamp,
        "secureHash" => $MobileSecureHash,
        "currentUrl" =>$data,
        "browsertype" => $browser,
        "AdditionalInfo" => $AdditionalInfo
    ];
    $json_encoded = json_encode($json);
    $APP_URL = "suyoolpay://suyool.com/suyool=?";
    $appUrl = $APP_URL.urlencode($json_encoded);
}else {
    $SecureHash = spfw_window_secure_hash($order_id);

    $json = [
        "TransactionID" => "$order_id",
        "Amount" => $amount,
        "Currency" => $currency,
        "SecureHash" => $SecureHash,
        "TS" => (string)$timestamp,
        "TranTS" => (string)$timestamp,
        "MerchantAccountID" => $merchant_id,
        "AdditionalInfo" => $AdditionalInfo
    ];
    $params['data'] = json_encode($json);

    $result = spfw_send_request($params);
    $response = json_decode($result, true);

    if ($suyool_sandbox == 'yes') {
        $message = $response['returnText'];
        $pictureURL = $response['pictureURL'];
        if (!empty($response['flag']) && $response['flag'] == 2) {

            $displayQRCont = '';
        } else {

            $displayQRCont = 'displayNone';
        }
    }else {
        $message = $response['ReturnText'];
        $pictureURL = $response['PictureURL'];
        if (!empty($response['Flag']) && $response['Flag'] == 2) {

            $displayQRCont = '';
        } else {

            $displayQRCont = 'displayNone';
        }
    }
}?>
<style>
    .logo{
        margin:20px auto 20px auto;
    }
    .displayNone{
        display: none;
    }
    .message{
        color: green;
    }
    .subtitle{
        font-size: 18px;
    }
    #qr-code{
        margin: 20px 0;
    }
    #qr-code img{
        max-width: 100%;
    }
    .IframeCont h5 {
        margin: 0;
        margin-bottom: 9px;
        color: #465567;
        font-family: \'Helvetica Neue, Bold\';
        font-size: 20px;
    }
    .stepsImageCont img {
        max-width: 45%;
    }
    .mobile-content {
        display: none;
    }
    .desktop-content {
        display: block;
    }
    @media(max-width: 768px) {
        .logo{
            margin:20px auto 50px auto;
        }

        .buttonCont {
            margin-bottom: 30px;
        }
        .open-app {
            background-color: #387ea5;
            color: #fff;
            border-radius: 7px;
            padding: 7px 46px;
            font-size: 16px;
            text-decoration: none;
        }
        .mobile-content {
            display: block;
        }
        .desktop-content {
            display: none;
        }
    }
</style>
<div id="suyool-iframe-QR" style="border: 1px solid #ccc; margin: 10px 0;max-width: 800px">
    <div class="mobile-content">
        <div align="center">
            <div class="logo">
                <img src="<?php echo plugins_url('suyool-payment/images/suyool-Logo.png'); ?>"/>
            </div>
            <div>
                <a href="<?php echo esc_html($appUrl)?>" id="deeplink-url"></a>
            </div>
            <div class="buttonCont">
                <a href="<?php echo esc_html($appUrl)?>" id="open_app" class="open-app">Open App</a>
            </div>
        </div>
    </div>
    <div class="desktop-content">
        <div align="center">
            <div class="logo">
                <img src="<?php echo plugins_url('suyool-payment/images/suyool-Logo.png'); ?>"/>
            </div>
            <div class="message" id="text-message">
                <?php echo esc_html($message) ?>
            </div>
            <div class="QR-cont <?php echo esc_html($displayQRCont) ?>">
                <div class="subtitle">
                    Scan this QR to complete the transaction
                </div>
                <div id="qr-code">
                    <img src="<?php echo esc_url($pictureURL)?>"/>
                </div>
            </div>
        </div>
        <div class="IframeCont" align="center">
            <h5>HOW TO PAY?</h5>
            <div class="stepsImageCont">
                <img src="<?php echo plugins_url('suyool-payment/images/Steps-to-scan-QR-syoul.png'); ?>"/>
            </div>
        </div>
    </div>
</div>

<script>
    function isMobileDevice() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }
    if (isMobileDevice()) {
        var deepLinkURL = document.querySelector("#deeplink-url").getAttribute("href");
        if (deepLinkURL != "") {
            setTimeout(function() {
                // Post the Deeplink URL to the parent page to trigger their location=url in order to open the sKash app
                // Because location=url doesnt work only on Safari from an iframe
                window.location.href = deepLinkURL;
            }, 1000);
        }
        document.getElementById("open_app").addEventListener("click", function(event) {
            event.preventDefault();
            window.location.href = deepLinkURL;
        });
    }
    jQuery(document).ready(function ($) {
        suyool_params.orderId = <?php echo wp_json_encode($order_id); ?>;
        setInterval(function () {
            $.ajax({
                url: suyool_params.ajaxurl,
                type: 'POST',
                data: {
                    'action': 'spfw_check_order_status',
                    'order_id': suyool_params.orderId,
                    'spfw_nonce': suyool_params.spfw_nonce
                },
                success: function (data) {
                    console.log(data);
                    if (data == 'completed' || data == 'processing') {
                        window.location.href = '<?php echo esc_url($dataUrl); ?>';
                    }
                    if (data == 'failed' || data == 'cancelled') {
                        window.location.href = '<?php echo esc_url($dataUrl); ?>';
                    }
                    if (data == 'cancelled') {
                        window.location.href = '<?php echo esc_url($checkout_page_url); ?>';
                    }
                }
            });
        }, 3000); // Check order status every 3 seconds
    });
</script>
