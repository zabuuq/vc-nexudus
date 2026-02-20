<?php

declare(strict_types=1);

namespace VC\Nexudus\Security;

final class Crypto {
	private string $key_material;

	public function __construct(string $key_material) {
		$this->key_material = $key_material;
	}

	public function encrypt(string $plaintext): string {
		if (function_exists('sodium_crypto_secretbox')) {
			$key   = sodium_crypto_generichash($this->key_material, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
			$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
			$ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);

			$encoded = wp_json_encode(
				[
					'algo'  => 'secretbox',
					'nonce' => base64_encode($nonce),
					'ct'    => base64_encode($ciphertext),
				],
				JSON_UNESCAPED_SLASHES
			);

			return is_string($encoded) ? base64_encode($encoded) : '';
		}

		$key = hash('sha256', $this->key_material, true);
		$iv  = random_bytes(12);
		$tag = '';
		$ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

		if (! is_string($ciphertext)) {
			return '';
		}

		$encoded = wp_json_encode(
			[
				'algo' => 'aes-256-gcm',
				'iv'   => base64_encode($iv),
				'tag'  => base64_encode($tag),
				'ct'   => base64_encode($ciphertext),
			],
			JSON_UNESCAPED_SLASHES
		);

		return is_string($encoded) ? base64_encode($encoded) : '';
	}

	public function decrypt(string $payload): string {
		$decoded = base64_decode($payload, true);
		if (! is_string($decoded)) {
			return '';
		}

		$data = json_decode($decoded, true);
		if (! is_array($data)) {
			return '';
		}

		$algo = isset($data['algo']) ? (string) $data['algo'] : '';
		if ('secretbox' === $algo && function_exists('sodium_crypto_secretbox_open')) {
			$nonce = base64_decode((string) ($data['nonce'] ?? ''), true);
			$ct    = base64_decode((string) ($data['ct'] ?? ''), true);
			if (! is_string($nonce) || ! is_string($ct)) {
				return '';
			}

			$key       = sodium_crypto_generichash($this->key_material, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
			$plaintext = sodium_crypto_secretbox_open($ct, $nonce, $key);
			return is_string($plaintext) ? $plaintext : '';
		}

		if ('aes-256-gcm' === $algo) {
			$iv  = base64_decode((string) ($data['iv'] ?? ''), true);
			$tag = base64_decode((string) ($data['tag'] ?? ''), true);
			$ct  = base64_decode((string) ($data['ct'] ?? ''), true);
			if (! is_string($iv) || ! is_string($tag) || ! is_string($ct)) {
				return '';
			}

			$key = hash('sha256', $this->key_material, true);
			$plaintext = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
			return is_string($plaintext) ? $plaintext : '';
		}

		return '';
	}
}
