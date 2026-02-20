<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use VC\Nexudus\Auth\TokenStore;
use VC\Nexudus\Security\Crypto;

final class Test_Token_Store extends TestCase {
	public function test_store_encrypts_and_decrypts_tokens(): void {
		$store = new TokenStore(new Crypto('unit-test-key'));
		$store->save(['access_token' => 'abc', 'refresh_token' => 'def']);

		$raw = $GLOBALS['vc_nexudus_test_options']['vc_nexudus_oauth_tokens'] ?? '';
		$this->assertIsString($raw);
		$this->assertNotSame('', $raw);
		$this->assertStringNotContainsString('access_token', $raw);

		$loaded = $store->get();
		$this->assertSame('abc', $loaded['access_token']);
		$this->assertSame('def', $loaded['refresh_token']);
	}
}
