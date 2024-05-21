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

define('PLUGIN_PATH_CONECTAPAG', plugin_dir_path(__FILE__));

// Verificar se WooCommerce está ativo.
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))))
    return;

add_action('plugins_loaded', 'init_conectapag');

function init_conectapag()
{
    if (class_exists('WC_Payment_Gateway'))
        require_once (PLUGIN_PATH_CONECTAPAG . 'includes/WC_Gateway_Conectapag.php');
}

add_filter('woocommerce_payment_gateways', 'add_wc_gateway_conectapag');

function add_wc_gateway_conectapag($methods)
{
    $methods[] = 'WC_Gateway_Conectapag';
    return $methods;
}






