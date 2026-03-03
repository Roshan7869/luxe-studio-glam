<?php
/**
 * Exotel SMS API Wrapper — Sprint 5
 *
 * Sends real SMS via Exotel REST API when configured,
 * falls back to error_log when keys are missing.
 */
class GlamLux_Exotel_API
{

	private $api_key;
	private $api_token;
	private $subdomain;
	private $sid;

	public function __construct()
	{
		$this->api_key = get_option('glamlux_exotel_key', '');
		$this->api_token = get_option('glamlux_exotel_token', '');
		$this->subdomain = get_option('glamlux_exotel_subdomain', 'api.exotel.com');
		$this->sid = get_option('glamlux_exotel_sid', '');
	}

	/**
	 * Send an SMS to the given phone number.
	 *
	 * @param string $phone_number  Recipient phone (E.164 format).
	 * @param string $message       Message body (max 160 chars recommended).
	 * @return bool True on success, false on failure.
	 */
	public function send_sms($phone_number, $message)
	{
		$phone_number = sanitize_text_field($phone_number);
		$message = sanitize_text_field($message);

		// Graceful degradation when API keys are not configured
		if (empty($this->api_key) || empty($this->sid)) {
			error_log(sprintf(
				'[GlamLux SMS] Not configured — skipping SMS to %s: %s',
				$phone_number, substr($message, 0, 80)
			));
			return false;
		}

		$url = sprintf(
			'https://%s/v1/Accounts/%s/Sms/send.json',
			$this->subdomain, $this->sid
		);

		$response = wp_remote_post($url, [
			'timeout' => 15,
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode($this->api_key . ':' . $this->api_token),
			],
			'body' => [
				'From' => $this->sid,
				'To' => $phone_number,
				'Body' => $message,
			],
		]);

		if (is_wp_error($response)) {
			error_log('[GlamLux SMS] Error: ' . $response->get_error_message());
			return false;
		}

		$code = wp_remote_retrieve_response_code($response);
		if ($code >= 200 && $code < 300) {
			return true;
		}

		error_log(sprintf(
			'[GlamLux SMS] HTTP %d sending to %s: %s',
			$code, $phone_number, wp_remote_retrieve_body($response)
		));
		return false;
	}

	/**
	 * @return bool True if Exotel credentials are configured.
	 */
	public function is_configured()
	{
		return !empty($this->api_key) && !empty($this->sid);
	}
}
