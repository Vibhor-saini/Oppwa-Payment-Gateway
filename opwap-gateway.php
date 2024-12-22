<?php
/*
Plugin Name: WooCommerce OPWAP Gateway
Description: Custom payment gateway for OPWAP in WooCommerce.
Version: 1.0
Author: Vibhor Saini
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Hook to load the payment gateway
add_action('plugins_loaded', 'init_opwap_gateway');

function init_opwap_gateway()
{
    class WC_Gateway_OPWAP extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $this->id = 'opwap';
            $this->has_fields = true; // Enable custom fields (used for card entry)
            $this->method_title = __('OPWAP', 'woocommerce');
            $this->method_description = __('Pay using OPWAP Payment Gateway.', 'woocommerce');

            // Load the settings
            $this->init_form_fields();
            $this->init_settings();

            // Define user settings
            $this->title = $this->get_option('title');
            $this->enabled = $this->get_option('enabled');

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        // Setup form fields for the admin panel
        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable OPWAP Payment', 'woocommerce'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This controls the title the user sees during checkout.', 'woocommerce'),
                    'default' => __('Pay via OPWAP  (Use Only For Subscription)', 'woocommerce'),
                    'desc_tip' => true,
                ),
            );
        }

        // Process the payment after form submission
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            // Prepare customer info for OPWAP
            $customer_info = array(
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'amount' => number_format($order->get_total(), 2, '.', ''),
                'country' => $order->get_billing_country(),
                'postcode' => $order->get_billing_postcode(),
                'street' => $order->get_billing_address_1(),
                'city' => $order->get_billing_city(),
                'currency' => get_woocommerce_currency(),
            );

            // Log the customer info (for debugging)
            error_log(print_r($customer_info, true));

            // Call OPWAP API and get the response
            $response = $this->request($customer_info);
            $responseData = json_decode($response, true);

            // Check if we got a checkout ID from OPWAP
            if (isset($responseData['id'])) {
                $checkoutId = $responseData['id'];

                $payment_page_id = get_page_by_path('payment-page'); // Replace with your page slug
                $redirect_url = get_permalink($payment_page_id->ID) . '?checkoutId=' . $checkoutId;

                return array(
                    'result' => 'success',
                    'redirect' => $redirect_url,
                );
            } else {
                wc_add_notice(__('Error in creating payment session: ' . $responseData['result']['description']), 'error');
                return;
            }
        }



        // Request to the OPWAP API
        private function request($customer_info)
        {
                $url = "";
                $data = http_build_query(array(
               'entityId' => '',
                'customer.givenName' => $customer_info['first_name'],
                'customer.surname' => $customer_info['last_name'],
                'customer.ip' => $_SERVER['REMOTE_ADDR'],
                'amount' => $customer_info['amount'],
                'billing.country' => $customer_info['country'],
                'customer.email' => $customer_info['email'],
                'currency' => $customer_info['currency'],
                'billing.postcode' => $customer_info['postcode'],
                'billing.street1' => $customer_info['street'],
                'billing.city' => $customer_info['city'],
                'createRegistration' => 'true',
                'paymentType' => 'DB',
                'standingInstruction.type' => "RECURRING",
                'standingInstruction.mode' => "INITIAL",
                'standingInstruction.source' => "CIT",
                'standingInstruction.recurringType' => "SUBSCRIPTION",
            ));

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer '
            ));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $responseData = curl_exec($ch);

            if (curl_errno($ch)) {
                return curl_error($ch);
            }
            curl_close($ch);
            return $responseData;
        }
    }
}

// Add the gateway to WooCommerce
add_filter('woocommerce_payment_gateways', 'add_opwap_gateway');

function add_opwap_gateway($methods)
{
    $methods[] = 'WC_Gateway_OPWAP'; // Add our gateway to WooCommerce
    return $methods;
}