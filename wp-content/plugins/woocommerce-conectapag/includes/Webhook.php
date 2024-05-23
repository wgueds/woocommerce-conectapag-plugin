<?php

// Registrar o endpoint do webhook
add_action('init', 'register_gateway_webhook_endpoint');

function register_gateway_webhook_endpoint()
{
    add_rewrite_rule('^gateway-webhook/?', 'index.php?gateway-webhook=1', 'top');
}

add_filter('query_vars', 'add_gateway_webhook_query_var');

function add_gateway_webhook_query_var($vars)
{
    $vars[] = 'gateway-webhook';
    return $vars;
}

add_action('template_redirect', 'handle_gateway_webhook');

function handle_gateway_webhook()
{
    if (get_query_var('gateway-webhook') == 1) {
        // Processar o webhook
        $payload = file_get_contents('php://input');
        $response = json_decode($payload);

        error_log('WEBHOOK');
        error_log(json_encode($response));

        if (!$response) {
            error_log('no information in webhook');
            exit;
        }

        $data = $response->data;

        $orders = wc_get_orders([
            'meta_key' => '_wc_order_payment_txid',
            'meta_value' => $data->reference_code,
            'meta_compare' => '=',
            'limit' => 1
        ]);

        if ($orders) {
            $order = reset($orders);

            switch ($data->status) {
                case 'paid':
                    $order->payment_complete();
                    $order->add_order_note(__('Pagamento confirmado via Pix.', 'meu-plugin'));
                    break;
                case 'canceled':
                case 'error':
                    wc_get_logger()->error(__('An error occurred in the payment gateway', 'conectapag-payment-woo'), ['source' => 'conectapag-payment-woo']);
                    $order->update_status('cancelled');
                    break;
            }
        }

        // Responder ao gateway de pagamento
        wp_send_json(['status' => 'success']);
        exit;
    }
}
