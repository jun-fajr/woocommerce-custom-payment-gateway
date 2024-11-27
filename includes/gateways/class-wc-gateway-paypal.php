<?php
if (!defined('ABSPATH')) {
  exit;
}

class WC_Gateway_PayPal_Custom extends WC_Payment_Gateway
{
  private $client_id;
  private $client_secret;
  private $mode;

  public function __construct()
  {
    $this->id                 = 'paypal_custom';
    $this->method_title       = __('PayPal Custom', 'wc-custom-payment');
    $this->method_description = __('Custom PayPal integration.', 'wc-custom-payment');
    $this->supports           = ['products'];

    // Load settings
    $this->init_form_fields();
    $this->init_settings();

    // Assign settings to properties
    $this->title           = $this->get_option('title');
    $this->description     = $this->get_option('description');
    $this->client_id       = $this->get_option($this->get_option('mode') === 'sandbox' ? 'sandbox_client_id' : 'live_client_id');
    $this->client_secret   = $this->get_option($this->get_option('mode') === 'sandbox' ? 'sandbox_client_secret' : 'live_client_secret');
    $this->mode            = $this->get_option('mode');

    // Save settings when admin updates
    add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
  }


  public function init_form_fields()
  {
    $this->form_fields = [
      'enabled' => [
        'title'   => __('Enable/Disable', 'wc-custom-payment'),
        'type'    => 'checkbox',
        'label'   => __('Enable PayPal Custom Payment', 'wc-custom-payment'),
        'default' => 'yes',
      ],
      'title' => [
        'title'       => __('Title', 'wc-custom-payment'),
        'type'        => 'text',
        'description' => __('This controls the title displayed during checkout.', 'wc-custom-payment'),
        'default'     => __('PayPal Custom', 'wc-custom-payment'),
        'desc_tip'    => true,
      ],
      'description' => [
        'title'       => __('Description', 'wc-custom-payment'),
        'type'        => 'textarea',
        'description' => __('This controls the description displayed during checkout.', 'wc-custom-payment'),
        'default'     => __('Pay using PayPal.', 'wc-custom-payment'),
      ],
      'mode' => [
        'title'   => __('Mode', 'wc-custom-payment'),
        'type'    => 'select',
        'description' => __('Choose whether to use Sandbox (testing) or Live (production) mode.', 'wc-custom-payment'),
        'default' => 'sandbox',
        'desc_tip' => true,
        'options' => [
          'sandbox' => __('Sandbox', 'wc-custom-payment'),
          'live'    => __('Live', 'wc-custom-payment'),
        ],
      ],
      'sandbox_client_id' => [
        'title'       => __('Sandbox Client ID', 'wc-custom-payment'),
        'type'        => 'text',
        'description' => __('Your PayPal Sandbox Client ID.', 'wc-custom-payment'),
        'desc_tip'    => true,
      ],
      'sandbox_client_secret' => [
        'title'       => __('Sandbox Client Secret', 'wc-custom-payment'),
        'type'        => 'password',
        'description' => __('Your PayPal Sandbox Client Secret.', 'wc-custom-payment'),
        'desc_tip'    => true,
      ],
      'live_client_id' => [
        'title'       => __('Live Client ID', 'wc-custom-payment'),
        'type'        => 'text',
        'description' => __('Your PayPal Live Client ID.', 'wc-custom-payment'),
        'desc_tip'    => true,
      ],
      'live_client_secret' => [
        'title'       => __('Live Client Secret', 'wc-custom-payment'),
        'type'        => 'password',
        'description' => __('Your PayPal Live Client Secret.', 'wc-custom-payment'),
        'desc_tip'    => true,
      ],
    ];
  }

  public function payment_fields()
  {
    // Tampilkan deskripsi metode pembayaran jika ada
    if ($this->description) {
      echo wpautop(wp_kses_post($this->description));
    }

    // Tambahkan form custom untuk frontend jika diperlukan
?>
    <div id="paypal-custom-checkout-form">
      <p><?php _e('You will be redirected to PayPal to complete your purchase.', 'wc-custom-payment'); ?></p>
    </div>
<?php
  }
  public function process_payment($order_id)
  {
    $order = wc_get_order($order_id);

    // Kirim data pesanan ke PayPal dan dapatkan URL redirect
    $paypal_url = $this->get_paypal_redirect_url($order);

    if (!$paypal_url) {
      wc_add_notice(__('There was an error connecting to PayPal. Please try again.', 'wc-custom-payment'), 'error');
      return ['result' => 'failure'];
    }

    // Tandai pesanan sebagai "On-hold" sementara menunggu pembayaran
    $order->update_status('on-hold', __('Waiting for PayPal payment', 'wc-custom-payment'));

    // Kembalikan hasil sukses dan redirect ke PayPal
    return [
      'result'   => 'success',
      'redirect' => $paypal_url,
    ];
  }

  // Contoh fungsi untuk mendapatkan URL redirect PayPal
  private function get_paypal_redirect_url($order)
  {
    // Pilih URL dan credential berdasarkan mode
    $mode = $this->get_option('mode'); // 'sandbox' atau 'live'
    $client_id = $mode === 'sandbox' ? $this->get_option('sandbox_client_id') : $this->get_option('live_client_id');
    $client_secret = $mode === 'sandbox' ? $this->get_option('sandbox_client_secret') : $this->get_option('live_client_secret');
    $api_url = $mode === 'sandbox'
      ? "https://api-m.sandbox.paypal.com/v2/checkout/orders"
      : "https://api-m.paypal.com/v2/checkout/orders";

    // Buat body request
    $body = [
      "intent" => "CAPTURE",
      "purchase_units" => [
        [
          "amount" => [
            "value" => $order->get_total(),
            "currency_code" => get_woocommerce_currency(),
          ],
        ],
      ],
    ];

    // Autentikasi ke PayPal untuk mendapatkan token akses
    $auth_response = wp_remote_post($mode === 'sandbox'
      ? "https://api-m.sandbox.paypal.com/v1/oauth2/token"
      : "https://api-m.paypal.com/v1/oauth2/token", [
      'body' => "grant_type=client_credentials",
      'headers' => [
        'Authorization' => 'Basic ' . base64_encode("$client_id:$client_secret"),
        'Content-Type'  => 'application/x-www-form-urlencoded',
      ],
    ]);

    if (is_wp_error($auth_response)) {
      return false;
    }

    $auth_result = json_decode(wp_remote_retrieve_body($auth_response), true);
    $access_token = $auth_result['access_token'] ?? null;

    if (!$access_token) {
      return false;
    }

    // Kirim order request ke PayPal
    $order_response = wp_remote_post($api_url, [
      'body'    => json_encode($body),
      'headers' => [
        'Authorization' => "Bearer $access_token",
        'Content-Type'  => 'application/json',
      ],
    ]);

    if (is_wp_error($order_response)) {
      return false;
    }

    $result = json_decode(wp_remote_retrieve_body($order_response), true);

    // Cari URL redirect PayPal
    foreach ($result['links'] as $link) {
      if ($link['rel'] === 'approve') {
        return $link['href'];
      }
    }

    return false;
  }
}
