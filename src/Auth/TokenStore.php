<?php

declare(strict_types=1);

namespace VC\Nexudus\Auth;

use VC\Nexudus\Security\Crypto;

final class TokenStore {
	private const TOKEN_OPTION_KEY = 'vc_nexudus_oauth_tokens';

	private Crypto $crypto;

	public function __construct(Crypto $crypto) {
		$this->crypto = $crypto;
	}

	/**
	 * @param array<string,mixed> $tokens
	 */
	public function save(array $tokens): bool {
		$json = wp_json_encode($tokens, JSON_UNESCAPED_SLASHES);
		if (! is_string($json) || '' === $json) {
			return false;
		}

		$encrypted = $this->crypto->encrypt($json);
		if ('' === $encrypted) {
			return false;
		}

		$updated = update_option(self::TOKEN_OPTION_KEY, $encrypted, false);
		if ($updated) {
			return true;
		}

		return (string) get_option(self::TOKEN_OPTION_KEY, '') === $encrypted;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function get(): array {
		$raw = get_option(self::TOKEN_OPTION_KEY, '');
		if (! is_string($raw) || '' === $raw) {
			return [];
		}

		$decrypted = $this->crypto->decrypt($raw);
		if ('' === $decrypted) {
			return [];
		}

		$data = json_decode($decrypted, true);
		return is_array($data) ? $data : [];
	}

	public function clear(): void {
		delete_option(self::TOKEN_OPTION_KEY);
	}
}
