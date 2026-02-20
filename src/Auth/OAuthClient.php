<?php

declare(strict_types=1);

namespace VC\Nexudus\Auth;

use VC\Nexudus\Support\Clock;
use WP_Error;

final class OAuthClient {
	private string $option_key;
	private Clock $clock;

	public function __construct(string $option_key, Clock $clock) {
		$this->option_key = $option_key;
		$this->clock      = $clock;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function authenticate(string $username, string $password): array|WP_Error {
		return $this->request_token(
			[
				'grant_type' => 'password',
				'username'   => $username,
				'password'   => $password,
			]
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function refresh(string $refresh_token): array|WP_Error {
		$settings  = $this->get_settings();
		$client_id = (string) ($settings['oauth_client_id_header'] ?? '');
		if ('' === $client_id) {
			return new WP_Error('vc_nexudus_missing_client_id', __('OAuth Client ID header is required for token refresh.', 'vc-nexudus'));
		}

		return $this->request_token(
			[
				'grant_type'    => 'refresh_token',
				'refresh_token' => $refresh_token,
			]
		);
	}

	/**
	 * @param array<string,string> $body
	 * @return array<string,mixed>|WP_Error
	 */
	private function request_token(array $body): array|WP_Error {
		$settings  = $this->get_settings();
		$token_url = isset($settings['oauth_token_url']) && '' !== (string) $settings['oauth_token_url']
			? (string) $settings['oauth_token_url']
			: 'https://spaces.nexudus.com/api/token';
		$client_id = (string) ($settings['oauth_client_id_header'] ?? '');

		$headers = [
			'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
			'Accept'       => 'application/json',
		];
		if ('' !== $client_id) {
			$headers['client_id'] = $client_id;
		}

		$response = wp_remote_post(
			$token_url,
			[
				'timeout' => 20,
				'headers' => $headers,
				'body'    => http_build_query($body, '', '&'),
			]
		);

		if (is_wp_error($response)) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code($response);
		$data   = json_decode((string) wp_remote_retrieve_body($response), true);
		if ($status < 200 || $status > 299 || ! is_array($data)) {
			return new WP_Error('vc_nexudus_oauth_failed', __('Unable to authenticate with Nexudus OAuth.', 'vc-nexudus'));
		}

		return $this->normalize_token_payload($data);
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>|WP_Error
	 */
	private function normalize_token_payload(array $payload): array|WP_Error {
		$access_token = isset($payload['access_token']) ? (string) $payload['access_token'] : '';
		if ('' === $access_token) {
			return new WP_Error('vc_nexudus_oauth_missing_token', __('OAuth response did not contain an access token.', 'vc-nexudus'));
		}

		$expires_in = isset($payload['expires_in']) ? absint($payload['expires_in']) : 0;

		return [
			'access_token'    => $access_token,
			'refresh_token'   => isset($payload['refresh_token']) ? (string) $payload['refresh_token'] : '',
			'token_type'      => isset($payload['token_type']) ? (string) $payload['token_type'] : 'bearer',
			'expires_in'      => $expires_in,
			'expires_at'      => $expires_in > 0 ? $this->clock->now() + $expires_in : 0,
			'scope'           => isset($payload['scope']) ? (string) $payload['scope'] : '',
			'last_refresh_at' => $this->clock->now(),
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function get_settings(): array {
		$settings = get_option($this->option_key, []);
		return is_array($settings) ? $settings : [];
	}
}
