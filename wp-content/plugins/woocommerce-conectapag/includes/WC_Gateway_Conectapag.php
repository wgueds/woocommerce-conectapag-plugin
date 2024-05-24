<?php

require_once PLUGIN_PATH_GATEWAY . 'includes/ConectaPagHelper.php';

class WC_Gateway_Conectapag extends WC_Payment_Gateway
{
    /**
     * Class constructor, more about it in Step 3
     */
    public function __construct()
    {
        $this->id = 'conectapag_payment';
        $this->icon = apply_filters('woocommerce_conectapag_icon', plugins_url('../assets/icon.png', __FILE__));
        $this->has_fields = false;
        $this->method_title = __('ConectaPag Payment', 'conectapag-payment-woo');
        $this->method_description = __(
            'This plugin allows you to add payment methods from the ConectaPag gateway.',
            'conectapag-payment-woo'
        );

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);

        // empty car when change status
        add_action('woocommerce_order_status_pending', 'empty_cart_on_order_status_change', 10, 2);
        add_action('woocommerce_order_status_processing', 'empty_cart_on_order_status_change', 10, 2);

        if (!defined('GATEWAY_URL_API'))
            require_once(PLUGIN_PATH_GATEWAY . $this->get_option('environment') . '_constants.php');
    }

    function empty_cart_on_order_status_change($order_id, $order)
    {
        // Checks whether the order has been paid (in case of asynchronous processing)
        if (in_array($order->get_status(), array('pending', 'processing'))) {
            // Gets WooCommerce Cart
            $woocommerce = WC();

            // Empty the cart
            $woocommerce->cart->empty_cart();
        }
    }

    /**
     * Plugin options, we deal with it in Step 3 too
     */
    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'conectapag-payment-woo'),
                'type' => 'checkbox',
                'label' => __('Enable or disable ConectaPag Payments', 'conectapag-payment-woo'),
                'default' => 'no'
            ],
            'title' => [
                'title' => __('ConectaPag Payments Gateway', 'conectapag-payment-woo'),
                'type' => 'text',
                'default' => __('ConectaPag Payments Gateway', 'conectapag-payment-woo'),
                'desc_tip' => true,
                'description' => __('Add a new title for the ConectaPag Payments Gateway that customers will see when they are in the checkout page.', 'conectapag-payment-woo')
            ],
            'description' => [
                'title' => __('ConectaPag Payments Gateway Description', 'conectapag-payment-woo'),
                'type' => 'textarea',
                'default' => __('Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.', 'conectapag-payment-woo'),
                'desc_tip' => true,
                'description' => __('Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.', 'conectapag-payment-woo')
            ],
            'environment' => [
                'title' => __('Staging/Production', 'conectapag-payment-woo'),
                'type' => 'select',
                'options' => [
                    'staging' => __('Staging', 'conectapag-payment-woo'),
                    'production' => __('Production', 'conectapag-payment-woo'),
                ],
                'description' => __('Environment in which payment will be processed.', 'conectapag-payment-woo')
            ],
            'api_client_id' => [
                'title' => __('Client ID', 'conectapag-payment-woo'),
                'type' => 'textarea',
                'default' => '',
                'desc_tip' => true,
                'description' => __('Client ID provided by Payment Gateway.', 'conectapag-payment-woo')
            ],
            'api_secret_id' => [
                'title' => __('Secret ID', 'conectapag-payment-woo'),
                'type' => 'textarea',
                'default' => '',
                'desc_tip' => true,
                'description' => __('Secret ID provided by Payment Gateway.', 'conectapag-payment-woo')
            ]
        ];
    }

    /*
     * We're processing the payments here, everything about it is in Step 5
     */
    public function process_payment($order_id)
    {
        global $woocommerce;

        $order = wc_get_order($order_id);
        $qr_code = $order->get_meta('_wc_order_payment_qrcode');

        if (!$qr_code) {
            $response = $this->send_payment_gateway($order);

            if (!$response['status']) {
                wc_get_logger()->error('Erro ao processar o pagamento: ' . $response['error'], ['source' => 'conectapag-payment-woo']);
                wc_add_notice(__($response['error'], 'conectapag-payment-woo'), 'error');
                $order->update_status('pending', __('Awaiting payment via QR Code', 'conectapag-payment-woo'));

                return [
                    'result' => 'fail',
                    'redirect' => '',
                ];
            }

            // Mark the order as pending
            $order->update_status('pendding', __('Awaiting payment via QR Code', 'conectapag-payment-woo'));

            // Save the QR Code as order metadata
            $order->update_meta_data('_wc_order_payment_qrcode', $response['qr_code']);
            $order->update_meta_data('_wc_order_payment_txid', $response['external_id']);
            $order->save();

            if (function_exists('WC'))
                WC()->cart->empty_cart();
        }

        // Return success and redirect to receipt page
        return [
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true),
        ];
    }

    private function send_payment_gateway($order)
    {
        $order_id = $order->get_id();
        $amount = $order->get_total();
        $client = new ConectaPagHelper($this->get_option('api_client_id'), $this->get_option('api_secret_id'));
        $hashPix = $client->getHashByKey('PIX');

        $payload = [
            'amount' => floatval($amount) * 100,
            'payment_method' => $hashPix,
            'client' => [
                'identifier' => $order_id,
                'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'document' => null,
            ]
        ];

        $dict = $client->sendTransaction($payload);

        if (!$dict['success']) {
            error_log(json_encode($dict));
            return ['status' => false, 'error' => $dict['error']];
        }

        return [
            'status' => true,
            'qr_code' => $dict['data']['pixCopiaECola'],
            'external_id' => $dict['data']['txid']
        ];
    }

    public function receipt_page($order_id)
    {
        echo '<p>' . __('Scan the QR Code below to make payment:', 'conectapag-payment-woo') . '</p>';
        echo '<img src="' . $this->generate_qr_code($order_id) . '" alt="' . esc_attr__('QR Code de Pagamento', 'conectapag-payment-woo') . '" />';
    }

    private function generate_qr_code(int $order_id)
    {
        $order = wc_get_order($order_id);
        $qr_code = $order->get_meta('_wc_order_payment_qrcode');

        // Path to save QR Code image
        $upload_dir = wp_upload_dir();
        $qr_code_path = $upload_dir['basedir'] . '/qrcodes/';
        $qr_code_url = $upload_dir['baseurl'] . '/qrcodes/';

        if (!file_exists($qr_code_path)) {
            mkdir($qr_code_path, 0755, true);
        }

        // QR Code file name
        $qr_code_file = "{$qr_code_path}order-{$order_id}.png";
        $qr_code_url .= "order-{$order_id}.png";

        // Generate the QR Code
        QRcode::png($qr_code, $qr_code_file, QR_ECLEVEL_L, 10);

        return $qr_code_url;
    }
}
