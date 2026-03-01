<?php
/**
 * Exotel SMS API Wrapper
 */
class GlamLux_Exotel_API {

	private $api_key;
	private $api_token;
	private $subdomain;
	private $sid;

	public function __construct() {
		$this->api_key = get_option('glamlux_exotel_key', '');
		$this->api_token = get_option('glamlux_exotel_token', '');
		$this->subdomain = get_option('glamlux_exotel_subdomain', 'api.exotel.com');
		$this->sid = get_option('glamlux_exotel_sid', '');
	}

	public function send_sms( $phone_number, $message ) {
		// If keys aren't set, log it and return (mocking)
		if ( empty( $this->api_key ) ) {
			error_log( 'Exotel Mock: Sending SMS to ' . $phone_number . ' -> ' . $message );
			return true;
		}

		// Actual cURL logic would go here
		return true;
	}
}
