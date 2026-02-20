<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use VC\Nexudus\Auth\OAuthClient;
use VC\Nexudus\Support\Clock;

final class Test_OAuth_Client extends TestCase {
	protected function setUp(): void {
		$GLOBALS['vc_nexudus_test_options']['vc_nexudus_settings'] = [
			'oauth_token_url'        => 'https://spaces.nexudus.com/api/token',
			'oauth_client_id_header' => 'test-client-id',
		];
	}

	public function test_authenticate_sends_form_encoded_body(): void {
		$GLOBALS['vc_nexudus_mock_post_response'] = [
			'response' => ['code' => 200],
			'body'     => '{"access_token":"abc","refresh_token":"ref","token_type":"bearer","expires_in":60}',
		];

		$client = new OAuthClient('vc_nexudus_settings', new Clock());
		$result = $client->authenticate('member@example.com', 'secret');

		$this->assertFalse(is_wp_error($result));
		$this->assertSame('abc', $result['access_token']);
		$this->assertSame('test-client-id', $GLOBALS['vc_nexudus_last_remote_post']['args']['headers']['client_id']);
		$this->assertIsString($GLOBALS['vc_nexudus_last_remote_post']['args']['body']);
		$this->assertStringContainsString('grant_type=password', $GLOBALS['vc_nexudus_last_remote_post']['args']['body']);
		$this->assertStringContainsString('username=member%40example.com', $GLOBALS['vc_nexudus_last_remote_post']['args']['body']);
	}

	public function test_refresh_requires_client_id(): void {
		$GLOBALS['vc_nexudus_test_options']['vc_nexudus_settings']['oauth_client_id_header'] = '';
		$client = new OAuthClient('vc_nexudus_settings', new Clock());
		$result = $client->refresh('ref-token');

		$this->assertTrue(is_wp_error($result));
		$this->assertSame('vc_nexudus_missing_client_id', $result->get_error_code());
	}
}
