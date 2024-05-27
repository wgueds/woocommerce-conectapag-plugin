<?php
/*
 * Plugin Name: ConectaPag WooCommerce 
 * Plugin URI: http://www.suscitar.com.br
 * Description: This plugin allows you to add payment methods from the ConectaPag gateway.
 * Version: 1.0
 * Author Name: Wesley Guedes
 * Author URI: http://www.suscitar.com.br
 * License: 0.1.0
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * text-domain: conectapag-payment-woo
 */

/**
 * https://www.youtube.com/watch?v=JbaBdyz48z4&list=PLNqG1qGUllk21IES6ZJ2WkX1BcbPQF4-7
 * https://developer.woocommerce.com/docs/woocommerce-payment-gateway-api/
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('PLUGIN_PATH_GATEWAY', plugin_dir_path(__FILE__));

// Incluir a biblioteca phpqrcode.
require_once PLUGIN_PATH_GATEWAY . 'includes/phpqrcode/qrlib.php';

// Verificar se WooCommerce está ativo.
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))))
    return;

/**
 * Init gatewayy
 */
add_action('plugins_loaded', 'init_conectapag');

function init_conectapag()
{
    if (class_exists('WC_Payment_Gateway')) {
        require_once (PLUGIN_PATH_GATEWAY . 'includes/WC_Gateway_Conectapag.php');
    }
}

/**
 * Add WC_Gateway_Conectapag
 */
add_filter('woocommerce_payment_gateways', 'add_wc_gateway_conectapag');

function add_wc_gateway_conectapag($methods)
{
    $methods[] = 'WC_Gateway_Conectapag';
    return $methods;
}

// /**
//  * Register WP-Cron
//  */
// register_activation_hook(PLUGIN_PATH_GATEWAY, 'conectapag_cron_job');

// function conectapag_cron_job()
// {
//     if (!wp_next_scheduled('conectapag_cron_job_hook')) {
//         wp_schedule_event(time(), 'hourly', 'conectapag_cron_job_hook');
//     }
// }

// // Função que será executada pela tarefa agendada
// add_action('conectapag_cron_job_hook', 'conectapag_cron_function');

// function conectapag_cron_function()
// {
//     $gateway = new WC_Gateway_Conectapag();

//     if (!empty($gateway->get_option('api_client_id')) && !empty($gateway->get_option('api_secret_id'))) {
//         $client = new ConectaPagHelper($gateway->get_option('api_client_id'), $gateway->get_option('api_secret_id'));
//         $client->getPaymentMethods();
//     }
// }

/**
 * add webhook route
 */
require_once PLUGIN_PATH_GATEWAY . 'includes/Webhook.php';
