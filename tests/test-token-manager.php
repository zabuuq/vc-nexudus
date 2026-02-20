<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use VC\Nexudus\Auth\OAuthClient;
use VC\Nexudus\Auth\TokenManager;
use VC\Nexudus\Auth\TokenStore;
use VC\Nexudus\Security\Crypto;
use VC\Nexudus\Support\Clock;

final class Test_Token_Manager extends TestCase {
	protected function setUp(): void {
		$GLOBALS['vc_nexudus_test_options']['vc_nexudus_settings'] = [
			'oauth_token_url'        => 'https://spaces.nexudus.com/api/token',
			'oauth_client_id_header' => 'test-client-id',
		];
	}

	public function test_get_access_token_does_not_auto_refresh(): void {
		$store = new TokenStore(new Crypto('unit-test-key'));
		$store->save([
			'access_token'  => 'expired-token',
			'refresh_token' => 'refresh-token',
			'expires_at'    => 1,
		]);

		$manager = new TokenManager($store, new OAuthClient('vc_nexudus_settings', new Clock()));
		$this->assertSame('expired-token', $manager->get_access_token());
	}

	public function test_refresh_tokens_clears_store_on_refresh_failure(): void {
		$store = new TokenStore(new Crypto('unit-test-key'));
		$store->save([
			'access_token'  => 'old-token',
			'refresh_token' => 'refresh-token',
		]);

		$GLOBALS['vc_nexudus_mock_post_response'] = [
			'response' => ['code' => 401],
			'body'     => '{"error":"invalid_grant"}',
		];

		$manager = new TokenManager($store, new OAuthClient('vc_nexudus_settings', new Clock()));
		$result  = $manager->refresh_tokens();

		$this->assertTrue(is_wp_error($result));
		$this->assertSame([], $store->get());
	}
}
