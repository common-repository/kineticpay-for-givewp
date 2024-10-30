<?php

class KineticpayGiveWPConnect
{
    public $kineticpay_secret_key;
	public $purpose;
	public $amount;
	public $phone;
	public $bank_id;
	public $buyer_name;
	public $email;
	public $button_lang;
	public $kineticpay_bill_url;
	public $redirect_url;
	public $fail_url;
	public $billcode;
	public $getBillTransactions;
	public $kineticpay_success_url;

    public function __construct($api_key)
    {
		
        $this->kineticpay_secret_key = $api_key;	
    }
	
	public function create_billcode()
	{
		// Get ID from user
		$bankid = $this->bank_id;
		// This is merchant_key get from Collection page
		$secretkey = $this->kineticpay_secret_key;
		// This variable should be generated or populated from your system
		$name = $this->buyer_name;
		$phone = $this->phone;
		$email = $this->email;
		$order_id = $this->billcode;
		$amount = $this->amount;
		$description = "Payment for " . $this->purpose . ", Buyer Name " . $name .
			", Email " . $email . ", Phone No. " . $phone;
		if ( is_null($this->fail_url) ) {
			$this->fail_url = $this->kineticpay_success_url;
		}
		$data = [
			'merchant_key' => $secretkey,
			'invoice' => $order_id,
			'amount' => $amount,
			'description' => $description,
			'bank' => $bankid,
			'callback_success' => $this->kineticpay_success_url,
			'callback_error' => $this->fail_url,
			'callback_status' => $this->kineticpay_success_url
		];		
		// API Endpoint URL
		$url = "https://manage.kineticpay.my/payment/create";
		$ch = curl_init( $url );
		// Setup request to send POST request.
		$payload = json_encode( $data );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		$result = curl_exec($ch);
		curl_close($ch);
		$response = json_decode($result, true);
		if (isset($response["error"])) {
			echo '<pre>' . var_export($response["error"], true) . '</pre>';
		} else {
			if (isset($response["html"])) {
				return $response["html"];
			} else {
				$eror = isset($response[0]) ? $response[0] : "Payment was declined. Something error with payment gateway, please contact admin.";
				echo $eror;
			}
		}
	}	

	public function success_action()
	{
		$secretkey = $this->kineticpay_secret_key;
		// This variable should be generated or populated from your system
		$order_id = $this->billcode;
		// API Endpoint URL
		$url = "https://manage.kineticpay.my/payment/status";
		$ch = curl_init( $url . '?merchant_key=' . $secretkey . '&invoice=' . (string)$order_id );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		$result = curl_exec($ch);		
		curl_close($ch);
		$response = json_decode($result, true);		
		return $response;
		
	}
}
