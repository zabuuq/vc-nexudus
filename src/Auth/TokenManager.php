<?php

declare(strict_types=1);

namespace VC\Nexudus\Auth;

use VC\Nexudus\Support\Clock;
use WP_Error;

final class TokenManager {
	private TokenStore $store;
	private OAuthClient $oauth_client;
	private Clock $clock;

	public function __construct(TokenStore $store, OAuthClient $oauth_client, Clock $clock) {
		$this->store        = $store;
		$this->oauth_client = $oauth_client;
		$this->clock        = $clock;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function connect(string $username, string $password): array|WP_Error {
		$tokens = $this->oauth_client->authenticate($username, $password);
		if (is_wp_error($tokens)) {
			return $tokens;
		}

		if (! $this->store->save($tokens)) {
			return new WP_Error('vc_nexudus_token_save_failed', __('Unable to securely store Nexudus tokens.', 'vc-nexudus'));
		}

		return $tokens;
	}

	public function disconnect(): void {
		$this->store->clear();
	}

	public function get_access_token(): string|WP_Error {
		$tokens = $this->store->get();
		if (empty($tokens['access_token'])) {
			return new WP_Error('vc_nexudus_not_connected', __('Nexudus is not connected. Please connect in settings.', 'vc-nexudus'));
		}

		$expires_at = isset($tokens['expires_at']) ? (int) $tokens['expires_at'] : 0;
		if ($expires_at > 0 && $expires_at <= $this->clock->now()) {
			$refreshed = $this->refresh_tokens();
			if (is_wp_error($refreshed)) {
				return $refreshed;
			}
			return (string) $refreshed['access_token'];
		}

		return (string) $tokens['access_token'];
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function refresh_tokens(): array|WP_Error {
		$tokens = $this->store->get();
		$refresh_token = isset($tokens['refresh_token']) ? (string) $tokens['refresh_token'] : '';
		if ('' === $refresh_token) {
			$this->disconnect();
			return new WP_Error('vc_nexudus_missing_refresh_token', __('Nexudus token refresh is unavailable. Please reconnect.', 'vc-nexudus'));
		}

		$refreshed = $this->oauth_client->refresh($refresh_token);
		if (is_wp_error($refreshed)) {
			$this->disconnect();
			return new WP_Error('vc_nexudus_refresh_failed', __('Token refresh failed. Please reconnect to Nexudus.', 'vc-nexudus'));
		}

		if ('' === (string) ($refreshed['refresh_token'] ?? '')) {
			$refreshed['refresh_token'] = $refresh_token;
		}

		if (! $this->store->save($refreshed)) {
			return new WP_Error('vc_nexudus_token_save_failed', __('Unable to securely store refreshed Nexudus token.', 'vc-nexudus'));
		}

		return $refreshed;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function get_status(): array {
		$tokens = $this->store->get();
		$expires_at = isset($tokens['expires_at']) ? (int) $tokens['expires_at'] : 0;
		return [
			'connected'       => ! empty($tokens['access_token']),
			'expires_at'      => $expires_at,
			'last_refresh_at' => isset($tokens['last_refresh_at']) ? (int) $tokens['last_refresh_at'] : 0,
		];
	}
}
