<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use VC\Nexudus\Security\Crypto;

final class Test_Token_Store extends TestCase {
	public function test_crypto_round_trip(): void {
		$crypto = new Crypto('unit-test-key');
		$encrypted = $crypto->encrypt('{"access_token":"abc"}');

		$this->assertNotSame('', $encrypted);
		$this->assertSame('{"access_token":"abc"}', $crypto->decrypt($encrypted));
	}
}
