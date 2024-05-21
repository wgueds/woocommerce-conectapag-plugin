<?php

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
                'default' => __('Please remit your payment to the shop to allow for the delivery to be made', 'conectapag-payment-woo'),
                'desc_tip' => true,
                'description' => __('Add a new title for the ConectaPag Payments Gateway that customers will see when they are in the checkout page.', 'conectapag-payment-woo')
            ]
        ];
    }

    /*
     * We're processing the payments here, everything about it is in Step 5
     */
    public function process_payment($order_id)
    {
        global $woocommerce;

        $order = new WC_Order($order_id);

        // Mark as on-hold (we're awaiting the cheque)
        $order->update_status('on-hold', __('Awaiting ConectaPag Payment', 'conectapag-payment-woo'));

        // Remove cart
        $woocommerce->cart->empty_cart();

        // Return thankyou redirect
        return [
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        ];
    }
}