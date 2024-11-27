<?php

/**
 * Plugin Name: WooCommerce Custom Payment Gateway
 * Description: Plugin untuk menambahkan gateway pembayaran custom seperti PayPal dan Midtrans.
 * Version: 1.0.0
 * Author: Yang Mulia
 * Text Domain: wc-custom-payment
 */

// Keluar jika diakses langsung
if (!defined('ABSPATH')) {
  exit;
}

// Pastikan WooCommerce aktif
add_action('plugins_loaded', 'wc_custom_gateway_init', 11);

function wc_custom_gateway_init()
{
  if (!class_exists('WC_Payment_Gateway')) {
    return;
  }

  // Include class utama
  require_once plugin_dir_path(__FILE__) . 'includes/class-wc-custom-gateway.php';
  require_once plugin_dir_path(__FILE__) . 'includes/gateways/class-wc-gateway-paypal.php';
  require_once plugin_dir_path(__FILE__) . 'includes/gateways/class-wc-gateway-midtrans.php';

  // Tambahkan gateway ke WooCommerce
  add_filter('woocommerce_payment_gateways', function ($gateways) {
    $gateways[] = 'WC_Gateway_PayPal_Custom';
    $gateways[] = 'WC_Gateway_Midtrans_Custom';
    return $gateways;
  });
}
