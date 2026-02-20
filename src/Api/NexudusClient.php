<?php

declare(strict_types=1);

namespace VC\Nexudus\Api;

use VC\Nexudus\Auth\TokenManager;
use WP_Error;

final class NexudusClient {
	private string $option_key;
	private TokenManager $token_manager;

	public function __construct(string $option_key, TokenManager $token_manager) {
		$this->option_key    = $option_key;
		$this->token_manager = $token_manager;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function request(string $path, array $query = []): array|WP_Error {
		$settings = $this->get_settings();
		$base_url = Endpoints::normalize_base_url((string) ($settings['tenant_base_url'] ?? ''));

		if ('' === $base_url) {
			return new WP_Error('vc_nexudus_missing_base', __('Nexudus tenant base URL is not configured.', 'vc-nexudus'));
		}

		$token = $this->token_manager->get_access_token();
		if (is_wp_error($token)) {
			return $token;
		}

		$url = $base_url . '/' . ltrim($path, '/');
		if (! empty($query)) {
			$url = add_query_arg($query, $url);
		}

		$response = $this->send_get_request($url, $token);
		if (is_wp_error($response)) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code($response);
		if (401 === $status || 403 === $status) {
			$refresh = $this->token_manager->refresh_tokens();
			if (is_wp_error($refresh)) {
				return $refresh;
			}

			$response = $this->send_get_request($url, (string) $refresh['access_token']);
			if (is_wp_error($response)) {
				return $response;
			}
			$status = (int) wp_remote_retrieve_response_code($response);
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($status < 200 || $status > 299) {
			if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				error_log('VC Nexudus API HTTP error code: ' . (string) $status);
			}
			return new WP_Error('vc_nexudus_http_error', __('Nexudus API request failed.', 'vc-nexudus'), ['status' => $status]);
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

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	private function send_get_request(string $url, string $token): array|WP_Error {
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

		return $response;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function get_settings(): array {
		$settings = get_option($this->option_key, []);
		return is_array($settings) ? $settings : [];
	}
}
