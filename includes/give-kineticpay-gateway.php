<?php

if (!defined('ABSPATH')) {
    exit;
}

class Give_Kineticpay_Gateway
{
    private static $instance;

    const QUERY_VAR = 'kineticpay_givewp_return';
    const LISTENER_PASSPHRASE = 'kineticpay_givewp_listener_passphrase';

    private function __construct()
    {
        add_action( 'init', array($this, 'start_return_listener') );
        add_action( 'init', array($this, 'return_listener') );
        add_action('give_gateway_kineticpay', array($this, 'process_payment'));
		add_action( 'give_kineticpay_cc_form', '__return_false' );
        add_action( 'give_kineticpay_cc_form', array($this, 'give_kineticpay_cc_form'), 10, 3 );
        add_filter('give_enabled_payment_gateways', array($this, 'give_filter_kineticpay_gateway'), 10, 2);
        add_filter('give_payment_confirm_kineticpay', array($this, 'give_kineticpay_success_page_content'));
    }

    public static function get_instance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function give_filter_kineticpay_gateway($gateway_list, $form_id)
    {
        if ((false === strpos($_SERVER['REQUEST_URI'], '/wp-admin/post-new.php?post_type=give_forms'))
            && $form_id
            && !give_is_setting_enabled(give_get_meta($form_id, 'kineticpay_customize_kineticpay_donations', true, 'global'), array('enabled', 'global'))
        ) {
            unset($gateway_list['kineticpay']);
        }
        return $gateway_list;
    }

    private function create_payment($purchase_data)
    {

        $form_id = intval($purchase_data['post_data']['give-form-id']);
        $price_id = isset($purchase_data['post_data']['give-price-id']) ? $purchase_data['post_data']['give-price-id'] : '';

        // Collect payment data.
        $insert_payment_data = array(
            'price' => $purchase_data['price'],
            'give_form_title' => $purchase_data['post_data']['give-form-title'],
            'give_form_id' => $form_id,
            'give_price_id' => $price_id,
            'date' => $purchase_data['date'],
            'user_email' => $purchase_data['user_email'],
            'purchase_key' => $purchase_data['purchase_key'],
            'currency' => give_get_currency($form_id, $purchase_data),
            'user_info' => $purchase_data['user_info'],
            'status' => 'pending',
            'gateway' => 'kineticpay',
        );

        /**
         * Filter the payment params.
         *
         * @since 3.0.2
         *
         * @param array $insert_payment_data
         */
        $insert_payment_data = apply_filters('give_create_payment', $insert_payment_data);

        // Record the pending payment.
        return give_insert_payment($insert_payment_data);
    }

    private function get_kineticpay($purchase_data)
    {

        $form_id = intval($purchase_data['post_data']['give-form-id']);

        $custom_donation = give_get_meta($form_id, 'kineticpay_customize_kineticpay_donations', true, 'global');
        $status = give_is_setting_enabled($custom_donation, 'enabled');

        if ($status) {
            return array(
                'api_key' => give_get_meta($form_id, 'kineticpay_api_key', true),
                'description' => give_get_meta($form_id, 'kineticpay_description', true, true),
            );
        }
        return array(
            'api_key' => give_get_option('kineticpay_api_key'),
            'description' => give_get_option('kineticpay_description', true),
        );
    }

    public static function get_listener_url1($payment_id)
    {
        $arg = array(
            'kineticpay' => 'yes',
			'paymentid' => $payment_id,
        );
        return add_query_arg($arg, site_url('/'));
    }
	
	public static function get_listener_url($payment_id)
    {
        $arg = array(
            'kineticpay' => 'success',
			'paymentid' => $payment_id,
        );
        return add_query_arg($arg, site_url('/'));
    }

    public function process_payment($posted_data)
    {
        // Validate nonce.
        give_validate_nonce($posted_data['gateway_nonce'], 'give-gateway');

        $form_id         = intval( $posted_data['post_data']['give-form-id'] );
		$price_id        = ! empty( $posted_data['post_data']['give-price-id'] ) ? $posted_data['post_data']['give-price-id'] : 0;
		$donation_amount = ! empty( $posted_data['price'] ) ? $posted_data['price'] : 0;
		
		$name = $posted_data['user_info']['first_name'] . ' ' . $posted_data['user_info']['last_name'];
		
		$user_email = $posted_data['user_email'];
		$user_name = empty($name) ? $user_email : trim($name);
		$user_mobile = isset($posted_data['mobile']) ? $posted_data['mobile'] : '012345678901';
		$user_bank = isset($_POST['bank_id']) ? sanitize_text_field($_POST['bank_id']) : '';
		
		// Setup the payment details.
		$donation_data = array(
			'price'           => $donation_amount,
			'give_form_title' => $posted_data['post_data']['give-form-title'],
			'give_form_id'    => $form_id,
			'give_price_id'   => $price_id,
			'date'            => $posted_data['date'],
			'user_email'      => $posted_data['user_email'],
			'purchase_key'    => $posted_data['purchase_key'],
			'currency'        => give_get_currency( $form_id ),
			'user_info'       => $posted_data['user_info'],
			'status'          => 'pending',
			'gateway'         => 'kineticpay',
		);

		// Record the pending donation.
		$donation_id = give_insert_payment( $donation_data );

		if ( ! $donation_id ) {

			// Record Gateway Error as Pending Donation in Give is not created.
			give_record_gateway_error(
				__( 'Kineticpay Error', 'give-kineticpay' ),
				sprintf(
				/* translators: %s Exception error message. */
					__( 'Unable to create a pending donation with Give.', 'give-kineticpay' )
				)
			);

			// Send user back to checkout.
			give_send_back_to_checkout( '?payment-mode=kineticpay' );
			return;
		}
		
		give_update_meta($donation_id, 'user_name', $user_name);
		give_update_meta($donation_id, 'user_mobile', $user_mobile);
		give_update_meta($donation_id, 'user_bank', $user_bank);
		$frt_url_kineticpay = self::get_listener_url1($donation_id);
		echo "<script>window.top.location.href='".$frt_url_kineticpay."';</script>";//wp_redirect($frt_url_kineticpay);
        exit;
    }

    public function give_kineticpay_cc_form($form_id)
    {
		$url = site_url() . '/?action="kineticpay_action"';
		$imagesrc = GIVE_RLTKNPAY_PLUGIN_URL . 'assets/images/kineticpay.png';
		$button_lang = __("Pay with kineticpay", "give-kineticpay");		
		$title = __('Pay With Kineticpay', 'give-kineticpay');
		$banks = '<style>.kineticpay-title{align-items: center;display: flex;}.kineticpay-logo{width: 100px;margin-left: 20px;}#bank_id{height: 50px;padding: 10px;border: 1px solid #253d80;}#bank_id option{font-size: 14px;font-weight: 500;color: #253d80;padding: 20px;}</style><div class="customs-select" style="margin-top: 10px; margin-bottom: 20px;">
		<h3 class="kineticpay-title">'.$title.'<img class="rounded kineticpay-logo" src="http://localhost/dev/wp-content/plugins/kn/assets/images/kineticpay.png"></h3>
		<label style="font-weight: 600;">Select Bank:</label>
		<select id="bank_id" name="bank_id" required>
			<option value="">Select Your Bank</option>
			<option value="ABMB0212">Alliance Bank Malaysia Berhad</option>
			<option value="ABB0233">Affin Bank Berhad</option>
			<option value="AMBB0209">Ambank (M) Berhad</option>
			<option value="BCBB0235">CIMB Bank Berhad</option>
			<option value="BIMB0340">Bank Islam Malaysia Berhad</option>
			<option value="BKRM0602">Bank Kerjasama Rakyat Malaysia Berhad</option>
			<option value="BMMB0341">Bank Muamalat Malaysia Berhad</option>
			<option value="BSN0601">Bank Simpanan Nasional</option>
			<option value="CIT0219">Citibank Berhad</option>
			<option value="HLB0224">Hong Leong Bank Berhad</option>
			<option value="HSBC0223">HSBC Bank Malaysia Berhad</option>
			<option value="KFH0346">Kuwait Finance House</option>
			<option value="MB2U0227">Maybank2u / Malayan Banking Berhad</option>
			<option value="MBB0228">Maybank2E / Malayan Banking Berhad E</option>
			<option value="OCBC0229">OCBC Bank (Malaysia) Berhad</option>
			<option value="PBB0233">Public Bank Berhad</option>
			<option value="RHB0218">RHB Bank Berhad</option>
			<option value="SCB0216">Standard Chartered Bank Malaysia Berhad</option>
			<option value="UOB0226">United Overseas Bank (Malaysia) Berhad</option>
		</select></div>';
		echo $banks;
    }

    private function publish_payment($payment_id, $data)
    {
        if ('publish' !== get_post_status($payment_id)) {
            give_update_payment_status($payment_id, 'publish');
            give_insert_payment_note($payment_id, "Bill ID: {$data['id']}.");
        }
    }
	
	
	public function start_return_listener()
    {
        if (!isset($_GET['paymentid']) && !isset($_GET['kineticpay'])) {
            return;
        }
		$get_kineticpay = sanitize_text_field($_GET['kineticpay']);
		if ($get_kineticpay === 'yes') {
			$payment_id = sanitize_text_field($_GET['paymentid']);
			$kineticpay_key = give_get_option('kineticpay_api_key');
			$amount = give_get_meta($payment_id, '_give_payment_total', true);
			$amt = number_format((float)$amount, 2, '.', '');
			$user_name = give_get_meta($payment_id, 'user_name', true);
			$user_email = give_get_meta($payment_id, '_give_payment_donor_email', true);
			$user_mobile = give_get_meta($payment_id, 'user_mobile', true);
			$user_bank = give_get_meta($payment_id, 'user_bank', true);
			$redirect_url_kineticpay = self::get_listener_url($payment_id);
			
			$kineticpay = new KineticpayGiveWPConnect($kineticpay_key);
			$kineticpay->purpose = "Donation";
			$kineticpay->amount = $amt;
			$kineticpay->buyer_name = $user_name;
			$kineticpay->email = $user_email;
			$kineticpay->phone = $user_mobile;
			$urlparts = parse_url(home_url());
			$domain = substr($urlparts['host'], 0, 5);
			$kineticpay->billcode = strtoupper($domain) . (string)$payment_id . 'KNGV';
			$kineticpay->bank_id = $user_bank;
			$kineticpay->kineticpay_success_url = $redirect_url_kineticpay;
			$kineticpay->fail_url = $redirect_url_kineticpay;
			$html = $kineticpay->create_billcode();
				
			echo $html;
			exit();
		}
		return;
    }
	
    public function return_listener()
    {
        if (!isset($_GET['paymentid']) && !isset($_GET['kineticpay'])) {
            return;
        }
		$get_kineticpay = sanitize_text_field($_GET['kineticpay']);
		if ($get_kineticpay === 'success') {
			$payment_id = sanitize_text_field($_GET['paymentid']);
			$kineticpay_key = give_get_option('kineticpay_api_key');
			$kineticpay = new KineticpayGiveWPConnect($kineticpay_key);
			$urlparts = parse_url(home_url());
			$domain = substr($urlparts['host'], 0, 5);
			$kineticpay->billcode = strtoupper($domain) . (string)$payment_id . 'KNGV';
			$response_kineticpay = $kineticpay->success_action();
			if( isset($response_kineticpay['code']) && $response_kineticpay['code'] == '00' )
			{
				if (give_get_payment_status($payment_id)) {
					$this->publish_payment($payment_id, $data);
				}
				$return = add_query_arg(array(
						'payment-confirmation' => 'kineticpay',
						'payment-id' => $payment_id,
					), get_permalink(give_get_option('success_page')));
			} else {
				$return = give_get_failed_transaction_uri('?payment-id=' . $payment_id);
			}
			wp_redirect($return);
			exit;
		}
		return;
    }

    public function give_kineticpay_success_page_content($content)
    {
        if ( ! isset( $_GET['payment-id'] ) && ! give_get_purchase_session() ) {
          return $content;
        }

        $payment_id = isset( $_GET['payment-id'] ) ? absint( sanitize_text_field($_GET['payment-id'] )) : false;

        if ( ! $payment_id ) {
            $session    = give_get_purchase_session();
            $payment_id = give_get_donation_id_by_key( $session['purchase_key'] );
        }

        $payment = get_post( $payment_id );
        if ( $payment && 'pending' === $payment->post_status ) {

            // Payment is still pending so show processing indicator to fix the race condition.
            ob_start();

            give_get_template_part( 'payment', 'processing' );

            $content = ob_get_clean();

        }

        return $content;
    }
}
Give_Kineticpay_Gateway::get_instance();
