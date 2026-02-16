<?php

declare(strict_types=1);

namespace VC\Nexudus\Api;

use WP_Error;

final class NexudusClient {
	private string $option_key;

	public function __construct(string $option_key) {
		$this->option_key = $option_key;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function request(string $path, array $query = []): array|WP_Error {
		$settings = $this->get_settings();
		$base_url = Endpoints::normalize_base_url((string) ($settings['api_base_url'] ?? ''));

		if ('' === $base_url) {
			return new WP_Error('vc_nexudus_missing_base', __('Nexudus API base URL is not configured.', 'vc-nexudus'));
		}

		$token = $this->get_access_token();
		if (is_wp_error($token)) {
			return $token;
		}

		$url = $base_url . '/' . ltrim($path, '/');
		if (! empty($query)) {
			$url = add_query_arg($query, $url);
		}

		$response = wp_remote_request(
			$url,
			[
				'method'  => 'GET',
				'timeout' => 20,
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
				],
			]
		);

		if (is_wp_error($response)) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code($response);
		$body   = wp_remote_retrieve_body($response);
		$data   = json_decode($body, true);

		if ($status < 200 || $status > 299) {
			return new WP_Error('vc_nexudus_http_error', __('Nexudus API request failed.', 'vc-nexudus'), ['status' => $status, 'body' => $data]);
		}

		if (! is_array($data)) {
			return new WP_Error('vc_nexudus_invalid_json', __('Nexudus API returned an unexpected payload.', 'vc-nexudus'));
		}

		return $data;
	}

	public function test_connection(): bool|WP_Error {
		$settings = $this->get_settings();
		$probe    = (string) ($settings['connection_test_path'] ?? '/');
		$response = $this->request($probe);

		if (is_wp_error($response)) {
			return $response;
		}

		return true;
	}

	private function get_access_token(): string|WP_Error {
		$token_cache_key = 'vc_nexudus_access_token';
		$cached_token    = get_transient($token_cache_key);
		if (is_string($cached_token) && '' !== $cached_token) {
			return $cached_token;
		}

		$settings      = $this->get_settings();
		$token_url     = (string) ($settings['oauth_token_url'] ?? '');
		$client_id     = (string) ($settings['oauth_client_id'] ?? '');
		$client_secret = (string) ($settings['oauth_client_secret'] ?? '');
		$grant_type    = (string) ($settings['oauth_grant_type'] ?? 'client_credentials');
		$scope         = (string) ($settings['oauth_scope'] ?? '');

		if ('' === $token_url || '' === $client_id || '' === $client_secret) {
			return new WP_Error('vc_nexudus_missing_oauth', __('OAuth settings are incomplete.', 'vc-nexudus'));
		}

		$body = [
			'grant_type'    => $grant_type,
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
		];
		if ('' !== $scope) {
			$body['scope'] = $scope;
		}

		$response = wp_remote_post(
			$token_url,
			[
				'timeout' => 20,
				'body'    => $body,
			]
		);

		if (is_wp_error($response)) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code($response);
		$data   = json_decode((string) wp_remote_retrieve_body($response), true);
		if ($status < 200 || $status > 299 || ! is_array($data)) {
			return new WP_Error('vc_nexudus_oauth_failed', __('Unable to retrieve OAuth token from Nexudus.', 'vc-nexudus'));
		}

		$access_token = isset($data['access_token']) ? (string) $data['access_token'] : '';
		$expires_in   = isset($data['expires_in']) ? absint($data['expires_in']) : HOUR_IN_SECONDS;

		if ('' === $access_token) {
			return new WP_Error('vc_nexudus_oauth_missing_token', __('OAuth response did not contain an access token.', 'vc-nexudus'));
		}

		set_transient($token_cache_key, $access_token, max(60, $expires_in - 60));
		return $access_token;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function get_settings(): array {
		$settings = get_option($this->option_key, []);
		return is_array($settings) ? $settings : [];
	}
}
