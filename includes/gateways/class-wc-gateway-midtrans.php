<?php
if (!defined('ABSPATH')) {
  exit;
}

class WC_Gateway_Midtrans_Custom extends WC_Payment_Gateway
{
  private $server_key;
  private $merchant_id;
  private $client_key;
  // private $mode;

  public function __construct()
  {
    $this->id = 'midtrans_custom';
    $this->method_title = __('Midtrans Custom', 'wc-custom-payment');
    $this->method_description = __('Gateway Midtrans Custom.', 'wc-custom-payment');

    // Load settings
    $this->init_form_fields();
    $this->init_settings();

    $this->title = $this->get_option('title');
    $this->description = $this->get_option('description');
    $this->server_key = $this->get_option('server_key');
    $this->merchant_id = $this->get_option('merchant_id');
    $this->client_key = $this->get_option('client_key');

    // $mode = $this->get_option('mode');  // Mengambil mode (sandbox atau live) dari pengaturan

    // Save admin options
    add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
  }

  public function init_form_fields()
  {
    $this->form_fields = [
      'enabled' => [
        'title' => __('Enable/Disable', 'wc-custom-payment'),
        'type' => 'checkbox',
        'label' => __('Enable Midtrans Custom', 'wc-custom-payment'),
        'default' => 'yes',
      ],
      'title' => [
        'title' => __('Title', 'wc-custom-payment'),
        'type' => 'text',
        'description' => __('Title for Midtrans payment', 'wc-custom-payment'),
        'default' => __('Midtrans', 'wc-custom-payment'),
      ],
      'description' => [
        'title' => __('Description', 'wc-custom-payment'),
        'type' => 'textarea',
        'description' => __('Payment description seen by customer.', 'wc-custom-payment'),
        'default' => __('Pay via Midtrans.', 'wc-custom-payment'),
      ],
      'server_key' => [
        'title' => __('Server Key', 'wc-custom-payment'),
        'type' => 'text',
        'description' => __('Midtrans Server Key.', 'wc-custom-payment'),
        'default' => '',
      ],
      'merchant_id' => [
        'title' => __('Merchant ID', 'wc-custom-payment'),
        'type' => 'text',
        'description' => __('Midtrans Merchant ID', 'wc-custom-payment'),
        'default' => '',
      ],
      'client_key' => [
        'title' => __('Client Key', 'wc-custom-payment'),
        'type' => 'text',
        'description' => __('Midtrans Client Key.', 'wc-custom-payment'),
        'default' => '',
      ],
      'mode' => [
        'title'   => __('Mode', 'wc-custom-payment'),
        'type'    => 'select',
        'description' => __('Select the mode for payment (Sandbox or Live).', 'wc-custom-payment'),
        'options' => [
          'sandbox' => __('Sandbox', 'wc-custom-payment'),
          'live'    => __('Live', 'wc-custom-payment'),
        ],
        'default' => 'sandbox',
      ],
    ];
  }

  // public function process_payment($order_id)
  // {
  //   $order = wc_get_order($order_id);
  //   $data = [
  //     'payment_type' => 'credit_card',
  //     'transaction_details' => [
  //       'order_id' => $order_id,
  //       'gross_amount' => $order->get_total(),
  //     ],
  //     'credit_card' => [
  //       'secure' => true,
  //     ],
  //     'customer_details' => [
  //       'first_name' => $order->get_billing_first_name(),
  //       'last_name' => $order->get_billing_last_name(),
  //       'email' => $order->get_billing_email(),
  //       'phone' => $order->get_billing_phone(),
  //     ],
  //   ];
  //   error_log('Request Data: ' . print_r($data, true));
  //   $token_data = $this->get_midtrans_token($data);

  //   if (is_wp_error($token_data)) {
  //     wc_add_notice(__('Payment error: ' . $token_data->get_error_message(), 'wc-custom-payment'), 'error');
  //     return;
  //   }

  //   wp_redirect($token_data['redirect_url']);
  //   exit;
  // }
  public function process_payment($order_id)
  {
    $order = wc_get_order($order_id);
    $data = [
      'payment_type' => 'credit_card',
      'transaction_details' => [
        'order_id' => $order_id,
        'gross_amount' => $order->get_total(),
      ],
      'credit_card' => [
        'secure' => true,
      ],
      'customer_details' => [
        'first_name' => $order->get_billing_first_name(),
        'last_name' => $order->get_billing_last_name(),
        'email' => $order->get_billing_email(),
        'phone' => $order->get_billing_phone(),
      ],
    ];

    // Log request data for debugging
    error_log('Request Data: ' . print_r($data, true));

    // Mendapatkan token dan redirect_url dari Midtrans
    $token_data = $this->get_midtrans_token($data);

    // Jika terjadi error, tampilkan pesan error
    if (is_wp_error($token_data)) {
      wc_add_notice(__('Payment error: ' . $token_data->get_error_message(), 'wc-custom-payment'), 'error');
      return;
    }

    // Pastikan redirect_url ada dalam respons
    if (isset($token_data['redirect_url'])) {
      wp_redirect($token_data['redirect_url']);
      exit;
    } else {
      // Jika tidak ada redirect_url, beri notifikasi error
      error_log('Midtrans response did not contain a redirect_url');
      wc_add_notice(__('Payment error: No redirect URL received from Midtrans.', 'wc-custom-payment'), 'error');
      return;
    }
  }

  public function get_midtrans_token($data)
  {
    $server_key = $this->server_key;  // Ambil server key dari pengaturan

    $mode = $this->get_option('mode');
    $url = ($mode === 'sandbox') ?
      'https://app.sandbox.midtrans.com/snap/v1/transactions' :
      'https://app.midtrans.com/snap/v1/transactions';

    // Membuat header Authorization dengan menggunakan Server Key
    $auth = base64_encode($server_key . ':');

    // Mengirim permintaan POST ke API Midtrans untuk mendapatkan token
    $response = wp_remote_post($url, [
      'method'    => 'POST',
      'body'      => json_encode($data),  // Data transaksi yang dikirimkan
      'headers'   => [
        'Authorization' => 'Basic ' . $auth,
        'Content-Type'  => 'application/json',
      ],
    ]);

    // Periksa respons dari API Midtrans
    if (is_wp_error($response)) {
      error_log('Midtrans API request error: ' . $response->get_error_message());
      return new WP_Error('midtrans_api_error', __('Failed to connect to Midtrans API.', 'wc-custom-payment'));
    }

    // Ambil body respons
    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);

    // Log respons untuk debugging
    error_log('Midtrans Response: ' . print_r($response_data, true));

    // Periksa apakah response sukses
    if (isset($response_data['status_code']) && $response_data['status_code'] === '200') {
      // Dapatkan token dan URL redirect yang diberikan Midtrans
      $token = $response_data['token_id'] ?? null;
      $redirect_url = $response_data['redirect_url'] ?? null;

      if ($token && $redirect_url) {
        // Jika token dan redirect_url ada, kembalikan hasil sukses
        return [
          'result'   => 'success',
          'redirect' => $redirect_url,  // redirect_url dari Midtrans
        ];
      } else {
        // Jika tidak ada token atau redirect_url
        wc_add_notice(__('Payment error: ', 'multi-payment-gateway') . __('No redirect URL or token received from Midtrans.', 'multi-payment-gateway'), 'error');
        return ['result' => 'failure'];
      }
    } else {
      // Jika status code bukan 200 atau ada error, log dan tampilkan pesan error
      error_log('Midtrans error: ' . print_r($response_data, true)); // Log error detail
      wc_add_notice(__('Payment error: ', 'multi-payment-gateway') . ($response_data['status_message'] ?? 'Unknown error'), 'error');
      return ['result' => 'failure'];
    }
  }





  // public function get_midtrans_token($data)
  // {
  //   $server_key = $this->server_key;  // Ambil server key dari pengaturan

  //   $mode = $this->get_option('mode');
  //   $url = ($mode === 'sandbox') ?
  //     'https://app.sandbox.midtrans.com/snap/v1/transactions' :
  //     'https://app.midtrans.com/snap/v1/transactions';

  //   // Membuat header Authorization dengan menggunakan Server Key
  //   $auth = base64_encode($server_key . ':');

  //   // Mengirim permintaan POST ke API Midtrans untuk mendapatkan token
  //   $response = wp_remote_post($url, [
  //     'method'    => 'POST',
  //     'body'      => json_encode($data),  // Data transaksi yang dikirimkan
  //     'headers'   => [
  //       'Authorization' => 'Basic ' . $auth,
  //       'Content-Type'  => 'application/json',
  //     ],
  //   ]);

  //   // Periksa respons dari API Midtrans
  //   if (is_wp_error($response)) {
  //     error_log('Midtrans API request error: ' . $response->get_error_message());
  //     return new WP_Error('midtrans_api_error', __('Failed to connect to Midtrans API.', 'wc-custom-payment'));
  //   }

  //   // Ambil body respons
  //   $response_body = wp_remote_retrieve_body($response);
  //   $response_data = json_decode($response_body, true);
  //   if (isset($response_data['status_code']) && $response_data['status_code'] === '200') {
  //     return [
  //       'token' => $response_data['token_id'],
  //       'redirect_url' => $response_data['redirect_url'],
  //     ];
  //   } else {
  //     error_log('Midtrans error: ' . print_r($response_data, true)); // Log error detail
  //     return new WP_Error('midtrans_token_error', __('Failed to get payment token from Midtrans.', 'wc-custom-payment'));
  //   }
  // }

  // public function get_midtrans_token($data)
  // {
  //   $server_key = $this->server_key;  // Server key dari Midtrans

  //   $mode = $this->get_option('mode');

  //   $url = ($mode === 'sandbox') ?
  //     'https://app.sandbox.midtrans.com/snap/v1/transactions' :
  //     'https://app.midtrans.com/snap/v1/transactions';

  //   // Membuat header Authorization dengan menggunakan Server Key
  //   $auth = base64_encode($server_key . ':');

  //   // Mengirim permintaan POST ke API Midtrans untuk mendapatkan token
  //   $response = wp_remote_post($url, [
  //     'method'    => 'POST',
  //     'body'      => json_encode($data),  // Data transaksi yang dikirimkan
  //     'headers'   => [
  //       'Authorization' => 'Basic ' . $auth,
  //       'Content-Type'  => 'application/json',
  //     ],
  //   ]);

  //   // Periksa respons dari API Midtrans
  //   if (is_wp_error($response)) {
  //     error_log('Midtrans API request error: ' . $response->get_error_message());
  //     return new WP_Error('midtrans_api_error', __('Failed to connect to Midtrans API.', 'wc-custom-payment'));
  //   }

  //   // Ambil body respons
  //   $response_body = wp_remote_retrieve_body($response);
  //   $response_data = json_decode($response_body, true);

  //   // Cek apakah token berhasil diterima
  //   if (isset($response_data['token_id'])) {
  //     return [
  //       'token' => $response_data['token_id'],
  //       'redirect_url' => $response_data['redirect_url'],
  //     ];
  //   } else {
  //     error_log('Midtrans API response error: ' . print_r($response_data, true));
  //     return new WP_Error('midtrans_token_error', __('Failed to get payment token from Midtrans.', 'wc-custom-payment'));
  //   }
  // }
}
